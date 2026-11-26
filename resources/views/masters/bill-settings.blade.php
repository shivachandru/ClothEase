@extends('layouts.app')
@section('title','Bill setting')
@section('section','Masters · Bill setting')
@section('content')
<div class="page-heading"><div><span class="eyebrow">MASTERS</span><h1>Bill setting</h1><p>Configure bill numbers and automatic product barcodes.</p></div></div>
<section class="panel form-panel" style="max-width:760px">
    <form method="post" action="{{ route('masters.bill-settings.update') }}" class="form-grid">
        @csrf @method('PUT')
        <div class="wide"><span class="eyebrow">BILL NUMBERS</span></div>
        <label>Bill prefix<input name="bill_prefix" value="{{ old('bill_prefix',$shop->bill_prefix ?: 'CE') }}" maxlength="30" pattern="[A-Za-z0-9_-]+" required><small>Letters, numbers, hyphens and underscores.</small></label>
        <label>Next bill sequence<input type="number" name="bill_next_number" min="1" max="999999999999" value="{{ old('bill_next_number',$shop->bill_next_number ?: 1) }}" required><small>Changing this affects new bills only.</small></label>
        <div class="wide"><span class="eyebrow">PREVIEW</span><p class="table-primary">{{ old('bill_prefix',$shop->bill_prefix ?: 'CE') }}-{{ str_pad((string)(old('bill_next_number',$shop->bill_next_number ?: 1)),6,'0',STR_PAD_LEFT) }}</p></div>
        <div class="wide" style="border-top:1px solid #e8eeeb;padding-top:18px"><span class="eyebrow">PRODUCT BARCODES</span></div>
        <label class="wide" style="display:flex;align-items:center;gap:9px"><input id="barcode-use-prefix" type="checkbox" name="barcode_use_prefix" value="1" @checked(old('barcode_use_prefix',$shop->barcode_use_prefix)) style="width:auto;height:auto;margin:0"><span>Include a prefix in generated barcodes</span></label>
        <label>Barcode prefix<input id="barcode-prefix" name="barcode_prefix" value="{{ old('barcode_prefix',$shop->barcode_prefix) }}" maxlength="30" pattern="[A-Za-z0-9_-]+" placeholder="e.g. CE" @required(old('barcode_use_prefix',$shop->barcode_use_prefix))><small>Letters, numbers, hyphens and underscores.</small></label>
        <label>Next barcode number <input id="barcode-next-number" type="number" name="barcode_next_number" min="1" max="999999999999" value="{{ old('barcode_next_number',$shop->barcode_next_number ?: 1) }}" required><small>Numbers already in use are skipped automatically.</small></label>
        <div class="wide"><span class="eyebrow">BARCODE PREVIEW</span><p id="barcode-preview" class="table-primary">{{ old('barcode_use_prefix',$shop->barcode_use_prefix) ? old('barcode_prefix',$shop->barcode_prefix) : '' }}{{ old('barcode_next_number',$shop->barcode_next_number ?: 1) }}</p></div>
        <div class="form-actions wide"><button class="button primary" type="submit">Save settings</button><button class="button secondary" type="submit" name="reset_barcode_sequence" value="1" formnovalidate onclick="return confirm('Reset the next barcode number to 1? Existing barcodes will be preserved and skipped.')">Reset barcode sequence</button></div>
    </form>
</section>
<script>
const useBarcodePrefix=document.getElementById('barcode-use-prefix'), barcodePrefix=document.getElementById('barcode-prefix');
const updateBarcodePreview=()=>{barcodePrefix.disabled=!useBarcodePrefix.checked;barcodePrefix.required=useBarcodePrefix.checked;document.getElementById('barcode-preview').textContent=(useBarcodePrefix.checked?barcodePrefix.value:'')+(document.getElementById('barcode-next-number').value||'1')};
useBarcodePrefix.addEventListener('change',updateBarcodePreview);barcodePrefix.addEventListener('input',updateBarcodePreview);document.getElementById('barcode-next-number').addEventListener('input',updateBarcodePreview);updateBarcodePreview();
</script>
@endsection
