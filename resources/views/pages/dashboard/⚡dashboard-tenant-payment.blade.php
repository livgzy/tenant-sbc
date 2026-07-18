<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\PaymentMethod;
use App\Models\Tenant;

new class extends Component
{
    use WithFileUploads;

    public $tenant_id;
    public $paymentMethods = [];
    
    // Form properties
    public $isEditing = false;
    public $paymentId = null;
    public $type = '';
    public $name_payment = '';
    public $account_number = null;
    public $account_name = '';
    public $qr_img = null;
    public $existing_qr = null;
    public $is_active = false;
    
    // Modal
    public $showModal = false;

    protected $rules = [
        'type' => 'required|in:bank_transfer,e_wallet,qris',
        'name_payment' => 'required|min:3|max:255',
        'account_name' => 'required|min:3|max:255',
        'account_number' => 'nullable|string|max:50',
        'qr_img' => 'nullable|image|max:2048',
        'is_active' => 'boolean',
    ];
    
    protected $messages = [
        'type.required' => 'Tipe pembayaran harus dipilih.',
        'name_payment.required' => 'Nama pembayaran harus diisi.',
        'account_name.required' => 'Nama akun harus diisi.',
        'qr_img.image' => 'File harus berupa gambar.',
        'qr_img.max' => 'Ukuran gambar maksimal 2MB.',
    ];
    
    public function mount()
    {
        $user = Auth::guard('tenant')->user();
        $reservation = $user->reservation()->latest()->first();
        
        $tenant = $reservation->tenant;
        
        if (!$tenant) {
            return redirect()->route('home');
        }
        
        $this->tenant_id = $tenant->id;
        $this->loadPaymentMethods();
    }
    
    public function loadPaymentMethods()
    {
        $this->paymentMethods = PaymentMethod::where('tenant_id', $this->tenant_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    public function editPayment($id)
    {
        $payment = PaymentMethod::findOrFail($id);
        $this->paymentId = $payment->id;
        $this->type = $payment->type;
        $this->name_payment = $payment->name_payment;
        $this->account_number = $payment->account_number;
        $this->account_name = $payment->account_name;
        $this->existing_qr = $payment->qr_img;
        $this->is_active = $payment->is_active;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function openAddPayment()
    {
        $this->reset(['isEditing', 'paymentId', 'type', 'name_payment', 'account_number', 'account_name', 'qr_img', 'existing_qr', 'is_active']);
        $this->showModal = true;
    }
    
    public function updatedType($value)
    {
        if ($value === 'qris') {
            $this->name_payment = 'QRIS';
        } else {
            if ($this->name_payment === 'QRIS') {
                $this->name_payment = '';
            }
            
            $this->qr_img = null;
            $this->existing_qr = null;
        }
    }
    
    public function savePayment()
    {
        $this->validate();
        
        if ($this->type === 'qris' && !$this->qr_img && !$this->existing_qr) {
            $this->addError('qr_img', 'QR Code wajib diupload untuk metode QRIS.');
            return;
        }
        
        if (in_array($this->type, ['bank_transfer', 'e_wallet']) && empty($this->account_number)) {
            $this->addError('account_number', 'Nomor rekening/akun wajib diisi untuk ' . ($this->type === 'bank_transfer' ? 'Bank Transfer' : 'E-Wallet') . '.');
            return;
        }
        
        $qrPath = $this->existing_qr;
        
        if ($this->qr_img) {
            if ($this->existing_qr && Storage::disk('public')->exists($this->existing_qr)) {
                Storage::disk('public')->delete($this->existing_qr);
            }
            $qrPath = $this->qr_img->store('payment_qr', 'public');
        }
        
        if ($this->isEditing) {
            $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
            $tenant = $reservation ? $reservation->tenant : null;

            if ($tenant->is_open) {
                $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
                return;
            }
            $payment = PaymentMethod::findOrFail($this->paymentId);
            $payment->update([
                'type' => $this->type,
                'name_payment' => $this->name_payment,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
                'qr_img' => $qrPath,
                'is_active' => $this->is_active,
            ]);
            // session()->flash('message', 'Metode pembayaran berhasil diperbarui!');
            $this->dispatch('toast', message: "Metode pembayaran berhasil diperbarui!");
        } else {
            PaymentMethod::create([
                'tenant_id' => $this->tenant_id,
                'type' => $this->type,
                'name_payment' => $this->name_payment,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
                'qr_img' => $qrPath,
                'is_active' => $this->is_active,
            ]);
            // session()->flash('message', 'Metode pembayaran berhasil ditambahkan!');
            $this->dispatch('toast', message: 'Metode pembayaran berhasil ditambahkan!');

        }
        
        $this->reset(['showModal', 'isEditing', 'paymentId', 'type', 'name_payment', 'account_number', 'account_name', 'qr_img', 'existing_qr', 'is_active']);
        $this->loadPaymentMethods();
    }
    
    
    public function deletePayment($deletePaymentId)
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        if ($tenant->is_open) {
            $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
            return;
        }

        $payment = PaymentMethod::findOrFail($deletePaymentId);
        
        if ($payment->qr_img && Storage::disk('public')->exists($payment->qr_img)) {
            Storage::disk('public')->delete($payment->qr_img);
        }
        
        $payment->delete();
        
        $this->loadPaymentMethods();
        // session()->flash('message', 'Metode pembayaran berhasil dihapus!');
        $this->dispatch('toast', message: 'Metode pembayaran berhasil dihapus!');

    }
    
    public function toggleStatus($id)
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        if ($tenant->is_open) {
            $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
            return;
        }

        $payment = PaymentMethod::findOrFail($id);
        $payment->update(['is_active' => !$payment->is_active]);
        $this->loadPaymentMethods();
        // session()->flash('message', 'Status pembayaran berhasil diubah!');
        $this->dispatch('toast', message: 'Status pembayaran berhasil diubah!');

    }

    public function render()
    {
        return $this->view()->layout('layouts::app', ['title' => 'Student Business Corner | Tenant Payment']);
    }
};
?>

<div>
    <div class="min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Metode Pembayaran (Non Tunai)</h1>
                    <p class="text-gray-500 mt-1">Kelola metode pembayaran non tunai yang tersedia untuk pelanggan</p>
                </div>
                <button wire:click='openAddPayment'
                        class="px-4 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Tambah Metode Pembayaran
                </button>
            </div>

            <!-- Payment Methods Grid -->
            @if(count($paymentMethods) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($paymentMethods as $payment)
                        <div class="bg-white rounded-xl border border-gray-300 shadow-sm overflow-hidden hover:shadow-md transition">
                            <!-- Header Card -->
                            <div class="px-4 py-3 border-b flex justify-between items-center
                                {{ $payment->is_active ? 'bg-gradient-to-r from-green-50 to-green-100' : 'bg-gray-50' }}">
                                <div class="flex items-center gap-2">
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ $payment->name_payment }}</span>
                                        <span class="text-xs text-gray-500 ml-1">({{ $payment->type_label ?? $payment->type}})</span>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $payment->is_active ? 'bg-green-200 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                    {{ $payment->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            <!-- Content -->
                            <div class="p-4 space-y-3">
                                @if($payment->account_number)
                                    <div>
                                        <p class="text-xs text-gray-500">
                                            {{ $payment->type === 'bank_transfer' ? 'Nomor Rekening' : 'Nomor Akun' }}
                                        </p>
                                        <p class="font-medium text-gray-800">{{ $payment->account_number }}</p>
                                    </div>
                                @endif

                                @if($payment->account_name)
                                    <div>
                                        <p class="text-xs text-gray-500">Nama Pemilik</p>
                                        <p class="font-medium text-gray-800">{{  $payment->account_name }}</p>
                                    </div>
                                @endif

                                @if($payment->type === 'qris')
                                <div x-data="{ openPreview: false }">
                                    <p class="text-xs text-gray-500 mb-2">QR Code</p>
                                    
                                    <div class="relative group w-24 h-24">
                                        <img src="{{ Storage::url($payment->qr_img) }}" 
                                            alt="QR Code {{ $payment->name_payment }}"
                                            @click="openPreview = true"
                                            class="w-24 h-24 object-contain rounded-lg border cursor-pointer hover:brightness-90 transition shadow-sm">
                                        
                                        <div @click="openPreview = true" class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center cursor-pointer">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                                            </svg>
                                        </div>
                                    </div>

                                    <div x-show="openPreview" 
                                        x-cloak
                                        @keydown.escape.window="openPreview = false"
                                        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-transition:leave="transition ease-in duration-200"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0">
                                        
                                        <div class="absolute inset-0" @click="openPreview = false"></div>

                                        <div class="relative max-w-sm md:max-w-md w-full bg-white rounded-2xl p-4 shadow-2xl z-10"
                                            x-transition:enter="transition ease-out duration-300 transform"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-200 transform"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95">
                                            
                                            <div class="flex justify-between items-center mb-3 pb-2 border-b">
                                                <h4 class="text-sm font-semibold text-gray-800">QR Code {{ $payment->name_payment }}</h4>
                                                <button @click="openPreview = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="bg-gray-50 rounded-xl p-2 flex items-center justify-center">
                                                <img src="{{ Storage::url($payment->qr_img) }}" 
                                                    alt="QR Code {{ $payment->name_payment }}"
                                                    class="max-h-[70vh] w-full object-contain rounded-lg">
                                            </div>
                                            
                                            <p class="text-center text-xs text-gray-400 mt-3">Tekan ESC atau klik di luar gambar untuk menutup</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                                <div class="pt-3 border-t">
                                    <p class="text-xs text-gray-500 mb-3">
                                        Dibuat: {{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y') }}
                                    </p>
                                    
                                    <!-- Tombol Aksi -->
                                    <div class="grid grid-cols-1 gap-2">
                                        <button wire:click="toggleStatus({{ $payment->id }})"
                                                class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition 
                                                {{$payment->is_active 
                                                    ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' 
                                                    : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                            </svg>
                                            {{$payment->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        
                                        <div class="grid grid-cols-2 gap-2">
                                            <button wire:click="editPayment({{ $payment->id }})"
                                                    class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition bg-blue-100 text-blue-700 hover:bg-blue-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </button>
                                            
                                            <button wire:click="deletePayment({{ $payment->id }})" wire:confirm='Apakah Anda yakin ingin menghapus Metode Pembayaran {{ $payment->name_payment }}'
                                                    class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition bg-red-100 text-red-700 hover:bg-red-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Empty State -->
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="w-24 h-24 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Belum Ada Metode Pembayaran</h3>
                    <p class="text-gray-500 mb-4">Tambahkan metode pembayaran untuk memudahkan pelanggan</p>
                    <button wire:click="openAddPayment" 
                            class="px-4 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Metode Pembayaran
                    </button>
                </div>
            @endif

            <!-- Modal Tambah/Edit Payment -->
                <div wire:show='showModal' wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="$set('showModal', false)"></div>
                        
                        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-auto">
                            <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                        <flux:icon.credit-card class="size-6 text-orange-500"/>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        {{ $isEditing ? 'Edit Metode Pembayaran' : 'Tambah Metode Pembayaran' }}
                                    </h3>
                                </div>
                                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>

                            <form wire:submit.prevent="savePayment" class="p-6 space-y-4">
                                <!-- Type -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                                        Tipe Pembayaran <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model.live="type" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        <option value="">Pilih Tipe</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="e_wallet">E-Wallet</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                    @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Nama Pembayaran -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                                        Nama Pembayaran <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           wire:model="name_payment" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent {{ $type === 'qris' ? 'bg-gray-100 cursor-not-allowed' : '' }}" 
                                           {{ $type === 'qris' ? 'readonly' : '' }}
                                           placeholder="Contoh: BCA, Mandiri, DANA, OVO, QRIS">
                                    @error('name_payment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Account Name -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                                        Nama Pemilik Akun <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" wire:model="account_name" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="Nama pemilik rekening/e-wallet">
                                    @error('account_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                @if($type !== 'qris')
                                <!-- Account Number -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                                        {{ $type === 'bank_transfer' ? 'Nomor Rekening' : 'Nomor Akun' }}
                                        @if(in_array($type, ['bank_transfer', 'e_wallet']))
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <input type="text" wire:model="account_number" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="{{ $type === 'bank_transfer' ? 'Contoh: 1234567890' :  '081234567890' }}">
                                    @error('account_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                @endif

                                <!-- QR Code (hanya untuk QRIS) -->
                                @if($type === 'qris')
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                                            QRIS <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-orange-500 transition">
                                            <div class="space-y-1 text-center">
                                                @if($qr_img)
                                                    <img src="{{ $qr_img->temporaryUrl() }}" class="mx-auto h-32 w-auto rounded-lg shadow">
                                                @elseif($existing_qr)
                                                    <img src="{{ Storage::url($existing_qr) }}" class="mx-auto h-32 w-auto rounded-lg shadow">
                                                @else
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                @endif
                                                <div class="flex justify-center text-sm text-gray-500">
                                                    <label for="qr_img" class="cursor-pointer bg-white rounded-md font-medium text-orange-600 hover:text-orange-500">
                                                        Upload QR Code
                                                        <input id="qr_img" type="file" wire:model="qr_img" class="sr-only" accept="image/*">
                                                    </label>
                                                </div>
                                                <p class="text-xs text-gray-500">PNG, JPG, JPEG up to 2MB</p>
                                            </div>
                                        </div>
                                        @error('qr_img') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                <!-- Active Status -->
                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="is_active" class="w-4 h-4 text-orange-500 rounded focus:ring-orange-500">
                                        <span class="text-sm text-gray-700">Metode pembayaran aktif</span>
                                    </label>
                                </div>

                                <div class="flex justify-end gap-3 pt-4 border-t">
                                    <button type="button" wire:click="$set('showModal', false)" 
                                            class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                                        Batal
                                    </button>
                                    <button type="submit" 
                                            wire:loading.attr="disabled"
                                            class="px-4 py-2 text-sm text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition flex items-center gap-2">
                                        {{ $isEditing ? 'Update' : 'Simpan' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
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
            <!-- Flash Message -->
            @if(session()->has('message'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-y-0"
                     x-transition:leave-end="opacity-0 transform translate-y-2"
                     class="fixed bottom-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg">
                    {{ session('message') }}
                </div>
            @endif
        </div>
    </div>
</div>