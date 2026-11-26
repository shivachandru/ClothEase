<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;
use App\Services\BarcodeService;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $shopId=$request->user()->shop_id; $search=trim($request->query('q',''));
        $products=Product::where('shop_id',$shopId)->with('category')->withCount('variants')->withSum('variants as stock_total','stock_quantity')->withMin('variants as min_price','selling_price')->withMax('variants as max_price','selling_price')->when($search,fn($q)=>$q->where(function($q)use($search){$q->where('name','like',"$search%")->orWhereHas('variants',fn($v)=>$v->where('sku','like',"$search%")->orWhere('barcode',$search));}))->latest()->paginate(12)->withQueryString();
        return view('products.index',compact('products','search'));
    }
    public function create(Request $request, BarcodeService $barcodes) { return view('products.create', ['barcodePreview' => $barcodes->previewForShop($request->user()->shop_id)]); }
    public function store(Request $request,AuditService $audit,BarcodeService $barcodes)
    {
        $shopId=$request->user()->shop_id;
        $data=$request->validate(['name'=>['required','string','max:150'],'category'=>['nullable','string','max:80'],
            'size'=>['nullable','string','max:30'],'colour'=>['nullable','string','max:40'],'sku'=>['nullable','string','max:80'],
            'barcode'=>['nullable','string','max:100'],'cost_price'=>['required','numeric','min:0'],'selling_price'=>['required','numeric','min:0.01'],
            'tax_rate'=>['nullable','numeric','min:0','max:100'],'stock_quantity'=>['required','integer','min:0'],'low_stock_threshold'=>['required','integer','min:0']]);
        $data['sku'] = trim((string) ($data['sku'] ?? '')) ?: null;
        if ($request->boolean('barcode_auto')) {
            $data['barcode'] = null;
        }
        validator($data,['sku'=>[Rule::unique('product_variants','sku')->where('shop_id',$shopId)],'barcode'=>['nullable',Rule::unique('product_variants','barcode')->where('shop_id',$shopId)]])->validate();
        $categoryId=($data['category']??null) ? Category::firstOrCreate(['shop_id'=>$shopId,'name'=>$data['category']])->id : null;
        $product=DB::transaction(function()use($data,$shopId,$request,$categoryId,$audit,$barcodes){
            $product=Product::create(['shop_id'=>$shopId,'category_id'=>$categoryId,'name'=>$data['name'],'status'=>'active']);
            $barcode = $request->boolean('barcode_auto') || trim((string) ($data['barcode'] ?? '')) === ''
                ? $barcodes->nextForShop($shopId)
                : trim((string) $data['barcode']);
            $variant=ProductVariant::create(['shop_id'=>$shopId,'product_id'=>$product->id,'size'=>$data['size']??null,'colour'=>$data['colour']??null,'sku'=>$data['sku'],'barcode'=>$barcode,'cost_price'=>$data['cost_price'],'selling_price'=>$data['selling_price'],'tax_rate'=>$data['tax_rate']??0,'stock_quantity'=>$data['stock_quantity'],'low_stock_threshold'=>$data['low_stock_threshold'],'is_active'=>true]);
            if($variant->stock_quantity>0) DB::table('stock_movements')->insert(['shop_id'=>$shopId,'variant_id'=>$variant->id,'user_id'=>$request->user()->id,'movement_type'=>'opening','quantity_in'=>$variant->stock_quantity,'quantity_out'=>0,'previous_quantity'=>0,'new_quantity'=>$variant->stock_quantity,'reason'=>'Opening balance','created_at'=>now(),'updated_at'=>now()]);
            $audit->record($request->user(),'product.created',$product,['variant_id'=>$variant->id,'sku'=>$variant->sku,'opening_stock'=>$variant->stock_quantity]);
            return $product;
        });
        return redirect()->route('products.index')->with('success',$product->name.' added to your catalogue.');
    }
    public function show(Request $request,Product $product)
    {
        abort_unless($product->shop_id===$request->user()->shop_id,404);
        $product->load('category','variants');
        return view('products.show',compact('product'));
    }
    public function edit(Request $request,Product $product)
    {
        abort_unless($product->shop_id===$request->user()->shop_id,404);
        $product->load('category');
        return view('products.edit',compact('product'));
    }
    public function update(Request $request,Product $product,AuditService $audit)
    {
        abort_unless($product->shop_id===$request->user()->shop_id,404);
        $data=$request->validate(['name'=>['required','string','max:150'],'category'=>['nullable','string','max:80'],
            'brand'=>['nullable','string','max:100'],'description'=>['nullable','string','max:2000']]);
        $categoryId=($data['category']??null)?Category::firstOrCreate(['shop_id'=>$product->shop_id,'name'=>$data['category']])->id:null;
        DB::transaction(function()use($product,$data,$categoryId,$audit,$request){$product->update(['name'=>$data['name'],'category_id'=>$categoryId,'brand'=>$data['brand']??null,'description'=>$data['description']??null]);$audit->record($request->user(),'product.updated',$product,['name'=>$product->name]);});
        return redirect()->route('products.show',$product)->with('success','Product details updated.');
    }
    public function addVariant(Request $request,Product $product,AuditService $audit,BarcodeService $barcodes)
    {
        abort_unless($product->shop_id===$request->user()->shop_id,404);
        $shopId=$request->user()->shop_id;
        $data=$request->validate(['size'=>['nullable','string','max:30'],'colour'=>['nullable','string','max:40'],'sku'=>['nullable','string','max:80'],
            'barcode'=>['nullable','string','max:100'],'cost_price'=>['required','numeric','min:0'],'selling_price'=>['required','numeric','min:0.01'],
            'wholesale_price'=>['nullable','numeric','min:0'],'tax_rate'=>['nullable','numeric','min:0','max:100'],'stock_quantity'=>['required','integer','min:0','max:1000000'],
            'low_stock_threshold'=>['required','integer','min:0','max:1000000']]);
        $data['sku'] = trim((string) ($data['sku'] ?? '')) ?: null;
        validator($data,['sku'=>[Rule::unique('product_variants','sku')->where('shop_id',$shopId)],'barcode'=>['nullable',Rule::unique('product_variants','barcode')->where('shop_id',$shopId)]])->validate();
        DB::transaction(function()use($data,$product,$shopId,$request,$audit,$barcodes){
            $barcode=trim((string)($data['barcode']??''))?:$barcodes->nextForShop($shopId);
            $variant=ProductVariant::create(['shop_id'=>$shopId,'product_id'=>$product->id,'size'=>$data['size']??null,'colour'=>$data['colour']??null,
                'sku'=>$data['sku'],'barcode'=>$barcode,'cost_price'=>$data['cost_price'],'selling_price'=>$data['selling_price'],
                'wholesale_price'=>$data['wholesale_price']??null,'tax_rate'=>$data['tax_rate']??0,'stock_quantity'=>$data['stock_quantity'],
                'low_stock_threshold'=>$data['low_stock_threshold'],'is_active'=>true]);
            if($variant->stock_quantity>0)DB::table('stock_movements')->insert(['shop_id'=>$shopId,'variant_id'=>$variant->id,'user_id'=>$request->user()->id,
                'movement_type'=>'opening','quantity_in'=>$variant->stock_quantity,'quantity_out'=>0,'previous_quantity'=>0,'new_quantity'=>$variant->stock_quantity,
                'reason'=>'Opening balance','created_at'=>now(),'updated_at'=>now()]);
            $audit->record($request->user(),'product.variant_created',$variant,['product_id'=>$product->id,'sku'=>$variant->sku,'opening_stock'=>$variant->stock_quantity]);
        });
        return redirect()->route('products.show',$product)->with('success','Variant added and opening stock recorded.');
    }
    public function updateVariant(Request $request,ProductVariant $variant,AuditService $audit)
    {
        abort_unless($variant->shop_id===$request->user()->shop_id,404);
        $shopId=$request->user()->shop_id;
        $data=$request->validate(['size'=>['nullable','string','max:30'],'colour'=>['nullable','string','max:40'],'sku'=>['nullable','string','max:80'],
            'barcode'=>['nullable','string','max:100'],'cost_price'=>['required','numeric','min:0'],'selling_price'=>['required','numeric','min:0.01'],
            'wholesale_price'=>['nullable','numeric','min:0'],'tax_rate'=>['nullable','numeric','min:0','max:100'],'low_stock_threshold'=>['required','integer','min:0','max:1000000'],'is_active'=>['nullable','boolean']]);
        $data['sku'] = trim((string) ($data['sku'] ?? '')) ?: null;
        validator($data,['sku'=>[Rule::unique('product_variants','sku')->where('shop_id',$shopId)->ignore($variant->id)],'barcode'=>['nullable',Rule::unique('product_variants','barcode')->where('shop_id',$shopId)->ignore($variant->id)]])->validate();
        DB::transaction(function()use($variant,$data,$audit,$request){
            $variant->update(['size'=>$data['size']??null,'colour'=>$data['colour']??null,'sku'=>$data['sku'],'barcode'=>$data['barcode']??null,
                'cost_price'=>$data['cost_price'],'selling_price'=>$data['selling_price'],'wholesale_price'=>$data['wholesale_price']??null,
                'tax_rate'=>$data['tax_rate']??0,'low_stock_threshold'=>$data['low_stock_threshold'],'is_active'=>(bool)($data['is_active']??false)]);
            $audit->record($request->user(),'product.variant_updated',$variant,['sku'=>$variant->sku,'selling_price'=>$variant->selling_price,'is_active'=>$variant->is_active]);
        });
        return redirect()->route('products.show',$variant->product_id)->with('success','Variant details saved. Stock was unchanged.');
    }
    public function archive(Request $request,Product $product,AuditService $audit)
    {
        abort_unless($product->shop_id===$request->user()->shop_id,404);
        DB::transaction(function()use($product,$audit,$request){$previous=$product->status;$product->update(['status'=>$previous==='active'?'archived':'active']);$audit->record($request->user(),$product->status==='active'?'product.restored':'product.archived',$product,['previous_status'=>$previous,'status'=>$product->status]);});
        return redirect()->route('products.show',$product)->with('success',$product->status==='active'?'Product restored.':'Product archived. Existing bills and stock history were retained.');
    }
    public function search(Request $request)
    {
        $q=trim($request->query('q','')); if(strlen($q)<1)return response()->json([]);
        return ProductVariant::with('product')->where('shop_id',$request->user()->shop_id)->where('is_active',true)->where(function($query)use($q){$query->whereHas('product',fn($p)=>$p->where('status','active')->where('name','like',"%$q%"))->orWhere('sku','like',"$q%")->orWhere('barcode',$q);})->limit(12)->get()->map(fn($v)=>['id'=>$v->id,'name'=>$v->product->name,'sku'=>$v->sku,'barcode'=>$v->barcode,'size'=>$v->size,'colour'=>$v->colour,'price'=>(float)$v->selling_price,'cost'=>(float)$v->cost_price,'stock'=>$v->stock_quantity,'tax'=>(float)$v->tax_rate]);
    }

}
