<?php

namespace App\Livewire;

use App\Models\EmployeeSchedule;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UserAccountsPage extends Component
{
    public string $accountName = '';

    public string $accountEmail = '';

    public string $accountPhone = '';

    public string $accountPassword = '';

    public string $accountRole = 'cashier';

    public ?int $accountOutletId = null;

    public bool $accountIsActive = true;

    public ?int $editingAccountId = null;

    public function updatedAccountRole(): void
    {
        if ($this->accountRole === 'owner') {
            $this->accountOutletId = null;
        }
    }

    public function saveAccount(): void
    {
        $this->authorizeOwner();
        $account = $this->editingAccountId ? User::findOrFail($this->editingAccountId) : null;
        $data = $this->validate($this->accountRules($account));

        if ($account?->is(Auth::user()) && ($data['accountRole'] !== 'owner' || ! $data['accountIsActive'])) {
            $this->addError('accountRole', 'Akun yang sedang digunakan harus tetap aktif sebagai owner.');

            return;
        }

        DB::transaction(function () use ($account, $data): void {
            $accountData = [
                'name' => $data['accountName'],
                'email' => $data['accountEmail'],
                'phone' => $data['accountPhone'],
                'role' => $data['accountRole'],
                'outlet_id' => $data['accountRole'] === 'cashier' ? $data['accountOutletId'] : null,
                'is_active' => $data['accountIsActive'],
            ];

            if ($data['accountPassword'] !== '') {
                $accountData['password'] = $data['accountPassword'];
            }

            $savedAccount = $account
                ? tap($account)->update($accountData)
                : User::create($accountData);

            if ($savedAccount->role === 'cashier') {
                EmployeeSchedule::firstOrCreate(
                    ['user_id' => $savedAccount->id],
                    [
                        'start_time' => '08:00',
                        'end_time' => '17:00',
                        'tolerance_minutes' => 10,
                        'work_days' => [1, 2, 3, 4, 5, 6],
                    ],
                );
            }
        });

        $message = $account ? 'Akun login berhasil diperbarui.' : 'Akun login berhasil ditambahkan.';
        $this->resetAccountForm();
        $this->dispatch('notify', $message);
    }

    public function editAccount(int $id): void
    {
        $this->authorizeOwner();
        $account = User::findOrFail($id);

        $this->editingAccountId = $account->id;
        $this->accountName = $account->name;
        $this->accountEmail = $account->email;
        $this->accountPhone = $account->phone ?? '';
        $this->accountPassword = '';
        $this->accountRole = $account->role;
        $this->accountOutletId = $account->outlet_id;
        $this->accountIsActive = $account->is_active;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetAccountForm();
    }

    public function toggleAccount(int $id): void
    {
        $this->authorizeOwner();
        $account = User::findOrFail($id);
        abort_if($account->is(Auth::user()), 422, 'Akun yang sedang digunakan tidak dapat dinonaktifkan.');

        $account->update(['is_active' => ! $account->is_active]);
        $this->dispatch('notify', 'Status akun login diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function accountRules(?User $account = null): array
    {
        return [
            'accountName' => ['required', 'string', 'max:100'],
            'accountEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account)],
            'accountPhone' => ['nullable', 'string', 'max:30'],
            'accountPassword' => [$account ? 'nullable' : 'required', 'string', 'min:8'],
            'accountRole' => ['required', Rule::in(['owner', 'cashier'])],
            'accountOutletId' => [
                Rule::requiredIf($this->accountRole === 'cashier'),
                'nullable',
                Rule::exists('outlets', 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'accountIsActive' => ['boolean'],
        ];
    }

    private function resetAccountForm(): void
    {
        $this->reset(['editingAccountId', 'accountName', 'accountEmail', 'accountPhone', 'accountPassword', 'accountOutletId']);
        $this->accountRole = 'cashier';
        $this->accountIsActive = true;
        $this->resetValidation();
    }

    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->isOwner(), 403);
    }

    public function render()
    {
        $accounts = User::with('outlet')->latest()->get();
        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();

        return view('livewire.user-accounts-page', [
            'accounts' => $accounts,
            'outlets' => $outlets,
            'activeAccounts' => $accounts->where('is_active', true)->count(),
            'ownerAccounts' => $accounts->where('role', 'owner')->count(),
            'cashierAccounts' => $accounts->where('role', 'cashier')->count(),
        ])->title('User Login — Laundry Pos');
    }
}
