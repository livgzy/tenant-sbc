<?php

use Livewire\Component;
use App\Livewire\Concerns\UsesTenantGuard;
use App\Models\Payout;
use App\Models\Reservation;
use App\Support\TenantAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

new class extends Component
{
    use UsesTenantGuard;

    public ?int $reservationId = null;

    public function mount()
    {
        $user = Auth::guard('tenant')->user();

        // Halaman ini digerbangi middleware tenant.access:tenant-payout-access,
        // jadi seharusnya selalu ada -- query ulang di sini murni pertahanan kedua
        $reservation = TenantAccess::unsettledReservationFor($user);

        if (! $reservation) {
            return redirect()->route('home');
        }

        $this->reservationId = $reservation->id;
    }

    public function submitPayout()
    {
        $user = Auth::guard('tenant')->user();

        $reservation = Reservation::with(['tenantWallet', 'paymentMethod'])->findOrFail($this->reservationId);

        // FIX 403: $this->authorize() bawaan Livewire resolve user lewat guard
        // DEFAULT aplikasi (Auth::user() tanpa argumen), bukan guard 'tenant'
        // yang dipakai project ini -- makanya harus eksplisit Gate::forUser().
        // Trait UsesTenantGuard di atas jadi jaring pengaman tambahan untuk
        // component lain yang masih pakai $this->authorize() polos.
        Gate::forUser($user)->authorize('submitPayout', $reservation);

        Payout::create([
            'tenant_wallet_id' => $reservation->tenantWallet->id,
            'user_id' => $user->id,
            'amount' => $reservation->tenantWallet->outstanding_balance,
            'payment_method_id' => $reservation->paymentMethod->id,
            // Snapshot detail rekening SAAT payout diajukan -- tetap akurat
            // walau rekeningnya nanti diedit atau soft-deleted
            'data_payment_method' => [
                'type' => $reservation->paymentMethod->type,
                'name_payment' => $reservation->paymentMethod->name_payment,
                'account_number' => $reservation->paymentMethod->account_number,
                'account_name' => $reservation->paymentMethod->account_name,
            ],
            'status' => 'Pending',
        ]);

        $this->dispatch('toast', message: 'Pengajuan payout berhasil dikirim, menunggu diproses admin.');
    }

    public function render()
    {
        $reservation = Reservation::with(['tenantWallet.payouts', 'paymentMethod', 'tenantHistory'])
            ->find($this->reservationId);

        return $this->view([
            'reservation' => $reservation,
        ])->layout('layouts::app', ['title' => 'Student Business Corner | Ajukan Payout']);
    }
};
?>

