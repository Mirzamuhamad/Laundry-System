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
class ManagementPage extends Component
{
    public string $outletName = '';

    public string $outletCode = '';

    public string $outletAddress = '';

    public string $outletPhone = '';

    public bool $outletIsActive = true;

    public ?int $editingOutletId = null;

    public ?int $deletingOutletId = null;

    public ?int $selectedOutletId = null;

    public string $employeeName = '';

    public string $employeeEmail = '';

    public string $employeePhone = '';

    public string $employeePassword = '';

    public ?int $employeeOutlet = null;

    public string $startTime = '08:00';

    public string $endTime = '17:00';

    public int $tolerance = 10;

    public bool $employeeIsActive = true;

    public ?int $editingEmployeeId = null;

    public ?int $deletingEmployeeId = null;

    public function addOutlet(): void
    {
        $this->authorizeOwner();
        $this->outletCode = strtoupper(trim($this->outletCode));
        $data = $this->validate($this->outletRules());
        Outlet::create(['name' => $data['outletName'], 'code' => strtoupper($data['outletCode']), 'address' => $data['outletAddress'], 'phone' => $data['outletPhone']]);
        $this->resetOutletForm();
        $this->dispatch('notify', 'Outlet berhasil ditambahkan.');
    }

    public function editOutlet(int $id): void
    {
        $this->authorizeOwner();
        $outlet = Outlet::findOrFail($id);

        $this->editingOutletId = $outlet->id;
        $this->outletName = $outlet->name;
        $this->outletCode = $outlet->code;
        $this->outletAddress = $outlet->address ?? '';
        $this->outletPhone = $outlet->phone ?? '';
        $this->outletIsActive = $outlet->is_active;
        $this->resetValidation();
    }

    public function updateOutlet(): void
    {
        $this->authorizeOwner();
        $outlet = Outlet::findOrFail($this->editingOutletId);
        $this->outletCode = strtoupper(trim($this->outletCode));
        $data = $this->validate($this->outletRules($outlet));

        $outlet->update([
            'name' => $data['outletName'],
            'code' => $data['outletCode'],
            'address' => $data['outletAddress'],
            'phone' => $data['outletPhone'],
            'is_active' => $data['outletIsActive'],
        ]);

        $this->resetOutletForm();
        $this->dispatch('notify', 'Data outlet berhasil diperbarui.');
    }

    public function cancelOutletEdit(): void
    {
        $this->resetOutletForm();
    }

    public function confirmDeleteOutlet(int $id): void
    {
        $this->authorizeOwner();
        $outlet = Outlet::findOrFail($id);

        $this->deletingOutletId = $outlet->id;
        $this->resetErrorBag('deleteOutlet');
    }

    public function deleteOutlet(): void
    {
        $this->authorizeOwner();
        $outlet = Outlet::findOrFail($this->deletingOutletId);

        DB::transaction(function () use ($outlet): void {
            $outlet->users()->update(['is_active' => false]);
            $outlet->update(['is_active' => false]);
            $outlet->delete();
        });

        if ($this->selectedOutletId === $outlet->id) {
            $this->selectedOutletId = null;
        }

        $this->deletingOutletId = null;
        $this->dispatch('notify', 'Outlet dihapus dan karyawannya dinonaktifkan.');
    }

    public function viewOutletEmployees(int $id): void
    {
        $this->authorizeOwner();
        $this->selectedOutletId = Outlet::findOrFail($id)->id;
    }

