<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <title>{{ $title ?? 'Laundry Pos' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body>
    <div class="app-shell" x-data="sidebarShell()" :class="collapsed ? 'sidebar-collapsed' : ''">
        <aside class="sidebar">
            <button type="button" class="brand" @click="toggle()" :title="collapsed ? 'Buka sidebar' : 'Tutup sidebar'" :aria-label="collapsed ? 'Buka sidebar' : 'Tutup sidebar'">
                <span class="brand-mark">LP</span>
                <span class="brand-copy"><strong>Laundry Pos</strong><small>{{ auth()->user()->outlet?->name ?? 'Semua outlet' }}</small></span>
            </button>

            <nav class="nav-list">
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Ringkasan" wire:navigate><span>⌂</span><span class="nav-label">Ringkasan</span></a>
                <a href="{{ route('pos') }}" class="nav-item {{ request()->routeIs('pos') ? 'active' : '' }}" title="POS" wire:navigate><span>＋</span><span class="nav-label">POS</span></a>
                <a href="{{ route('transactions') }}" class="nav-item {{ request()->routeIs('transactions') ? 'active' : '' }}" title="Transaksi" wire:navigate><span>▤</span><span class="nav-label">Transaksi</span></a>
                <a href="{{ route('attendance') }}" class="nav-item {{ request()->routeIs('attendance') ? 'active' : '' }}" title="Absensi" wire:navigate><span>◉</span><span class="nav-label">Absensi</span></a>
                @if(auth()->user()->isOwner())
                    <div class="nav-section">Manajemen</div>
                    <a href="{{ route('products') }}" class="nav-item {{ request()->routeIs('products') ? 'active' : '' }}" title="Produk" wire:navigate><span>◇</span><span class="nav-label">Produk</span></a>
                    <a href="{{ route('expenses') }}" class="nav-item {{ request()->routeIs('expenses') ? 'active' : '' }}" title="Expense" wire:navigate><span>&#8599;&#65038;</span><span class="nav-label">Expense</span></a>
                    <a href="{{ route('reports') }}" class="nav-item {{ request()->routeIs('reports') ? 'active' : '' }}" title="Laporan" wire:navigate><span>▥</span><span class="nav-label">Laporan</span></a>
                    <a href="{{ route('user-accounts') }}" class="nav-item {{ request()->routeIs('user-accounts') ? 'active' : '' }}" title="User Login" wire:navigate><span class="nav-user-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span class="nav-label">User Login</span></a>
                    <a href="{{ route('management') }}" class="nav-item {{ request()->routeIs('management') ? 'active' : '' }}" title="Pengaturan" wire:navigate><span>⚙</span><span class="nav-label">Pengaturan</span></a>
                @endif
            </nav>

            <div class="user-card">
                <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isOwner() ? 'Owner' : 'Kasir' }}</small></div>
                <form class="logout-form" method="POST" action="{{ route('logout') }}" x-on:submit.prevent="askLogout($event)">@csrf<button type="submit" title="Keluar" aria-label="Keluar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></button></form>
            </div>
        </aside>

        <main class="main-content">
            {{ $slot }}
        </main>

        <nav class="bottom-nav mobile-primary-nav five-item-nav" x-data="{ moreOpen: false }" x-on:keydown.escape.window="moreOpen = false">
            <a data-mobile-nav-item href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" wire:navigate><span>⌂</span><small>Ringkas</small></a>
            <a data-mobile-nav-item href="{{ route('transactions') }}" class="{{ request()->routeIs('transactions') ? 'active' : '' }}" wire:navigate><span>▤</span><small>Order</small></a>
            <a data-mobile-nav-item href="{{ route('pos') }}" class="bottom-pos {{ request()->routeIs('pos') ? 'active' : '' }}" wire:navigate><span class="bottom-pos-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span><small>POS</small></a>
            @if(auth()->user()->isOwner())
                <a data-mobile-nav-item href="{{ route('reports') }}" class="{{ request()->routeIs('reports') ? 'active' : '' }}" wire:navigate><span>▥</span><small>Laporan</small></a>
            @else
                <a data-mobile-nav-item href="{{ route('attendance') }}" class="{{ request()->routeIs('attendance') ? 'active' : '' }}" wire:navigate><span>◉</span><small>Absen</small></a>
            @endif
            <div data-mobile-nav-item class="bottom-more" x-on:click.outside="moreOpen = false">
                <button type="button" class="bottom-more-toggle {{ request()->routeIs('attendance', 'products', 'expenses', 'user-accounts', 'management') && auth()->user()->isOwner() ? 'active' : '' }}" x-on:click="moreOpen = ! moreOpen" :aria-expanded="moreOpen" aria-haspopup="true"><span>•••</span><small>More</small></button>
                <div class="bottom-more-menu" x-show="moreOpen" x-transition.origin.bottom.right x-cloak>
                    @if(auth()->user()->isOwner())
                        <a href="{{ route('attendance') }}" class="{{ request()->routeIs('attendance') ? 'active' : '' }}" x-on:click="moreOpen = false" wire:navigate><span>◉</span><span><strong>Absensi</strong><small>Catat kehadiran</small></span></a>
                        <a href="{{ route('products') }}" class="{{ request()->routeIs('products') ? 'active' : '' }}" x-on:click="moreOpen = false" wire:navigate><span class="bottom-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12v9"/></svg></span><span><strong>Produk</strong><small>Kelola layanan dan varian</small></span></a>
                        <a href="{{ route('expenses') }}" class="{{ request()->routeIs('expenses') ? 'active' : '' }}" x-on:click="moreOpen = false" wire:navigate><span class="bottom-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H19a1 1 0 0 1 1 1v15H6.5A2.5 2.5 0 0 1 4 17.5v-11Z"/><path d="M4 7h16M15 12h5v4h-5a2 2 0 0 1 0-4Z"/></svg></span><span><strong>Expense</strong><small>Catat pengeluaran</small></span></a>
                        <a href="{{ route('user-accounts') }}" class="{{ request()->routeIs('user-accounts') ? 'active' : '' }}" x-on:click="moreOpen = false" wire:navigate><span class="bottom-user-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span><strong>User Login</strong><small>Kelola akses akun</small></span></a>
                        <a href="{{ route('management') }}" class="{{ request()->routeIs('management') ? 'active' : '' }}" x-on:click="moreOpen = false" wire:navigate><span class="bottom-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg></span><span><strong>Pengaturan</strong><small>Kelola outlet dan karyawan</small></span></a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" x-on:submit.prevent="moreOpen = false; askLogout($event)">@csrf<button type="submit"><span class="bottom-logout-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></span><span><strong>Keluar</strong><small>Akhiri sesi</small></span></button></form>
                </div>
            </div>
        </nav>

        <div class="modal-backdrop logout-backdrop" x-show="logoutOpen" x-cloak x-transition.opacity x-on:click.self="cancelLogout()" x-on:keydown.escape.window="cancelLogout()">
            <section class="logout-dialog" role="dialog" aria-modal="true" aria-labelledby="logout-title">
                <div class="logout-dialog-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></div>
                <p class="eyebrow">KONFIRMASI KELUAR</p>
                <h2 id="logout-title">Yakin ingin keluar?</h2>
                <p>Sesi Anda di Laundry Pos akan diakhiri. Pastikan transaksi yang sedang dikerjakan sudah disimpan.</p>
                <div class="logout-dialog-actions"><button type="button" class="btn btn-ghost" x-on:click="cancelLogout()">Tetap di sini</button><button type="button" class="btn logout-confirm-button" x-on:click="confirmLogout()">Ya, keluar</button></div>
            </section>
        </div>
    </div>
    <div class="toast" x-data="{ show: false, message: '' }" x-on:notify.window="message=$event.detail; show=true; setTimeout(()=>show=false,3000)" x-show="show" x-transition x-cloak x-text="message"></div>
    @livewireScripts
    @fluxScripts
</body>
</html>
