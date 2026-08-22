<?php

namespace App\Models;

use App\Services\FonnteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentBatch extends Model
{
    public const PAYMENT_WINDOW_MINUTES = 30;
 
    protected $fillable = [
        'batch_number',
        'user_id',
        'total_amount',
        'status',
        'xendit_payment_request_id',
        'xendit_reference_id',
        'xendit_qr_string',
        'xendit_status',
        'paid_at',
        'expired_at',
    ];
 
    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_at'      => 'datetime',
        'expired_at'   => 'datetime',
    ];
 
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
 
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
 
    public function isExpired(): bool
    {
        return $this->status === 'Pending' && $this->expired_at?->isPast();
    }
 
    /**
     * Satu sumber kebenaran untuk "pembayaran berhasil" — dipanggil dari webhook
     * Xendit ATAUPUN dari polling manual (fallback lokal/dev). Idempotent: aman
     * dipanggil berkali-kali (mis. webhook Xendit terkirim lebih dari sekali).
     */
    // public function markAsPaid(): void
    // {
    //     if ($this->status === 'Berhasil') {
    //         return;
    //     }
 
    //     DB::transaction(function () {
    //         $this->update([
    //             'status'  => 'Berhasil',
    //             'paid_at' => now(),
    //         ]);
 
    //         $this->orders()->get()->each(
    //             fn (Order $order) => $order->update(['payment_status' => 'Sudah Dibayar'])
    //         );
    //     });
 
    //     $this->orders->each(
    //         fn (Order $order) => FonnteService::sendOrderNotification($order->load('items'))
    //     );
    // }
 
    // public function markAsExpired(): void
    // {
    //     if ($this->status !== 'Pending') {
    //         return;
    //     }
 
    //     DB::transaction(function () {
    //         $this->update(['status' => 'Kadaluarsa']);
    //         $this->orders()->update(['status' => 'Dibatalkan']);
    //     });
    // }
}
