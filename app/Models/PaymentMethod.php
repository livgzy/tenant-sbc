<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'name_payment',
        'account_number',
        'account_name',
        'qr_img',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tenant_id' => 'integer',
    ];


    public function getTypeLabelAttribute()
    {
        return [
            'bank_transfer' => 'Bank Transfer',
            'e_wallet' => 'E-Wallet',
            'qris' => 'QRIS',
        ][$this->type] ?? $this->type;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
