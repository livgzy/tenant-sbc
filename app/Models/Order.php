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
        'reservation_id',
        'user_id',
        'order_type',
        'status',
        'total_amount',
        'payment_status',
        'payment_method',
        'pickup_time',
        'pickup_slot_id',
        'data_pickup_slot',
        'payment_batch_id',
    ];
 
    protected $casts = [
        'data_tenant'      => 'array',
        'data_pickup_slot' => 'array',
        'total_amount'     => 'decimal:2',
        'pickup_time'      => 'datetime:H:i',
        'paid_at'          => 'datetime',
        'expired_at'       => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
 
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentBatch()
    {
        return $this->belongsTo(PaymentBatch::class);
    }
 
    public function pickupSlot()
    {
        return $this->belongsTo(PickupSlot::class);
    }
}
