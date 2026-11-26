@extends('layouts.app') @section('title','My profile') @section('section','My profile')
@section('content')
<div class="page-heading"><div><span class="eyebrow">ACCOUNT SETTINGS</span><h1>My profile</h1><p>Update your name, username, shop name, email, or password.</p></div></div>
<section class="panel form-panel" style="max-width:760px"><form method="post" action="{{ route('profile.update') }}" class="form-grid">@csrf @method('PUT')
<label>Name<input name="name" value="{{ old('name',$user->name) }}" maxlength="100" required></label>
<label>Username<input name="username" value="{{ old('username',$user->username) }}" maxlength="80" pattern="[A-Za-z0-9_-]+" required></label>
<label class="wide">Shop name<input name="shop_name" value="{{ old('shop_name',$shop?->name) }}" maxlength="150" required></label>
<label class="wide">Email<input name="email" type="email" value="{{ old('email',$user->email) }}" maxlength="150" required></label>
<div class="wide"><span class="eyebrow">CHANGE PASSWORD <span class="optional">OPTIONAL</span></span></div>
<label>New password<input name="password" type="password" minlength="10" autocomplete="new-password"></label><label>Confirm new password<input name="password_confirmation" type="password" minlength="10" autocomplete="new-password"></label>
<div class="form-actions wide"><button class="button primary">Save profile</button></div></form></section>
@endsection
