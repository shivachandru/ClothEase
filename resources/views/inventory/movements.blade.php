@extends('layouts.app')
@section('title','Stock movements')
@section('section','Inventory')
@section('content')
<div class="page-heading"><div><span class="eyebrow">STOCK CONTROL</span><h1>Stock movements</h1><p>Review every recorded stock-in and stock-out change.</p></div><div class="heading-actions"><a class="button secondary" href="{{ route('inventory.index') }}">Inventory</a><a class="button secondary" href="{{ route('inventory.stock-status') }}">Stock status</a></div></div>
<section class="panel inventory-panel">
    <form class="toolbar" method="get" action="{{ route('inventory.movements') }}">
        <label><span>Movement</span><select name="direction"><option value="">All movements</option><option value="in" @selected(($filters['direction']??'')==='in')>Stock in</option><option value="out" @selected(($filters['direction']??'')==='out')>Stock out</option></select></label>
        <label class="search-box"><span>⌕</span><input name="q" value="{{ $filters['q']??'' }}" placeholder="Search product or SKU…"></label>
        <label><span>From</span><input type="date" name="from" value="{{ $filters['from']??'' }}"></label>
        <label><span>To</span><input type="date" name="to" value="{{ $filters['to']??'' }}"></label>
        <button class="button primary" type="submit">Filter</button>
        <a class="button secondary" href="{{ route('inventory.movements.export',request()->query()) }}">Export Excel CSV</a>
        <span class="result-count">{{ number_format($movements->total()) }} movements</span>
    </form>
    @if($movements->isEmpty())
        <div class="empty-state"><div class="empty-icon">↕</div><b>No stock movements found</b><p>Try another filter or record a stock change.</p></div>
    @else
        <div class="table-wrap"><table><thead><tr><th>WHEN</th><th>PRODUCT / VARIANT</th><th>DIRECTION</th><th>TYPE</th><th>IN</th><th>OUT</th><th>BEFORE → AVAILABLE</th><th>RECORDED BY</th><th>REASON</th></tr></thead><tbody>
        @foreach($movements as $movement)
            @php($variantLabel=collect([$movement->variant?->size,$movement->variant?->colour])->filter()->join(' · '))
            <tr><td>{{ $movement->created_at->format('d M Y, h:i A') }}</td><td><b class="table-primary">{{ $movement->variant?->product?->name??'Deleted product' }}</b><small>{{ $movement->variant?->sku??'—' }}{{ $variantLabel?' · '.$variantLabel:'' }}</small></td><td><span class="status-badge {{ $movement->quantity_out>0?'warning':'' }}">{{ $movement->quantity_in>0?'Stock in':'Stock out' }}</span></td><td>{{ ucfirst(str_replace('_',' ',$movement->movement_type)) }}</td><td>{{ $movement->quantity_in?:'—' }}</td><td>{{ $movement->quantity_out?:'—' }}</td><td>{{ $movement->previous_quantity }} → {{ $movement->new_quantity }}</td><td>{{ $movement->user?->name??'System' }}</td><td>{{ $movement->reason??'—' }}</td></tr>
        @endforeach
        </tbody></table></div>
        <div class="pagination">{{ $movements->links() }}</div>
    @endif
</section>
@endsection
