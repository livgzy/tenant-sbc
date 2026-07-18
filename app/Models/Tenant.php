<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'tenant_code',
        'reservation_id',
        'store_name',
        'slug',
        'description',
        'phone',
        'is_open',
        'tenant_img'
    ];

    protected $casts = [
        'is_open' => 'boolean',
    ];
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment_method()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function pick_slot()
    {
        return $this->hasMany(PickupSlot::class);
    }
}
