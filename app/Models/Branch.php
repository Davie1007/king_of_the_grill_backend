<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tillNumber',
        'roleConfig',
        'managerId',
        'type',
        'latitude',
        'longitude',
        'service_radius',
        'daily_target',
        'weekly_target',
        'monthly_target',
        'yearly_target'
    ];
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    // 🔥 Add this
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    public function sales()
    {
        return $this->hasMany(\App\Models\Sale::class);
    }
    public function payments()
    {
        return $this->hasMany(\App\Models\Sale::class);
    }
    public function bills()
    {
        return $this->hasMany(\App\Models\Bill::class);
    }

    public function darajaApp()
    {
        return $this->belongsTo(DarajaApp::class);
    }

}

