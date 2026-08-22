<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantWallet extends Model
{
    use HasFactory;
 
    protected $fillable = [
        'reservation_id',
        'tenant_code',
        'total_earned',
        'total_paid_out',
    ];
 
    protected $casts = [
        'total_earned'   => 'decimal:2',
        'total_paid_out' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
 
    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'reservation_id', 'reservation_id');
    }
 
    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->total_earned - (float) $this->total_paid_out;
    }

    public function hasPendingPayout(): bool
    {
        return $this->payouts()->whereIn('status', ['Pending', 'Diproses'])->exists();
    }
 
    public function getIsSettledAttribute(): bool
    {
        return $this->outstanding_balance <= 0
            && ! $this->payouts()->whereIn('status', ['Pending', 'Diproses'])->exists();
    }
}
