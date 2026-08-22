<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'tenant_id',
        'reservation_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'is_available',
        'is_preorder',
        'product_img',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function category()
    {
        return $this->belongsTo(Categorie::class,  "category_id");
    }

    public function orderItems()
    {
        return $this->belongsToMany(Order::class);
    }

    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
