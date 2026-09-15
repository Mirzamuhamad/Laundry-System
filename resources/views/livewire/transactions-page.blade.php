<div class="transactions-page" x-data="transactionList($wire)" x-on:livewire:navigating.window="closeScanner()">
    @php
        $statusLabels = ['received' => 'Diterima', 'processing' => 'Diproses', 'ready' => 'Siap diambil', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
        $activeFilterCount = collect([$search, $status, $paymentStatus, $dateFrom, $dateTo, $dueFilter, auth()->user()->isOwner() ? $outletId : null])
            ->filter(fn ($value) => filled($value))
            ->count();
    @endphp

    <header class="page-header transaction-page-header">
        <div>
            <p class="eyebrow">OPERASIONAL</p>
            <h1>Daftar transaksi</h1>
            <p class="muted">Pantau pembayaran dan progres semua order.</p>
        </div>
        <a href="{{ route('pos') }}" class="btn btn-primary" wire:navigate>＋ Transaksi baru</a>
    </header>

    <div class="transaction-mobile-toolbar">
        <label class="transaction-mobile-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nomor, pelanggan, WhatsApp..." aria-label="Cari transaksi">
        </label>
        <button type="button" class="transaction-qr-button" @click="openScanner()">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z"/></svg>
            <span>Scan QR</span>
        </button>
    </div>

    <section class="panel transaction-panel">
        <section class="due-schedule" x-data>
            <header class="due-schedule-head">
                <div>
                    <strong>Jadwal selesai</strong>
                    <small>Pilih tanggal untuk melihat transaksi yang harus selesai.</small>
                </div>
                <div class="due-schedule-arrows" aria-label="Geser daftar tanggal">
                    <button type="button" aria-label="Tanggal sebelumnya" @click="$refs.dueDates.scrollBy({ left: -320, behavior: 'smooth' })">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" aria-label="Tanggal berikutnya" @click="$refs.dueDates.scrollBy({ left: 320, behavior: 'smooth' })">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </header>

            <div class="due-date-strip" x-ref="dueDates">
                <button type="button" class="due-date-card overdue {{ $dueFilter === 'overdue' ? 'active' : '' }}" wire:click="selectDueFilter('overdue')">
                    <span class="due-date-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.8 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg>
                    </span>
                    <span><strong>Terlambat</strong><small>Lewat estimasi</small></span>
                    <b>{{ $overdueCount }}</b>
                </button>

                @foreach($dueDateOptions as $dueDate)
                    <button
                        type="button"
                        class="due-date-card {{ $dueFilter === $dueDate['value'] ? 'active' : '' }} {{ $dueDate['count'] > 0 ? 'has-orders' : '' }}"
                        wire:click="selectDueFilter('{{ $dueDate['value'] }}')"
                        wire:key="due-date-{{ $dueDate['value'] }}"
                    >
                        <span><strong>{{ $dueDate['day'] }}</strong><small>{{ $dueDate['date'] }}</small></span>
                        <b>{{ $dueDate['count'] }}</b>
                    </button>
                @endforeach
            </div>
        </section>

        <button
            type="button"
            class="mobile-filter-toggle"
            :class="{ 'active': filtersOpen }"
            :aria-expanded="filtersOpen"
            @click="filtersOpen = ! filtersOpen"
        >
            <span class="mobile-filter-icon">⌕</span>
            <span>
                <strong>Filter & pencarian</strong>
                <small>{{ $activeFilterCount > 0 ? $activeFilterCount.' filter aktif' : 'Cari dan saring transaksi' }}</small>
            </span>
            <b x-text="filtersOpen ? '−' : '+'"></b>
        </button>

        <div class="filters transaction-filters" :class="{ 'mobile-open': filtersOpen }">
            <label class="transaction-search-field">
                <span>Cari transaksi</span>
                <span class="search-box">⌕<input wire:model.live.debounce.300ms="search" placeholder="Nomor, nama, WhatsApp..."></span>
            </label>

            @if(auth()->user()->isOwner())
                <label class="transaction-filter-field">
                    <span>Outlet</span>
                    <select wire:model.live="outletId">
                        <option value="">Semua outlet</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="transaction-filter-field">
                <span>Status pengerjaan</span>
                <select wire:model.live="status">
                    <option value="">Semua status</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="transaction-filter-field">
                <span>Status pembayaran</span>
                <select wire:model.live="paymentStatus">
                    <option value="">Semua pembayaran</option>
                    <option value="unpaid">Belum dibayar</option>
                    <option value="partial">Sebagian</option>
                    <option value="paid">Lunas</option>
                </select>
            </label>

            <label class="transaction-filter-field">
                <span>Dari tanggal</span>
                <input type="date" wire:model.live="dateFrom">
            </label>

            <label class="transaction-filter-field">
                <span>Sampai tanggal</span>
                <input type="date" wire:model.live="dateTo">
            </label>

            <label class="per-page-control filter-per-page transaction-filter-field">
                <span>Tampilkan</span>
                <select wire:model.live="perPage">
                    @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }} data</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="table-wrap transaction-desktop-list">
            <table>
                <thead>
                    <tr><th>Order</th><th>Pelanggan</th><th>Jatuh tempo</th><th>Status</th><th>Pembayaran</th><th>Total</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr wire:key="order-table-{{ $order->id }}">
                            <td><strong>{{ $order->number }}</strong><small>{{ $order->created_at->format('d M Y, H:i') }} · {{ $order->outlet->name }}</small></td>
                            <td><strong>{{ $order->customer_name }}</strong><small>{{ $order->customer_phone }}</small></td>
                            <td class="{{ $order->due_at?->isPast() && ! in_array($order->status, ['ready', 'completed', 'cancelled']) ? 'text-danger' : '' }}">{{ $order->due_at?->format('d M, H:i') ?? '-' }}</td>
                            <td>
                                <select class="status-select status-{{ $order->status }}" wire:change="updateStatus({{ $order->id }}, $event.target.value)">
                                    @foreach($statusLabels as $key => $label)
                                        <option value="{{ $key }}" @selected($order->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><span class="badge pay-{{ $order->payment_status }}">{{ __('status.'.$order->payment_status) }}</span><small>Rp{{ number_format($order->paid_amount, 0, ',', '.') }} dibayar</small></td>
                            <td><strong>Rp{{ number_format($order->total, 0, ',', '.') }}</strong></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="icon-btn transaction-detail-trigger" title="Lihat detail" wire:click="openDetail({{ $order->id }})"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button>
                                    @if($order->balance > 0)<button type="button" class="icon-btn" title="Tambah pembayaran" wire:click="openPayment({{ $order->id }})">Rp</button>@endif
                                    <button type="button" class="icon-btn" title="Cetak" wire:click="printOrder({{ $order->id }})" wire:loading.attr="disabled">▧</button>
                                    @if($order->whatsapp_url)<a class="icon-btn whatsapp" title="Bagikan WhatsApp" href="{{ $order->whatsapp_url }}" target="_blank">WA</a>@endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-cell">Transaksi tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="transaction-mobile-list">
            @forelse($orders as $order)
                @php($isOverdue = $order->due_at?->isPast() && ! in_array($order->status, ['ready', 'completed', 'cancelled']))
                <article class="transaction-mobile-card {{ $isOverdue ? 'is-overdue' : '' }}" wire:key="order-card-{{ $order->id }}" x-data="{ servicesOpen: false }">
                    <header class="transaction-card-head">
                        <div>
                            <small>{{ $order->created_at->format('d M Y, H:i') }}</small>
                            <strong>{{ $order->number }}</strong>
                        </div>
                        <span class="badge pay-{{ $order->payment_status }}">{{ __('status.'.$order->payment_status) }}</span>
                    </header>

                    <button type="button" class="transaction-card-customer" @click="servicesOpen = ! servicesOpen" :aria-expanded="servicesOpen">
                        <span>{{ str($order->customer_name)->substr(0, 2)->upper() }}</span>
                        <div>
                            <strong>{{ $order->customer_name }}</strong>
                            <small>{{ $order->items->count() }} layanan · ketuk untuk melihat rincian</small>
                        </div>
                        <span class="transaction-card-chevron" :class="{ 'open': servicesOpen }">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>

                    <div class="transaction-card-details">
                        <div><small>Layanan</small><strong>{{ $order->items->count() }} jenis layanan</strong></div>
                        <div class="{{ $isOverdue ? 'text-danger' : '' }}"><small>Jatuh tempo</small><strong>{{ $order->due_at?->format('d M, H:i') ?? '-' }}</strong></div>
                        <div><small>Sudah dibayar</small><strong>Rp{{ number_format($order->paid_amount, 0, ',', '.') }}</strong></div>
                        <div><small>Total transaksi</small><strong class="transaction-card-total">Rp{{ number_format($order->total, 0, ',', '.') }}</strong></div>
                    </div>

                    <section class="transaction-card-services" x-show="servicesOpen" x-cloak x-transition>
                        <div class="transaction-card-services-head"><strong>Rincian layanan</strong><small>{{ $order->outlet->name }}</small></div>
                        @forelse($order->items as $item)
                            <article wire:key="order-card-{{ $order->id }}-item-{{ $item->id }}">
                                <div>
                                    <strong>{{ $item->product_name }}</strong>
                                    <small>
                                        @if($item->variant_name){{ $item->variant_name }}@if($item->duration_hours) · {{ $item->duration_hours }} jam @endif · @endif
                                        {{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }} × Rp{{ number_format($item->unit_price, 0, ',', '.') }}
                                    </small>
                                </div>
                                <strong>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</strong>
                            </article>
                        @empty
                            <p>Rincian layanan belum tersedia.</p>
                        @endforelse
                    </section>

                    <label class="transaction-card-status">
                        <span>Status pengerjaan</span>
                        <select class="status-select status-{{ $order->status }}" wire:change="updateStatus({{ $order->id }}, $event.target.value)">
                            @foreach($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($order->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="transaction-card-actions">
                        @if($order->balance > 0)
                            <button type="button" class="transaction-card-action payment" wire:click="openPayment({{ $order->id }})">
                                <span class="transaction-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg></span>
                                <strong>Bayar</strong>
                            </button>
                        @endif
                        <button type="button" class="transaction-card-action" wire:click="printOrder({{ $order->id }})" wire:loading.attr="disabled">
                            <span class="transaction-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6zM18 12h.01"/></svg></span>
                            <strong>Cetak struk</strong>
                        </button>
                        @if($order->whatsapp_url)
                            <a class="transaction-card-action whatsapp" href="{{ $order->whatsapp_url }}" target="_blank">
                                <span class="transaction-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.7a8.5 8.5 0 1 1 16.2-4.1Z"/><path d="M8.2 7.8c.2-.4.4-.4.7-.4h.5l.8 2c.1.2 0 .4-.1.6l-.6.7c-.2.2-.1.4 0 .6.7 1.2 1.7 2.2 3 2.8.2.1.4.1.6-.1l.8-1c.2-.2.4-.3.6-.2l2 .9c.3.1.4.3.4.5 0 .5-.2 1.4-.7 1.8-.5.5-1.3.8-2.2.6-1.1-.2-2.6-.8-4.4-2.3-2-1.7-3.2-3.8-3.5-5-.2-.7.2-1.3.4-1.5Z"/></svg></span>
                                <strong>Kirim struk</strong>
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="transaction-mobile-empty"><span>⌕</span><strong>Transaksi tidak ditemukan</strong><small>Coba ubah kata kunci atau filter.</small></div>
            @endforelse
        </div>

        {{ $orders->links('components.table-pagination') }}
    </section>

    <div class="transaction-qr-modal" x-show="scannerOpen" x-cloak x-transition.opacity x-on:click.self="closeScanner()" x-on:keydown.escape.window="closeScanner()">
        <section role="dialog" aria-modal="true" aria-labelledby="transaction-qr-title">
            <header>
                <div><p class="eyebrow">SCAN STRUK</p><h2 id="transaction-qr-title">Arahkan ke QR struk</h2><small>Transaksi akan ditemukan secara otomatis.</small></div>
                <button type="button" @click="closeScanner()" aria-label="Tutup scanner">×</button>
            </header>
            <div class="transaction-qr-camera">
                <video x-ref="qrVideo" autoplay muted playsinline></video>
                <div class="transaction-qr-guide"><i></i><i></i><i></i><i></i></div>
                <span class="transaction-qr-scanline"></span>
            </div>
            <p class="transaction-qr-hint" x-show="!scannerError">Pastikan QR terlihat jelas dan berada di dalam kotak.</p>
            <p class="transaction-qr-error" x-show="scannerError" x-text="scannerError"></p>
            <button type="button" class="btn btn-ghost full" @click="closeScanner()">Batal</button>
        </section>
    </div>

    @if($detailOrder)
        <div class="modal-backdrop top" wire:click.self="$set('detailOrderId', null)">
            <section class="modal completed-order-modal transaction-detail-modal">
                <div class="modal-head">
                    <div><p class="eyebrow">DETAIL TRANSAKSI</p><h2>{{ $detailOrder->number }}</h2><p class="muted">Rincian pelanggan dan layanan transaksi.</p></div>
                    <button type="button" wire:click="$set('detailOrderId', null)">×</button>
                </div>
                <div class="completed-order-summary">
                    <div><span>Pelanggan</span><strong>{{ $detailOrder->customer_name }}</strong><small>{{ $detailOrder->customer_phone }}</small></div>
                    <div><span>Outlet</span><strong>{{ $detailOrder->outlet->name }}</strong><small>{{ $detailOrder->user->name }}</small></div>
                    <div><span>Tanggal masuk</span><strong>{{ $detailOrder->created_at->format('d M Y, H:i') }}</strong><small>{{ $statusLabels[$detailOrder->status] ?? $detailOrder->status }}</small></div>
                    <div><span>Estimasi selesai</span><strong>{{ $detailOrder->due_at?->format('d M Y, H:i') ?? '-' }}</strong><small>{{ $detailOrder->items->count() }} layanan</small></div>
                </div>
                <div class="completed-order-items">
                    @forelse($detailOrder->items as $item)
                        <article wire:key="detail-order-item-{{ $item->id }}">
                            <div>
                                <strong>{{ $item->product_name }}</strong>
                                <small>{{ $item->variant_name }}@if($item->duration_hours) · {{ $item->duration_hours }} jam @endif</small>
                                <small>{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }} × Rp{{ number_format($item->unit_price, 0, ',', '.') }}</small>
                            </div>
                            <b>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</b>
                        </article>
                    @empty
                        <div class="empty-cell">Rincian layanan belum tersedia.</div>
                    @endforelse
                </div>
                <div class="completed-order-totals">
                    <div><span>Total</span><strong>Rp{{ number_format($detailOrder->total, 0, ',', '.') }}</strong></div>
                    <div><span>Dibayar</span><strong>Rp{{ number_format($detailOrder->paid_amount, 0, ',', '.') }}</strong></div>
                    <div class="balance"><span>Sisa</span><strong>Rp{{ number_format($detailOrder->balance, 0, ',', '.') }}</strong></div>
                </div>
                <div class="completed-order-actions"><button type="button" class="btn btn-ghost" wire:click="$set('detailOrderId', null)">Tutup</button><button type="button" class="btn btn-primary" wire:click="printOrder({{ $detailOrder->id }})" wire:loading.attr="disabled">▧ Cetak struk</button></div>
            </section>
        </div>
    @endif

    @if($selectedOrderId)
        <div class="modal-backdrop" wire:click.self="$set('selectedOrderId', null)">
            <section class="modal small">
                <div class="modal-head"><div><p class="eyebrow">PEMBAYARAN</p><h2>Tambah pembayaran</h2></div><button wire:click="$set('selectedOrderId', null)">×</button></div>
                <form wire:submit="addPayment" class="stack-form">
                    <label>Jumlah<input type="number" wire:model="paymentAmount">@error('paymentAmount')<small class="form-error">{{ $message }}</small>@enderror</label>
                    <label>Metode<select wire:model="paymentMethod"><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select></label>
                    <div class="form-actions"><button type="button" class="btn btn-ghost" wire:click="$set('selectedOrderId', null)">Batal</button><button class="btn btn-primary">Simpan pembayaran</button></div>
                </form>
            </section>
        </div>
    @endif
</div>
