<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Url;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant;
use App\Models\Order;
use App\Models\Product;
use App\Models\PickupSlot;
use App\Models\PaymentMethod;
use App\Events\StoreStatusChanged;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithFileUploads;

    // Profile properties
    public $tenant_id;
    public $store_name;
    public $tenant_code;
    public $description;
    public $phone;
    public $is_open;
    public $tenant_img;
    public $existing_image;
    
    // User properties
    public $user_name;
    public $user_email;
    public $user_phone;
    public $user_nim;
    public $user_prodi;
    public $user_semester;
    
    // Password change
    public $current_password;
    public $new_password;
    public $new_password_confirmation;
    
    // Pickup slots
    public $pickupSlots = [];
    public $daysOfWeek = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    // Modal
    public $showEditProfile = false;
    public $showAddSlot = false;
    public $editingSlotId = null;
    public $showValidationErrors = false;

    public $tenantValidationErrors = [];

    
    // Slot form
    public $slotDay = '';
    public $slotStart = '';
    public $slotEnd = '';
    
    // Active tab
    #[Url(as: 'tab', except: 'profile')]
    public $activeTab = 'profile';
    
    protected $rules = [
        'store_name' => 'required|min:3|max:255',
        'description' => 'nullable|string|max:1000',
        'phone' => 'required|string|max:15',
        'is_open' => 'boolean',
        'tenant_img' => 'nullable|image|max:2048',
    ];
    
    protected $rulesPickupSlot = [
        'slotDay' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
        'slotStart' => 'required|date_format:H:i',
        'slotEnd' => 'required|date_format:H:i|after:slotStart',
    ];
    
    protected $messages = [
        'store_name.required' => 'Nama tenant harus diisi.',
        'tenant_img.image' => 'File harus berupa gambar.',
        'tenant_img.max' => 'Ukuran gambar maksimal 2MB.',
        'slotStart.date_format' => 'Format waktu tidak valid.',
        'slotEnd.date_format' => 'Format waktu tidak valid.',
        'slotEnd.after' => 'Waktu selesai harus setelah waktu mulai.',
    ];
    
    public function mount()
    {
        if (!$this->reservation?->tenant) {
            return redirect()->route('home');
        }
        
        $this->tenant_id = $this->reservation?->tenant->id;
        $this->store_name = $this->reservation?->tenant->store_name;
        $this->tenant_code = $this->reservation?->tenant->tenant_code;
        $this->description = $this->reservation?->tenant->description;
        $this->phone = $this->reservation?->tenant->phone;
        $this->is_open = $this->reservation?->tenant->is_open;
        $this->existing_image = $this->reservation?->tenant->tenant_img;
        
        $this->user_name = $this->reservation?->user->name;
        $this->user_email = $this->reservation?->user->email;
        $this->user_phone = $this->reservation?->user->phone;
        $this->user_nim = $this->reservation?->user->nim;
        $this->user_prodi = $this->reservation?->user->prodi;
        $this->user_semester = $this->reservation?->user->semester;
        
        $this->loadPickupSlots();
    }

    #[Computed]
    public function reservation()
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        return $reservation;
    }

    #[Computed]
    public function totalMenus()
    {
        return Product::where('tenant_id', $this->tenant_id)->count();
    }

    #[Computed]
    public function totalOrders()
    {
        return Order::where('reservation_id', $this->reservation->id)->where('status', '!=', 'Dibatalkan')->count();
    }
    
    public function loadPickupSlots()
    {
        $this->pickupSlots = PickupSlot::where('tenant_id', $this->tenant_id)
            ->orderBy('dayPickup')
            ->orderBy('start_time')
            ->get();
    }
    
    public function updateProfile()
    {
        $this->validate();

        $user = Auth::guard('tenant')->user();
        $reservation = $user->reservation()->latest()->first();

        if ($reservation->tenant->id !== $this->tenant_id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        $tenant = Tenant::findOrFail($this->tenant_id);
        
        $imagePath = $this->existing_image;
        if ($this->tenant_img) {
            if ($this->existing_image && Storage::disk('public')->exists($this->existing_image)) {
                Storage::disk('public')->delete($this->existing_image);
            }
            $imagePath = $this->tenant_img->store('tenants', 'public');
        }

        if ($tenant->is_open) {
            $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
            return;
        }
        
        $tenant->update([
            'store_name' => $this->store_name,
            'slug' => Str::slug($this->store_name),
            'description' => $this->description,
            'phone' => $this->phone,
            'is_open' => $this->is_open,
            'tenant_img' => $imagePath,
        ]);
        
        $this->existing_image = $imagePath;
        $this->showEditProfile = false;
        
        // session()->flash('message', 'Profil tenant berhasil diperbarui!');
        $this->dispatch('toast', message: "Profil tenant berhasil diperbarui");
    }
    
    
    public function openAddSlot()
    {
        $this->reset(['slotDay', 'slotStart', 'slotEnd', 'editingSlotId']);
        $this->showAddSlot = true;
    }
    
    public function editSlot($slotId)
    {
        $slot = PickupSlot::findOrFail($slotId);
        $this->editingSlotId = $slotId;
        $this->slotDay = $slot->dayPickup;
        $this->slotStart = \Carbon\Carbon::parse($slot->start_time)->format('H:i');
        $this->slotEnd = \Carbon\Carbon::parse($slot->end_time)->format('H:i');
        $this->showAddSlot = true;
    }
    
    public function saveSlot()
    {
        $this->validate($this->rulesPickupSlot);

        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        $duplicateCheck = PickupSlot::where('tenant_id', $this->tenant_id)
            ->where('dayPickup', $this->slotDay)
            ->when($this->editingSlotId, function ($query) {
                return $query->where('id', '!=', $this->editingSlotId);
            })
            ->exists();

        if ($duplicateCheck) {
            $this->addError('slotDay', "Hari " . ucfirst($this->slotDay) . " sudah terdaftar di sistem Anda.");
            return;
        }

        if ($this->editingSlotId) {
            if ($tenant->is_open) {
                $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
                return;
            }
            
            $slot = PickupSlot::findOrFail($this->editingSlotId);
            $slot->update([
                'dayPickup' => $this->slotDay,
                'start_time' => $this->slotStart,
                'end_time' => $this->slotEnd,
            ]);
            // session()->flash('message', 'Slot pickup berhasil diperbarui!');
            $this->dispatch('toast', message: "Slot pickup berhasil diperbarui!");
        } else {
            PickupSlot::create([
                'tenant_id' => $this->tenant_id,
                'dayPickup' => $this->slotDay,
                'start_time' => $this->slotStart,
                'end_time' => $this->slotEnd,
            ]);
            $this->dispatch('toast', message: "Slot pickup berhasil ditambahkan");
        }
        
        $this->reset(['slotDay', 'slotStart', 'slotEnd', 'editingSlotId']);
        $this->showAddSlot = false;
        $this->loadPickupSlots();
    }
        
    public function deleteSlot($slotId)
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        if ($tenant->is_open) {
            $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
            return;
        }
        
        $slot = PickupSlot::findOrFail($slotId);
        $slot->delete();
        $this->loadPickupSlots();
        // session()->flash('message', 'Slot pickup berhasil dihapus!');
        $this->dispatch('toast', message: "Slot pickup berhasil dihapus");
    }
    
    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    protected function validateTenantCanOpen(Tenant $tenant): array
    {
        $errors = [];

        // 1. Payment Method — minimal 1 metode pembayaran aktif
        // $hasPaymentMethod = PaymentMethod::where('tenant_id', $tenant->id)
        //     ->where('is_active', true)
        //     ->exists();

        // if (!$hasPaymentMethod) {
        //     $errors[] = 'Tenant harus memiliki minimal 1 metode pembayaran aktif.';
        // }

        // 2. Pickup Slot — wajib HANYA jika ada produk pre-order
        $hasPreorderProduct = Product::where('tenant_id', $tenant->id)
            ->where('is_preorder', true)
            ->exists();

        if ($hasPreorderProduct) {
            $hasPickupSlot = PickupSlot::where('tenant_id', $tenant->id)->exists();

            if (!$hasPickupSlot) {
                $errors[] = 'Tenant memiliki produk pre-order, sehingga wajib memiliki minimal 1 slot waktu buka.';
            }
        }

        // 3. Product — semua produk wajib punya nama, harga (>0), dan gambar
        $incompleteProductsCount = Product::where('tenant_id', $tenant->id)
            ->where(function ($query) {
                $query->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhereNull('price')
                    ->orWhere('price', '<=', 0)
                    ->orWhereNull('product_img')
                    ->orWhere('product_img', '');
            })
            ->count();

        if ($incompleteProductsCount > 0) {
            $errors[] = "Terdapat {$incompleteProductsCount} produk yang belum lengkap (nama/harga/gambar).";
        }

        // 4. Tenant — store_name & phone wajib diisi
        if (blank($tenant->store_name) || blank($tenant->phone)) {
            $errors[] = 'Nama tenant dan nomor telepon tenant harus diisi.';

        }
        return $errors;
    }

    public function toggleStatusTenant()
    {
        $tenant = Tenant::findOrFail($this->tenant_id);

        // Validasi hanya berlaku saat tenant hendak DIBUKA
        if (!$tenant->is_open) {
            $errors = $this->validateTenantCanOpen($tenant);

            if (!empty($errors)) {
                $this->tenantValidationErrors = $errors;
                $this->showValidationErrors = true;
                return;
            }
        }

        
        $tenant->update(['is_open' => !$tenant->is_open]);
        $this->is_open = $tenant->is_open;
        broadcast(new StoreStatusChanged($tenant));

        $this->dispatch('toast', message: $tenant->is_open
            ? 'Tenant berhasil dibuka.'
            : 'Tenant berhasil ditutup.'
        );
    }

    public function render()
    {
        return $this->view()->layout('layouts::app', ['title' => 'Student Business Corner | Tenant Profile']);
    }
};
?>

