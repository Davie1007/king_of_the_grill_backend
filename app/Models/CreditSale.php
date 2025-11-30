<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CreditSale extends Model
{
    use HasFactory;

    protected $fillable = ['sale_id', 'customer', 'customer_phone', 'total_amount', 'amount_paid'];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function repayments()
    {
        return $this->hasMany(CreditRepayment::class);
    }

    public function getBalanceAttribute()
    {
        return $this->total_amount - $this->amount_paid;
    }

    /**
     * 🔹 Mutator: Normalize phone before saving
     */
    public function setCustomerPhoneAttribute($value)
    {
        // Keep only digits
        $phone = preg_replace('/\D/', '', $value);

        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1); // convert 07xxxx → 2547xxxx
        } elseif (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone; // enforce 254 prefix
        }

        $this->attributes['customer_phone'] = $phone;
    }

    /**
     * 🔹 Accessor: Always return in 2547XXXXXXX format (never with +)
     */
    public function getCustomerPhoneAttribute($value)
    {
        $phone = preg_replace('/\D/', '', $value);

        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }

        return $phone;
    }
}