<div>
    <div class="min-h-screen">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="mb-2">
                <h1 class="text-3xl font-bold text-gray-800">Ajukan Payout</h1>
                <p class="text-gray-500 mt-1">Cairkan pendapatan dari periode sewa yang sudah berakhir</p>
            </div>

            @if(! $reservation)
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <p class="text-gray-500">Tidak ada payout yang perlu diajukan saat ini.</p>
                </div>
            @else
                @php
                    $wallet = $reservation->tenantWallet;
                    $pendingPayout = $wallet?->payouts->whereIn('status', ['Pending', 'Diproses'])->first();
                    $history = $wallet?->payouts->sortByDesc('created_at') ?? collect();
                @endphp

                <!-- Info Periode Sewa -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-4">
                        <h3 class="text-white font-semibold">
                            {{ $reservation->tenantHistory->store_name ?? 'Tenant' }}
                            <span class="text-orange-100 font-normal">
                                (Tenant {{ $reservation->tenantHistory->tenant_code ?? '-' }})
                            </span>
                        </h3>
                        <p class="text-orange-100 text-sm mt-0.5">
                            {{ \Carbon\Carbon::parse($reservation->start_date)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($reservation->end_date)->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="p-6">
                        <p class="text-sm text-gray-500 mb-1">Saldo Tersedia</p>
                        <p class="text-3xl font-bold text-orange-600">
                            Rp {{ number_format($wallet?->outstanding_balance ?? 0, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            Total pendapatan Rp {{ number_format($wallet?->total_earned ?? 0, 0, ',', '.') }}
                            &middot; sudah dicairkan Rp {{ number_format($wallet?->total_paid_out ?? 0, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <!-- Rekening Tujuan -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Rekening Tujuan</h3>

                    @if($reservation->paymentMethod)
                        <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-gray-800">{{ $reservation->paymentMethod->name_payment }}</p>
                                <p class="text-sm text-gray-600">{{ $reservation->paymentMethod->account_number }}</p>
                                <p class="text-xs text-gray-400">a.n. {{ $reservation->paymentMethod->account_name }}</p>
                            </div>
                            <a href="{{ route('payout.payment') }}" class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                                Ubah
                            </a>
                        </div>
                    @else
                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                            <p class="text-sm text-yellow-800 mb-3">
                                Kamu belum mendaftarkan rekening tujuan payout. Daftarkan dulu sebelum bisa mengajukan payout.
                            </p>
                            <a href="{{ route('payout.payment') }}"
                               class="inline-block px-4 py-2 bg-yellow-500 text-white text-sm font-medium rounded-lg hover:bg-yellow-600 transition">
                                Daftarkan Rekening
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Status / Aksi -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    @if($pendingPayout)
                        <div class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <svg class="w-6 h-6 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-blue-900">Pengajuan sedang diproses</p>
                                <p class="text-xs text-blue-700">
                                    Rp {{ number_format($pendingPayout->amount, 0, ',', '.') }} &middot; diajukan {{ $pendingPayout->created_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>
                    @elseif(($wallet?->outstanding_balance ?? 0) <= 0)
                        <div class="text-center py-4 text-gray-500 text-sm">
                            Tidak ada saldo yang bisa dicairkan.
                        </div>
                    @elseif(! $reservation->paymentMethod)
                        <div class="text-center py-4 text-gray-400 text-sm">
                            Daftarkan rekening tujuan dulu untuk bisa mengajukan payout.
                        </div>
                    @else
                        <button wire:click="submitPayout"
                                wire:confirm="Ajukan payout sebesar Rp {{ number_format($wallet->outstanding_balance, 0, ',', '.') }} ke rekening {{ $reservation->paymentMethod->name_payment }}?"
                                wire:loading.attr="disabled"
                                class="w-full bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white font-semibold py-3 rounded-xl transition">
                            <span wire:loading.remove wire:target="submitPayout">Ajukan Payout Sekarang</span>
                            <span wire:loading wire:target="submitPayout">Mengirim...</span>
                        </button>
                    @endif
                </div>

                <!-- Riwayat Payout -->
                @if($history->isNotEmpty())
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h3 class="font-semibold text-gray-800">Riwayat Pengajuan</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @foreach($history as $payout)
                                @php
                                    $badge = match($payout->status) {
                                        'Berhasil' => 'bg-green-100 text-green-800',
                                        'Ditolak' => 'bg-red-100 text-red-800',
                                        'Diproses' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-yellow-100 text-yellow-800',
                                    };
                                @endphp
                                <div class="p-4 flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-800">Rp {{ number_format($payout->amount, 0, ',', '.') }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $payout->created_at->format('d/m/Y H:i') }} &middot;
                                            {{ $payout->data_payment_method['name_payment'] ?? '-' }}
                                        </p>
                                        @if($payout->status === 'Ditolak' && $payout->rejection_reason)
                                            <p class="text-xs text-red-500 mt-1">{{ $payout->rejection_reason }}</p>
                                        @endif
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $badge }}">{{ $payout->status }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div
        x-data="{ show: false, message: '' }"
        x-on:toast.window="
            show = false;
            $nextTick(() => {
                message = $event.detail.message;
                show = true;
                setTimeout(() => show = false, 3000);
            });
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        x-cloak
        class="fixed bottom-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg"
        x-text="message">
    </div>
</div>