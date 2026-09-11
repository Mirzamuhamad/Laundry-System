<div>
    <header class="page-header">
        <div><p class="eyebrow">{{ now()->translatedFormat('l, d F Y') }}</p><h1>Selamat datang, {{ strtok(auth()->user()->name, ' ') }}!</h1><p class="muted">Berikut kondisi operasional laundry hari ini.</p></div>
        <a href="{{ route('pos') }}" class="btn btn-primary" wire:navigate>＋ Transaksi baru</a>
    </header>

    <section class="metric-grid">
        <article class="metric-card accent"><span class="metric-icon">Rp</span><div><small>Pembayaran hari ini</small><strong>Rp{{ number_format($todayRevenue,0,',','.') }}</strong><em>{{ $todayCount }} transaksi</em></div></article>
        <article class="metric-card"><span class="metric-icon teal">▤</span><div><small>Order aktif</small><strong>{{ $activeCount }}</strong><em>Sedang dikerjakan</em></div></article>
        <article class="metric-card"><span class="metric-icon amber">!</span><div><small>Terlambat</small><strong>{{ $overdueCount }}</strong><em>Perlu perhatian</em></div></article>
        @if(auth()->user()->isOwner())
        <article class="metric-card"><span class="metric-icon rose">&#8599;&#65038;</span><div><small>Expense hari ini</small><strong>Rp{{ number_format($todayExpense,0,',','.') }}</strong><em>Semua outlet</em></div></article>
        @else
        <article class="metric-card"><span class="metric-icon blue">◉</span><div><small>Absensi</small><strong>{{ $attendance?->check_in_at?->format('H:i') ?? '--:--' }}</strong><em>{{ $attendance?->check_out_at ? 'Pulang '.$attendance->check_out_at->format('H:i') : ($attendance ? 'Sudah masuk' : 'Belum absen') }}</em></div></article>
        @endif
    </section>

    <section class="dashboard-grid">
        <article class="panel">
            <div class="panel-head"><div><h2>Transaksi terbaru</h2><p>Aktivitas order terakhir</p></div><a href="{{ route('transactions') }}" wire:navigate>Lihat semua →</a></div>
            <div class="table-wrap">
                <table><thead><tr><th>Order</th><th>Pelanggan</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                @forelse($recentOrders as $order)
                    <tr><td><strong>{{ $order->number }}</strong><small>{{ $order->created_at->format('H:i') }} · {{ $order->outlet->name }}</small></td><td>{{ $order->customer_name }}</td><td><span class="badge status-{{ $order->status }}">{{ __('status.'.$order->status) }}</span></td><td><strong>Rp{{ number_format($order->total,0,',','.') }}</strong></td></tr>
                @empty<tr><td colspan="4" class="empty-cell">Belum ada transaksi hari ini.</td></tr>@endforelse
                </tbody></table>
            </div>
        </article>
        <aside class="panel quick-panel">
            <div class="panel-head"><div><h2>Aksi cepat</h2><p>Pekerjaan rutin Anda</p></div></div>
            <a href="{{ route('pos') }}" wire:navigate><span class="quick-icon">＋</span><div><strong>Buat transaksi</strong><small>Catat laundry baru</small></div><b>›</b></a>
            <a href="{{ route('attendance') }}" wire:navigate><span class="quick-icon blue">◉</span><div><strong>Absensi</strong><small>Masuk atau keluar</small></div><b>›</b></a>
            @if(auth()->user()->isOwner())<a href="{{ route('expenses') }}" wire:navigate><span class="quick-icon rose">&#8599;&#65038;</span><div><strong>Catat expense</strong><small>Tambah pengeluaran</small></div><b>›</b></a>@endif
        </aside>
    </section>
</div>
