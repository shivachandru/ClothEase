@extends('layouts.app')
@section('title','Customers')
@section('section','Customers')
@section('content')
<div class="page-heading"><div><span class="eyebrow">CUSTOMER ACCOUNTS</span><h1>Customers</h1><p>Optional customer profiles, purchase history and Udhaar balance.</p></div><a class="button secondary" href="{{ route('customers.export') }}">↓ Download Excel CSV</a></div>
<div class="finance-layout">
    <section class="panel form-panel"><div class="form-section-title"><span class="step-number">＋</span><div><h2>Add a customer</h2><p>Walk-in bills do not need a customer profile.</p></div></div>
        <form method="post" action="{{ route('customers.store') }}" class="form-grid">@csrf
            <label>Name<input name="name" maxlength="150" required></label><label>Mobile<input name="mobile" inputmode="tel" maxlength="20"></label>
            <label class="wide">Address <span class="optional">OPTIONAL</span><input name="address" maxlength="1000"></label><label class="wide">Notes <span class="optional">OPTIONAL</span><input name="notes" maxlength="1000"></label>
            <div class="form-actions wide"><button class="button primary">Save customer</button></div>
        </form>
    </section>
    <section class="panel expense-summary"><span class="eyebrow">REGISTERED CUSTOMERS</span><strong>{{ number_format($customers->total()) }}</strong><p>Customer records in this shop.</p><div class="metric-icon blue">♙</div></section>
</div>
<section class="panel"><div class="panel-heading"><div><h2>Customer list</h2><p>Outstanding balances include open Udhaar bills.</p></div></div>
@if($customers->isEmpty())
    <div class="empty-state"><div class="empty-icon">♙</div><b>No customer profiles yet</b><p>Add profiles when you need purchase history or credit tracking.</p></div>
@else
    <div class="table-wrap"><table><thead><tr><th>CUSTOMER</th><th>MOBILE</th><th>BILLS</th><th>OUTSTANDING CREDIT</th><th></th></tr></thead><tbody>
    @foreach($customers as $customer)<tr><td><a class="table-primary" href="{{ route('customers.show',$customer) }}">{{ $customer->name }}</a></td><td>{{ $customer->mobile ?? '—' }}</td><td>{{ $customer->sales_count }}</td><td class="amount">₹{{ number_format($customer->outstanding_balance??0,2) }}</td><td><a class="text-link" href="{{ route('customers.show',$customer) }}">View account →</a></td></tr>@endforeach
    </tbody></table></div><div class="pagination">{{ $customers->links() }}</div>
@endif
</section>
@endsection
