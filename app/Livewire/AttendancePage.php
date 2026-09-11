<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AttendancePage extends Component
{
    public ?int $outletId = null;

    public string $from = '';

    public string $to = '';

    public ?int $employeeId = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->outletId = $user->isOwner() ? ($user->outlet_id ?? Outlet::where('is_active', true)->value('id')) : $user->outlet_id;
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = today()->format('Y-m-d');
    }

    public function checkIn(string $photo): void
    {
        $this->recordPhoto($photo, 'in');
    }

    public function checkOut(string $photo): void
    {
        $this->recordPhoto($photo, 'out');
    }

    private function recordPhoto(string $dataUrl, string $type): void
    {
        $user = Auth::user();

        if (! $user->isOwner()) {
            $this->outletId = $user->outlet_id;
        }

        if (! $this->outletId) {
            $this->attendanceFailed('Outlet belum dipilih.');

            return;
        }
        if (! preg_match('/^data:image\/(jpeg|jpg);base64,/', $dataUrl)) {
            $this->attendanceFailed('Format foto tidak valid. Silakan ambil foto ulang.');

            return;
        }
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false || strlen($binary) > 4 * 1024 * 1024) {
            $this->attendanceFailed('Ukuran foto terlalu besar. Silakan ambil foto ulang.');

            return;
        }
        $businessDate = now()->timezone(config('app.timezone'))->toDateString();
        $attendance = Attendance::query()->where('user_id', $user->id)->whereDate('attendance_date', $businessDate)->first();

        if ($type === 'out' && (! $attendance || ! $attendance->check_in_at)) {
            $this->attendanceFailed('Absen masuk hari ini belum ditemukan. Silakan lakukan absen masuk terlebih dahulu.');

            return;
        }
        if ($type === 'in' && $attendance?->check_in_at) {
            $this->attendanceFailed('Anda sudah absen masuk hari ini pada '.$attendance->check_in_at->format('H:i').'.');

            return;
        }
        if ($type === 'out' && $attendance?->check_out_at) {
            $this->attendanceFailed('Anda sudah absen keluar hari ini pada '.$attendance->check_out_at->format('H:i').'.');

            return;
        }

        DB::transaction(function () use ($attendance, $binary, $businessDate, $type, $user): void {
            if ($type === 'in') {
                $path = 'attendance/'.$user->id.'/'.$businessDate.'-in-'.Str::random(8).'.jpg';
                Storage::disk('local')->put($path, $binary);
                $schedule = $user->schedule;
                $late = $schedule && now()->gt(now()->startOfDay()->setTimeFromTimeString($schedule->start_time)->addMinutes($schedule->tolerance_minutes));
                Attendance::create(['user_id' => $user->id, 'outlet_id' => $this->outletId, 'attendance_date' => $businessDate, 'check_in_at' => now(), 'check_in_photo' => $path, 'status' => $late ? 'late' : 'present']);

                return;
            }

            $path = 'attendance/'.$user->id.'/'.$businessDate.'-out-'.Str::random(8).'.jpg';
            Storage::disk('local')->put($path, $binary);
            $attendance->update(['check_out_at' => now(), 'check_out_photo' => $path, 'work_duration_minutes' => (int) $attendance->check_in_at->diffInMinutes(now())]);
        });
        $this->dispatch('attendance-saved');
        $this->dispatch('notify', $type === 'in' ? 'Absen masuk berhasil.' : 'Absen keluar berhasil.');
    }

    private function attendanceFailed(string $message): void
    {
        $this->dispatch('attendance-failed', message: $message);
    }

    public function render()
    {
        $user = Auth::user();
        $today = Attendance::where('user_id', $user->id)->whereDate('attendance_date', now()->timezone(config('app.timezone'))->toDateString())->first();
        $history = Attendance::with(['user', 'outlet'])
            ->when(! $user->isOwner(), fn ($query) => $query->where('user_id', $user->id))
            ->when($user->isOwner() && $this->employeeId, fn ($query) => $query->where('user_id', $this->employeeId))
            ->when($user->isOwner() && $this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId))
            ->whereDate('attendance_date', '>=', $this->from)
            ->whereDate('attendance_date', '<=', $this->to)
            ->latest('attendance_date')
            ->limit(50)
            ->get();

        return view('livewire.attendance-page', ['today' => $today, 'history' => $history, 'outlets' => Outlet::where('is_active', true)->get(), 'employees' => User::where('role', 'cashier')->where('is_active', true)->get()])->title('Absensi — Laundry Pos');
    }
}