    public function addEmployee(): void
    {
        $this->authorizeOwner();
        $data = $this->validate($this->employeeRules());

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['employeeName'],
                'email' => $data['employeeEmail'],
                'phone' => $data['employeePhone'],
                'password' => $data['employeePassword'],
                'outlet_id' => $data['employeeOutlet'],
                'role' => 'cashier',
                'is_active' => true,
            ]);
            EmployeeSchedule::create([
                'user_id' => $user->id,
                'start_time' => $data['startTime'],
                'end_time' => $data['endTime'],
                'tolerance_minutes' => $data['tolerance'],
                'work_days' => [1, 2, 3, 4, 5, 6],
            ]);
        });

        $this->resetEmployeeForm();
        $this->dispatch('notify', 'Karyawan berhasil ditambahkan.');
    }

    public function editEmployee(int $id): void
    {
        $this->authorizeOwner();
        $employee = User::with('schedule')->where('role', 'cashier')->findOrFail($id);

        $this->selectedOutletId = null;
        $this->editingEmployeeId = $employee->id;
        $this->employeeName = $employee->name;
        $this->employeeEmail = $employee->email;
        $this->employeePhone = $employee->phone ?? '';
        $this->employeePassword = '';
        $this->employeeOutlet = $employee->outlet_id;
        $this->startTime = substr($employee->schedule?->start_time ?? '08:00', 0, 5);
        $this->endTime = substr($employee->schedule?->end_time ?? '17:00', 0, 5);
        $this->tolerance = $employee->schedule?->tolerance_minutes ?? 10;
        $this->employeeIsActive = $employee->is_active;
        $this->resetValidation();
    }

    public function updateEmployee(): void
    {
        $this->authorizeOwner();
        $employee = User::where('role', 'cashier')->findOrFail($this->editingEmployeeId);
        $data = $this->validate($this->employeeRules($employee));

        DB::transaction(function () use ($data, $employee): void {
            $employeeData = [
                'name' => $data['employeeName'],
                'email' => $data['employeeEmail'],
                'phone' => $data['employeePhone'],
                'outlet_id' => $data['employeeOutlet'],
                'is_active' => $data['employeeIsActive'],
            ];

            if ($data['employeePassword'] !== '') {
                $employeeData['password'] = $data['employeePassword'];
            }

            $employee->update($employeeData);
            $employee->schedule()->updateOrCreate([], [
                'start_time' => $data['startTime'],
                'end_time' => $data['endTime'],
                'tolerance_minutes' => $data['tolerance'],
                'work_days' => $employee->schedule?->work_days ?? [1, 2, 3, 4, 5, 6],
            ]);
        });

        $this->resetEmployeeForm();
        $this->dispatch('notify', 'Data karyawan berhasil diperbarui.');
    }

    public function cancelEmployeeEdit(): void
    {
        $this->resetEmployeeForm();
    }

    public function confirmDeleteEmployee(int $id): void
    {
        $this->authorizeOwner();
        $employee = User::where('role', 'cashier')->findOrFail($id);

        $this->deletingEmployeeId = $employee->id;
    }

    public function deleteEmployee(): void
    {
        $this->authorizeOwner();
        $employee = User::where('role', 'cashier')->findOrFail($this->deletingEmployeeId);

        DB::transaction(function () use ($employee): void {
            $employee->update(['is_active' => false]);
            $employee->delete();
        });

        $this->deletingEmployeeId = null;
        $this->dispatch('notify', 'Karyawan berhasil dihapus. Riwayat kerjanya tetap tersimpan.');
    }

    public function toggleEmployee(int $id): void
    {
        $this->authorizeOwner();
        $user = User::where('role', 'cashier')->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
    }

    /**
     * @return array<string, mixed>
     */
    private function outletRules(?Outlet $outlet = null): array
    {
        return [
            'outletName' => ['required', 'string', 'max:100'],
            'outletCode' => ['required', 'string', 'max:20', Rule::unique('outlets', 'code')->ignore($outlet)],
            'outletAddress' => ['nullable', 'string', 'max:500'],
            'outletPhone' => ['nullable', 'string', 'max:30'],
            'outletIsActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeRules(?User $employee = null): array
    {
        return [
            'employeeName' => ['required', 'string', 'max:100'],
            'employeeEmail' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee)],
            'employeePhone' => ['nullable', 'string', 'max:30'],
            'employeePassword' => [$employee ? 'nullable' : 'required', 'string', 'min:8'],
            'employeeOutlet' => ['required', Rule::exists('outlets', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'tolerance' => ['required', 'integer', 'min:0', 'max:120'],
            'employeeIsActive' => ['boolean'],
        ];
    }

    private function resetOutletForm(): void
    {
        $this->reset(['editingOutletId', 'outletName', 'outletCode', 'outletAddress', 'outletPhone']);
        $this->outletIsActive = true;
        $this->resetValidation();
    }

    private function resetEmployeeForm(): void
    {
        $this->reset(['editingEmployeeId', 'employeeName', 'employeeEmail', 'employeePhone', 'employeePassword', 'employeeOutlet']);
        $this->startTime = '08:00';
        $this->endTime = '17:00';
        $this->tolerance = 10;
        $this->employeeIsActive = true;
        $this->resetValidation();
    }

    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->isOwner(), 403);
    }

    public function render()
    {
        $outlets = Outlet::query()
            ->withCount(['users as employees_count' => fn ($query) => $query->where('role', 'cashier')])
            ->orderBy('name')
            ->get();
        $employees = User::with(['outlet', 'schedule'])->where('role', 'cashier')->latest()->get();
        $selectedOutlet = $this->selectedOutletId ? Outlet::find($this->selectedOutletId) : null;
        $outletEmployees = $selectedOutlet
            ? User::with('schedule')->where('role', 'cashier')->whereBelongsTo($selectedOutlet)->orderBy('name')->get()
            : collect();

        return view('livewire.management-page', compact('outlets', 'employees', 'selectedOutlet', 'outletEmployees'))->title('Pengaturan — Laundry Pos');
    }
}
