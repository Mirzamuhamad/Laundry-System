<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
