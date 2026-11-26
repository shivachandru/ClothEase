<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ClothEase POS') · ClothEase</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/clothease-app-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/operations.css') }}?v={{ filemtime(public_path('css/operations.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/exchange.css') }}">
    <link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar-scroll.css') }}">
    @stack('head')
</head>
<body class="{{ request()->routeIs('pos') ? 'pos-screen ' : '' }}{{ auth()->user()->shop?->use_celandine_font ? 'font-celandine' : '' }}">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><img class="brand-logo" src="{{ asset('images/clothease-logo.png') }}" alt="ClothEase Smart Retail Solutions"></a>
        <div class="shop-switch"><span class="shop-avatar">{{ strtoupper(substr(auth()->user()->shop->name ?? 'C',0,1)) }}</span><span><b>{{ auth()->user()->shop->name ?? 'Your shop' }}</b><small>Local workspace</small></span><x-icon name="chevron-down" class="chevron" /></div>
        <div class="sidebar-scroll">
        <nav class="main-nav">
            <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><x-icon name="layout-dashboard" /> Overview</a>
            <details class="nav-group" @if(request()->routeIs('facility.*','users.*')) open @endif>
                <summary><x-icon name="settings-2" /> Configuration <span class="nav-group-chevron"></span></summary>
                <div class="sub-nav">
                    <a class="nav-link {{ request()->routeIs('facility.*')?'active':'' }}" href="{{ route('facility.edit') }}"><x-icon name="store" /> Facility profile</a>
                    @if(auth()->user()->role==='owner')<a class="nav-link {{ request()->routeIs('users.*')?'active':'' }}" href="{{ route('users.index') }}"><x-icon name="users-round" /> Users &amp; access</a>@endif
                </div>
            </details>
            @if(auth()->user()->role==='owner')<details class="nav-group" @if(request()->routeIs('masters.roles.*','masters.bill-settings.*','masters.receipt-template.*')) open @endif>
                <summary><x-icon name="clipboard-list" /> Masters <span class="nav-group-chevron"></span></summary>
                <div class="sub-nav">
                    <a class="nav-link {{ request()->routeIs('masters.roles.*')?'active':'' }}" href="{{ route('masters.roles.index') }}"><x-icon name="contact-round" /> Role assign</a>
                    <a class="nav-link {{ request()->routeIs('masters.bill-settings.*')?'active':'' }}" href="{{ route('masters.bill-settings.edit') }}"><x-icon name="receipt-text" /> Bill setting</a>
                    <a class="nav-link {{ request()->routeIs('masters.receipt-template.*')?'active':'' }}" href="{{ route('masters.receipt-template.edit') }}"><x-icon name="receipt-text" /> Receipt template</a>
                </div>
            </details>@endif
            <details class="nav-group" @if(request()->routeIs('pos','sales.index','returns.*','expenses.*')) open @endif>
                <summary><x-icon name="receipt-text" /> Billing <span class="nav-group-chevron"></span></summary>
                <div class="sub-nav">
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('pos'))<a class="nav-link {{ request()->routeIs('pos')?'active':'' }}" href="{{ route('pos') }}"><x-icon name="plus" /> New bill <kbd>F8</kbd></a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('sales'))<a class="nav-link {{ request()->routeIs('sales.index')?'active':'' }}" href="{{ route('sales.index') }}"><x-icon name="history" /> Bill history <kbd>F9</kbd></a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('sales')||auth()->user()->canAccess('returns'))<a class="nav-link {{ request()->routeIs('returns.*')?'active':'' }}" href="{{ route('returns.index') }}"><x-icon name="rotate-ccw" /> Process return</a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('expenses'))<a class="nav-link {{ request()->routeIs('expenses.*')?'active':'' }}" href="{{ route('expenses.index') }}"><x-icon name="wallet" /> Expenses</a>@endif
                </div>
            </details>
            <details class="nav-group" @if(request()->routeIs('products.*','inventory.*','purchases.*','suppliers.*','customers.*')) open @endif>
                <summary><x-icon name="boxes" /> Inventory <span class="nav-group-chevron"></span></summary>
                <div class="sub-nav">
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('products'))<a class="nav-link {{ request()->routeIs('products.*')?'active':'' }}" href="{{ route('products.index') }}"><x-icon name="shirt" /> Products <kbd>F5</kbd></a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('inventory'))<a class="nav-link {{ request()->routeIs('inventory.*')?'active':'' }}" href="{{ route('inventory.index') }}"><x-icon name="boxes" /> Inventory <kbd>F6</kbd></a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('purchases'))<a class="nav-link {{ request()->routeIs('purchases.*')?'active':'' }}" href="{{ route('purchases.index') }}"><x-icon name="shopping-cart" /> Purchases <kbd>F7</kbd></a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('suppliers'))<a class="nav-link {{ request()->routeIs('suppliers.*')?'active':'' }}" href="{{ route('suppliers.index') }}"><x-icon name="truck" /> Suppliers</a>@endif
                    @if(auth()->user()->role==='owner'||auth()->user()->canAccess('customers'))<a class="nav-link {{ request()->routeIs('customers.*')?'active':'' }}" href="{{ route('customers.index') }}"><x-icon name="contact-round" /> Customers</a>@endif
                </div>
            </details>
            @if(auth()->user()->role==='owner'||auth()->user()->canAccess('reports'))<a class="nav-link {{ request()->routeIs('reports.*')?'active':'' }}" href="{{ route('reports.sales') }}"><x-icon name="chart-no-axes-combined" /> Sales report</a>@endif
            @if(auth()->user()->role==='owner')<a class="nav-link {{ request()->routeIs('audit.*')?'active':'' }}" href="{{ route('audit.index') }}"><x-icon name="clipboard-list" /> Activity log</a>@endif
            @if(auth()->user()->role==='owner')<a class="nav-link {{ request()->routeIs('backups.*')?'active':'' }}" href="{{ route('backups.index') }}"><x-icon name="database-backup" /> Backups</a>@endif
        </nav>
        <div class="nav-label nav-label-lower">MANAGE</div>
        <div class="nav-note"><span class="nav-note-icon">✦</span><div><b>Built for your store</b><p>Keep bills quick and stock clear, every day.</p></div></div>
        </div>
        <div class="sidebar-bottom"><div class="user-chip"><span class="user-avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span><b>{{ auth()->user()->name }}</b><small>{{ ucfirst(str_replace('_',' ',auth()->user()->role)) }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Sign out"><x-icon name="log-out" /></button></form></div></div>
    </aside>
    <main class="main-area">
        <header class="topbar"><button class="mobile-menu" type="button" onclick="document.body.classList.toggle('menu-open')"><x-icon name="menu" /></button><div class="breadcrumb">Workspace <span>/</span> <b>@yield('section','Overview')</b></div><div class="top-actions"><a class="text-link" href="{{ route('profile.edit') }}">Profile</a><form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary" type="submit">Log out</button></form><span class="today-label">{{ now()->format('D, d M Y') }}</span><span class="online-pill"><i></i> Local mode</span></div></header>
        <section class="page-content">
            @if(session('success'))<div class="alert success flash-toast" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error flash-toast" role="alert">{{ $errors->first() }}</div>@endif
            @yield('content')
        </section>
    </main>
</div>
@stack('scripts')
<script>window.showToast=function(message,type='error'){const toast=document.createElement('div');toast.className=`app-toast ${type}`;toast.setAttribute('role',type==='error'?'alert':'status');toast.textContent=message;document.body.appendChild(toast);window.setTimeout(()=>{toast.classList.add('leaving');window.setTimeout(()=>toast.remove(),220)},4500)};document.querySelectorAll('.flash-toast').forEach(toast=>window.setTimeout(()=>{toast.classList.add('leaving');window.setTimeout(()=>toast.remove(),220)},6000))</script>
@if(auth()->user()->role==='owner'||auth()->user()->canAccess('pos'))
<script>
document.addEventListener('keydown', event => {
    if (event.key !== 'F8') return;
    event.preventDefault();
    @if(request()->routeIs('pos'))
        document.getElementById('product-search')?.focus();
    @else
        window.location.assign(@json(route('pos')));
    @endif
});
</script>
@endif
</body>
</html>
