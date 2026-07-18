<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickupSlot extends Model
{
    protected $fillable = [
        'tenant_id',
        'dayPickup',
        'start_time',
        'end_time',
        'label',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
    
    public function getFormattedRangeAttribute(): string
    {
        $start = date('H:i', strtotime($this->start_time));
        $end = date('H:i', strtotime($this->end_time));
        
        return "{$start} - {$end}";
    }
}
