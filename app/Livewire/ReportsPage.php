<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ReportsPage extends Component
{
    use WithPagination;

    public string $from = '';

    public string $to = '';

    public ?int $outletId = null;

    public int $ordersPerPage = 10;

    public int $expensesPerPage = 10;

    public int $attendancesPerPage = 10;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = today()->format('Y-m-d');
    }

    public function setPeriod(string $period): void
    {
        if ($period === 'today') {
            $this->from = today()->format('Y-m-d');
        } elseif ($period === 'week') {
            $this->from = now()->startOfWeek()->format('Y-m-d');
        } else {
            $this->from = now()->startOfMonth()->format('Y-m-d');
        } $this->to = today()->format('Y-m-d');
        $this->resetReportPages();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'outletId'], true)) {
            $this->resetReportPages();
        }

        if ($property === 'ordersPerPage') {
            $this->ordersPerPage = $this->normalizePerPage($this->ordersPerPage);
            $this->resetPage('ordersPage');
        }

        if ($property === 'expensesPerPage') {
            $this->expensesPerPage = $this->normalizePerPage($this->expensesPerPage);
            $this->resetPage('expensesPage');
        }

        if ($property === 'attendancesPerPage') {
            $this->attendancesPerPage = $this->normalizePerPage($this->attendancesPerPage);
            $this->resetPage('attendancesPage');
        }
    }

    public function render()
    {
        $allOrders = Order::with(['outlet', 'user'])->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])->when($this->outletId, fn ($q) => $q->where('outlet_id', $this->outletId));
        $orders = (clone $allOrders)->where('status', '!=', 'cancelled');
        $payments = Payment::whereBetween('paid_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])->when($this->outletId, fn ($q) => $q->whereHas('order', fn ($s) => $s->where('outlet_id', $this->outletId)));
        $expenses = Expense::query()
            ->whereDate('expense_date', '>=', $this->from)
            ->whereDate('expense_date', '<=', $this->to)
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId));
        $attendances = Attendance::with(['user.schedule', 'outlet'])
            ->whereDate('attendance_date', '>=', $this->from)
            ->whereDate('attendance_date', '<=', $this->to)
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId));
        $sales = (clone $orders)->sum('total');
        $received = (clone $payments)->sum('amount');
        $expenseTotal = (clone $expenses)->sum('amount');
        $profit = $received - $expenseTotal;
        $receivables = (clone $orders)->selectRaw('COALESCE(SUM(total - paid_amount), 0) AS balance')->value('balance');
        $topProducts = OrderItem::selectRaw('product_name, SUM(quantity) as qty, SUM(order_items.subtotal) as revenue')->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])->where('status', '!=', 'cancelled')->when($this->outletId, fn ($s) => $s->where('outlet_id', $this->outletId)))->groupBy('product_name')->orderByDesc('revenue')->limit(6)->get();
        $expenseByCategory = (clone $expenses)->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->get();
        $transactions = (clone $orders)->count();
        $reportOrders = (clone $allOrders)->latest()->paginate($this->ordersPerPage, ['*'], 'ordersPage');
        $reportExpenses = (clone $expenses)->with(['outlet', 'user', 'attachments'])->latest('expense_date')->latest('id')->paginate($this->expensesPerPage, ['*'], 'expensesPage');
        $reportAttendances = (clone $attendances)->latest('attendance_date')->latest('check_in_at')->latest('id')->paginate($this->attendancesPerPage, ['*'], 'attendancesPage');
        $attendanceCount = (clone $attendances)->count();
        $lateCount = (clone $attendances)->where('status', 'late')->count();

        return view('livewire.reports-page', compact('sales', 'received', 'expenseTotal', 'profit', 'receivables', 'topProducts', 'expenseByCategory', 'transactions', 'reportOrders', 'reportExpenses', 'reportAttendances', 'attendanceCount', 'lateCount'))->with('outlets', Outlet::where('is_active', true)->get())->title('Laporan — Laundry Pos');
    }

    private function resetReportPages(): void
    {
        $this->resetPage('ordersPage');
        $this->resetPage('expensesPage');
        $this->resetPage('attendancesPage');
    }

    private function normalizePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }
}
