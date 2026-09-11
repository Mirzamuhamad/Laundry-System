<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
