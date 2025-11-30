<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleItem extends Model
{
    use HasFactory;
    protected $fillable = ['sale_id','item_id','quantity','price'];
    protected $casts = ['quantity'=>'decimal:2','price'=>'decimal:2'];

    public function sale(){ return $this->belongsTo(Sale::class); }
    public function item(){ return $this->belongsTo(InventoryItem::class,'item_id'); }
}
