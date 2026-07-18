<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuickOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quick_order_id',
        'product_id',
        'price',
        'quantity',
    ];

    public function order()
    {
        return $this->belongsTo(QuickOrder::class, 'quick_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
