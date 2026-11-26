<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\BackupService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_shop_setup_creates_owner_and_closes_registration(): void
    {
        $response=$this->post(route('setup.store'),['shop_name'=>'Needle & Thread','owner_name'=>'Asha Rao','mobile'=>'9876543210','email'=>'asha@example.test','password'=>'a-strong-password','password_confirmation'=>'a-strong-password']);
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('shops',['name'=>'Needle & Thread']);
        $this->assertDatabaseHas('users',['email'=>'asha@example.test','role'=>'owner']);
        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertOk()->assertSee('Today’s sales');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('setup'))->assertNotFound();
    }

    public function test_owner_only_sees_products_from_their_own_shop(): void
    {
        [$user,$product]=$this->shopWithVariant('Blue kurta');
        [, $otherProduct]=$this->shopWithVariant('Red saree');
        $this->actingAs($user)->get(route('products.index'))->assertOk()->assertSee('Blue kurta')->assertDontSee('Red saree');
        $this->assertNotEquals($product->shop_id,$otherProduct->shop_id);
    }

    public function test_sale_records_snapshots_payment_and_stock_ledger_in_one_checkout(): void
    {
        [$user,$product,$variant]=$this->shopWithVariant('Cotton shirt',4,350,180,5);
        $this->actingAs($user)->postJson(route('sales.store'),[
            'items'=>[['variant_id'=>$variant->id,'quantity'=>2,'discount'=>25]],
            'discount'=>10,'payments'=>[['method'=>'cash','amount'=>698.75]],
        ])->assertOk()->assertJsonPath('total','698.75');

        $this->assertDatabaseHas('sales',['shop_id'=>$user->shop_id,'total'=>698.75,'discount'=>35,'tax_total'=>33.75]);
        $this->assertDatabaseHas('sale_items',['product_name'=>'Cotton shirt','sku'=>'SH-M-BLK','cost_price'=>180,'quantity'=>2,'tax_rate'=>5]);
        $this->assertDatabaseHas('payments',['shop_id'=>$user->shop_id,'method'=>'cash','amount'=>698.75]);
        $this->assertDatabaseHas('stock_movements',['variant_id'=>$variant->id,'movement_type'=>'sale','quantity_out'=>2,'previous_quantity'=>4,'new_quantity'=>2]);
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>2]);
        $sale=Sale::firstOrFail();
        $this->assertDatabaseHas('audit_logs',['shop_id'=>$user->shop_id,'action'=>'sale.completed','subject_type'=>Sale::class,'subject_id'=>$sale->id]);
        $this->actingAs($user)->get(route('sales.receipt',$sale))->assertOk()->assertSee($sale->bill_number)->assertSee('Cotton shirt');
    }

    public function test_insufficient_stock_rolls_back_sale_and_payment(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',1,350,180,0);
        $this->actingAs($user)->postJson(route('sales.store'),[
            'items'=>[['variant_id'=>$variant->id,'quantity'=>2]],'payments'=>[['method'=>'upi','amount'=>700]],
        ])->assertUnprocessable();
        $this->assertSame(0,Sale::count());
        $this->assertSame(0,StockMovement::where('movement_type','sale')->count());
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>1]);
    }

    public function test_split_payment_must_equal_bill_total(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',3,350,180,0);
        $this->actingAs($user)->postJson(route('sales.store'),[
            'items'=>[['variant_id'=>$variant->id,'quantity'=>1]],
            'payments'=>[['method'=>'cash','amount'=>100],['method'=>'upi','amount'=>200]],
        ])->assertUnprocessable();
        $this->assertSame(0,Sale::count());
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>3]);
    }

    public function test_pos_screen_renders_for_an_authenticated_shop_user(): void
    {
        [$user]=$this->shopWithVariant('Cotton shirt');
        $this->actingAs($user)->get(route('pos'))->assertOk()->assertSee('New bill')->assertSee('F2')->assertSee('F8')->assertSee('customer-search')->assertSee('Allow Udhaar');
    }

    public function test_stock_manager_can_adjust_count_and_cashier_cannot(): void
    {
        [$owner,, $variant]=$this->shopWithVariant('Cotton shirt',4);
        $stockManager=User::create(['shop_id'=>$owner->shop_id,'name'=>'Stock Manager','email'=>fake()->unique()->safeEmail(),'password'=>Hash::make('a-strong-password'),'role'=>'stock_manager','is_active'=>true]);
        $this->actingAs($stockManager)->post(route('inventory.adjust',$variant),['new_quantity'=>7,'reason'=>'Physical count'])->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('stock_movements',['variant_id'=>$variant->id,'movement_type'=>'adjustment','quantity_in'=>3,'quantity_out'=>0,'previous_quantity'=>4,'new_quantity'=>7,'reason'=>'Physical count']);
        $cashier=User::create(['shop_id'=>$owner->shop_id,'name'=>'Cashier','email'=>fake()->unique()->safeEmail(),'password'=>Hash::make('a-strong-password'),'role'=>'cashier','is_active'=>true]);
        $this->actingAs($cashier)->post(route('inventory.adjust',$variant),['new_quantity'=>0,'reason'=>'Not allowed'])->assertForbidden();
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>7]);
    }

    public function test_expense_and_sales_report_include_cost_and_expense_in_profit(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',3,350,180,0);
        $this->actingAs($user)->postJson(route('sales.store'),['items'=>[['variant_id'=>$variant->id,'quantity'=>1]],'payments'=>[['method'=>'cash','amount'=>350]]])->assertOk();
        $this->post(route('expenses.store'),['category'=>'Rent','amount'=>100,'expense_date'=>now()->toDateString(),'payment_method'=>'cash','description'=>'Shop rent'])->assertRedirect(route('expenses.index'));
        $this->get(route('reports.sales',['from'=>now()->toDateString(),'to'=>now()->toDateString()]))->assertOk()->assertSee('₹350.00')->assertSee('₹170.00')->assertSee('₹70.00');
        $this->get(route('reports.sales',['from'=>now()->toDateString(),'to'=>now()->toDateString(),'export'=>1]))->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
        $this->get(route('expenses.index'))->assertOk()->assertSee('Shop rent')->assertSee('₹100.00');
    }

    public function test_purchase_receives_stock_and_tracks_partial_supplier_payment(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',3,350,180,0);
        $supplier=Supplier::create(['shop_id'=>$user->shop_id,'name'=>'Textile Wholesale','is_active'=>true]);
        $this->actingAs($user)->get(route('purchases.create'))->assertOk()->assertSee('Textile Wholesale');
        $this->get(route('suppliers.index'))->assertOk()->assertSee('Textile Wholesale');
        $this->actingAs($user)->post(route('purchases.store'),[
            'supplier_id'=>$supplier->id,'invoice_number'=>'SUP-INV-100','purchase_date'=>now()->toDateString(),
            'items'=>[['variant_id'=>$variant->id,'quantity'=>5,'unit_cost'=>170,'discount'=>20,'tax_rate'=>5]],
            'amount_paid'=>300,'payment_method'=>'upi','payment_reference'=>'TXN-101',
        ])->assertRedirect(route('purchases.index'));
        $this->assertDatabaseHas('purchases',['invoice_number'=>'SUP-INV-100','total'=>871.5,'amount_paid'=>300,'balance_due'=>571.5,'status'=>'partial']);
        $this->assertDatabaseHas('supplier_payments',['supplier_id'=>$supplier->id,'amount'=>300,'method'=>'upi','reference'=>'TXN-101']);
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>8,'cost_price'=>170]);
        $this->assertDatabaseHas('stock_movements',['variant_id'=>$variant->id,'movement_type'=>'purchase','quantity_in'=>5,'previous_quantity'=>3,'new_quantity'=>8]);
        $this->get(route('purchases.index'))->assertOk()->assertSee('SUP-INV-100')->assertSee('₹571.50');
        $this->get(route('suppliers.show',$supplier))->assertOk()->assertSee('Textile Wholesale')->assertSee('₹571.50');
        $this->post(route('suppliers.payments.store',$supplier),['amount'=>200,'method'=>'cash','reference'=>'SUP-PAY-2'])->assertRedirect(route('suppliers.show',$supplier));
        $this->assertDatabaseHas('purchases',['invoice_number'=>'SUP-INV-100','amount_paid'=>500,'balance_due'=>371.5,'status'=>'partial']);
        $this->assertDatabaseHas('supplier_payments',['supplier_id'=>$supplier->id,'purchase_id'=>Purchase::firstOrFail()->id,'amount'=>200,'reference'=>'SUP-PAY-2']);
        $this->get(route('suppliers.show',$supplier))->assertOk()->assertSee('₹371.50');
    }

    public function test_invalid_purchase_payment_rolls_back_stock_and_invoice(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',3);
        $supplier=Supplier::create(['shop_id'=>$user->shop_id,'name'=>'Textile Wholesale','is_active'=>true]);
        $this->actingAs($user)->postJson(route('purchases.store'),[
            'supplier_id'=>$supplier->id,'invoice_number'=>'SUP-INV-BAD','purchase_date'=>now()->toDateString(),
            'items'=>[['variant_id'=>$variant->id,'quantity'=>2,'unit_cost'=>100]],'amount_paid'=>250,
        ])->assertUnprocessable();
        $this->assertSame(0,Purchase::count());
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>3]);
        $this->assertDatabaseMissing('stock_movements',['variant_id'=>$variant->id,'movement_type'=>'purchase']);
    }

    public function test_partial_returns_refund_once_and_only_good_items_restock(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',2,350,180,0);
        $saleResponse=$this->actingAs($user)->postJson(route('sales.store'),[
            'items'=>[['variant_id'=>$variant->id,'quantity'=>2]],'discount'=>100,'payments'=>[['method'=>'cash','amount'=>600]],
        ])->assertOk();
        $sale=Sale::firstOrFail();
        $saleItem=$sale->items()->firstOrFail();
        $this->post(route('returns.store',$sale),['items'=>[['sale_item_id'=>$saleItem->id,'quantity'=>1,'condition'=>'good']],
            'refund_method'=>'cash'])->assertRedirect(route('sales.receipt',$sale));
        $this->assertDatabaseHas('sale_returns',['sale_id'=>$sale->id,'refund_total'=>300]);
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>1]);
        $this->postJson(route('returns.store',$sale),['items'=>[['sale_item_id'=>$saleItem->id,'quantity'=>2,'condition'=>'good']],
            'refund_method'=>'cash'])->assertUnprocessable();
        $this->post(route('returns.store',$sale),['items'=>[['sale_item_id'=>$saleItem->id,'quantity'=>1,'condition'=>'damaged']],
            'refund_method'=>'upi','reference'=>'REF-2'])->assertRedirect(route('sales.receipt',$sale));
        $this->assertDatabaseHas('sale_returns',['sale_id'=>$sale->id,'refund_total'=>300]);
        $this->assertDatabaseHas('refund_payments',['shop_id'=>$user->shop_id,'method'=>'upi','amount'=>300,'reference'=>'REF-2']);
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock_quantity'=>1]);
        $this->get(route('returns.create',$sale))->assertOk()->assertSee('Fully returned');
        $this->get(route('reports.sales',['from'=>now()->toDateString(),'to'=>now()->toDateString()]))->assertOk()->assertSee('₹0.00')->assertSee('₹-180.00');
    }

    public function test_exchange_uses_return_credit_and_collects_price_difference(): void
    {
        [$user,$originalProduct,$original]=$this->shopWithVariant('Cotton shirt',2,350,180,0);
        $replacementProduct=Product::create(['shop_id'=>$user->shop_id,'name'=>'Linen shirt','status'=>'active']);
        $replacement=ProductVariant::create(['shop_id'=>$user->shop_id,'product_id'=>$replacementProduct->id,'size'=>'L','colour'=>'White','sku'=>'LIN-L-WHT','cost_price'=>200,'selling_price'=>400,'tax_rate'=>0,'stock_quantity'=>2,'low_stock_threshold'=>1,'is_active'=>true]);
        $this->actingAs($user)->postJson(route('sales.store'),['items'=>[['variant_id'=>$original->id,'quantity'=>1]],'payments'=>[['method'=>'cash','amount'=>350]]])->assertOk();
        $sale=Sale::firstOrFail();$saleItem=$sale->items()->firstOrFail();
        $this->post(route('returns.store',$sale),[
            'items'=>[['sale_item_id'=>$saleItem->id,'quantity'=>1,'condition'=>'good']],
            'replacement_items'=>[['variant_id'=>$replacement->id,'quantity'=>1]],
            'refund_method'=>'cash','collection_method'=>'upi','collection_reference'=>'UPI-EX-1',
        ])->assertRedirect();
        $return=\App\Models\SaleReturn::firstOrFail();
        $exchange=Sale::findOrFail($return->exchange_sale_id);
        $this->assertSame('400.00',$exchange->total);
        $this->assertDatabaseHas('payments',['sale_id'=>$exchange->id,'method'=>'exchange_credit','amount'=>350]);
        $this->assertDatabaseHas('payments',['sale_id'=>$exchange->id,'method'=>'upi','amount'=>50,'reference'=>'UPI-EX-1']);
        $this->assertDatabaseHas('product_variants',['id'=>$original->id,'stock_quantity'=>2]);
        $this->assertDatabaseHas('product_variants',['id'=>$replacement->id,'stock_quantity'=>1]);
        $this->get(route('reports.sales',['from'=>now()->toDateString(),'to'=>now()->toDateString()]))->assertOk()->assertSee('₹400.00')->assertSee('₹200.00');
    }

    public function test_customer_credit_sale_and_later_payment_collection_reduce_balance(): void
    {
        [$user,,$variant]=$this->shopWithVariant('Cotton shirt',3,350,180,0);
        $customer=Customer::create(['shop_id'=>$user->shop_id,'name'=>'Meera Shah','mobile'=>'9876500000']);
        $this->actingAs($user)->postJson(route('sales.store'),[
            'items'=>[['variant_id'=>$variant->id,'quantity'=>1]],'customer_id'=>$customer->id,'use_credit'=>true,
            'payments'=>[['method'=>'cash','amount'=>100]],
        ])->assertOk();
        $sale=Sale::firstOrFail();
        $this->assertDatabaseHas('sales',['id'=>$sale->id,'customer_id'=>$customer->id,'amount_paid'=>100,'balance_due'=>250]);
        $this->assertDatabaseHas('customer_payments',['customer_id'=>$customer->id,'sale_id'=>$sale->id,'amount'=>100]);
        $this->get(route('customers.show',$customer))->assertOk()->assertSee('₹250.00');
        $this->post(route('customers.payments.store',$customer),['amount'=>200,'method'=>'upi','reference'=>'COL-200'])->assertRedirect(route('customers.show',$customer));
        $this->assertDatabaseHas('sales',['id'=>$sale->id,'amount_paid'=>300,'balance_due'=>50]);
        $this->assertDatabaseHas('customer_payments',['customer_id'=>$customer->id,'sale_id'=>$sale->id,'method'=>'upi','amount'=>200,'reference'=>'COL-200']);
        $this->assertDatabaseHas('payments',['sale_id'=>$sale->id,'method'=>'upi','amount'=>200]);
        $this->get(route('customers.show',$customer))->assertOk()->assertSee('₹50.00');
    }

    public function test_owner_can_create_staff_and_staff_can_sign_in_with_pin(): void
    {
        [$owner]=$this->shopWithVariant('Cotton shirt');
        $this->actingAs($owner)->get(route('users.index'))->assertOk()->assertSee('Create staff account');
        $this->post(route('users.store'),['name'=>'Ravi Cashier','email'=>'ravi@example.test','role'=>'cashier',
            'password'=>'strong-cashier-password','password_confirmation'=>'strong-cashier-password','pin'=>'482913'])->assertRedirect(route('users.index'));
        $this->get(route('audit.index'))->assertOk()->assertSee('User Created')->assertSee('ravi@example.test');
        $this->assertDatabaseHas('users',['shop_id'=>$owner->shop_id,'email'=>'ravi@example.test','role'=>'cashier']);
        $staff=User::where('email','ravi@example.test')->firstOrFail();
        $this->assertNotSame('482913',$staff->pin_hash);
        $this->post(route('logout'));
        $this->post(route('login.pin'),['email'=>'ravi@example.test','pin'=>'482913'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff);
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('inventory.index'))->assertOk();
    }

    public function test_product_supports_multiple_variants_and_stock_is_not_changed_by_price_edits(): void
    {
        [$user,$product,$variant]=$this->shopWithVariant('Cotton shirt',4);
        $this->actingAs($user)->get(route('products.show',$product))->assertOk()->assertSee('Add another variant')->assertSee('SH-M-BLK');
        $this->post(route('products.variants.store',$product),['size'=>'L','colour'=>'White','sku'=>'SH-L-WHT','barcode'=>'890000002','cost_price'=>190,
            'selling_price'=>380,'wholesale_price'=>300,'tax_rate'=>5,'stock_quantity'=>6,'low_stock_threshold'=>2])->assertRedirect(route('products.show',$product));
        $newVariant=ProductVariant::where('sku','SH-L-WHT')->firstOrFail();
        $this->assertDatabaseHas('stock_movements',['variant_id'=>$newVariant->id,'movement_type'=>'opening','quantity_in'=>6,'new_quantity'=>6]);
        $this->put(route('variants.update',$variant),['size'=>'M','colour'=>'Black','sku'=>'SH-M-BLK','barcode'=>null,'cost_price'=>180,
            'selling_price'=>375,'wholesale_price'=>null,'tax_rate'=>0,'low_stock_threshold'=>2,'is_active'=>1])->assertRedirect(route('products.show',$product));
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'selling_price'=>375,'stock_quantity'=>4]);
        $this->post(route('products.archive',$product))->assertRedirect(route('products.show',$product));
        $this->assertDatabaseHas('products',['id'=>$product->id,'status'=>'archived']);
    }

    public function test_sqlite_backup_is_verified_and_restore_keeps_a_pre_restore_snapshot(): void
    {
        $directory=storage_path('framework/testing');if(!is_dir($directory))mkdir($directory,0700,true);
        $database=$directory.DIRECTORY_SEPARATOR.'backup-test-'.bin2hex(random_bytes(4)).'.sqlite';
        $pdo=new \PDO('sqlite:'.$database);$pdo->exec('CREATE TABLE sample (value TEXT)');$pdo->exec("INSERT INTO sample VALUES ('original')");unset($pdo);
        $service=new BackupService();$backup=$service->create($database);
        $this->assertTrue($service->verify($backup['name'])['valid']);
        $pdo=new \PDO('sqlite:'.$database);$pdo->exec("UPDATE sample SET value='changed'");unset($pdo);
        $result=$service->restore($backup['name'],$database);
        $pdo=new \PDO('sqlite:'.$database);$value=$pdo->query('SELECT value FROM sample')->fetchColumn();unset($pdo);
        $this->assertSame('original',$value);
        $this->assertTrue($service->verify($result['pre_restore']['name'])['valid']);
        foreach([$backup['name'],$result['pre_restore']['name']] as $name){@unlink(storage_path('app/private/backups/'.$name));@unlink(storage_path('app/private/backups/'.$name.'.json'));}
        @unlink($database);
    }

    private function shopWithVariant(string $name,int $quantity=4,float $price=350,float $cost=180,float $tax=0): array
    {
        $shop=Shop::create(['name'=>'Shop '.fake()->unique()->word(),'owner_name'=>'Shop Owner']);
        $user=User::create(['shop_id'=>$shop->id,'name'=>'Shop Owner','email'=>fake()->unique()->safeEmail(),'password'=>Hash::make('a-strong-password'),'role'=>'owner','is_active'=>true]);
        $product=Product::create(['shop_id'=>$shop->id,'name'=>$name,'status'=>'active']);
        $variant=ProductVariant::create(['shop_id'=>$shop->id,'product_id'=>$product->id,'size'=>'M','colour'=>'Black','sku'=>'SH-M-BLK','cost_price'=>$cost,'selling_price'=>$price,'tax_rate'=>$tax,'stock_quantity'=>$quantity,'low_stock_threshold'=>2,'is_active'=>true]);
        return [$user,$product,$variant];
    }
}
