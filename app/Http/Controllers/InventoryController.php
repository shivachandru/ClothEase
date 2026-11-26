<?php
namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\AuditService;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $shopId=$request->user()->shop_id; $search=trim($request->query('q',''));
        $variants=ProductVariant::with('product')->where('shop_id',$shopId)->when($search,fn($q)=>$q->where(function($q)use($search){$q->where('sku','like',"%$search%")->orWhere('barcode',$search)->orWhereHas('product',fn($p)=>$p->where('name','like',"%$search%"));}))->orderBy('stock_quantity')->paginate(15)->withQueryString();
        $movementTotal=StockMovement::where('shop_id',$shopId)->count();
        $unitsOnHand=ProductVariant::where('shop_id',$shopId)->sum('stock_quantity');
        $lowStockCount=ProductVariant::where('shop_id',$shopId)->whereColumn('stock_quantity','<=','low_stock_threshold')->count();
        $variantCount=ProductVariant::where('shop_id',$shopId)->count();
        $stockValue=ProductVariant::where('shop_id',$shopId)->sum(DB::raw('stock_quantity * cost_price'));
        $recent=StockMovement::with('variant.product','user')->where('shop_id',$shopId)->latest()->limit(8)->get();
        return view('inventory.index',compact('variants','recent','search','movementTotal','stockValue','unitsOnHand','lowStockCount','variantCount'));
    }

    public function movements(Request $request)
    {
        $filters=$this->movementFilters($request);
        $movements=$this->movementQuery($request,$filters)->paginate(25)->withQueryString();
        return view('inventory.movements',compact('movements','filters'));
    }

    public function exportMovements(Request $request)
    {
        $filters=$this->movementFilters($request);
        $query=$this->movementQuery($request,$filters);
        return response()->streamDownload(function()use($query){
            $out=fopen('php://output','w');
            fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Date','Product','Variant','SKU','Direction','Movement type','Quantity in','Quantity out','Previous quantity','Available quantity','Recorded by','Reason']);
            $query->chunk(500,function($rows)use($out){
                foreach($rows as $movement){
                    fputcsv($out,[$movement->created_at->format('Y-m-d H:i:s'),$movement->variant?->product?->name??'',collect([$movement->variant?->size,$movement->variant?->colour])->filter()->join(' / '),$movement->variant?->sku??'', $movement->quantity_in>0?'Stock in':'Stock out',str_replace('_',' ',$movement->movement_type),$movement->quantity_in,$movement->quantity_out,$movement->previous_quantity,$movement->new_quantity,$movement->user?->name??'System',$movement->reason??'']);
                }
            });
            fclose($out);
        },'stock-movements-'.($filters['direction']?:'all').'-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function stockStatus(Request $request)
    {
        $filters=$this->stockFilters($request);
        $variants=$this->stockQuery($request,$filters)->paginate(25)->withQueryString();
        return view('inventory.stock-status',compact('variants','filters'));
    }

    public function exportStockStatus(Request $request)
    {
        $filters=$this->stockFilters($request);
        $query=$this->stockQuery($request,$filters);
        return response()->streamDownload(function()use($query){
            $out=fopen('php://output','w');
            fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Product','Variant','SKU','Barcode','Available quantity','Low stock threshold','Stock status','Cost price','Selling price']);
            $query->chunk(500,function($rows)use($out){
                foreach($rows as $variant){
                    $status=$variant->stock_quantity===0?'Out of stock':($variant->stock_quantity<=$variant->low_stock_threshold?'Low stock':'In stock');
                    fputcsv($out,[$variant->product?->name??'',collect([$variant->size,$variant->colour])->filter()->join(' / '),$variant->sku,$variant->barcode,$variant->stock_quantity,$variant->low_stock_threshold,$status,$variant->cost_price,$variant->selling_price]);
                }
            });
            fclose($out);
        },'stock-status-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function movementFilters(Request $request): array
    {
        return $request->validate(['direction'=>['nullable','in:in,out'],'q'=>['nullable','string','max:100'],'from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']]);
    }

    private function movementQuery(Request $request,array $filters)
    {
        return StockMovement::with('variant.product','user')->where('stock_movements.shop_id',$request->user()->shop_id)
            ->when(($filters['direction']??'')==='in',fn($q)=>$q->where('quantity_in','>',0))
            ->when(($filters['direction']??'')==='out',fn($q)=>$q->where('quantity_out','>',0))
            ->when($filters['q']??null,fn($q)=>$q->whereHas('variant',fn($v)=>$v->where('sku','like','%'.$filters['q'].'%')->orWhereHas('product',fn($p)=>$p->where('name','like','%'.$filters['q'].'%'))))
            ->when($filters['from']??null,fn($q)=>$q->whereDate('stock_movements.created_at','>=',$filters['from']))
            ->when($filters['to']??null,fn($q)=>$q->whereDate('stock_movements.created_at','<=',$filters['to']))
            ->latest('stock_movements.created_at')->latest('stock_movements.id');
    }

    private function stockFilters(Request $request): array
    {
        return $request->validate(['q'=>['nullable','string','max:100'],'status'=>['nullable','in:all,low,out,available']]);
    }

    private function stockQuery(Request $request,array $filters)
    {
        return ProductVariant::with('product')->where('product_variants.shop_id',$request->user()->shop_id)
            ->when($filters['q']??null,fn($q)=>$q->where(function($q)use($filters){$q->where('sku','like','%'.$filters['q'].'%')->orWhere('barcode','like','%'.$filters['q'].'%')->orWhereHas('product',fn($p)=>$p->where('name','like','%'.$filters['q'].'%'));}))
            ->when(($filters['status']??'')==='out',fn($q)=>$q->where('stock_quantity',0))
            ->when(($filters['status']??'')==='low',fn($q)=>$q->where('stock_quantity','>',0)->whereColumn('stock_quantity','<=','low_stock_threshold'))
            ->when(($filters['status']??'')==='available',fn($q)=>$q->whereColumn('stock_quantity','>','low_stock_threshold'))
            ->orderBy('stock_quantity')->orderBy('sku');
    }
    public function adjust(Request $request, ProductVariant $variant,AuditService $audit)
    {
        abort_unless($variant->shop_id===$request->user()->shop_id,404);
        $data=$request->validate(['new_quantity'=>['required','integer','min:0','max:1000000'],'reason'=>['required','string','max:150']]);
        DB::transaction(function()use($request,$variant,$data,$audit){
            $locked=ProductVariant::where('shop_id',$request->user()->shop_id)->lockForUpdate()->findOrFail($variant->id);
            $before=$locked->stock_quantity; $after=(int)$data['new_quantity'];
            if($before===$after) return;
            $locked->stock_quantity=$after; $locked->save();
            $movement=StockMovement::create(['shop_id'=>$request->user()->shop_id,'variant_id'=>$locked->id,'user_id'=>$request->user()->id,
                'movement_type'=>'adjustment','quantity_in'=>max(0,$after-$before),'quantity_out'=>max(0,$before-$after),
                'previous_quantity'=>$before,'new_quantity'=>$after,'reason'=>$data['reason']]);
            $audit->record($request->user(),'stock.adjusted',$movement,['variant_id'=>$locked->id,'previous_quantity'=>$before,'new_quantity'=>$after,'reason'=>$data['reason']]);
        });
        return redirect()->route('inventory.index')->with('success','Stock count updated and recorded in the movement history.');
    }
}
