<div>
    <header class="page-header"><div><p class="eyebrow">MASTER DATA</p><h1>Produk & layanan</h1><p class="muted">Atur layanan kiloan, satuan, dan harga setiap outlet.</p></div><button class="btn btn-primary" wire:click="openForm">＋ Tambah produk</button></header>
    <section class="panel">
        <div class="toolbar"><div class="search-box">⌕<input wire:model.live.debounce.300ms="search" placeholder="Cari produk..."></div><span class="muted">{{ $products->total() }} produk</span></div>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Satuan</th><th>Harga</th><th>Estimasi</th><th>Outlet</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($products as $product)@php($activeVariants = $product->variants->where('is_active', true))<tr wire:key="product-{{ $product->id }}"><td><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Tanpa kategori' }} · {{ $activeVariants->count() }} varian aktif</small></td><td>{{ strtoupper($product->unit) }}</td><td><strong>@if($activeVariants->isNotEmpty())Mulai Rp{{ number_format($activeVariants->min('price'),0,',','.') }}@else—@endif</strong></td><td>@if($activeVariants->isNotEmpty()){{ $activeVariants->min('duration_hours') }}–{{ $activeVariants->max('duration_hours') }} jam @else—@endif</td><td>{{ $product->outlet?->name ?? 'Semua outlet' }}</td><td><button wire:click="toggle({{ $product->id }})" class="badge {{ $product->is_active ? 'badge-success' : 'badge-muted' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</button></td><td><div class="row-actions"><button type="button" class="icon-btn" title="Edit produk" aria-label="Edit {{ $product->name }}" wire:click="openForm({{ $product->id }})">✎</button><button type="button" class="icon-btn danger" title="Hapus produk" aria-label="Hapus {{ $product->name }}" wire:click="confirmDeleteProduct({{ $product->id }})">×</button></div></td></tr>
        @empty<tr><td colspan="7" class="empty-cell">Belum ada produk.</td></tr>@endforelse
        </tbody></table></div>{{ $products->links() }}
    </section>

    @if($showForm)<div class="modal-backdrop" wire:click.self="$set('showForm', false)"><section class="modal"><div class="modal-head"><div><p class="eyebrow">{{ $editingId ? 'EDIT' : 'BARU' }}</p><h2>{{ $editingId ? 'Ubah produk' : 'Tambah produk' }}</h2></div><button wire:click="$set('showForm', false)">×</button></div>
        <form wire:submit="save" class="form-grid">
            <label class="span-2">Nama produk<input wire:model="name" placeholder="Contoh: Cuci Kering Lipat">@error('name')<small class="form-error">{{ $message }}</small>@enderror</label>
            <label>Kategori<select wire:model="category_id"><option value="">Tanpa kategori</option>@foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select></label>
            <label>Outlet<select wire:model="outlet_id"><option value="">Semua outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}">{{ $outlet->name }}</option>@endforeach</select></label>
            <label>Satuan<input wire:model="unit" list="unit-options" placeholder="kg, pcs, meter, dll"><datalist id="unit-options"><option value="kg"><option value="pcs"><option value="pasang"><option value="meter"><option value="paket"></datalist></label>
            <label>Minimum jumlah<input type="number" step="0.1" wire:model="minimum_quantity"></label>
            <label>Pembulatan jumlah<input type="number" step="0.1" wire:model="rounding_increment"></label>
            <section class="variant-editor span-2">
                <div class="variant-editor-head"><div><strong>Varian layanan</strong><small>Pelanggan memilih varian setelah produk masuk ke keranjang.</small></div><button type="button" class="btn btn-soft btn-compact" wire:click="addVariant">＋ Tambah varian</button></div>
                @error('variants')<small class="form-error">{{ $message }}</small>@enderror
                <div class="variant-list">
                    @foreach($variants as $index => $variant)
                        <div class="variant-row" wire:key="product-variant-{{ $variant['id'] ?? 'new-'.$index }}-{{ $index }}">
                            <label>Nama<input wire:model="variants.{{ $index }}.name" placeholder="Reguler"></label>
                            <label>Harga<input type="number" wire:model="variants.{{ $index }}.price" placeholder="8000"></label>
                            <label>Durasi (jam)<input type="number" wire:model="variants.{{ $index }}.duration_hours" placeholder="72"></label>
                            <label class="variant-active"><input type="checkbox" wire:model="variants.{{ $index }}.is_active"><span>Aktif</span></label>
                            <button type="button" class="icon-btn danger" title="Hapus varian" wire:click="removeVariant({{ $index }})">×</button>
                            @error("variants.$index.name")<small class="form-error">{{ $message }}</small>@enderror
                            @error("variants.$index.price")<small class="form-error">{{ $message }}</small>@enderror
                            @error("variants.$index.duration_hours")<small class="form-error">{{ $message }}</small>@enderror
                        </div>
                    @endforeach
                </div>
            </section>
            <div class="span-2 inline-create"><input wire:model="newCategory" placeholder="Kategori baru"><button type="button" class="btn btn-soft" wire:click="addCategory">Tambah kategori</button></div>
            <div class="form-actions span-2"><button type="button" class="btn btn-ghost" wire:click="$set('showForm', false)">Batal</button><button class="btn btn-primary">Simpan produk</button></div>
        </form>
    </section></div>@endif

    @if($deletingProductId)<div class="modal-backdrop top" wire:click.self="cancelDeleteProduct"><section class="modal small"><div class="modal-head"><div><p class="eyebrow danger-text">HAPUS PRODUK</p><h2>Hapus {{ $deletingProductName }}?</h2></div><button type="button" wire:click="cancelDeleteProduct">×</button></div><div class="delete-warning"><strong>Produk akan dihapus dari master dan POS.</strong><p>Riwayat transaksi serta detail varian yang sudah tercatat tetap tersimpan.</p></div><div class="form-actions"><button type="button" class="btn btn-ghost" wire:click="cancelDeleteProduct">Batal</button><button type="button" class="btn btn-danger" wire:click="deleteProduct" wire:loading.attr="disabled">Ya, hapus produk</button></div></section></div>@endif
</div>
