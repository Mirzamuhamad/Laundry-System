<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'integer'];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }
}
