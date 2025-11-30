<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseDispatch extends Model
{
    use HasFactory;
    protected $fillable = ['warehouse_item_id','branch_id','quantity','notes','performed_by_id'];

    public function warehouseItem(){ return $this->belongsTo(WarehouseItem::class,'warehouse_item_id'); }
    public function branch(){ return $this->belongsTo(Branch::class); }
    public function performedBy(){ return $this->belongsTo(User::class,'performed_by_id'); }
}
