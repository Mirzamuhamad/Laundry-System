<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'subtotal' => 'integer', 'total' => 'integer', 'paid_amount' => 'integer'];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getBalanceAttribute(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        if (! $this->customer_phone) {
            return null;
        }
        $phone = preg_replace('/\D+/', '', $this->customer_phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $itemLines = $items->map(function (OrderItem $item): string {
            $quantity = rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',');
            $variant = $item->variant_name ? ' — '.$item->variant_name.($item->duration_hours ? ' ('.$item->duration_hours.' jam)' : '') : '';

            return '- '.$item->product_name.$variant.' ('.$quantity.' '.$item->unit.') Rp'.number_format($item->subtotal, 0, ',', '.');
        })->implode("\n");
        $message = "Halo *{$this->customer_name}*, berikut nota laundry Anda.\n\n"
            ."*{$this->outlet->name}*\n"
            ."No. Nota: *{$this->number}*\n"
            .'Tanggal: '.$this->created_at->format('d/m/Y H:i')."\n\n"
            ."*Layanan*\n{$itemLines}\n\n"
            .'*Total: Rp'.number_format($this->total, 0, ',', '.')."*\n"
            .'Dibayar: Rp'.number_format($this->paid_amount, 0, ',', '.')."\n"
            .'Sisa: Rp'.number_format($this->balance, 0, ',', '.')."\n"
            .'Status: '.__('status.'.$this->status)."\n"
            .'Estimasi selesai: '.($this->due_at?->format('d/m/Y H:i') ?? '-')."\n\n"
            .'Outlet: '.($this->outlet->address ?? '-')."\n"
            .'WhatsApp: '.($this->outlet->phone ?? '-')."\n\n"
            .'Terima kasih sudah mempercayakan laundry Anda kepada kami.';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
