<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // 'tenant_id',
        'reservation_id',
        'type',
        'name_payment',
        'account_number',
        'account_name',
    ];

    // public function tenant()
    // {
    //     return $this->belongsTo(Tenant::class, 'tenant_id');
    // }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
 
    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }
    
    public function getTypeLabelAttribute()
    {
        return [
            'bank_transfer' => 'Bank Transfer',
            'e_wallet' => 'E-Wallet',
            'qris' => 'QRIS',
        ][$this->type] ?? $this->type;
    }
}
