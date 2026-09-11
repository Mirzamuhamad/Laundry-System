<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseAttachment extends Model
{
    protected $guarded = [];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
