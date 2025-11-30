<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id','supplier','reference_no','amount','category',
        'description','bill_date','due_date','status',
        'paid_amount','balance',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance'     => 'decimal:2',
        'bill_date'   => 'date',
        'due_date'    => 'date',
    ];

    public function branch()   { return $this->belongsTo(Branch::class); }
    public function payments() { return $this->hasMany(BillPayment::class); }

    /** Recalculate status & balance – call after every payment */
    public function refreshPaymentStatus(): void
    {
        $this->paid_amount = $this->payments()->sum('amount');
        $this->balance     = max($this->amount - $this->paid_amount, 0);

        $this->status = match (true) {
            $this->paid_amount >= $this->amount => 'paid',
            $this->paid_amount > 0               => 'partially_paid',
            default                              => 'unpaid',
        };

        $this->saveQuietly();
    }
}
