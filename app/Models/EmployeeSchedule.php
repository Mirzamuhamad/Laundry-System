<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['work_days' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
