@extends('layouts.app')
@section('title','Receipt template')
@section('section','Masters · Receipt template')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">MASTERS</span><h1>Receipt template</h1><p>Choose how each section lines up on printed bills.</p></div>
</div>
<div class="receipt-template-layout">
    <section class="panel form-panel receipt-template-controls">
        <form method="post" action="{{ route('masters.receipt-template.update') }}" class="form-grid">
            @csrf @method('PUT')
            <div class="receipt-alignment-grid">
                @foreach(['header'=>'Store header','details'=>'Bill details','items'=>'Items table','summary'=>'Totals','payments'=>'Payment summary','gst'=>'GST breakup','footer'=>'Footer'] as $key=>$label)
                    <label>{{ $label }}
                        <select name="receipt_alignment[{{ $key }}]" data-alignment="{{ $key }}" required>
                            @foreach(['left'=>'Left','center'=>'Center','right'=>'Right'] as $value=>$option)
                                <option value="{{ $value }}" @selected($alignment[$key]===$value)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
            <label class="wide">Receipt font size <span id="receipt-font-size-label">{{ $fontSize }} px</span>
                <input type="range" id="receipt-font-size" name="receipt_font_size" min="8" max="16" step="1" value="{{ $fontSize }}">
                <small>Adjusts other receipt text. The store header and items table have separate controls below.</small>
            </label>
            <label class="wide">Store header font size <span id="receipt-header-font-size-label">{{ $headerFontSize }} px</span>
                <input type="range" id="receipt-header-font-size" name="receipt_header_font_size" min="12" max="30" step="1" value="{{ $headerFontSize }}">
            </label>
            <label class="wide">Subheader &amp; GST font size <span id="receipt-subheader-gst-font-size-label">{{ $subheaderGstFontSize }} px</span>
                <input type="range" id="receipt-subheader-gst-font-size" name="receipt_subheader_gst_font_size" min="7" max="16" step="1" value="{{ $subheaderGstFontSize }}">
            </label>
            <label class="wide">Items table font size <span id="receipt-items-font-size-label">{{ $itemsFontSize }} px</span>
                <input type="range" id="receipt-items-font-size" name="receipt_items_font_size" min="7" max="16" step="1" value="{{ $itemsFontSize }}">
            </label>
            <label class="wide"><input type="checkbox" id="receipt-show-gstin" name="receipt_show_gstin" value="1" @checked(old('receipt_show_gstin', $showGstin))> Show GST number on receipt</label>
            <div class="form-actions wide">
                <button class="button primary" type="submit">Save template</button>
                <button class="button secondary" type="submit" name="reset_alignment" value="1" formnovalidate onclick="return confirm('Restore the default receipt alignment?')">Restore defaults</button>
            </div>
        </form>
    </section>
    <section class="receipt-template-preview-wrap">
        <div class="panel-heading"><div><h2>Live preview</h2><p>Preview alignment before saving.</p></div></div>
        <article class="receipt thermal-receipt receipt-template-preview" style="--receipt-font-scale:{{ $fontSize / 10 }};--receipt-header-font-scale:{{ $headerFontSize / 18 }};--receipt-items-font-scale:{{ $itemsFontSize / 9 }};--receipt-subheader-gst-font-scale:{{ $subheaderGstFontSize / 10 }}">
            <header class="thermal-header" data-preview-section="header">
                @if($shop->logo_path)<img class="thermal-shop-logo" src="{{ route('facility.logo',['path'=>$shop->logo_path]) }}" alt="{{ $shop->name }} logo">@endif
                <h1>{{ $shop->name }}</h1>
                @if($shop->subheader)<p class="thermal-subheader">{{ $shop->subheader }}</p>@endif
                @if($shop->address)<p>{{ $shop->address }}</p>@endif
                @if($shop->gstin)<p class="thermal-gstin" data-preview-gstin @unless($showGstin) hidden @endunless>GSTIN: {{ $shop->gstin }}</p>@endif
            </header>
            <section class="thermal-meta align-{{ $alignment['details'] }}" data-preview-section="details">
                <div><span>Bill No.</span><b>CE-000001</b></div><div><span>Date</span><b>{{ now()->format('d-m-Y h:i A') }}</b></div><div><span>User</span><b>Cashier</b></div>
            </section>
            <table class="thermal-items align-{{ $alignment['items'] }}" data-preview-section="items">
                <thead><tr><th>ITEM</th><th>QTY</th><th>MRP</th><th>RATE</th><th>AMT</th></tr></thead>
                <tbody><tr><td>Sample item<small>SKU-001 · M · Black</small></td><td>1</td><td>500.00</td><td>500.00</td><td>525.00</td></tr></tbody>
            </table>
            <section class="thermal-summary align-{{ $alignment['summary'] }}" data-preview-section="summary">
                <div><span>MRP Total</span><b>₹500.00</b></div><div><span>Total Pieces</span><b>1</b></div><div class="thermal-net"><span>Net Amount</span><b>₹525.00</b></div>
            </section>
            <section class="thermal-payments align-{{ $alignment['payments'] }}" data-preview-section="payments"><div><span>Cash Amt</span><b>₹525.00</b></div></section>
            <section class="thermal-gst align-{{ $alignment['gst'] }}" data-preview-section="gst">
                <h2>GST BREAKUP</h2><table><thead><tr><th>TAX VALUE</th><th>CGST<br>RATE · AMT</th><th>SGST<br>RATE · AMT</th><th>TOTAL</th></tr></thead><tbody><tr><td>500.00</td><td>2.50%<br>12.50</td><td>2.50%<br>12.50</td><td>525.00</td></tr></tbody></table>
            </section>
            <div class="thermal-bill-total align-{{ $alignment['summary'] }}"><span>TOTAL BILL AMOUNT</span><b>₹525.00</b></div>
            <footer class="thermal-footer" data-preview-section="footer">Thank you for shopping with us!</footer>
        </article>
    </section>
