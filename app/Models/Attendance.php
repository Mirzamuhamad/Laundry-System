<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }
}
