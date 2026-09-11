<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->number }}</title>
    <style>
        *{box-sizing:border-box}body{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#111;margin:0;background:#eee}.receipt{width:80mm;min-height:100vh;margin:auto;padding:6mm;background:white;font-size:12px}.center{text-align:center}.title{font-size:18px;font-weight:800}.muted{color:#555}.line{border-top:1px dashed #333;margin:10px 0}.row{display:flex;justify-content:space-between;gap:12px}.items{width:100%;border-collapse:collapse}.items td{padding:4px 0;vertical-align:top}.items td:last-child{text-align:right}.total{font-size:15px;font-weight:800}.receipt-qr{display:block;width:36mm;height:36mm;margin:7px auto 4px;image-rendering:pixelated}.receipt-link{font-size:9px;overflow-wrap:anywhere}.no-print{display:flex;gap:8px;justify-content:center;padding:20px}.no-print button,.no-print a{padding:10px 16px;border:0;border-radius:8px;background:#0f766e;color:#fff;text-decoration:none;cursor:pointer}@media print{body{background:white}.receipt{width:100%;padding:2mm}.no-print{display:none}}@page{margin:2mm;size:80mm auto}
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Cetak struk</button>@if($order->whatsapp_url)<a href="{{ $order->whatsapp_url }}" target="_blank">Kirim WhatsApp</a>@endif<a href="{{ route('transactions') }}">Kembali</a></div>
    <article class="receipt">
        <div class="center"><div class="title">{{ $order->outlet->name }}</div><div>{{ $order->outlet->address }}</div><div>{{ $order->outlet->phone }}</div></div>
        <div class="line"></div>
        <div class="row"><span>No.</span><strong>{{ $order->number }}</strong></div>
        <div class="row"><span>Tanggal</span><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Kasir</span><span>{{ $order->user->name }}</span></div>
        <div class="row"><span>Pelanggan</span><span>{{ $order->customer_name }}</span></div>
        <div class="line"></div>
        <table class="items">
            @foreach($order->items as $item)
            <tr><td>{{ $item->product_name }}@if($item->variant_name)<br><span class="muted">{{ $item->variant_name }}@if($item->duration_hours) · {{ $item->duration_hours }} jam @endif</span>@endif<br><span class="muted">{{ rtrim(rtrim(number_format($item->quantity,2,',','.'),'0'),',') }} {{ $item->unit }} × Rp{{ number_format($item->unit_price,0,',','.') }}</span></td><td>Rp{{ number_format($item->subtotal,0,',','.') }}</td></tr>
            @endforeach
        </table>
        <div class="line"></div>
        <div class="row"><span>Subtotal</span><span>Rp{{ number_format($order->subtotal,0,',','.') }}</span></div>
        @if($order->discount_amount)<div class="row"><span>Diskon</span><span>-Rp{{ number_format($order->discount_amount,0,',','.') }}</span></div>@endif
        <div class="row total"><span>TOTAL</span><span>Rp{{ number_format($order->total,0,',','.') }}</span></div>
        <div class="row"><span>Dibayar</span><span>Rp{{ number_format($order->paid_amount,0,',','.') }}</span></div>
        <div class="row"><span>Sisa</span><span>Rp{{ number_format($order->balance,0,',','.') }}</span></div>
        <div class="line"></div>
        <div class="center"><strong>Estimasi selesai</strong><br>{{ $order->due_at?->format('d/m/Y H:i') ?? '-' }}</div>
        <div class="line"></div>
        <div class="center"><strong>Scan untuk membuka transaksi</strong><img class="receipt-qr" src="{{ $qrCodeUrl }}" alt="QR transaksi {{ $order->number }}"><div class="receipt-link">{{ $receiptUrl }}</div></div>
        <div class="line"></div>
        <div class="center"><span class="muted">Simpan struk ini sebagai bukti pengambilan.<br>Terima kasih sudah mempercayai kami.</span></div>
    </article>
    @if(request('autoprint'))<script>window.addEventListener('load',()=>window.print())</script>@endif
</body>
</html>
