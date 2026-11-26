<?php
namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\BillingService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index() { return view('pos.index'); }
    public function store(Request $request, BillingService $billing,AuditService $audit)
    {
        $data=$request->validate(['items'=>['required','array','min:1'],'items.*.variant_id'=>['required','integer'],
            'items.*.quantity'=>['required','integer','min:1','max:999'],'items.*.discount'=>['nullable','numeric','min:0'],
            'discount'=>['nullable','numeric','min:0'],'include_tax'=>['nullable','boolean'],'tendered_amount'=>['nullable','numeric','min:0'],'customer_id'=>['nullable','integer','exists:customers,id'],'customer_name'=>['nullable','string','max:150'],'customer_mobile'=>['nullable','string','max:20'],'use_credit'=>['nullable','boolean'],
            'payments'=>['nullable','array'],'payments.*.method'=>['required','in:cash,upi,card,other'],'payments.*.amount'=>['required','numeric','gt:0'],
            'payments.*.reference'=>['nullable','string','max:100']]);
        $sale=$billing->complete($request->user(),$data['items'],$data['payments']??[],$data['discount']??0,$data['customer_id']??null,(bool)($data['use_credit']??false),$audit,(bool)($data['include_tax']??true),(float)($data['tendered_amount']??0),$data['customer_name']??null,$data['customer_mobile']??null);
        return response()->json(['message'=>'Bill completed','bill_number'=>$sale->bill_number,'total'=>$sale->total,'receipt'=>route('sales.receipt',$sale).'?from=pos']);
    }
    public function receipt(Request $request, Sale $sale)
    {
        abort_unless($sale->shop_id===$request->user()->shop_id,404);
        $returnFrom=in_array($request->query('from'),['pos','history'],true)?$request->query('from'):'history';
        $backUrl=$returnFrom==='pos'?route('pos'):route('sales.index');
        return view('sales.receipt',['sale'=>$sale->load('items','payments','customer','user'),'shop'=>$request->user()->shop,'returnFrom'=>$returnFrom,'backUrl'=>$backUrl]);
    }
    public function history(Request $request)
    {
        $sales=Sale::where('shop_id',$request->user()->shop_id)->with('items','payments','user')->latest('sold_at')->paginate(20);
        return view('sales.history',compact('sales'));
    }
}
