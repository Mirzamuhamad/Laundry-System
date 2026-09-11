<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $orders = Order::query()->when(! $user->isOwner(), fn ($q) => $q->where('outlet_id', $user->outlet_id));
        $todayOrders = (clone $orders)->whereDate('created_at', today());
        $todayRevenue = (clone $todayOrders)->sum('paid_amount');
        $todayCount = (clone $todayOrders)->count();
        $activeCount = (clone $orders)->whereNotIn('status', ['completed', 'cancelled'])->count();
        $overdueCount = (clone $orders)->whereNotIn('status', ['ready', 'completed', 'cancelled'])->where('due_at', '<', now())->count();
        $todayExpense = $user->isOwner() ? Expense::whereDate('expense_date', today())->sum('amount') : 0;
        $recentOrders = (clone $orders)->with('outlet')->latest()->limit(6)->get();
        $attendance = Attendance::where('user_id', $user->id)->whereDate('attendance_date', today())->first();

        return view('livewire.dashboard', compact('todayRevenue', 'todayCount', 'activeCount', 'overdueCount', 'todayExpense', 'recentOrders', 'attendance'))
            ->title('Ringkasan — Laundry Pos');
    }
}
