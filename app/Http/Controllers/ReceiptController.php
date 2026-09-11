<?php

namespace App\Http\Controllers;

use App\Models\Order;

class ReceiptController extends Controller
{
    public function __invoke(Order $order)
    {
        $user = request()->user();
        abort_unless($user->isOwner() || $user->outlet_id === $order->outlet_id, 403);
        $order->load(['outlet', 'items', 'payments', 'user']);
        $receiptUrl = route('transactions.receipt', $order);
        $qrCodeUrl = 'https://quickchart.io/qr?'.http_build_query([
            'text' => $receiptUrl,
            'size' => 180,
            'margin' => 1,
            'ecLevel' => 'M',
        ]);

        return view('orders.receipt', compact('order', 'receiptUrl', 'qrCodeUrl'));
    }
}
