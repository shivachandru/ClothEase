@extends('layouts.app')
@section('title','Stock status')
@section('section','Inventory')
@section('content')
<div class="page-heading"><div><span class="eyebrow">STOCK CONTROL</span><h1>Stock status</h1><p>Available quantity for every product variant.</p></div><div class="heading-actions"><a class="button secondary" href="{{ route('inventory.index') }}">Inventory</a><a class="button secondary" href="{{ route('inventory.movements') }}">All movements</a></div></div>
<section class="panel inventory-panel">
    <form class="toolbar" method="get" action="{{ route('inventory.stock-status') }}">
        <label class="search-box"><span>⌕</span><input name="q" value="{{ $filters['q']??'' }}" placeholder="Search product, SKU or barcode…"></label>
        <label><span>Status</span><select name="status"><option value="all" @selected(($filters['status']??'all')==='all')>All items</option><option value="available" @selected(($filters['status']??'')==='available')>In stock</option><option value="low" @selected(($filters['status']??'')==='low')>Low stock</option><option value="out" @selected(($filters['status']??'')==='out')>Out of stock</option></select></label>
        <button class="button primary" type="submit">Filter</button>
        <a class="button secondary" href="{{ route('inventory.stock-status.export',request()->query()) }}">Export Excel CSV</a>
        <span class="result-count">{{ number_format($variants->total()) }} items</span>
    </form>
    @if($variants->isEmpty())
        <div class="empty-state"><div class="empty-icon">▥</div><b>No items found</b><p>Try changing your search or stock status filter.</p></div>
    @else
        <div class="table-wrap"><table><thead><tr><th>PRODUCT / VARIANT</th><th>SKU</th><th>BARCODE</th><th>AVAILABLE QTY</th><th>LOW STOCK AT</th><th>STATUS</th></tr></thead><tbody>
        @foreach($variants as $variant)
            @php($stockStatus=$variant->stock_quantity===0?'Out of stock':($variant->stock_quantity<=$variant->low_stock_threshold?'Low stock':'In stock'))
            <tr><td><div class="product-cell"><span class="product-thumb">{{ strtoupper(substr($variant->product?->name??'?',0,1)) }}</span><span><b class="table-primary">{{ $variant->product?->name??'Deleted product' }}</b><small>{{ collect([$variant->size,$variant->colour])->filter()->join(' · ') ?: 'Standard' }}</small></span></div></td><td>{{ $variant->sku }}</td><td>{{ $variant->barcode??'—' }}</td><td><b class="stock-count {{ $variant->stock_quantity===0?'out':'' }}">{{ number_format($variant->stock_quantity) }} pcs</b></td><td>{{ number_format($variant->low_stock_threshold) }} pcs</td><td><span class="status-badge {{ $stockStatus==='In stock'?'':($stockStatus==='Low stock'?'warning':'danger') }}">{{ $stockStatus }}</span></td></tr>
        @endforeach
        </tbody></table></div>
        <div class="pagination">{{ $variants->links() }}</div>
    @endif
</section>
@endsection
