<?php

namespace App\Support;

use App\Models\Order;

class BluetoothReceipt
{
    public function build(Order $order): string
    {
        $order->load(['outlet', 'items']);
        $receiptUrl = route('transactions.receipt', $order);
        $lines = [
            '[C]'.strtoupper($order->outlet->name),
            '[C]'.($order->outlet->address ?? ''),
            '[C]'.($order->outlet->phone ?? ''),
            '[L]--------------------------------',
            '[L]No: '.$order->number,
            '[L]Tanggal: '.$order->created_at->format('d/m/Y H:i'),
            '[L]Pelanggan: '.$order->customer_name,
            '[L]--------------------------------',
        ];

        foreach ($order->items as $item) {
            $quantity = rtrim(rtrim(number_format($item->quantity, 2, '.', ''), '0'), '.');
            $lines[] = '[L]'.$item->product_name;
            if ($item->variant_name) {
                $lines[] = '[L]'.$item->variant_name.($item->duration_hours ? ' - '.$item->duration_hours.' jam' : '');
            }
            $lines[] = '[L]'.$quantity.' '.$item->unit.' x '.number_format($item->unit_price, 0, ',', '.').' = '.number_format($item->subtotal, 0, ',', '.');
        }

        $lines = [...$lines,
            '[L]--------------------------------',
            '[R]TOTAL Rp'.number_format($order->total, 0, ',', '.'),
            '[R]DIBAYAR Rp'.number_format($order->paid_amount, 0, ',', '.'),
            '[R]SISA Rp'.number_format($order->balance, 0, ',', '.'),
            '[L]--------------------------------',
            '[C]Estimasi selesai',
            '[C]'.($order->due_at?->format('d/m/Y H:i') ?? '-'),
            '[L]--------------------------------',
            '[C]Scan untuk membuka transaksi',
            '[QR]'.$receiptUrl,
            '[C]'.$order->number,
            '[L]--------------------------------',
            '[C]Terima kasih',
        ];

        return implode("\n", $lines);
    }
}
