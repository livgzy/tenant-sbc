<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class FonnteService
{
    public static function notifyCustomerStatus(Order $order): void
    {
        $phone = $order->user->phone ?? null;

        if (empty($phone)) {
            Log::warning("Fonnte: nomor WA customer kosong untuk order {$order->order_number}");
            return;
        }

        $message = self::buildCustomerMessage($order);

        if (empty($message)) {
            return;
        }

        self::send($phone, $message);
    }

    private static function buildCustomerMessage(Order $order): string
    {
        $storeName = data_get($order->data_tenant, 'store_name', 'Tenant');
        $storePhone = data_get($order->data_tenant, 'phone', 'Tenant');
        $customerName = $order->user->name;
        $orderNumber = $order->order_number;

        $pickupDay = data_get($order->data_pickup_slot, 'dayPickup', '-');
        $pickupStart = data_get($order->data_pickup_slot, 'start_time', '');
        $pickupEnd = data_get($order->data_pickup_slot, 'end_time', '');
        $pickupTime = $order->pickup_time;

        return match ($order->status) {
            'Diproses' => "Halo {$customerName}, 👋\n\n"
                . "Pesanan Anda *{$orderNumber}* di *{$storeName}* telah *Diterima* dan sedang diproses oleh tenant.\n\n"
                . "Kami akan mengabari lagi begitu pesanan siap diambil. Terima kasih! 🙏",

            'Dibatalkan' => "Halo {$customerName},\n\n"
                . "Mohon maaf, pesanan Anda *{$orderNumber}* di *{$storeName}* telah *Dibatalkan* oleh tenant.\n\n"
                . "Jika Anda sudah melakukan pembayaran, silakan hubungi tenant terkait {$storePhone} untuk proses lebih lanjut.",

            'Selesai' => "Halo {$customerName}, 🎉\n\n"
                . "Pesanan Anda *{$orderNumber}* di *{$storeName}* sudah *Siap Diambil*!\n\n"
                . "Silakan ambil sesuai jadwal pickup: *{$pickupDay}, {$pickupTime} ({$pickupStart}-{$pickupEnd})*.\n\n"
                . "Ditunggu kedatangannya ya!",

            default => '',
        };
    }

    private static function send(string $phone, string $message): void
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.fonnte.token'),
        ])->asForm()->post('https://api.fonnte.com/send', [
            'target'  => self::formatPhone($phone),
            'message' => $message,
        ]);

        if (! $response->successful()) {
            Log::error("Fonnte gagal kirim pesan ke {$phone}: " . $response->body());
        }
    }

    private static function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }
}