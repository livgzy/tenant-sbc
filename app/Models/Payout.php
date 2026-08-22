<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;
 
    protected $fillable = [
        'tenant_wallet_id',
        'user_id',
        'amount',
        'fee_amount',
        'net_amount',
        'payment_method_id',
        'data_payment_method',
        'status',
        'xendit_payout_id',
        'xendit_status',
        'rejection_reason',
    ];
 
    protected $casts = [
        'amount'              => 'decimal:2',
        'data_payment_method' => 'array',
    ];

    public function tenantWallet()
    {
        return $this->belongsTo(TenantWallet::class);
    }
 
    public function user()
    {
        return $this->belongsTo(UserTenant::class, 'user_id');
    }
 
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }
}
