<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLoginCode extends Model
{
    protected $fillable = ['public_id', 'customer_id', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];
    protected $hidden = ['code_hash'];
    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function customer() { return $this->belongsTo(Customer::class); }
}
