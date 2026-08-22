<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\UserTenant;
/**
 * 3 kondisi ini SALING EKSKLUSIF dan SALING MELENGKAPI -- setiap user pasti masuk
 * tepat 1 dari 3 kondisi berikut, tidak pernah 0 dan (dalam kondisi normal) tidak
 * pernah lebih dari 1:
 *   1. hasActiveTenant()    -> sedang jalanin tenant aktif sekarang
 *   2. hasUnsettledPayout() -> reservasi sudah berakhir, masih ada urusan payout
 *   3. selain keduanya      -> bebas mengajukan reservasi baru
 */
class TenantAccess
{
    public static function hasActiveTenant(UserTenant $user): bool
    {
        return Reservation::where('user_id', $user->id)
            ->where('is_ended', false)
            ->whereHas('tenant')
            ->exists();
    }
 
    /**
     * Reservasi (sudah berakhir) yang masih ada urusan payout-nya. Dipakai
     * langsung oleh halaman pendaftaran rekening payout untuk tahu reservasi
     * mana yang sedang diurus -- bukan cuma butuh true/false seperti
     * hasUnsettledPayout().
     */
    public static function unsettledReservationFor(UserTenant $user): ?Reservation
    {
        return Reservation::where('user_id', $user->id)
            ->where('is_ended', true)
            ->whereHas('tenantWallet', function ($query) {
                $query->where(function ($q) {
                    $q->whereColumn('total_earned', '>', 'total_paid_out')
                        ->orWhereHas('payouts', fn ($p) => $p->whereIn('status', ['Pending', 'Diproses']));
                });
            })
            ->latest('end_date')
            ->first();
    }
 
    public static function hasUnsettledPayout(UserTenant $user): bool
    {
        return self::unsettledReservationFor($user) !== null;
    }

    public static function homeRouteFor(UserTenant $user): string
    {
        if (self::hasActiveTenant($user)) {
            return 'home';
        }

        if (self::hasUnsettledPayout($user)) {
            return 'tenant.payout';
        }

        return 'tenant.reservation';
    }
}