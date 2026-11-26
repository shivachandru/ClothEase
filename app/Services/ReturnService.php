<?php
namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Models\RefundPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function refund(User $user,Sale $sale,array $items,string $method,?string $reference=null,array $replacementItems=[],string $collectionMethod='cash',?string $collectionReference=null,?AuditService $audit=null): SaleReturn
    {
        return DB::transaction(function()use($user,$sale,$items,$method,$reference,$replacementItems,$collectionMethod,$collectionReference,$audit){
            $sale=Sale::where('shop_id',$user->shop_id)->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if($sale->status!=='completed')throw ValidationException::withMessages(['items'=>'Only completed bills can be returned.']);
            $saleItems=$sale->items()->lockForUpdate()->get()->keyBy('id');
            $previous=DB::table('sale_return_items')->join('sale_returns','sale_returns.id','=','sale_return_items.sale_return_id')->where('sale_returns.sale_id',$sale->id)->select('sale_return_items.sale_item_id',DB::raw('SUM(sale_return_items.quantity) as returned_qty'),DB::raw('SUM(sale_return_items.refund_amount) as refunded'),DB::raw('SUM(sale_return_items.tax_amount) as tax_refunded'))->groupBy('sale_return_items.sale_item_id')->get()->keyBy('sale_item_id');
            $billDiscount=max(0,(float)$sale->discount-(float)$sale->items()->sum('discount'));
            $lineGross=(float)$sale->items()->sum('line_total');
            $return=SaleReturn::create(['shop_id'=>$user->shop_id,'sale_id'=>$sale->id,'user_id'=>$user->id,
                'return_number'=>'RET-'.now()->format('ymd').'-'.Str::upper((string)Str::ulid()),'refund_total'=>0,'status'=>'completed','returned_at'=>now()]);
            $refundTotal=0.0;
            foreach($items as $input){
                $sold=$saleItems->get((int)$input['sale_item_id']);
                if(!$sold)throw ValidationException::withMessages(['items'=>'A selected item is not part of this bill.']);
                $prior=$previous->get($sold->id); $already=(int)($prior->returned_qty??0); $remaining=$sold->quantity-$already; $qty=(int)$input['quantity'];
                if($qty<1||$qty>$remaining)throw ValidationException::withMessages(['items'=>"Return quantity exceeds the remaining quantity for {$sold->product_name}."]);
                $allocBillDiscount=$lineGross>0?round($billDiscount*((float)$sold->line_total/$lineGross),2):0;
                $lineRefund=max(0,(float)$sold->line_total-$allocBillDiscount);
                $remainingRefund=max(0,$lineRefund-(float)($prior->refunded??0));
                $refund=($qty===$remaining)?round($remainingRefund,2):round($lineRefund*$qty/$sold->quantity,2);
                $taxRefund=($qty===$remaining)?round(max(0,(float)$sold->tax_amount-(float)($prior->tax_refunded??0)),2):round((float)$sold->tax_amount*$qty/$sold->quantity,2);
                $costAmount=round((float)$sold->cost_price*$qty,2);
                $variant=null; $restocked=false;
                if($input['condition']==='good'&&$sold->variant_id){
                    $variant=ProductVariant::where('shop_id',$user->shop_id)->whereKey($sold->variant_id)->lockForUpdate()->first();
                    if($variant){$before=$variant->stock_quantity;$variant->stock_quantity+=$qty;$variant->save();$restocked=true;
                        StockMovement::create(['shop_id'=>$user->shop_id,'variant_id'=>$variant->id,'user_id'=>$user->id,'movement_type'=>'sale_return','quantity_in'=>$qty,'quantity_out'=>0,'previous_quantity'=>$before,'new_quantity'=>$variant->stock_quantity,'reference_type'=>SaleReturn::class,'reference_id'=>$return->id,'reason'=>'Good-condition return '.$return->return_number]);}
                }
                SaleReturnItem::create(['sale_return_id'=>$return->id,'sale_item_id'=>$sold->id,'variant_id'=>$sold->variant_id,
                    'product_name'=>$sold->product_name,'quantity'=>$qty,'condition'=>$input['condition'],'restocked'=>$restocked,
                    'refund_amount'=>$refund,'tax_amount'=>$taxRefund,'cost_amount'=>$costAmount]);
                $refundTotal+=$refund;
            }
            if(!$items)throw ValidationException::withMessages(['items'=>'Select at least one item to return.']);
            if($refundTotal>$sale->total)throw ValidationException::withMessages(['items'=>'Refund exceeds the original bill total.']);
            $creditToBalance=min($refundTotal,(float)$sale->balance_due);
            if($creditToBalance>0){$sale->balance_due=round((float)$sale->balance_due-$creditToBalance,2);$sale->save();}
            $availableCredit=round(max(0,$refundTotal-$creditToBalance),2);
            $return->update(['refund_total'=>round($refundTotal,2)]);
            $replacementTotal=0.0; $exchangeSale=null;
            if($replacementItems){
                $exchangeSale=Sale::create(['shop_id'=>$user->shop_id,'user_id'=>$user->id,'bill_number'=>'CE-'.now()->format('ymd').'-'.Str::upper((string)Str::ulid()),
                    'status'=>'completed','subtotal'=>0,'discount'=>0,'tax_total'=>0,'total'=>0,'sold_at'=>now()]);
                $subtotal=$taxTotal=0.0;
                foreach($replacementItems as $line){
                    $variant=ProductVariant::with('product')->where('shop_id',$user->shop_id)->whereKey($line['variant_id'])->lockForUpdate()->first();
                    if(!$variant||!$variant->is_active)throw ValidationException::withMessages(['replacement_items'=>'A replacement variant is no longer active.']);
                    $qty=(int)$line['quantity']; if($qty<1)throw ValidationException::withMessages(['replacement_items'=>'Replacement quantity must be at least one.']);
                    if(!$user->shop->allow_negative_stock&&$variant->stock_quantity<$qty)throw ValidationException::withMessages(['replacement_items'=>"Only {$variant->stock_quantity} left for replacement SKU {$variant->sku}."]);
                    $price=(float)$variant->selling_price;$tax=round($price*$qty*(float)$variant->tax_rate/100,2);$lineTotal=round($price*$qty+$tax,2);
                    SaleItem::create(['sale_id'=>$exchangeSale->id,'variant_id'=>$variant->id,'product_name'=>$variant->product->name,'sku'=>$variant->sku,
                        'variant_label'=>trim(implode(' · ',array_filter([$variant->size,$variant->colour])))?:null,'quantity'=>$qty,'unit_price'=>$price,
                        'cost_price'=>$variant->cost_price,'discount'=>0,'tax_rate'=>$variant->tax_rate,'tax_amount'=>$tax,'line_total'=>$lineTotal]);
                    $before=$variant->stock_quantity;$variant->stock_quantity-=$qty;$variant->save();
                    StockMovement::create(['shop_id'=>$user->shop_id,'variant_id'=>$variant->id,'user_id'=>$user->id,'movement_type'=>'sale','quantity_in'=>0,'quantity_out'=>$qty,
                        'previous_quantity'=>$before,'new_quantity'=>$variant->stock_quantity,'reference_type'=>Sale::class,'reference_id'=>$exchangeSale->id,'reason'=>'Replacement sale for '.$return->return_number]);
                    $subtotal+=$price*$qty;$taxTotal+=$tax;
                }
                $replacementTotal=round($subtotal+$taxTotal,2);$creditApplied=min($availableCredit,$replacementTotal);$additional=max(0,$replacementTotal-$creditApplied);
                $exchangeSale->update(['customer_id'=>$sale->customer_id,'subtotal'=>round($subtotal,2),'tax_total'=>round($taxTotal,2),'total'=>$replacementTotal,'amount_paid'=>$creditApplied,'balance_due'=>$additional]);
                if($creditApplied>0)Payment::create(['shop_id'=>$user->shop_id,'sale_id'=>$exchangeSale->id,'method'=>'exchange_credit','amount'=>$creditApplied,'reference'=>$return->return_number,'paid_at'=>now()]);
                if($additional>0&&$collectionMethod==='udhaar'&&!$sale->customer_id)throw ValidationException::withMessages(['collection_method'=>'A customer is required to add an exchange balance to Udhaar.']);
                if($additional>0&&$collectionMethod!=='udhaar'){
                    Payment::create(['shop_id'=>$user->shop_id,'sale_id'=>$exchangeSale->id,'method'=>$collectionMethod,'amount'=>$additional,'reference'=>$collectionReference,'paid_at'=>now()]);
                    $exchangeSale->update(['amount_paid'=>round($creditApplied+$additional,2),'balance_due'=>0]);
                }
                $return->update(['exchange_sale_id'=>$exchangeSale->id]);
            }
            $refundRemainder=round(max(0,$availableCredit-$replacementTotal),2);
            if($refundRemainder>0)RefundPayment::create(['shop_id'=>$user->shop_id,'sale_return_id'=>$return->id,'method'=>$method,
                'amount'=>$refundRemainder,'reference'=>$reference,'refunded_at'=>now()]);
            $audit?->record($user,'sale.returned',$return,['return_number'=>$return->return_number,'refund_value'=>$return->refund_total,'refund_paid'=>$refundRemainder,'exchange_sale_id'=>$exchangeSale?->id]);
            if($exchangeSale)$audit?->record($user,'sale.exchange_created',$exchangeSale,['bill_number'=>$exchangeSale->bill_number,'total'=>$replacementTotal,'return_id'=>$return->id]);
            return $return->load('items','payments');
        });
    }
}
