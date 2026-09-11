# Laundry Pos

Web POS laundry ringan berbasis Laravel 13, Livewire 4, Tailwind CSS 4, Alpine.js, dan Flux UI.

## Fitur

- Multi-outlet dengan akses owner dan kasir.
- Produk kiloan, satuan, pasang, meter, paket, atau satuan kustom.
- POS satu layar, pelanggan dapat dicari atau ditambah tanpa pindah halaman.
- Pembayaran tunai, transfer, QRIS, pembayaran parsial, dan cetak langsung ke printer thermal Bluetooth BLE ESC/POS.
- Status order dari diterima sampai selesai dan nota WhatsApp dengan format siap kirim.
- Expense dengan maksimal lima foto bukti.
- Laporan seluruh transaksi (termasuk transaksi lunas), detail expense, pembayaran, piutang, estimasi net profit, dan ekspor Excel.
- Absensi masuk/keluar dengan foto langsung dari kamera dan jam server.
- UI mobile-first dan PWA dasar.

## Menjalankan aplikasi

Persyaratan: PHP 8.4+, Composer 2, Node.js 20+, dan ekstensi PHP `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, serta `zip`.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Printer Bluetooth

1. Buka POS melalui Chrome atau Edge pada `localhost` atau domain HTTPS.
2. Nyalakan printer thermal Bluetooth BLE yang mendukung perintah ESC/POS.
3. Tekan **Hubungkan printer**, lalu pilih perangkat pada dialog browser.
4. Setelah terhubung, tombol **Simpan & cetak** akan mengirim struk langsung ke printer. Jika koneksi gagal, aplikasi membuka tampilan cetak browser sebagai cadangan.

Printer Bluetooth Classic/SPP tidak dapat diakses langsung oleh Web Bluetooth. Untuk tipe tersebut, gunakan dialog cetak browser/driver sistem atau printer BLE/Wi-Fi yang kompatibel.

## Akun demo

- Owner: `owner@laundry.test` / `password`
- Kasir: `kasir@laundry.test` / `password`

Ganti password akun demo sebelum aplikasi digunakan di produksi. SQLite dipakai untuk pengembangan lokal; ubah konfigurasi `DB_*` pada `.env` untuk MySQL produksi.
