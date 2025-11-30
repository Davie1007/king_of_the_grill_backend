<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'amount',
        'phone',
        'method',
        'used',
        'branch_id',
        'reference', 
        'bill_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'used' => 'boolean',
    ];
    public function sale()
    {
        return $this->hasOne(Sale::class, 'payment_id');
    }
    public function creditRepayment()
    {
        return $this->hasOne(CreditRepayment::class);
    }
    public function bill()
    {
        return $this->belongsTo(\App\Models\Bill::class);
    }
}