</div>
<script>
document.querySelectorAll('[data-alignment]').forEach(select=>select.addEventListener('change',()=>{
    const section=document.querySelector(`[data-preview-section="${select.dataset.alignment}"]`);
    if(!section)return;
    section.classList.remove('align-left','align-center','align-right');
    section.classList.add(`align-${select.value}`);
    if(select.dataset.alignment==='summary')document.querySelector('.thermal-bill-total').className=`thermal-bill-total align-${select.value}`;
}));
const receiptFontSize=document.getElementById('receipt-font-size');
receiptFontSize?.addEventListener('input',()=>{
    document.getElementById('receipt-font-size-label').textContent=receiptFontSize.value+' px';
    document.querySelector('.receipt-template-preview')?.style.setProperty('--receipt-font-scale',Number(receiptFontSize.value)/10);
});
const sectionFontControls=[
    {input:'receipt-header-font-size',label:'receipt-header-font-size-label',variable:'--receipt-header-font-scale',base:18},
    {input:'receipt-items-font-size',label:'receipt-items-font-size-label',variable:'--receipt-items-font-scale',base:9},
    {input:'receipt-subheader-gst-font-size',label:'receipt-subheader-gst-font-size-label',variable:'--receipt-subheader-gst-font-scale',base:10},
];
sectionFontControls.forEach(control=>{
    const input=document.getElementById(control.input);
    input?.addEventListener('input',()=>{
        document.getElementById(control.label).textContent=input.value+' px';
        document.querySelector('.receipt-template-preview')?.style.setProperty(control.variable,Number(input.value)/control.base);
    });
});
document.getElementById('receipt-show-gstin')?.addEventListener('change',event=>{
    const gstin=document.querySelector('[data-preview-gstin]');
    if(gstin)gstin.hidden=!event.target.checked;
});
</script>
@endsection
