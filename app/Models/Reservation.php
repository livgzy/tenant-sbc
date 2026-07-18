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
        'reasons',
        'is_acknowledged',
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
}
