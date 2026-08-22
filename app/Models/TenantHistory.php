<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TenantHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_code',
        'reservation_id',
        'store_name',
        'slug',
        'description',
        'phone',
        'tenant_img',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'reservation_id', 'reservation_id')->withTrashed();
    }
 
    public function paymentMethod()
    {
        return $this->hasOne(PaymentMethod::class, 'reservation_id', 'reservation_id')->withTrashed();
    }
 
    public function tenantWallet()
    {
        return $this->belongsTo(TenantWallet::class, 'reservation_id', 'reservation_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'reservation_id', 'reservation_id');
    }
}
