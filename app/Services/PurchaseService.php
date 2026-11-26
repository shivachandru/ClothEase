<?php
namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Services\BarcodeService;

class PurchaseService
{
    public function receive(User $user,array $data,?AuditService $audit=null,?BarcodeService $barcodes=null): Purchase
    {
        $barcodes ??= app(BarcodeService::class);
        return DB::transaction(function()use($user,$data,$audit,$barcodes){
            $shop=$user->shop;
            if(!empty($data['supplier_id'])){
                $supplier=Supplier::where('shop_id',$shop->id)->whereKey($data['supplier_id'])->where('is_active',true)->first();
                if(!$supplier)throw ValidationException::withMessages(['supplier_id'=>'Choose a supplier from this shop.']);
            }else{
                $supplierName=trim((string)($data['supplier_name']??''));
                if($supplierName==='')throw ValidationException::withMessages(['supplier_name'=>'Enter the vendor name for this GRN.']);
                $supplier=Supplier::where('shop_id',$shop->id)->whereRaw('LOWER(name) = ?', [strtolower($supplierName)])->first();
                if(!$supplier)$supplier=Supplier::create(['shop_id'=>$shop->id,'name'=>$supplierName,'is_active'=>true]);
                elseif(!$supplier->is_active)$supplier->update(['is_active'=>true]);
            }
            $purchase=Purchase::create(['shop_id'=>$shop->id,'supplier_id'=>$supplier->id,'user_id'=>$user->id,
                'invoice_number'=>$data['invoice_number'],'purchase_date'=>$data['purchase_date'],'status'=>'unpaid',
                'subtotal'=>0,'discount'=>0,'tax_total'=>0,'total'=>0,'amount_paid'=>0,'balance_due'=>0]);
            $subtotal=$lineDiscountTotal=$taxTotal=0.0;
            $newProducts=[];
            foreach($data['items'] as $line){
                if(!empty($line['variant_id'])){
                    $variant=ProductVariant::with('product')->where('shop_id',$shop->id)->whereKey($line['variant_id'])->lockForUpdate()->first();
                    if(!$variant||!$variant->is_active)throw ValidationException::withMessages(['items'=>'A selected variant is no longer active.']);
                }else{
                    $productName=trim((string)$line['new_product_name']);
                    $productKey=strtolower($productName);
                    $product=$newProducts[$productKey]??Product::where('shop_id',$shop->id)->whereRaw('LOWER(name) = ?',[$productKey])->where('status','active')->first();
                    if(!$product){
                        $categoryName=trim((string)($line['new_product_category']??''));
                        $categoryId=$categoryName!==''?Category::firstOrCreate(['shop_id'=>$shop->id,'name'=>$categoryName])->id:null;
                        $product=Product::create(['shop_id'=>$shop->id,'category_id'=>$categoryId,'name'=>$productName,
                            'brand'=>trim((string)($line['new_product_brand']??''))?:null,'status'=>'active']);
                    }
                    $newProducts[$productKey]=$product;
                    $sku=trim((string)($line['sku']??''));
                    if($sku===''){
                        do{$sku='GRN-'.Str::upper(Str::random(10));}
                        while(ProductVariant::where('shop_id',$shop->id)->where('sku',$sku)->exists());
                    }
                    $barcode=trim((string)($line['barcode']??''))?:$barcodes->nextForShop($shop->id);
                    $variant=ProductVariant::create(['shop_id'=>$shop->id,'product_id'=>$product->id,'size'=>trim((string)($line['size']??''))?:null,
                        'colour'=>trim((string)($line['colour']??''))?:null,'sku'=>$sku,'barcode'=>$barcode,
                        'cost_price'=>round((float)$line['unit_cost'],2),'selling_price'=>round((float)$line['selling_price'],2),
                        'tax_rate'=>(float)($line['tax_rate']??0),'stock_quantity'=>0,'low_stock_threshold'=>5,'is_active'=>true]);
                    $variant->setRelation('product',$product);
                }
                $qty=(int)$line['quantity']; $cost=round((float)$line['unit_cost'],2);
                $lineDiscount=min(max(0,(float)($line['discount']??0)),$cost*$qty);
                $tax=round(max(0,$cost*$qty-$lineDiscount)*(float)($line['tax_rate']??0)/100,2);
                $lineTotal=round($cost*$qty-$lineDiscount+$tax,2);
                PurchaseItem::create(['purchase_id'=>$purchase->id,'variant_id'=>$variant->id,'product_name'=>$variant->product->name,
                    'sku'=>$variant->sku,'variant_label'=>trim(implode(' · ',array_filter([$variant->size,$variant->colour])))?:null,
                    'quantity'=>$qty,'unit_cost'=>$cost,'discount'=>$lineDiscount,'tax_rate'=>$line['tax_rate']??0,'tax_amount'=>$tax,'line_total'=>$lineTotal]);
                $before=$variant->stock_quantity; $variant->stock_quantity+=$qty; $variant->cost_price=$cost; $variant->save();
                StockMovement::create(['shop_id'=>$shop->id,'variant_id'=>$variant->id,'user_id'=>$user->id,'movement_type'=>'purchase',
                    'quantity_in'=>$qty,'quantity_out'=>0,'previous_quantity'=>$before,'new_quantity'=>$variant->stock_quantity,
                    'reference_type'=>Purchase::class,'reference_id'=>$purchase->id,'reason'=>'GRN '.$data['invoice_number']]);
                $subtotal+=$cost*$qty; $lineDiscountTotal+=$lineDiscount; $taxTotal+=$tax;
            }
            $billDiscount=min(max(0,(float)($data['discount']??0)),max(0,$subtotal-$lineDiscountTotal));
            $total=round(max(0,$subtotal-$lineDiscountTotal-$billDiscount)+$taxTotal,2);
            $paid=round((float)($data['amount_paid']??0),2);
            if($paid>$total)throw ValidationException::withMessages(['amount_paid'=>'Amount paid cannot exceed the purchase total.']);
            $purchase->update(['subtotal'=>round($subtotal,2),'discount'=>round($lineDiscountTotal+$billDiscount,2),'tax_total'=>round($taxTotal,2),
                'total'=>$total,'amount_paid'=>$paid,'balance_due'=>round($total-$paid,2),
                'status'=>$paid==0?'unpaid':($paid>=$total?'paid':'partial')]);
            if($paid>0)SupplierPayment::create(['shop_id'=>$shop->id,'supplier_id'=>$supplier->id,'purchase_id'=>$purchase->id,'user_id'=>$user->id,
                'method'=>$data['payment_method']??'cash','amount'=>$paid,'reference'=>$data['payment_reference']??null,'paid_at'=>now()]);
            $audit?->record($user,'purchase.received',$purchase,['invoice_number'=>$purchase->invoice_number,'total'=>$total,'amount_paid'=>$paid,'balance_due'=>$purchase->balance_due]);
            return $purchase->load('items','payments');
        });
    }
}
