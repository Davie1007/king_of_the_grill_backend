<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'payment_method',
        'total',
        'seller_id',
        'cash_tendered',
        'change',
        'payment_status',
        'customer_name',
        'customer_id_number',
        'customer_telephone_number',
        'credit_sale_id',
        'mpesa_ref',
        'mpesa_amount',
        'payment_id',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'cash_tendered' => 'decimal:2',
        'change' => 'decimal:2',
    ];

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creditSale()
    {
        return $this->belongsTo(CreditSale::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
