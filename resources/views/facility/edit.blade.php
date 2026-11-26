@extends('layouts.app')
@section('title','Facility profile')
@section('section','Facility profile')
@section('content')
<div class="page-heading"><div><span class="eyebrow">FACILITY</span><h1>{{ $shop->name }}</h1><p>Store details, receipt branding, and the users assigned to this facility.</p></div></div>
<section class="panel form-panel" style="max-width:850px;margin-bottom:22px">
    <div class="panel-heading"><div><h2>Store profile</h2><p>These details are shared by all users in this facility and appear on receipts.</p></div></div>
    @if(auth()->user()->role==='owner')
    <form method="post" action="{{ route('facility.update') }}" enctype="multipart/form-data" class="form-grid">
        @csrf @method('PUT')
        <label class="wide">Store name<input name="name" value="{{ old('name',$shop->name) }}" maxlength="150" required></label>
        <label class="wide">Store subheader<input name="subheader" value="{{ old('subheader',$shop->subheader) }}" maxlength="200" placeholder="Optional line below the store name on receipts"></label>
        <label>Contact number<input name="mobile" value="{{ old('mobile',$shop->mobile) }}" maxlength="20" inputmode="tel"></label>
        <label>Email<input type="email" name="email" value="{{ old('email',$shop->email) }}" maxlength="150"></label>
        <label>GST number (GSTIN)<input name="gstin" value="{{ old('gstin',$shop->gstin) }}" maxlength="20" autocomplete="off"></label>
        <label class="wide">Address<textarea name="address" rows="3" maxlength="1000">{{ old('address',$shop->address) }}</textarea></label>
        <label class="wide">Store logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG or WebP, up to 2 MB. The logo prints on receipts.</small></label>
        <div class="wide facility-font-option"><label><input type="checkbox" name="use_celandine_font" value="1" @checked(old('use_celandine_font', $shop->use_celandine_font))> Use Celandine Regular font</label><small>Uses the font installed on this device.</small></div>
        @if($shop->logo_path)<div class="wide"><img src="{{ route('facility.logo',['path'=>$shop->logo_path]) }}" alt="{{ $shop->name }} logo" style="max-width:150px;max-height:90px;object-fit:contain"></div>@endif
        <div class="form-actions wide"><button class="button primary">Save facility profile</button></div>
    </form>
    @else
    <div style="padding:0 20px 20px"><p><b>Subheader:</b> {{ $shop->subheader ?: '—' }}</p><p><b>Contact:</b> {{ $shop->mobile ?: '—' }} · {{ $shop->email ?: '—' }}</p><p><b>Address:</b> {{ $shop->address ?: '—' }}</p><p><b>GSTIN:</b> {{ $shop->gstin ?: '—' }}</p>@if($shop->logo_path)<img src="{{ route('facility.logo',['path'=>$shop->logo_path]) }}" alt="{{ $shop->name }} logo" style="max-width:150px;max-height:90px;object-fit:contain">@endif</div>
    @endif
</section>
<section class="panel"><div class="panel-heading"><div><h2>Users in this facility</h2><p>Owner and staff accounts assigned to this store.</p></div><span class="result-count">{{ $users->count() }} users</span></div>
    <div class="table-wrap"><table><thead><tr><th>NAME</th><th>USERNAME</th><th>EMAIL</th><th>ROLE</th><th>STATUS</th></tr></thead><tbody>
    @forelse($users as $user)<tr><td class="table-primary">{{ $user->name }}{{ $user->role==='owner'?' (Admin)':'' }}</td><td>{{ $user->username }}</td><td>{{ $user->email }}</td><td>{{ $user->role==='owner'?'Owner':($user->accessRole?->name ?? ucfirst(str_replace('_',' ',$user->role))) }}</td><td><span class="status-badge">{{ $user->is_active?'Active':'Disabled' }}</span></td></tr>@empty<tr><td colspan="5">No users assigned.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
