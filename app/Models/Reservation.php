<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'statusApprove',
        'end_date',
        'is_acknowledged',
        'activated_at',
        'is_ended',
        'reasons',
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime:Y-m-d',
    ];

    public function approvalTenant()
    {
        return $this->hasOne(ApprovalTenant::class, 'reservation_id');
    }

    public function user()
    {
        return $this->belongsTo(UserTenant::class, 'user_id');
    }

    public function tenant()
    {
        return $this->hasOne(Tenant::class, 'reservation_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
 
    public function paymentMethod()
    {
        return $this->hasOne(PaymentMethod::class, 'reservation_id');
    }

    public function tenantHistory()
    {
        return $this->hasOne(TenantHistory::class, 'reservation_id');
    }

    public function tenantWallet()
    {
        return $this->hasOne(TenantWallet::class, 'reservation_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    
    public function getOutstandingBalanceAttribute(): float
    {
        return $this->tenantWallet
            ? (float) $this->tenantWallet->total_earned - (float) $this->tenantWallet->total_paid_out
            : 0.0;
    }
 
    public function getIsPayoutSettledAttribute(): bool
    {
        return $this->tenantWallet ? (bool) $this->tenantWallet->is_settled : true;
    }
}
