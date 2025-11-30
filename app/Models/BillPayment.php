<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id','branch_id','user_id','amount',
        'payment_method','note','paid_at',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'paid_at'  => 'datetime',
    ];

    public function bill()   { return $this->belongsTo(Bill::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function user()   { return $this->belongsTo(User::class); }

    /** When a BillPayment is created → create an Expense automatically */
    protected static function booted()
    {
        static::created(function (self $payment) {
            $bill = $payment->bill;

            Expense::create([
                'branch_id' => $payment->branch_id,
                'user_id'   => $payment->user_id,
                'title'     => "Bill payment – {$bill->supplier} (Ref: {$bill->reference_no})",
                'amount'    => $payment->amount,
                'category'  => $bill->category ?? 'Miscellaneous',
                'note'      => "Bill #{$bill->id} – {$payment->note}",
            ]);

            $bill->refreshPaymentStatus();
        });
    }
}
