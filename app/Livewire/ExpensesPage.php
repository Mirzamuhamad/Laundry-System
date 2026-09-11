<?php

namespace App\Livewire;

use App\Models\Expense;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ExpensesPage extends Component
{
    use WithFileUploads, WithPagination;

    public bool $showForm = false;

    public ?int $outletId = null;

    public string $expenseDate = '';

    public string $category = 'Bahan laundry';

    public $amount = '';

    public string $paymentMethod = 'cash';

    public string $description = '';

    public array $photos = [];

    public string $filterFrom = '';

    public string $filterTo = '';

    public ?int $filterOutlet = null;

    public function mount(): void
    {
        $this->expenseDate = today()->format('Y-m-d');
        $this->filterFrom = now()->startOfMonth()->format('Y-m-d');
        $this->filterTo = today()->format('Y-m-d');
        $this->outletId = Outlet::where('is_active', true)->value('id');
    }

    public function save(): void
    {
        $data = $this->validate(['outletId' => 'required|exists:outlets,id', 'expenseDate' => 'required|date', 'category' => 'required|max:80', 'amount' => 'required|integer|min:1', 'paymentMethod' => 'required|in:cash,transfer,qris', 'description' => 'nullable|max:1000', 'photos' => 'array|max:5', 'photos.*' => 'image|max:4096']);
        DB::transaction(function () use ($data) {
            $expense = Expense::create(['outlet_id' => $data['outletId'], 'user_id' => Auth::id(), 'expense_date' => $data['expenseDate'], 'category' => $data['category'], 'amount' => $data['amount'], 'payment_method' => $data['paymentMethod'], 'description' => $data['description']]);
            foreach ($this->photos as $photo) {
                $path = $photo->store('expenses/'.$expense->id, 'local');
                $expense->attachments()->create(['path' => $path, 'original_name' => $photo->getClientOriginalName()]);
            }
        });
        $this->reset(['amount', 'description', 'photos']);
        $this->expenseDate = today()->format('Y-m-d');
        $this->category = 'Bahan laundry';
        $this->paymentMethod = 'cash';
        $this->showForm = false;
        $this->dispatch('notify', 'Expense berhasil dicatat.');
    }

    public function render()
    {
        $query = Expense::with(['outlet', 'user', 'attachments'])->when($this->filterOutlet, fn ($q) => $q->where('outlet_id', $this->filterOutlet))->when($this->filterFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterFrom))->when($this->filterTo, fn ($q) => $q->whereDate('expense_date', '<=', $this->filterTo));

        return view('livewire.expenses-page', ['expenses' => (clone $query)->latest('expense_date')->paginate(12), 'total' => (clone $query)->sum('amount'), 'outlets' => Outlet::where('is_active', true)->get()])->title('Expense — Laundry Pos');
    }
}
