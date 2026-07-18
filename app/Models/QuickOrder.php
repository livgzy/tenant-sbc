<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuickOrder extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'order_number',
        'tenant_id',
        'total_amount',
    ];

    public function items()
    {
        return $this->hasMany(QuickOrderItem::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
