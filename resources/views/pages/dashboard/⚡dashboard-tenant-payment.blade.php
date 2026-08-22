<?php

use Livewire\Component;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $reservation_id;
    public $store_name;
    public $tenant_code;

    // Form properties — 1 record per reservation, jadi tidak perlu $paymentMethod (array)
    public $paymentId = null;
    public $type = '';
    public $name_payment = '';
    public $account_number = null;
    public $account_name = '';

    public $showModal = false;

    protected $rules = [
        'type' => 'required|in:bank_transfer,e_wallet',
        'name_payment' => 'required|min:3|max:255',
        'account_name' => 'required|min:3|max:255',
        'account_number' => 'required|string|max:50',
    ];

    protected $messages = [
        'type.required' => 'Tipe rekening harus dipilih.',
        'name_payment.required' => 'Nama bank/e-wallet harus diisi.',
        'account_name.required' => 'Nama pemilik rekening harus diisi.',
        'account_number.required' => 'Nomor rekening/akun wajib diisi.',
    ];

    public function mount()
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();

        if (!$reservation) {
            return redirect()->route('home');
        }

        $this->reservation_id = $reservation->id;

        // Tenant sudah non-aktif di titik ini (halaman ini digerbangi middleware
        // tenant-payout-access, yang cuma nyala SETELAH tenant berhenti). Relasi
        // ->tenant() sudah null karena di-reset saat stopTenantReservation, jadi
        // info toko diambil dari snapshot tenantHistory, bukan tenant aktif.
        $this->store_name  = $reservation->tenantHistory->store_name ?? '-';
        $this->tenant_code = $reservation->tenantHistory->tenant_code ?? '-';

        $this->loadPaymentMethod();
    }

    public function loadPaymentMethod()
    {
        $payment = PaymentMethod::where('reservation_id', $this->reservation_id)->first();

        if ($payment) {
            $this->paymentId = $payment->id;
            $this->type = $payment->type;
            $this->name_payment = $payment->name_payment;
            $this->account_number = $payment->account_number;
            $this->account_name = $payment->account_name;
        }
    }

    public function openForm()
    {
        $this->showModal = true;
    }

    public function closeForm()
    {
        $this->showModal = false;
        $this->resetValidation();
        $this->loadPaymentMethod(); // buang perubahan yang belum disimpan
    }

    /**
     * updateOrCreate karena reservation_id sekarang unique — 1 reservasi hanya
     * boleh punya 1 rekening payout, jadi "tambah" dan "edit" sama-sama lewat sini.
     */
    public function savePayment()
    {
        $this->validate();

        PaymentMethod::updateOrCreate(
            ['reservation_id' => $this->reservation_id],
            [
                'type' => $this->type,
                'name_payment' => $this->name_payment,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
            ]
        );

        $this->showModal = false;
        $this->loadPaymentMethod();

        $this->dispatch('toast', message: 'Rekening payout berhasil disimpan!');
    }

    public function deletePayment()
    {
        PaymentMethod::where('reservation_id', $this->reservation_id)->forceDelete();

        $this->reset(['paymentId', 'type', 'name_payment', 'account_number', 'account_name']);

        $this->dispatch('toast', message: 'Rekening payout berhasil dihapus!');
    }

    public function render()
    {
        return $this->view()->layout('layouts::app', ['title' => 'Student Business Corner | Rekening Payout']);
    }
};
?>

<div>
    <div class="min-h-screen">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Rekening Payout</h1>
                <p class="text-gray-500 mt-1">
                    Daftarkan 1 rekening tujuan pencairan hasil penjualan
                    <span class="font-medium">{{ $store_name }}</span> ({{ $tenant_code }}).
                </p>
            </div>

            @if($paymentId)
                <div class="bg-white rounded-xl border border-gray-300 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center">
                        <div>
                            <span class="font-semibold text-gray-800">{{ $name_payment }}</span>
                            <span class="text-xs text-gray-500 ml-1">
                                ({{ $type === 'bank_transfer' ? 'Bank Transfer' : 'E-Wallet' }})
                            </span>
                        </div>
                    </div>

                    <div class="p-4 space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">
                                {{ $type === 'bank_transfer' ? 'Nomor Rekening' : 'Nomor Akun' }}
                            </p>
                            <p class="font-medium text-gray-800">{{ $account_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Nama Pemilik</p>
                            <p class="font-medium text-gray-800">{{ $account_name }}</p>
                        </div>

                        <div class="pt-3 border-t grid grid-cols-2 gap-2">
                            <button wire:click="openForm"
                                    class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition bg-blue-100 text-blue-700 hover:bg-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit
                            </button>
                            <button wire:click="deletePayment"
                                    wire:confirm="Apakah Anda yakin ingin menghapus rekening payout ini?"
                                    class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition bg-red-100 text-red-700 hover:bg-red-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="w-24 h-24 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Belum Ada Rekening Payout</h3>
                    <p class="text-gray-500 mb-4">Daftarkan rekening tujuan untuk pencairan hasil penjualan kamu</p>
                    <button wire:click="openForm"
                            class="px-4 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Daftarkan Rekening
                    </button>
                </div>
            @endif

            <!-- Modal Tambah/Edit Rekening Payout -->
            <div wire:show="showModal" wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="closeForm"></div>

                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-auto">
                        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                    <flux:icon.credit-card class="size-6 text-orange-500"/>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    {{ $paymentId ? 'Edit Rekening Payout' : 'Daftarkan Rekening Payout' }}
                                </h3>
                            </div>
                            <button wire:click="closeForm" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <form wire:submit.prevent="savePayment" class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Tipe Rekening <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="type"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option value="">Pilih Tipe</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="e_wallet">E-Wallet</option>
                                </select>
                                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Nama Bank/E-Wallet <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="name_payment"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Contoh: BCA, Mandiri, DANA, OVO">
                                @error('name_payment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Nama Pemilik Akun <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="account_name"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Nama pemilik rekening/e-wallet">
                                @error('account_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    {{ $type === 'bank_transfer' ? 'Nomor Rekening' : 'Nomor Akun' }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="account_number"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="{{ $type === 'bank_transfer' ? 'Contoh: 1234567890' : '081234567890' }}">
                                @error('account_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t">
                                <button type="button" wire:click="closeForm"
                                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                                    Batal
                                </button>
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 text-sm text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition flex items-center gap-2">
                                    {{ $paymentId ? 'Update' : 'Simpan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Toast -->
            <div
                x-data="{ show: false, message: '', type: 'success' }"
                x-on:error.window="
                    show = false;
                    $nextTick(() => {
                        message = $event.detail.message;
                        type = $event.detail.type ?? 'success';
                        show = true;
                        setTimeout(() => show = false, 3000);
                    });
                "
                x-on:toast.window="
                    show = false;
                    $nextTick(() => {
                        message = $event.detail.message;
                        type = 'success';
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
                class="fixed bottom-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white flex items-center gap-2"
                :class="type === 'error' ? 'bg-red-500' : 'bg-green-500'">
                <svg x-show="type === 'error'" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span x-text="message"></span>
            </div>
        </div>
    </div>
</div>