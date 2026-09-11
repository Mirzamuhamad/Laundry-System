<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ExpenseAttachment;
use Illuminate\Support\Facades\Storage;

class SecureFileController extends Controller
{
    public function expense(ExpenseAttachment $attachment)
    {
        $user = request()->user();
        abort_unless($user->isOwner(), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path);
    }

    public function attendance(Attendance $attendance, string $type)
    {
        $user = request()->user();
        abort_unless($user->isOwner() || $user->id === $attendance->user_id, 403);
        abort_unless(in_array($type, ['in', 'out'], true), 404);
        $path = $type === 'in' ? $attendance->check_in_photo : $attendance->check_out_photo;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
