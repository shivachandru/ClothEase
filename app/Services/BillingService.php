<?php
namespace App\Services;

use App\Models\Payment;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function complete(User $cashier, array $lines, array $payments, float $billDiscount = 0, ?int $customerId = null, bool $allowCredit = false, ?AuditService $audit = null, bool $includeTax = true, float $tenderedAmount = 0, ?string $customerName = null, ?string $customerMobile = null): Sale
    {
        return DB::transaction(function () use ($cashier, $lines, $payments, $billDiscount, $customerId, $allowCredit, $audit, $includeTax, $tenderedAmount, $customerName, $customerMobile) {
            $shop = $cashier->shop;
            $shop = $shop->newQuery()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $customer=$customerId?Customer::where('shop_id',$shop->id)->whereKey($customerId)->first():null;
            if($customerId&&!$customer)throw ValidationException::withMessages(['customer_id'=>'Choose a customer from this shop.']);
            if(!$customer && ($customerName || $customerMobile)){
                $customer=$customerMobile ? Customer::where('shop_id',$shop->id)->where('mobile',$customerMobile)->first() : null;
                if(!$customer)$customer=Customer::create(['shop_id'=>$shop->id,'name'=>$customerName ?: 'Walk-in customer','mobile'=>$customerMobile ?: null]);
            }
            if($allowCredit&&!$customer)throw ValidationException::withMessages(['customer_id'=>'Select a customer before allowing an Udhaar balance.']);
            $sale = new Sale(['shop_id' => $shop->id, 'user_id' => $cashier->id,
                'customer_id'=>$customer?->id,
                'bill_number' => $shop->bill_prefix.'-'.str_pad((string)$shop->bill_next_number, 6, '0', STR_PAD_LEFT),
                'status' => 'completed', 'subtotal' => 0, 'discount' => 0, 'tax_total' => 0, 'incl_tax'=>$includeTax, 'total' => 0,'amount_paid'=>0,'balance_due'=>0,
                'sold_at' => now()]);
            $sale->save();
            $shop->increment('bill_next_number');
            $subtotal = $taxTotal = $itemDiscountTotal = 0.0;
            foreach ($lines as $line) {
                $variant = $shop->variants()->whereKey($line['variant_id'])->lockForUpdate()->first();
                if (!$variant || !$variant->is_active) throw ValidationException::withMessages(['cart' => 'A selected variant is no longer available.']);
                $qty = (int) $line['quantity'];
                if ($qty < 1) throw ValidationException::withMessages(['cart' => 'Quantity must be at least one.']);
                if (!$shop->allow_negative_stock && $variant->stock_quantity < $qty) {
                    throw ValidationException::withMessages(['cart' => "Only {$variant->stock_quantity} left for {$variant->product->name} ({$variant->sku})."]);
                }
                $price = (float) $variant->selling_price;
                $itemDiscount = min((float) ($line['discount'] ?? 0), $price * $qty);
                $taxable = max(0, $price * $qty - $itemDiscount);
                $tax = $includeTax ? round(($price * $qty) * ((float) $variant->tax_rate / 100), 2) : 0;
                $lineTotal = round($taxable + $tax, 2);
                $before = $variant->stock_quantity;
                $variant->stock_quantity -= $qty; $variant->save();
                $label = trim(implode(' · ', array_filter([$variant->size, $variant->colour])));
                SaleItem::create(['sale_id' => $sale->id, 'variant_id' => $variant->id,
                    'product_name' => $variant->product->name, 'sku' => $variant->sku,
                    'variant_label' => $label ?: null, 'quantity' => $qty, 'unit_price' => $price,
                    'cost_price' => $variant->cost_price, 'discount' => $itemDiscount,
                    'tax_rate' => $variant->tax_rate, 'tax_amount' => $tax, 'line_total' => $lineTotal]);
                StockMovement::create(['shop_id' => $shop->id, 'variant_id' => $variant->id, 'user_id' => $cashier->id,
                    'movement_type' => 'sale', 'quantity_in' => 0, 'quantity_out' => $qty,
                    'previous_quantity' => $before, 'new_quantity' => $variant->stock_quantity,
                    'reference_type' => Sale::class, 'reference_id' => $sale->id, 'reason' => 'Bill '.$sale->bill_number]);
                $subtotal += $price * $qty; $itemDiscountTotal += $itemDiscount; $taxTotal += $tax;
            }
            if (!$lines) throw ValidationException::withMessages(['cart' => 'Add at least one item.']);
            $discount = min(max(0, $billDiscount), max(0, $subtotal - $itemDiscountTotal));
            $total = round(max(0, $subtotal + $taxTotal - $itemDiscountTotal - $discount), 2);
            $paymentTotal = round(collect($payments)->sum(fn ($p) => (float) $p['amount']), 2);
            if($paymentTotal-$total>0.009||(!$allowCredit&&abs($paymentTotal-$total)>0.009))throw ValidationException::withMessages(['payments'=>'Payment amounts must equal the bill total unless customer credit is enabled.']);
            $balance=round(max(0,$total-$paymentTotal),2);
            $cashPaid=round(collect($payments)->where('method','cash')->sum(fn($p)=>(float)$p['amount']),2);
            $tendered=$tenderedAmount>0?$tenderedAmount:$cashPaid;
            if($tendered+0.009<$cashPaid)throw ValidationException::withMessages(['tendered_amount'=>'Tendered cash cannot be less than the cash payment amount.']);
            $changeDue=round(max(0,$tendered-$cashPaid),2);
            $sale->update(['subtotal' => round($subtotal, 2), 'discount' => round($discount + $itemDiscountTotal, 2), 'tax_total' => round($taxTotal, 2), 'incl_tax'=>$includeTax, 'total' => $total,'amount_paid'=>$paymentTotal,'balance_due'=>$balance,'tendered_amount'=>$tendered,'change_due'=>$changeDue]);
            foreach ($payments as $payment) {
                Payment::create(['shop_id' => $shop->id, 'sale_id' => $sale->id,
                    'method' => $payment['method'], 'amount' => $payment['amount'], 'reference' => $payment['reference'] ?? null, 'paid_at' => now()]);
                if($customer)CustomerPayment::create(['shop_id'=>$shop->id,'customer_id'=>$customer->id,'sale_id'=>$sale->id,'user_id'=>$cashier->id,
                    'method'=>$payment['method'],'amount'=>$payment['amount'],'reference'=>$payment['reference']??null,'paid_at'=>now()]);
            }
            $audit?->record($cashier,'sale.completed',$sale,['bill_number'=>$sale->bill_number,'total'=>$total,'items_count'=>count($lines),'customer_id'=>$customer?->id,'tax_included'=>$includeTax]);
            return $sale->load('items', 'payments');
        });
    }
}
