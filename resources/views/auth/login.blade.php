<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <title>Masuk — Laundry Pos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-wrap">
        <section class="login-visual">
            <div class="login-logo"><span>LP</span> Laundry Pos</div>
            <div>
                <p class="eyebrow light">POS LAUNDRY MODERN</p>
                <h1>Operasional rapi,<br>pelanggan lebih happy.</h1>
                <p>Transaksi, pengeluaran, absensi, dan laporan outlet dalam satu tempat.</p>
            </div>
            <div class="login-stats"><span><strong>10 detik</strong>absensi foto</span><span><strong>1 layar</strong>transaksi cepat</span></div>
        </section>
        <section class="login-panel">
            <form method="POST" action="{{ route('login.store') }}" class="login-form">
                @csrf
                <div><p class="eyebrow">SELAMAT DATANG</p><h2>Masuk ke akun Anda</h2><p class="muted">Gunakan email dan password yang terdaftar.</p></div>
                <label>Email<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus></label>
                <label>Password<input type="password" name="password" placeholder="••••••••" required></label>
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
                <label class="check-row"><input type="checkbox" name="remember"> Ingat saya di perangkat ini</label>
                <button class="btn btn-primary btn-large" type="submit">Masuk ke Laundry Pos</button>
                <p class="login-hint">Akun demo: <strong>owner@laundry.test</strong> / <strong>password</strong></p>
            </form>
        </section>
    </main>
</body>
</html>
