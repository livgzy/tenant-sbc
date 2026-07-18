<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'order_number',
        'data_tenant',
        'user_id',
        'status',
        'total_amount',
        'payment_status',
        'payment_method',
        'payment_method_id',
        'data_payment_method',
        'pickup_time',
        'pickup_slot_id',
        'data_pickup_slot',
        'payment_proof_img',
    ];
 
    protected $casts = [
        'data_tenant' => 'array',
        'data_payment_method' => 'array',
        'data_pickup_slot' => 'array',
        'total_amount' => 'decimal:2',
    ];
 
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
 
    public function user()
    {
        return $this->belongsTo(User::class);
    }
 
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
 
    public function pickupSlot()
    {
        return $this->belongsTo(PickupSlot::class);
    }
}
