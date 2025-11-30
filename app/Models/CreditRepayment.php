<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CreditRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_sale_id',
        'amount',
        'payment_method',
        'transaction_id',
        'payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function creditSale()
    {
        return $this->belongsTo(CreditSale::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
