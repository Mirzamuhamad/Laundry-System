<div x-data="attendanceCamera($wire)" x-on:attendance-saved.window="closeCamera()" x-on:attendance-failed.window="error=$event.detail.message; loading=false">
    <header class="page-header">
        <div>
            <p class="eyebrow">KEHADIRAN</p>
            <h1>Absensi karyawan</h1>
            <p class="muted">Absen masuk dan keluar dengan foto langsung dari kamera.</p>
        </div>
        <div class="live-clock" x-data="clockWidget()" x-init="start()">
            <strong x-text="time"></strong>
            <small>{{ now()->translatedFormat('d F Y') }}</small>
        </div>
    </header>

    <div class="attendance-grid">
        <section class="attendance-card">
            <div class="attendance-profile">
                <div class="avatar large">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</div>
                <div>
                    <p class="eyebrow">ABSENSI HARI INI</p>
                    <h2>{{ auth()->user()->name }}</h2>
                    <p>{{ auth()->user()->outlet?->name ?? 'Owner' }}</p>
                </div>
            </div>

            @if(auth()->user()->isOwner())
                <label class="outlet-select full">
                    Lokasi absensi
                    <select wire:model="outletId">
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="attendance-times">
                <div><small>Jam masuk</small><strong>{{ $today?->check_in_at?->format('H:i') ?? '--:--' }}</strong></div>
                <span>→</span>
                <div><small>Jam keluar</small><strong>{{ $today?->check_out_at?->format('H:i') ?? '--:--' }}</strong></div>
            </div>

            @if(! $today?->check_in_at)
                <button class="btn btn-primary btn-large full" @click="openCamera('in')">◎ Ambil foto & absen masuk</button>
            @elseif(! $today?->check_out_at)
                <button class="btn btn-primary btn-large full" @click="openCamera('out')">◎ Ambil foto & absen keluar</button>
            @else
                <div class="attendance-done">✓ Absensi hari ini sudah lengkap</div>
            @endif

            <p class="privacy-hint">Foto hanya dapat dilihat oleh Anda dan owner.</p>
        </section>

        <section class="panel attendance-history-panel" x-data="{ attendancePhoto: null, attendancePhotoLabel: '' }">
            <div class="panel-head">
                <div>
                    <h2>Riwayat absensi</h2>
                    <p>Kehadiran dan foto pada periode terpilih</p>
                </div>
            </div>

            @if(auth()->user()->isOwner())
                <div class="filters">
                    <select wire:model.live="employeeId">
                        <option value="">Semua karyawan</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" wire:model.live="from">
                    <input type="date" wire:model.live="to">
                </div>
            @endif

            <div class="attendance-list">
                @forelse($history as $row)
                    <article wire:key="attendance-{{ $row->id }}" class="{{ $row->status === 'late' ? 'is-late' : '' }}">
                        <div class="date-box">
                            <strong>{{ $row->attendance_date->format('d') }}</strong>
                            <small>{{ $row->attendance_date->translatedFormat('M') }}</small>
                        </div>

                        <div class="attendance-person">
                            <strong>{{ auth()->user()->isOwner() ? $row->user->name : $row->attendance_date->translatedFormat('l') }}</strong>
                            <small>{{ $row->outlet->name }}</small>
                        </div>

                        <div class="time-pair">
                            <span>Masuk <strong>{{ $row->check_in_at?->format('H:i') ?? '—' }}</strong></span>
                            <span>Keluar <strong>{{ $row->check_out_at?->format('H:i') ?? '—' }}</strong></span>
                        </div>

                        <span class="badge {{ $row->status === 'late' ? 'badge-danger' : 'badge-success' }}">{{ $row->status === 'late' ? 'Terlambat' : 'Hadir' }}</span>

                        <div class="attendance-photo-gallery">
                            @if($row->check_in_photo)
                                <button
                                    type="button"
                                    class="attendance-photo-thumb"
                                    data-photo="{{ route('attendance.photo', [$row, 'in']) }}"
                                    data-label="Foto absen masuk {{ $row->user->name }}"
                                    aria-label="Lihat foto absen masuk {{ $row->user->name }}"
                                    @click="attendancePhoto = $el.dataset.photo; attendancePhotoLabel = $el.dataset.label"
                                >
                                    <img src="{{ route('attendance.photo', [$row, 'in']) }}" alt="Foto masuk {{ $row->user->name }}" loading="lazy">
                                    <span>Masuk</span>
                                </button>
                            @endif

                            @if($row->check_out_photo)
                                <button
                                    type="button"
                                    class="attendance-photo-thumb"
                                    data-photo="{{ route('attendance.photo', [$row, 'out']) }}"
                                    data-label="Foto absen keluar {{ $row->user->name }}"
                                    aria-label="Lihat foto absen keluar {{ $row->user->name }}"
                                    @click="attendancePhoto = $el.dataset.photo; attendancePhotoLabel = $el.dataset.label"
                                >
                                    <img src="{{ route('attendance.photo', [$row, 'out']) }}" alt="Foto keluar {{ $row->user->name }}" loading="lazy">
                                    <span>Keluar</span>
                                </button>
                            @endif

                            @if(! $row->check_in_photo && ! $row->check_out_photo)
                                <span class="attendance-photo-empty">Belum ada foto</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty-cell">Belum ada riwayat absensi.</p>
                @endforelse
            </div>

            <div
                class="attendance-photo-modal"
                x-show="attendancePhoto"
                x-cloak
                x-transition.opacity
                @click.self="attendancePhoto = null"
                @keydown.escape.window="attendancePhoto = null"
            >
                <section class="attendance-photo-dialog">
                    <header>
                        <div><p class="eyebrow">BUKTI ABSENSI</p><h3 x-text="attendancePhotoLabel"></h3></div>
                        <button type="button" aria-label="Tutup foto" @click="attendancePhoto = null">×</button>
                    </header>
                    <img :src="attendancePhoto" :alt="attendancePhotoLabel">
                </section>
            </div>
        </section>
    </div>

    <div class="camera-modal" x-show="cameraOpen" x-cloak x-transition>
        <section>
            <div class="modal-head">
                <div><p class="eyebrow" x-text="mode === 'in' ? 'ABSEN MASUK' : 'ABSEN KELUAR'"></p><h2>Ambil foto</h2></div>
                <button @click="closeCamera()">×</button>
            </div>
            <div class="camera-frame">
                <video x-ref="video" autoplay playsinline x-show="! photoData"></video>
                <img :src="photoData" x-show="photoData" alt="Pratinjau foto absensi">
                <canvas x-ref="canvas" hidden></canvas>
                <div class="camera-guide"></div>
            </div>
            <p class="camera-error" x-show="error" x-text="error"></p>
            <div class="camera-actions">
                <button class="btn btn-ghost" @click="photoData ? retake() : closeCamera()" x-text="photoData ? 'Ambil ulang' : 'Batal'"></button>
                <button class="btn btn-primary" @click="photoData ? submit() : capture()" :disabled="loading"><span x-text="loading ? 'Menyimpan...' : (photoData ? 'Gunakan foto' : 'Ambil foto')"></span></button>
            </div>
        </section>
    </div>
</div>
