<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalTenant extends Model
{
    
    protected $fillable = [
        'tenant_code',
        'reservation_id',
        'store_name',
        'slug',
        'description',
        'phone',
        'tenant_img',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
    
    public function menus()
    {
        return $this->hasMany(ApprovalMenu::class, 'tenant_id');
    }
}
