@extends('layouts.app') @section('title','Add staff') @section('section','Users & access')
@section('content')
<div class="page-heading"><div><span class="eyebrow">TEAM ACCESS</span><h1>Add staff</h1><p>Create a username and password, then assign a role.</p></div><a class="button secondary" href="{{ route('users.index') }}">← User list</a></div>
<section class="panel form-panel" style="max-width:900px"><form method="post" action="{{ route('users.store') }}" class="form-grid">@csrf
<label>Name<input name="name" value="{{ old('name') }}" required maxlength="100"></label><label>Username<input name="username" value="{{ old('username') }}" required maxlength="80" pattern="[A-Za-z0-9_-]+" autocomplete="off"></label><label>Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="150"></label>
<label>Access role<select name="access_role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)old('access_role_id',$roles->first()?->id)===(string)$role->id)>{{ $role->name }}</option>@endforeach</select></label><label>Quick PIN <span class="optional">OPTIONAL · 4–8 DIGITS</span><input name="pin" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8"></label>
<label>Password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></label>
<p class="wide muted">Sidebar access comes from the assigned role. <a class="text-link" href="{{ route('masters.roles.index') }}">Manage roles →</a></p>
<div class="form-actions wide"><a class="button secondary" href="{{ route('users.index') }}">Cancel</a><button class="button primary">Create account</button></div></form></section>
@endsection
