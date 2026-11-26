<?php
namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $purchases=Purchase::where('shop_id',$request->user()->shop_id)->with('supplier')->latest('purchase_date')->paginate(20);
        return view('purchases.index',compact('purchases'));
    }
    public function create(Request $request)
    {
        $suppliers=Supplier::where('shop_id',$request->user()->shop_id)->where('is_active',true)->orderBy('name')->limit(200)->get();
        return view('purchases.create',compact('suppliers'));
    }
    public function store(Request $request,PurchaseService $service,AuditService $audit)
    {
        $shopId=$request->user()->shop_id;
        $data=$request->validate(['supplier_id'=>['nullable','integer',Rule::exists('suppliers','id')->where('shop_id',$shopId)],
            'supplier_name'=>['nullable','required_without:supplier_id','string','max:150'],
            'invoice_number'=>['required','string','max:100',Rule::unique('purchases','invoice_number')->where('shop_id',$shopId)],
            'purchase_date'=>['required','date','before_or_equal:today'],'items'=>['required','array','min:1'],
            'items.*.variant_id'=>['nullable','integer'],'items.*.new_product_name'=>['nullable','string','max:150'],
            'items.*.new_product_category'=>['nullable','string','max:80'],'items.*.new_product_brand'=>['nullable','string','max:100'],
            'items.*.size'=>['nullable','string','max:30'],'items.*.colour'=>['nullable','string','max:40'],
            'items.*.sku'=>['nullable','string','max:80'],'items.*.barcode'=>['nullable','string','max:100'],
            'items.*.selling_price'=>['nullable','numeric','gt:0','max:99999999'],
            'items.*.quantity'=>['required','integer','min:1','max:100000'],
            'items.*.unit_cost'=>['required','numeric','min:0','max:99999999'],'items.*.discount'=>['nullable','numeric','min:0'],
            'items.*.tax_rate'=>['nullable','numeric','min:0','max:100'],'discount'=>['nullable','numeric','min:0'],
            'amount_paid'=>['nullable','numeric','min:0'],'payment_method'=>['nullable','in:cash,upi,card,other'],
            'payment_reference'=>['nullable','string','max:100']]);
        foreach($data['items'] as $index=>$item){
            if(empty($item['variant_id'])&&empty(trim((string)($item['new_product_name']??'')))){
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.new_product_name"=>'Choose a catalogue item or add a product name.']);
            }
            if(empty($item['variant_id'])&&empty($item['selling_price'])){
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.selling_price"=>'Enter the selling price for the new catalogue item.']);
            }
        }
        $skus=collect($data['items'])->pluck('sku')->filter()->map(fn($sku)=>strtolower(trim($sku)));
        if($skus->count()!==$skus->unique()->count())throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'Each new item must have a different SKU.']);
        $barcodes=collect($data['items'])->pluck('barcode')->filter()->map(fn($barcode)=>trim($barcode));
        if($barcodes->count()!==$barcodes->unique()->count())throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'Each new item must have a different barcode.']);
        foreach($data['items'] as $index=>$item){
            if(!empty($item['sku'])&&\App\Models\ProductVariant::where('shop_id',$shopId)->where('sku',trim($item['sku']))->exists())
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.sku"=>'This SKU is already used in the catalogue.']);
            if(!empty($item['barcode'])&&\App\Models\ProductVariant::where('shop_id',$shopId)->where('barcode',trim($item['barcode']))->exists())
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.barcode"=>'This barcode is already used in the catalogue.']);
        }
        $purchase=$service->receive($request->user(),$data,$audit);
        return redirect()->route('purchases.index')->with('success','GRN '.$purchase->invoice_number.' saved. New catalogue items and received stock were updated.');
    }
}
