<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Support\BluetoothReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TransactionsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $paymentStatus = '';

    public ?int $outletId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selectedOrderId = null;

    public $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public int $perPage = 10;

    public bool $dueTodayOnly = false;

    public function mount(): void
    {
        if (! Auth::user()->isOwner()) {
            $this->outletId = Auth::user()->outlet_id;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'paymentStatus', 'outletId', 'dateFrom', 'dateTo', 'dueTodayOnly'], true)) {
            $this->resetPage();
        }

        if ($property === 'perPage') {
            $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;
            $this->resetPage();
        }
    }

    private function findAllowed(int $id): Order
    {
        return Order::when(! Auth::user()->isOwner(), fn ($q) => $q->where('outlet_id', Auth::user()->outlet_id))->findOrFail($id);
    }

    public function updateStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, ['received', 'processing', 'ready', 'completed', 'cancelled']), 422);
        $order = $this->findAllowed($id);
        $order->update(['status' => $status, 'completed_at' => $status === 'completed' ? now() : null]);
        $this->dispatch('notify', 'Status order diperbarui.');
    }

    public function openPayment(int $id): void
    {
        $order = $this->findAllowed($id);
        $this->selectedOrderId = $id;
        $this->paymentAmount = $order->balance;
    }

    public function printOrder(int $id): void
    {
        $order = $this->findAllowed($id);

        $this->dispatch(
            'print-receipt',
            text: resolve(BluetoothReceipt::class)->build($order),
            fallbackUrl: route('transactions.receipt', $order),
        );
    }

    public function addPayment(): void
    {
        $order = $this->findAllowed($this->selectedOrderId);
        $data = $this->validate(['paymentAmount' => 'required|integer|min:1|max:'.$order->balance, 'paymentMethod' => 'required|in:cash,transfer,qris']);
        DB::transaction(function () use ($order, $data) {
            Payment::create(['order_id' => $order->id, 'user_id' => Auth::id(), 'method' => $data['paymentMethod'], 'amount' => $data['paymentAmount'], 'paid_at' => now()]);
            $paid = $order->payments()->sum('amount');
            $order->update(['paid_amount' => $paid, 'payment_status' => $paid >= $order->total ? 'paid' : 'partial']);
        });
        $this->selectedOrderId = null;
        $this->dispatch('notify', 'Pembayaran berhasil dicatat.');
    }

    public function render()
    {
        $dueTodayCount = Order::query()
            ->when(! Auth::user()->isOwner(), fn ($query) => $query->where('outlet_id', Auth::user()->outlet_id))
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId))
            ->whereDate('due_at', today())
            ->whereNotIn('status', ['ready', 'completed', 'cancelled'])
            ->count();

        $orders = Order::with(['outlet', 'user', 'items'])
            ->when(! Auth::user()->isOwner(), fn ($query) => $query->where('outlet_id', Auth::user()->outlet_id))
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId))
            ->when($this->search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery->where('number', 'like', '%'.$this->search.'%')->orWhere('customer_name', 'like', '%'.$this->search.'%')->orWhere('customer_phone', 'like', '%'.$this->search.'%')))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->paymentStatus, fn ($query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->dueTodayOnly, fn ($query) => $query->whereDate('due_at', today())->whereNotIn('status', ['ready', 'completed', 'cancelled']))
            ->when($this->dueTodayOnly, fn ($query) => $query->orderBy('due_at'), fn ($query) => $query->latest())
            ->paginate($this->perPage);

        return view('livewire.transactions-page', ['orders' => $orders, 'outlets' => Outlet::where('is_active', true)->get(), 'dueTodayCount' => $dueTodayCount])->title('Transaksi — Laundry Pos');
    }
}