<div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Profile Tenant</h1>
            <p class="text-gray-500 mt-1">Kelola informasi tenant dan waktu buka Anda</p>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-gray-50 rounded-xl overflow-hidden mb-6">
            <div class="border-b border-gray-400">
                <nav class="flex -mb-px">
                    <button wire:click="switchTab('profile')" 
                        class="px-6 py-3 text-sm font-medium transition-all duration-200
                        {{ $activeTab === 'profile' 
                            ? 'text-orange-600 border-b-2 border-orange-600 bg-orange-50' 
                            : 'text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Profile Tenant
                        </div>
                    </button>
                    <button wire:click="switchTab('open')"
                        class="px-6 py-3 text-sm font-medium transition-all duration-200
                        {{ $activeTab === 'open' 
                            ? 'text-orange-600 border-b-2 border-orange-600 bg-orange-50' 
                            : 'text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Waktu Buka
                        </div>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Tab Content: Profile -->
        @if($activeTab === 'profile')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile Card -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Informasi Toko -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white border border-gray-300 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gradient-to-r from-orange-50 to-amber-50">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-800">Informasi Tenant</h3>
                                <button x-on:click="$wire.showEditProfile = true"class="px-3 py-1.5 text-sm bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                                    Edit Profile
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row gap-6">
                                <div class="flex-shrink-0 flex flex-col items-center">
                                    <div class="w-32 h-32 rounded-xl overflow-hidden bg-gray-100 shadow-sm border border-gray-200">
                                        @if($existing_image)
                                            <img src="{{ Storage::disk('public')->url($existing_image) }}" 
                                                 alt="{{ $store_name }}"
                                                 class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center">
                                                <flux:icon.building-storefront class="size-20 text-gray-400"/>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-4 flex flex-col items-center space-y-1.5">
                                        <label class="relative inline-flex items-center cursor-pointer select-none">
                                            <input type="checkbox"
                                                x-bind:checked="$wire.is_open"
                                                x-on:click.prevent="
                                                    if (! confirm('Apakah Tenant Ingin Dibuka?')) { return; }
                                                    await $wire.toggleStatusTenant();
                                                    $el.checked = $wire.is_open;
                                                "
                                                class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-orange-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                        </label>
                                        <span class="text-xs font-semibold {{ $is_open ? 'text-green-700' : 'text-red-600' }}">
                                            {{ $is_open ? 'Tenant Buka' : 'Tenant Tutup' }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex-1 space-y-2">
                                    <div>
                                        <p class="text-xs text-gray-500">Nama Tenant</p>
                                        <p class="font-semibold text-gray-800">{{ $store_name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Kode Tenant</p>
                                        <p class="font-medium"><span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">Tenant {{ $tenant_code ?? '' }}</span></p>
                                    </div>
                                    @if($description)
                                    <div>
                                        <p class="text-xs text-gray-500">Deskripsi</p>
                                        <p class="text-sm text-gray-600">{{ $description }}</p>
                                    </div>
                                    @endif
                                    @if($phone)
                                    <div>
                                        <p class="text-xs text-gray-500">No. Telepon</p>
                                        <p class="text-sm text-gray-600">{{ $phone }}</p>
                                    </div>
                                    @endif
                                    <div>
                                        <p class="text-xs text-gray-500">Tanggal Bergabung</p>
                                        <p class="text-sm text-gray-600">{{ $created_at ?? now()->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                  
                </div>

                <!-- Informasi Pemilik -->
                <div class="bg-white  border border-gray-300 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b bg-orange-50">
                        <h3 class="text-lg font-semibold text-gray-800">Informasi Pemilik</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Nama Lengkap</p>
                                <p class="font-medium text-gray-800">{{ $user_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">NIM</p>
                                <p class="font-medium text-gray-800">{{ $user_nim }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Program Studi</p>
                                <p class="font-medium text-gray-800">{{ $user_prodi }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Semester</p>
                                <p class="font-medium text-gray-800">{{ $user_semester }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Email</p>
                                <p class="font-medium text-gray-800">{{ $user_email }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">No. Telepon</p>
                                <p class="font-medium text-gray-800">{{ $user_phone ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="space-y-4">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gradient-to-r from-orange-50 to-amber-50">
                        <h3 class="text-lg font-semibold text-gray-800">Statistik</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Menu</span>
                            <span class="font-bold text-gray-800">{{ $this->totalMenus ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pesanan</span>
                            <span class="font-bold text-gray-800">{{ $this->totalOrders ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status</span>
                            <span class="px-2 py-1 text-xs rounded-full 
                                {{ $is_open ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $is_open ? 'Buka' : 'Tutup' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Tab Content: Pickup Slots -->
        @if($activeTab === 'open')
        <div class="bg-white border border-gray-300 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-orange-50 to-amber-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Waktu Pickup (Untuk Menu Pre Order Only)</h3>
                <button wire:click="openAddSlot" 
                        class="px-3 py-1.5 text-sm bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Tambah Slot
                </button>
            </div>
            <div class="p-6">
                @if($pickupSlots->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hari</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Mulai</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Selesai</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($pickupSlots as $slot)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-700">
                                            {{ $daysOfWeek[$slot->dayPickup] ?? $slot->dayPickup }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm space-x-2">
                                            <button wire:click="editSlot({{ $slot->id }})" 
                                                    class="text-indigo-600 hover:text-indigo-900">
                                                Edit
                                            </button>
                                            <button wire:click="deleteSlot({{ $slot->id }})" 
                                                    wire:confirm="Apakah Anda yakin ingin menghapus slot ini?"
                                                    class="text-red-600 hover:text-red-900">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-500">Belum ada slot pickup yang ditambahkan</p>
                        <p class="text-sm text-gray-400 mt-1">Tambahkan slot pickup untuk memudahkan pelanggan</p>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Modal Edit Profile -->
       
            <div wire:show="showEditProfile" wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="$set('showEditProfile', false)"></div>
                    
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-auto max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Edit Profile Tenant</h3>
                            <button wire:click="$set('showEditProfile', false)" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <form wire:submit.prevent="updateProfile" class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Nama tenant <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="store_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
                                @error('store_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                                <textarea wire:model="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500"></textarea>
                                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">No. Telepon <span class="text-red-500">*</span></label>
                                <input type="tel" wire:model="phone" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
                                @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Foto Tenant <span class="text-red-500">*</span></label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl">
                                    <div class="space-y-1 text-center">
                                        @if($tenant_img)
                                            <img src="{{ $tenant_img->temporaryUrl() }}" class="mx-auto h-32 w-auto rounded-lg">
                                        @elseif($existing_image)
                                            <img src="{{ Storage::disk('public')->url($existing_image) }}" class="mx-auto h-32 w-auto rounded-lg">
                                        @else
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        @endif
                                        <div class="flex justify-center text-sm text-gray-500">
                                            <label for="tenant_img" class="cursor-pointer bg-white rounded-md font-medium text-orange-600 hover:text-orange-500">
                                                Upload foto
                                                <input id="tenant_img" type="file" wire:model="tenant_img" class="sr-only" accept="image/*">
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, JPEG up to 2MB</p>
                                    </div>
                                </div>
                                @error('tenant_img') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t">
                                <button type="button" wire:click="$set('showEditProfile', false)" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                                <button type="submit" class="px-4 py-2 text-sm text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div wire:show='showAddSlot' wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="$set('showAddSlot', false)"></div>
                    
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-auto">
                        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $editingSlotId ? 'Edit Slot Pickup' : 'Tambah Slot Pickup' }}
                            </h3>
                            <button wire:click="$set('showAddSlot', false)" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <form wire:submit.prevent="saveSlot" class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Hari</label>
                                <select wire:model="slotDay" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
                                    <option value="">Pilih Hari</option>
                                    @foreach($daysOfWeek as $day)
                                        <option value="{{ $day }}">{{ $day }}</option>
                                    @endforeach
                                </select>
                                @error('slotDay') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-time-input model="slotStart" label="Jam Mulai" />
                                    @error('slotStart') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-time-input model="slotEnd" label="Jam Selesai" />
                                    @error('slotEnd') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t">
                                <button type="button" x-on:click="$wire.showAddSlot = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                                <button type="submit" class="px-4 py-2 text-sm text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition">
                                    {{ $editingSlotId ? 'Update' : 'Simpan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
        <!-- Modal Validasi Error -->
        <div wire:show="showValidationErrors" wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="$set('showValidationErrors', false)"></div>

                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-auto">
                    <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Tenant Belum Bisa Dibuka</h3>
                        </div>
                        <button wire:click="$set('showValidationErrors', false)" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6">
                        <p class="text-sm text-gray-500 mb-3">Lengkapi hal berikut sebelum membuka tenant:</p>
                        <ul class="space-y-2">
                            @foreach($tenantValidationErrors as $error)
                                <li class="flex items-start gap-2 text-sm text-gray-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                                    <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span>{{ $error }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="px-6 pb-6 flex justify-end">
                        <button wire:click="$set('showValidationErrors', false)" class="px-4 py-2 text-sm text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition">
                            Mengerti
                        </button>
                    </div>
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
                 class="fixed bottom-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg">
                {{ session('message') }}
            </div>
        @endif
    </div>
</div>