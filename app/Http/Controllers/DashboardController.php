<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $shopId = $request->user()->shop_id;
        $range = in_array($request->query('range'), ['today', 'week', 'month'], true) ? $request->query('range') : 'today';
        $end = now()->endOfDay();
        $start = match ($range) {
            'week' => now()->subDays(6)->startOfDay(),
            'month' => now()->subMonthNoOverflow()->startOfDay(),
            default => now()->startOfDay(),
        };
        $rangeLabel = match ($range) { 'week' => 'Last 7 days', 'month' => 'Last month', default => 'Today' };
        $dateStart = $start->toDateString();
        $dateEnd = $end->toDateString();
        $sales = Sale::where('shop_id', $shopId)->whereBetween('sold_at', [$start, $end])->where('status', 'completed');
        $summary = ['sales'=>(clone $sales)->sum('total'), 'bills'=>(clone $sales)->count(),
            'items'=>(int) DB::table('sale_items')->join('sales','sales.id','=','sale_items.sale_id')->where('sales.shop_id',$shopId)->whereBetween('sales.sold_at',[$start,$end])->sum('sale_items.quantity'),
            'expenses'=>DB::table('expenses')->where('shop_id',$shopId)->whereBetween('expense_date',[$dateStart,$dateEnd])->sum('amount')];
        $refunds=DB::table('sale_returns')->where('shop_id',$shopId)->whereBetween('returned_at',[$start,$end]);
        $refundTotal=(float)(clone $refunds)->sum('refund_total');
        $returnTax=(float)DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->where('sale_returns.shop_id',$shopId)->whereBetween('sale_returns.returned_at',[$start,$end])->sum('sale_return_items.tax_amount');
        $restockedCost=(float)DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->where('sale_returns.shop_id',$shopId)->whereBetween('sale_returns.returned_at',[$start,$end])->where('sale_return_items.restocked',true)->sum('sale_return_items.cost_amount');
        $summary['sales']-=$refundTotal;
        $summary['items']-=(int)DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->where('sale_returns.shop_id',$shopId)->whereBetween('sale_returns.returned_at',[$start,$end])->sum('sale_return_items.quantity');
        $summary['profit'] = (float) (clone $sales)->sum('total') - $refundTotal - ((float) (clone $sales)->sum('tax_total') - $returnTax)
            - (float) DB::table('sale_items')->join('sales','sales.id','=','sale_items.sale_id')->where('sales.shop_id',$shopId)->whereBetween('sales.sold_at',[$start,$end])->sum(DB::raw('sale_items.cost_price * sale_items.quantity')) + $restockedCost;
        $summary['profit'] -= $summary['expenses'];
        $recent = Sale::where('shop_id',$shopId)->whereBetween('sold_at',[$start,$end])->with('items')->latest('sold_at')->limit(6)->get();
        $lowStock = ProductVariant::with('product')->where('shop_id',$shopId)->where('is_active',true)->whereColumn('stock_quantity','<=','low_stock_threshold')->orderBy('stock_quantity')->limit(6)->get();
        return view('dashboard', compact('summary','recent','lowStock','range','rangeLabel'));
    }
}
