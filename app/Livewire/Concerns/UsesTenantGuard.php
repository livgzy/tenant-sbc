<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Pasang trait ini di SEMUA Livewire component project Tenant yang memanggil
 * $this->authorize(...) atau @can(...) di view-nya.
 *
 * Root cause "403 unauthorized" yang sering muncul: $this->authorize() bawaan
 * Livewire (dari trait AuthorizesRequests) resolve user lewat Auth::user()
 * TANPA argumen -- yaitu guard DEFAULT aplikasi ('web'). Project Tenant ini
 * login lewat guard 'tenant', jadi Auth::user() versi default selalu null,
 * dan Gate otomatis menolak (karena parameter Policy non-nullable) SEBELUM
 * sempat menjalankan logic Policy sama sekali.
 *
 * Trait ini override authorize() supaya otomatis pakai Auth::guard('tenant'),
 * jadi tidak perlu Gate::forUser() manual di tiap method satu-satu.
 */
trait UsesTenantGuard
{
    public function authorize($ability, $arguments = [])
    {
        $user = Auth::guard('tenant')->user();

        return Gate::forUser($user)->authorize($ability, $arguments);
    }
}