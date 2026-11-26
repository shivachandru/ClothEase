<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $sale->bill_number }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="receipt-page thermal-page">
    <div class="receipt-actions thermal-actions">
        <span class="receipt-action-group">
            <a href="{{ $backUrl }}" class="button secondary">← {{ $returnFrom==='pos'?'Back to billing':'Bill history' }}</a>
            @if(auth()->user()->role==='owner'||auth()->user()->canAccess('sales'))
                <a href="{{ route('returns.create',$sale) }}?from={{ $returnFrom }}" class="button secondary">↩ Process return</a>
            @endif
        </span>
        <button class="button primary" onclick="window.print()">Print receipt</button>
    </div>

    @if($returnFrom==='pos')
        <div class="alert success thermal-success" role="status">Bill {{ $sale->bill_number }} completed successfully.</div>
    @endif

    @php
        $receiptAlignment = array_merge([
            'header'=>'center','details'=>'left','items'=>'left','summary'=>'left','payments'=>'left','gst'=>'center','footer'=>'center',
        ], $shop->receipt_alignment ?? []);
        $totalPieces = $sale->items->sum('quantity');
        $mrpTotal = $sale->items->sum(fn ($item) => (float) $item->unit_price * (int) $item->quantity);
        $taxGroups = $sale->items->groupBy(fn ($item) => number_format((float) $item->tax_rate, 2, '.', ''));
        $paymentsByMethod = $sale->payments->groupBy('method');
    @endphp

    <article class="receipt thermal-receipt" style="--receipt-font-scale:{{ ($shop->receipt_font_size ?: 10) / 10 }};--receipt-header-font-scale:{{ ($shop->receipt_header_font_size ?: 18) / 18 }};--receipt-items-font-scale:{{ ($shop->receipt_items_font_size ?: 9) / 9 }};--receipt-subheader-gst-font-scale:{{ ($shop->receipt_subheader_gst_font_size ?: 10) / 10 }}">
        <header class="thermal-header" style="text-align:{{ $receiptAlignment['header'] }}">
            @if($shop->logo_path)
                <img class="thermal-shop-logo" src="{{ route('facility.logo',['path'=>$shop->logo_path]) }}" alt="{{ $shop->name }} logo">
            @else
                <img class="receipt-brand-logo" src="{{ asset('images/clothease-logo.png') }}" alt="ClothEase Smart Retail Solutions">
            @endif
            <h1>{{ $shop->name }}</h1>
            @if($shop->subheader)<p class="thermal-subheader">{{ $shop->subheader }}</p>@endif
            @if($shop->address)<p>{{ $shop->address }}</p>@endif
            @if($shop->gstin && ($shop->receipt_show_gstin ?? true))<p class="thermal-gstin">GSTIN: {{ $shop->gstin }}</p>@endif
        </header>

        <section class="thermal-meta align-{{ $receiptAlignment['details'] }}">
            <div><span>Bill No.</span><b>{{ $sale->bill_number }}</b></div>
            <div><span>Date</span><b>{{ $sale->sold_at->format('d-m-Y h:i A') }}</b></div>
            <div><span>User</span><b>{{ $sale->user?->name ?? 'Staff' }}</b></div>
            @if($sale->customer)<div><span>Customer</span><b>{{ $sale->customer->name }}</b></div>@endif
        </section>

        <table class="thermal-items align-{{ $receiptAlignment['items'] }}">
            <colgroup><col style="width:39%"><col style="width:9%"><col style="width:16%"><col style="width:16%"><col style="width:20%"></colgroup>
            <thead><tr><th>ITEM</th><th>QTY</th><th>MRP</th><th>RATE</th><th>AMT</th></tr></thead>
            <tbody>
                @foreach($sale->items as $item)
                    @php
                        $itemMrp = (float) $item->unit_price;
                        $itemRate = $item->quantity > 0 ? (($itemMrp * $item->quantity - (float) $item->discount) / $item->quantity) : $itemMrp;
                    @endphp
                    <tr>
                        <td>{{ $item->product_name }}<small>{{ $item->sku }}{{ $item->variant_label?' · '.$item->variant_label:'' }}</small></td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($itemMrp, 2) }}</td>
                        <td>{{ number_format($itemRate, 2) }}</td>
                        <td>{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="thermal-summary align-{{ $receiptAlignment['summary'] }}">
            <div><span>MRP Total</span><b>₹{{ number_format($mrpTotal, 2) }}</b></div>
            @if((float) $sale->discount > 0)<div><span>Discount Amount</span><b>−₹{{ number_format($sale->discount, 2) }}</b></div>@endif
            <div><span>Total Pieces</span><b>{{ number_format($totalPieces) }}</b></div>
            <div class="thermal-net"><span>Net Amount</span><b>₹{{ number_format($sale->total, 2) }}</b></div>
        </section>

        <section class="thermal-payments align-{{ $receiptAlignment['payments'] }}">
            @php
                $paymentLabels = ['cash'=>'Cash Amt','upi'=>'UPI Amt','card'=>'Card Amt','other'=>'Other Amt'];
                $paymentCounts = $sale->payments->countBy('method');
                $paymentIndexes = [];
            @endphp
            @foreach($sale->payments->sortBy('id') as $payment)
                @php
                    $paymentIndexes[$payment->method] = ($paymentIndexes[$payment->method] ?? 0) + 1;
                    $label = $paymentLabels[$payment->method] ?? ucfirst($payment->method).' Amt';
                    if ($paymentCounts->get($payment->method, 0) > 1) $label .= ' '.$paymentIndexes[$payment->method];
                @endphp
                <div><span>{{ $label }}</span><b>₹{{ number_format($payment->amount, 2) }}</b></div>
            @endforeach
            @if((float) $sale->balance_due > 0)<div><span>Balance</span><b>₹{{ number_format($sale->balance_due, 2) }}</b></div>@endif
            @if((float) $sale->change_due > 0)<div><span>Change</span><b>₹{{ number_format($sale->change_due, 2) }}</b></div>@endif
        </section>

        @if($sale->incl_tax && (float) $sale->tax_total > 0)
            <section class="thermal-gst align-{{ $receiptAlignment['gst'] }}">
                <h2>GST BREAKUP</h2>
                <table>
                    <thead><tr><th>TAX VALUE</th><th>CGST<br>RATE · AMT</th><th>SGST<br>RATE · AMT</th><th>TOTAL</th></tr></thead>
                    <tbody>
                        @foreach($taxGroups as $rate=>$items)
                            @php
                                $taxableValue = $items->sum(fn ($item) => (float) $item->unit_price * (int) $item->quantity - (float) $item->discount);
                                $groupTax = $items->sum(fn ($item) => (float) $item->tax_amount);
                                $halfTax = round($groupTax / 2, 2);
                                $otherHalfTax = round($groupTax - $halfTax, 2);
                                $halfRate = (float) $rate / 2;
                            @endphp
                            @if($groupTax > 0)
                                <tr>
                                    <td>{{ number_format($taxableValue, 2) }}</td>
                                    <td>{{ number_format($halfRate, 2) }}%<br>{{ number_format($halfTax, 2) }}</td>
                                    <td>{{ number_format($halfRate, 2) }}%<br>{{ number_format($otherHalfTax, 2) }}</td>
                                    <td>{{ number_format($taxableValue + $groupTax, 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="thermal-tax-total"><span>Total GST</span><b>₹{{ number_format($sale->tax_total, 2) }}</b></div>
            </section>
        @endif

        <div class="thermal-bill-total align-{{ $receiptAlignment['summary'] }}"><span>TOTAL BILL AMOUNT</span><b>₹{{ number_format($sale->total, 2) }}</b></div>
        <footer class="thermal-footer" style="text-align:{{ $receiptAlignment['footer'] }}">{{ $shop->receipt_footer ?: 'Thank you for shopping with us!' }}</footer>
    </article>
    <script>
        document.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
