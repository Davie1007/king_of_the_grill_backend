<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username', 'email', 'password', 'role', 'branch_id', 'photo'
    ];

    protected $hidden = ['password', 'remember_token'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Each user may have an employee profile
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}

