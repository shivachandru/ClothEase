<?php
namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $filters=$request->validate(['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from'],'tax_mode'=>['nullable','in:all,inclusive,exclusive']]);
        $from=$filters['from']??now()->toDateString(); $to=$filters['to']??now()->toDateString(); $shopId=$request->user()->shop_id;
        $taxMode=$filters['tax_mode']??'all';
        $query=Sale::where('shop_id',$shopId)->where('status','completed')->whereBetween('sold_at',[$from.' 00:00:00',$to.' 23:59:59']);
        if($taxMode!=='all')$query->where('incl_tax',$taxMode==='inclusive');
        $bills=(clone $query)->with('items','payments')->latest('sold_at')->paginate(20)->withQueryString();
        $salesTotal=(float)(clone $query)->sum('total'); $taxTotal=(float)(clone $query)->sum('tax_total');
        $costQuery=DB::table('sale_items')->join('sales','sales.id','=','sale_items.sale_id')->where('sales.shop_id',$shopId)->where('sales.status','completed')->whereBetween('sales.sold_at',[$from.' 00:00:00',$to.' 23:59:59']);
        if($taxMode!=='all')$costQuery->where('sales.incl_tax',$taxMode==='inclusive');
        $cost=(float)$costQuery->sum(DB::raw('sale_items.cost_price * sale_items.quantity'));
        $expenseTotal=(float)DB::table('expenses')->where('shop_id',$shopId)->whereDate('expense_date','>=',$from)->whereDate('expense_date','<=',$to)->sum('amount');
        $refundQuery=DB::table('sale_returns')->join('sales','sales.id','=','sale_returns.sale_id')->where('sale_returns.shop_id',$shopId)->whereBetween('sale_returns.returned_at',[$from.' 00:00:00',$to.' 23:59:59']);
        if($taxMode!=='all')$refundQuery->where('sales.incl_tax',$taxMode==='inclusive');
        $refundTotal=(float)(clone $refundQuery)->sum('sale_returns.refund_total');
        $refundTaxQuery=DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->join('sales','sales.id','=','sale_returns.sale_id')->where('sale_returns.shop_id',$shopId)->whereBetween('sale_returns.returned_at',[$from.' 00:00:00',$to.' 23:59:59']);
        if($taxMode!=='all')$refundTaxQuery->where('sales.incl_tax',$taxMode==='inclusive');
        $refundTax=(float)(clone $refundTaxQuery)->sum('sale_return_items.tax_amount');
        $restockedCostQuery=DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->join('sales','sales.id','=','sale_returns.sale_id')->where('sale_returns.shop_id',$shopId)->whereBetween('sale_returns.returned_at',[$from.' 00:00:00',$to.' 23:59:59'])->where('sale_return_items.restocked',true);
        if($taxMode!=='all')$restockedCostQuery->where('sales.incl_tax',$taxMode==='inclusive');
        $restockedCost=(float)$restockedCostQuery->sum('sale_return_items.cost_amount');
        $salesTotal-=$refundTotal; $taxTotal-=$refundTax; $cost-=$restockedCost;
        $summary=['bills'=>(clone $query)->count(),'sales'=>$salesTotal,'gross_profit'=>$salesTotal-$taxTotal-$cost,'expenses'=>$expenseTotal,'operating_profit'=>$salesTotal-$taxTotal-$cost-$expenseTotal];
        if($request->boolean('export')) return $this->export($query);
        return view('reports.sales',compact('bills','summary','from','to','taxMode'));
    }
    private function export($query): StreamedResponse
    {
        return response()->streamDownload(function()use($query){
            $out=fopen('php://output','w'); fputcsv($out,['Bill number','Date','Items','Subtotal INR','Discount INR','Tax INR','Total INR','Payment methods']);
            $query->with('items','payments')->orderBy('sold_at')->chunk(300,function($sales)use($out){foreach($sales as $sale)fputcsv($out,[$sale->bill_number,$sale->sold_at->format('Y-m-d H:i:s'),$sale->items->sum('quantity'),$sale->subtotal,$sale->discount,$sale->tax_total,$sale->total,$sale->payments->pluck('method')->unique()->join('|')]);});
            fclose($out);
        },'clothease-sales-report.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
