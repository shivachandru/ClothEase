<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPaymentService
{
    public function collect(Customer $customer,User $user,float $amount,string $method,?string $reference=null,?AuditService $audit=null): void
    {
        DB::transaction(function()use($customer,$user,$amount,$method,$reference,$audit){
            $customer=Customer::where('shop_id',$customer->shop_id)->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $sales=$customer->sales()->where('balance_due','>',0)->orderBy('sold_at')->lockForUpdate()->get();
            $outstanding=round((float)$sales->sum('balance_due'),2);
            if($amount<0.01||$amount-$outstanding>0.009)throw ValidationException::withMessages(['amount'=>'Collection cannot exceed this customer’s outstanding balance.']);
            $remaining=round($amount,2);
            foreach($sales as $sale){
                if($remaining<=0)break;
                $applied=min($remaining,(float)$sale->balance_due);
                $sale->amount_paid=round((float)$sale->amount_paid+$applied,2);$sale->balance_due=round((float)$sale->balance_due-$applied,2);$sale->save();
                Payment::create(['shop_id'=>$customer->shop_id,'sale_id'=>$sale->id,'method'=>$method,'amount'=>$applied,'reference'=>$reference,'paid_at'=>now()]);
                CustomerPayment::create(['shop_id'=>$customer->shop_id,'customer_id'=>$customer->id,'sale_id'=>$sale->id,'user_id'=>$user->id,
                    'method'=>$method,'amount'=>$applied,'reference'=>$reference,'paid_at'=>now()]);
                $audit?->record($user,'customer.payment_collected',$sale,['customer_id'=>$customer->id,'amount'=>$applied,'method'=>$method]);
                $remaining=round($remaining-$applied,2);
            }
        });
    }
}
