<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryItem extends Model
{
    use HasFactory;
    protected $fillable = ['branch_id', 'name', 'price', 'price2', 'price3', 'buying_price', 'stock', 'unit', 'is_butchery', 'image'];

    protected $casts = [
        'price' => 'decimal:2',
        'price2' => 'decimal:2',
        'price3' => 'decimal:2',
        'buying_price' => 'decimal:2',
        'stock' => 'decimal:2',
        'is_butchery' => 'boolean',
    ];

    public function branch(){ return $this->belongsTo(Branch::class); }
}
