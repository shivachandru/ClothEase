@extends('layouts.app')
@section('title','Process returns')
@section('section','Process returns')
@section('content')
<div class="page-heading"><div><span class="eyebrow">RETURNS & EXCHANGES</span><h1>Process return</h1><p>Select a completed bill to refund or exchange returned items.</p></div><a class="button secondary" href="{{ route('sales.index') }}">Bill history</a></div>
<section class="panel">
    @if($sales->isEmpty())
        <div class="empty-state"><div class="empty-icon">↩</div><b>No completed bills yet</b><p>Completed bills will appear here for return processing.</p></div>
    @else
        <div class="table-wrap"><table><thead><tr><th>BILL</th><th>DATE & TIME</th><th>CREATED BY</th><th>ITEMS</th><th>AMOUNT</th><th>RETURN STATUS</th><th></th></tr></thead><tbody>
        @foreach($sales as $sale)
            <tr><td><a class="table-primary" href="{{ route('sales.receipt',$sale) }}?from=history">{{ $sale->bill_number }}</a></td><td>{{ $sale->sold_at->format('d M Y, h:i A') }}</td><td>{{ $sale->user?->name ?? 'Staff' }}</td><td>{{ $sale->items->sum('quantity') }}</td><td class="amount">₹{{ number_format($sale->total,2) }}</td><td>{{ $sale->returns->isNotEmpty()?'Previously returned':'No returns' }}</td><td><a class="button secondary" href="{{ route('returns.create',$sale) }}?from=history">Process return</a></td></tr>
        @endforeach
        </tbody></table></div><div class="pagination">{{ $sales->links() }}</div>
    @endif
</section>
@endsection
