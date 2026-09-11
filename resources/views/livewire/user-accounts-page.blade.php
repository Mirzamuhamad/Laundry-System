<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">MANAJEMEN AKSES</p>
            <h1>User login</h1>
            <p class="muted">Buat dan kelola akun yang dapat masuk ke Laundry Pos.</p>
        </div>
    </header>

    <div class="account-metrics">
        <article><span>Semua akun</span><strong>{{ $accounts->count() }}</strong></article>
        <article><span>Akun aktif</span><strong>{{ $activeAccounts }}</strong></article>
        <article><span>Owner</span><strong>{{ $ownerAccounts }}</strong></article>
        <article><span>Kasir</span><strong>{{ $cashierAccounts }}</strong></article>
    </div>

    <div class="user-account-layout">
        <section class="panel account-form-panel">
            <div class="panel-head">
                <div>
                    <h2>{{ $editingAccountId ? 'Edit user login' : 'Tambah user login' }}</h2>
                    <p>{{ $editingAccountId ? 'Password boleh dikosongkan jika tidak diubah' : 'Gunakan email dan password untuk masuk' }}</p>
                </div>
            </div>

            <form wire:submit="saveAccount" class="stack-form" x-data="{ showPassword: false }">
                <label>
                    Nama pengguna
                    <input wire:model="accountName" autocomplete="name" placeholder="Contoh: Rina Kasir">
                    @error('accountName')<small class="form-error">{{ $message }}</small>@enderror
                </label>

                <label>
                    Email login
                    <input type="email" wire:model="accountEmail" autocomplete="username" placeholder="nama@email.com">
                    @error('accountEmail')<small class="form-error">{{ $message }}</small>@enderror
                </label>

                <label>
                    Nomor WhatsApp
                    <input wire:model="accountPhone" autocomplete="tel" placeholder="08xxxxxxxxxx">
                    @error('accountPhone')<small class="form-error">{{ $message }}</small>@enderror
                </label>

                <div class="form-grid">
                    <label>
                        Role
                        <select wire:model.live="accountRole">
                            <option value="cashier">Kasir</option>
                            <option value="owner">Owner</option>
                        </select>
                        @error('accountRole')<small class="form-error">{{ $message }}</small>@enderror
                    </label>

                    <label>
                        Status akun
                        <select wire:model="accountIsActive">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                        @error('accountIsActive')<small class="form-error">{{ $message }}</small>@enderror
                    </label>
                </div>

                @if($accountRole === 'cashier')
                    <label>
                        Outlet
                        <select wire:model="accountOutletId">
                            <option value="">Pilih outlet</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                        <small class="account-field-hint">Kasir otomatis masuk ke outlet ini saat login.</small>
                        @error('accountOutletId')<small class="form-error">{{ $message }}</small>@enderror
                    </label>
                @endif

                <label>
                    {{ $editingAccountId ? 'Password baru' : 'Password awal' }}
                    <span class="password-field">
                        <input :type="showPassword ? 'text' : 'password'" wire:model="accountPassword" autocomplete="new-password" placeholder="Minimal 8 karakter">
                        <button type="button" @click="showPassword = ! showPassword" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'">
                            <svg x-show="! showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M6.3 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </span>
                    @if($editingAccountId)<small class="account-field-hint">Kosongkan jika password lama tetap digunakan.</small>@endif
                    @error('accountPassword')<small class="form-error">{{ $message }}</small>@enderror
                </label>

                <div class="account-form-actions">
                    @if($editingAccountId)
                        <button type="button" class="btn btn-ghost" wire:click="cancelEdit">Batal</button>
                    @endif
                    <button class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveAccount">
                        {{ $editingAccountId ? 'Simpan perubahan' : 'Tambah akun login' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="panel account-list-panel">
            <div class="panel-head">
                <div><h2>Daftar user login</h2><p>{{ $accounts->count() }} akun terdaftar</p></div>
            </div>

            <div class="table-wrap account-desktop-list">
                <table>
                    <thead><tr><th>Pengguna</th><th>Role</th><th>Outlet</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach($accounts as $account)
                            <tr wire:key="account-row-{{ $account->id }}">
                                <td><strong>{{ $account->name }}</strong><small>{{ $account->email }}{{ $account->phone ? ' · '.$account->phone : '' }}</small></td>
                                <td><span class="badge {{ $account->role === 'owner' ? 'badge-soft' : 'badge-muted' }}">{{ $account->role === 'owner' ? 'Owner' : 'Kasir' }}</span></td>
                                <td>{{ $account->outlet?->name ?? 'Semua outlet' }}</td>
                                <td>
                                    @if($account->is(auth()->user()))
                                        <span class="badge badge-success">Aktif · Anda</span>
                                    @else
                                        <button type="button" wire:click="toggleAccount({{ $account->id }})" class="badge {{ $account->is_active ? 'badge-success' : 'badge-muted' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                                    @endif
                                </td>
                                <td><button type="button" class="icon-btn" title="Edit akun" aria-label="Edit akun {{ $account->name }}" wire:click="editAccount({{ $account->id }})">✎</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="account-mobile-list">
                @foreach($accounts as $account)
                    <article wire:key="account-card-{{ $account->id }}">
                        <div class="account-avatar">{{ str($account->name)->substr(0, 2)->upper() }}</div>
                        <div class="account-card-copy">
                            <strong>{{ $account->name }}</strong>
                            <small>{{ $account->email }}</small>
                            <span>{{ $account->role === 'owner' ? 'Owner · Semua outlet' : 'Kasir · '.($account->outlet?->name ?? 'Belum ada outlet') }}</span>
                        </div>
                        <button type="button" class="account-edit-button" aria-label="Edit akun {{ $account->name }}" wire:click="editAccount({{ $account->id }})">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16-.8 4.8L8 20l11-11-4-4L4 16Z"/><path d="m13.5 6.5 4 4"/></svg>
                        </button>
                        <div class="account-card-status">
                            @if($account->is(auth()->user()))
                                <span class="badge badge-success">Aktif · Anda</span>
                            @else
                                <button type="button" wire:click="toggleAccount({{ $account->id }})" class="badge {{ $account->is_active ? 'badge-success' : 'badge-muted' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</div>
