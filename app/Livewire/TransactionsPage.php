<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Support\BluetoothReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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

    public ?int $detailOrderId = null;

    public $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public int $perPage = 10;

    public string $dueFilter = '';

    public function mount(): void
    {
        if (! Auth::user()->isOwner()) {
            $this->outletId = Auth::user()->outlet_id;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'paymentStatus', 'outletId', 'dateFrom', 'dateTo', 'dueFilter'], true)) {
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

    public function openDetail(int $id): void
    {
        $this->detailOrderId = $this->findAllowed($id)->id;
    }

    public function scanReceiptQr(string $qrValue): void
    {
        $path = mb_strlen($qrValue) <= 2048 ? parse_url(trim($qrValue), PHP_URL_PATH) : false;

        if (! is_string($path) || preg_match('#^/transactions/(\d+)/receipt/?$#', $path, $matches) !== 1) {
            $this->dispatch('notify', 'QR tidak dikenali. Gunakan QR yang tercetak pada struk transaksi.');

            return;
        }

        $order = Order::query()
            ->when(! Auth::user()->isOwner(), fn ($query) => $query->where('outlet_id', Auth::user()->outlet_id))
            ->find((int) $matches[1]);

        if (! $order) {
            $this->dispatch('notify', 'Transaksi tidak ditemukan atau tidak dapat diakses.');

            return;
        }

        $this->search = $order->number;
        $this->status = '';
        $this->paymentStatus = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->dueFilter = '';
        $this->outletId = $order->outlet_id;
        $this->detailOrderId = $order->id;
        $this->resetPage();
        $this->dispatch('notify', 'Transaksi '.$order->number.' ditemukan.');
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

    public function selectDueFilter(string $filter): void
    {
        abort_unless($filter === 'overdue' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter) === 1, 422);

        $this->dueFilter = $this->dueFilter === $filter ? '' : $filter;
        $this->resetPage();
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
        $today = CarbonImmutable::today();
        $lastScheduleDate = $today->addDays(29);
        $unfinishedDueQuery = Order::query()
            ->when(! Auth::user()->isOwner(), fn ($query) => $query->where('outlet_id', Auth::user()->outlet_id))
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId))
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['ready', 'completed', 'cancelled']);

        $overdueCount = (clone $unfinishedDueQuery)
            ->where('due_at', '<', now())
            ->count();

        $dueCounts = (clone $unfinishedDueQuery)
            ->whereBetween('due_at', [$today->startOfDay(), $lastScheduleDate->endOfDay()])
            ->selectRaw('DATE(due_at) as due_date, COUNT(*) as total')
            ->groupByRaw('DATE(due_at)')
            ->pluck('total', 'due_date');

        $dueDateOptions = collect(range(0, 29))->map(function (int $dayOffset) use ($dueCounts, $today): array {
            $date = $today->addDays($dayOffset);
            $dateValue = $date->toDateString();

            return [
                'value' => $dateValue,
                'day' => $dayOffset === 0 ? 'Hari ini' : ucfirst($date->translatedFormat('D')),
                'date' => $date->translatedFormat('d M'),
                'count' => (int) ($dueCounts[$dateValue] ?? 0),
            ];
        });

        $orders = Order::with(['outlet', 'user', 'items'])
            ->when(! Auth::user()->isOwner(), fn ($query) => $query->where('outlet_id', Auth::user()->outlet_id))
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId))
            ->when($this->search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery->where('number', 'like', '%'.$this->search.'%')->orWhere('customer_name', 'like', '%'.$this->search.'%')->orWhere('customer_phone', 'like', '%'.$this->search.'%')))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->paymentStatus, fn ($query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->dueFilter === 'overdue', fn (Builder $query) => $query->where('due_at', '<', now())->whereNotIn('status', ['ready', 'completed', 'cancelled']))
            ->when($this->dueFilter !== '' && $this->dueFilter !== 'overdue', fn (Builder $query) => $query->whereDate('due_at', $this->dueFilter)->whereNotIn('status', ['ready', 'completed', 'cancelled']))
            ->when($this->dueFilter !== '', fn ($query) => $query->orderBy('due_at'), fn ($query) => $query->latest())
            ->paginate($this->perPage);

        $detailOrder = $this->detailOrderId
            ? Order::with(['outlet', 'user', 'items'])
                ->when(! Auth::user()->isOwner(), fn ($query) => $query->where('outlet_id', Auth::user()->outlet_id))
                ->find($this->detailOrderId)
            : null;

        return view('livewire.transactions-page', [
            'orders' => $orders,
            'detailOrder' => $detailOrder,
            'outlets' => Outlet::where('is_active', true)->get(),
            'overdueCount' => $overdueCount,
            'dueDateOptions' => $dueDateOptions,
        ])->title('Transaksi — Laundry Pos');
    }
}
