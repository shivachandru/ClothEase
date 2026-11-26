<?php
namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ReturnService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $sales = Sale::where('shop_id', $request->user()->shop_id)
            ->where('status', 'completed')->with('items', 'user', 'returns')
            ->latest('sold_at')->paginate(20);
        return view('sales.returns-index', compact('sales'));
    }

    public function create(Request $request,Sale $sale)
    {
        abort_unless($sale->shop_id===$request->user()->shop_id,404);
        abort_unless($sale->status==='completed',422);
        $sale->load('items','customer');
        $returned=DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->where('sale_returns.sale_id',$sale->id)->select('sale_return_items.sale_item_id',DB::raw('SUM(sale_return_items.quantity) as quantity'))->groupBy('sale_return_items.sale_item_id')->get()->keyBy('sale_item_id');
        $returnFrom=in_array($request->query('from'),['pos','history'],true)?$request->query('from'):'history';
        return view('sales.return',compact('sale','returned','returnFrom'));
    }
    public function store(Request $request,Sale $sale,ReturnService $service,AuditService $audit)
    {
        abort_unless($sale->shop_id===$request->user()->shop_id,404);
        $data=$request->validate(['items'=>['required','array','min:1'],'items.*.sale_item_id'=>['required','integer','distinct'],
            'items.*.quantity'=>['required','integer','min:1'],'items.*.condition'=>['required','in:good,damaged'],
            'refund_method'=>['required','in:cash,upi,card,other'],'reference'=>['nullable','string','max:100'],
            'replacement_items'=>['nullable','array'],'replacement_items.*.variant_id'=>['required','integer','exists:product_variants,id'],
            'replacement_items.*.quantity'=>['required','integer','min:1','max:999'],'collection_method'=>['nullable','in:cash,upi,card,other,udhaar'],
            'collection_reference'=>['nullable','string','max:100'],'return_from'=>['nullable','in:pos,history']]);
        $return=$service->refund($request->user(),$sale,$data['items'],$data['refund_method'],$data['reference']??null,
            $data['replacement_items']??[],$data['collection_method']??'cash',$data['collection_reference']??null,$audit);
        $receiptSale=$return->exchange_sale_id ? Sale::findOrFail($return->exchange_sale_id) : $sale;
        $returnFrom=in_array($data['return_from']??null,['pos','history'],true)?$data['return_from']:'history';
        return redirect()->to(route('sales.receipt',$receiptSale).'?from='.$returnFrom)->with('success','Return '.$return->return_number.' recorded. Return value: ₹'.number_format($return->refund_total,2));
    }
}
