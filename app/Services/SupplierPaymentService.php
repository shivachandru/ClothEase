<?php
namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function collect(Supplier $supplier,User $user,float $amount,string $method,?string $reference=null,?AuditService $audit=null): void
    {
        DB::transaction(function()use($supplier,$user,$amount,$method,$reference,$audit){
            $supplier=Supplier::where('shop_id',$supplier->shop_id)->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $purchases=$supplier->purchases()->where('balance_due','>',0)->orderBy('purchase_date')->lockForUpdate()->get();
            $outstanding=round((float)$purchases->sum('balance_due'),2);
            if($amount<0.01||$amount-$outstanding>0.009)throw ValidationException::withMessages(['amount'=>'Payment cannot exceed this supplier’s outstanding balance.']);
            $remaining=round($amount,2);
            foreach($purchases as $purchase){
                if($remaining<=0)break;
                $applied=min($remaining,(float)$purchase->balance_due);
                $purchase->amount_paid=round((float)$purchase->amount_paid+$applied,2);$purchase->balance_due=round((float)$purchase->balance_due-$applied,2);
                $purchase->status=$purchase->balance_due<=0?'paid':'partial';$purchase->save();
                SupplierPayment::create(['shop_id'=>$supplier->shop_id,'supplier_id'=>$supplier->id,'purchase_id'=>$purchase->id,'user_id'=>$user->id,
                    'method'=>$method,'amount'=>$applied,'reference'=>$reference,'paid_at'=>now()]);
                $audit?->record($user,'supplier.payment_collected',$purchase,['supplier_id'=>$supplier->id,'amount'=>$applied,'method'=>$method]);
                $remaining=round($remaining-$applied,2);
            }
        });
    }
}
