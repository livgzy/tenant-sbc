<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Models\UserTenant;

class ReservationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id;
    }

        /**
     * Boleh daftar/lihat form rekening tujuan payout.
     * Cukup: miliknya sendiri + reservasinya sudah berakhir. Validasi lebih detail
     * (misal sudah pernah daftar rekening) ditangani PaymentMethod::booted() di model.
     */
    public function managePayoutMethod(UserTenant $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id
            && $reservation->is_ended;
    }
 
    /**
     * Boleh ajukan payout. Semua syarat harus terpenuhi:
     * - reservasi miliknya sendiri dan sudah berakhir
     * - masih ada saldo yang belum dicairkan
     * - rekening tujuan sudah didaftarkan
     * - tidak ada payout lain yang masih menggantung (Pending/Diproses)
     */
    public function submitPayout(UserTenant $user, Reservation $reservation): bool
    {
        if ($reservation->user_id !== $user->id || ! $reservation->is_ended) {
            return false;
        }
 
        $wallet = $reservation->tenantWallet;
 
        if (! $wallet || $wallet->outstanding_balance <= 0) {
            return false;
        }
 
        if (! $reservation->paymentMethod) {
            return false;
        }
 
        return ! $wallet->hasPendingPayout();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Reservation $reservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Reservation $reservation): bool
    {
        return false;
    }
}
