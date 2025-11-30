<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DarajaApp extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'consumer_key',
        'consumer_secret',
        'shortcode',
        'passkey',
        'environment',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
