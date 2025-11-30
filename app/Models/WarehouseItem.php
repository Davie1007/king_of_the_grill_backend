<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseItem extends Model
{
    use HasFactory;
    protected $fillable = ['name','unit','stock','supplier_id'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
