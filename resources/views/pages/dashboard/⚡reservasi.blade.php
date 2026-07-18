<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Categorie;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
// use App\Events\NewReservationCreated;
use App\Models\ApprovalTenant;


new class extends Component
{
    use WithFileUploads;
    
    public $currentStep = 1;
    public $tenant_code = '';
    public $start_date = '';
    public $end_date = '';
    
    public $store_name = '';
    public $description = '';
    public $tenant_img = null;
    
    public $products = [];
    public $temp_product_name = '';
    public $temp_product_category = '';
    public $temp_product_description = '';
    public $temp_product_price = '';
    public $temp_product_is_preorder = '';
    public $temp_product_img = null;
    
    // Validation flags
    public $isImageUploaded = false;
    public $imagePreviewUrl = null;
    
    protected $rules = [
        'tenant_code' => 'required|in:A,B,C',
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after_or_equal:start_date',
        'store_name' => 'required|max:255',
        'description' => 'nullable|string|max:1000',
        'tenant_img' => 'nullable|image|max:2048',
        'products.*.name' => 'required|min:3|max:255',
        'products.*.category' => 'required|string|max:100',
        'products.*.description' => 'nullable|string|max:500',
        'products.*.price' => 'required|numeric|min:0',
        'products.*.is_preorder' => 'nullable',
    ];
    
    protected $messages = [
        'tenant_code.required' => 'Silakan pilih tenant.',
        'start_date.required' => 'Tanggal mulai harus diisi.',
        'start_date.after_or_equal' => 'Tanggal mulai tidak boleh kurang dari hari ini.',
        'end_date.required' => 'Tanggal selesai harus diisi.',
        'end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
        'store_name.required' => 'Nama tenant harus diisi.',
        'products.*.name.required' => 'Nama menu harus diisi.',
        'products.*.price.category' => 'Kategori menu harus diisi.',
        'products.*.price.required' => 'Harga menu harus diisi.',
        'products.*.price.numeric' => 'Harga harus berupa angka.',
    ];
    
    public function mount()
    {
        $this->products = [];
        $this->isImageUploaded = false;
        
        // Buat folder preview jika belum ada
        if (!Storage::disk('public')->exists('preview')) {
            Storage::disk('public')->makeDirectory('preview');
        }
    }
    
    public function updatedTenantImg()
    {
        $this->validate([
            'tenant_img' => 'image|max:2048',
        ]);
    }
    
    public function updatedTempProductImg()
    {
        $this->validate([
            'temp_product_img' => 'image|max:2048',
        ]);
        
        if ($this->temp_product_img) {
            $this->isImageUploaded = true;
            $this->imagePreviewUrl = $this->temp_product_img->temporaryUrl();
        } else {
            $this->isImageUploaded = false;
            $this->imagePreviewUrl = null;
        }
    }
    
    public function addProduct()
    {
        // Validate all fields including image (required)
        $this->validate([
            'temp_product_name' => 'required|max:255',
            'temp_product_price' => 'required|numeric|min:0',
            'temp_product_is_preorder' => 'nullable',
            'temp_product_description' => 'nullable|string|max:500',
            'temp_product_category' => 'nullable|string|max:100',
            'temp_product_img' => 'required|image|max:2048',
        ]);
        
        // Generate unique filename
        $filename = Str::uuid() . '.' . $this->temp_product_img->extension();
        $previewPath = 'preview/' . $filename;
        
        // Store image to preview folder
        $storedPath = $this->temp_product_img->storeAs('preview', $filename, 'public');
        
        // Store product data with preview path
        $this->products[] = [
            'name' => $this->temp_product_name,
            'category' => $this->temp_product_category,
            'description' => $this->temp_product_description,
            'price' => $this->temp_product_price,
            'is_preorder' => $this->temp_product_is_preorder,
            'preview_path' => $previewPath, 
            'temp_image_url' => Storage::url($previewPath), 
        ];
        
        $this->reset(['temp_product_name', 'temp_product_category', 'temp_product_description', 'temp_product_price', 'temp_product_is_preorder', 'temp_product_img']);
        $this->isImageUploaded = false;
        $this->imagePreviewUrl = null;
        
        session()->flash('message', 'Menu berhasil ditambahkan!');
    }
    
    public function removeProduct($index)
    {
        if (isset($this->products[$index]['preview_path'])) {
            $previewPath = $this->products[$index]['preview_path'];
            if (Storage::disk('public')->exists($previewPath)) {
                Storage::disk('public')->delete($previewPath);
            }
        }
        
        unset($this->products[$index]);
        $this->products = array_values($this->products);
        
        session()->flash('message', 'Menu berhasil dihapus!');
    }
    
    public function nextStep()
    {
        if ($this->currentStep == 1) {
            $this->validate([
                'tenant_code' => 'required|in:A,B,C',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);
        } elseif ($this->currentStep == 2) {
            $this->validate([
                'store_name' => 'required|min:3|max:255',
                'description' => 'nullable|string|max:1000',
                'tenant_img' => 'nullable|image|max:2048',
            ]);
        }
        
        $this->currentStep++;
    }
    
    public function previousStep()
    {
        $this->currentStep--;
    }
    
    public function submitReservation()
    {
        $this->validate();
        
        if (empty($this->products)) {
            session()->flash('error', 'Minimal harus menambahkan 1 menu.');
            $this->currentStep = 3;
            return;
        }
        
        $tenantImgPath = null;
        if ($this->tenant_img) {
            $tenantImgPath = $this->tenant_img->store('approval_tenant', 'public');
        }
        
        $reservation = Reservation::create([
            'user_id' => Auth::guard("tenant")->id(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
        
        $approvalTenantId = DB::connection()->table('approval_tenants')->insertGetId([
            'tenant_code' => $this->tenant_code,
            'reservation_id' => $reservation->id,
            'store_name' => $this->store_name,
            'slug' => Str::slug($this->store_name),
            'description' => $this->description,
            'phone' => Auth::guard("tenant")->user()->phone,
            'tenant_img' => $tenantImgPath ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        
        // Save products with images to approval_menus
        foreach ($this->products as $product) {
            $finalProductImgPath = null;
            
            if (isset($product['preview_path'])) {
                $oldPath = $product['preview_path'];
                $newFilename = 'approval_menu/' . basename($oldPath);
                
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->move($oldPath, $newFilename);
                    $finalProductImgPath = $newFilename;
                }
            }
            $originalSlug = Str::slug($product['name']);
            $slug = $originalSlug;
            $counter = 1;

            while (DB::table('approval_menus')->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            DB::connection()->table('approval_menus')->insert([
                'tenant_id' => $approvalTenantId,
                'category_id' => $product['category'] ?? null,
                'name' => $product['name'],
                'slug' => $slug,
                'description' => $product['description'],
                'price' => $product['price'],
                'is_preorder' => $product['is_preorder'],
                'product_img' => $finalProductImgPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // Reload approval tenant with updated menus count
        // $approvalTenant = ApprovalTenant::with('menus')->find($approvalTenantId);
        
        // Broadcast event (tanpa queue untuk menghindari error serialisasi)
        // try {
        //     broadcast(new NewReservationCreated($reservation, $approvalTenant))->toOthers();
        // } catch (\Exception $e) {
        //     \Log::error('Broadcast error: ' . $e->getMessage());
        // }
        
        $this->cleanPreviewFolder();
        
        session()->flash('success', 'Reservasi berhasil! Tunggu Approval.');
        
        return redirect("/reservasi");
    }
    
    private function cleanPreviewFolder()
    {
        if (Storage::disk('public')->exists('preview')) {
            $files = Storage::disk('public')->files('preview');
            foreach ($files as $file) {
                Storage::disk('public')->delete($file);
            }
        }
    }
    
    #[Computed]
    public function categories() 
    {
        return Categorie::all();
    }

    #[Computed]
    public function alreadyReservf() 
    {
       return Reservation::where('user_id', Auth::guard("tenant")->id())
       ->value('statusApprove');
    }

    #[Computed]
    public function latestReservation() 
    {
       return Reservation::where('user_id', Auth::guard("tenant")->id())->latest()->first();
    }

    public function updateIsacknowledged() 
    {
        if ($this->latestReservation) {
            Reservation::where('user_id', Auth::guard("tenant")->id())
                ->where('id', $this->latestReservation->id)
                ->update(['is_acknowledged' => 1]);
            
            unset($this->latestReservation);
        }       
    }
    
    public function render()
    {
        return $this->view()->layout('layouts.app')->title("Student Business Corner | Reservasi Tenant");
    }
};
?>

<div>
    @if ($this->latestReservation && $this->latestReservation->statusApprove === 0 && !$this->latestReservation->is_acknowledged)
    <div class="h-screen flex items-center justify-center z-50">
        <div class="max-w-md w-full mx-4">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Icon Header -->
                <div class="px-6 py-4 text-center">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto">
                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                            <flux:icon.exclamation-circle class="size-12 text-orange-500"/>
                        </div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="p-6 text-center">
                    <h3 class="text-xl font-bold text-red-700 mb-2">Reservasi Ditolak</h3>
                    <p class="text-gray-600 mb-4">Reservasi sebelumnya ditolak oleh admin.</p>
                    
                    @if($this->latestReservation->reasons)
                        <div class="bg-red-50 rounded-lg p-3 mb-4 text-left">
                            <p class="text-sm font-semibold text-red-700 mb-1">Alasan Penolakan:</p>
                            <p class="text-sm text-red-600">{{ $this->latestReservation->reasons }}</p>
                        </div>
                    @else
                    <div class="bg-red-50 rounded-lg p-3 mb-4 text-left">
                        <p class="text-sm font-semibold text-red-700 mb-1">Alasan Penolakan:</p>
                        <p class="text-sm text-red-600">Tidak ada Alasan Khusus</p>
                    </div>
                    @endif
                    
                    <p class="text-xs text-gray-400 mb-6">
                        Tanggal pengajuan: {{ \Carbon\Carbon::parse($this->latestReservation->created_at)->format('d/m/Y H:i') }}
                    </p>
                    
                    <button wire:click="updateIsacknowledged" 
                            class="w-full px-4 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
                        <flux:icon.loading wire:loading class="size-6"/>               
                        <span wire:loading.remove class="text-white font-semibold ">Buat Reservasi Baru</span>
                    </button>
                </div>

            </div>
        </div>
    </div>

<!-- Status Menunggu - Full Height Center -->
@elseif ($this->latestReservation && $this->latestReservation->statusApprove === null && !$this->latestReservation->is_acknowledged)
    <div class="h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-4">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Icon Header with Animation -->
                <div class="px-6 py-4 text-center">
                    <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto">
                        <flux:icon.clock class="size-12 text-orange-500"/>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="p-6 text-center">
                    <h3 class="text-xl font-bold text-yellow-700 mb-2">Reservasi Diproses</h3>
                    <p class="text-gray-600 mb-4">Reservasi Anda sedang menunggu persetujuan admin.</p>
                    
                    <div class="bg-yellow-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></div>
                            <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse delay-150"></div>
                            <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse delay-300"></div>
                        </div>
                        <p class="text-sm text-yellow-700">Sedang diproses oleh admin</p>
                    </div>
                    
                    <p class="text-xs text-gray-400 mb-6">
                        Tanggal pengajuan: {{ \Carbon\Carbon::parse($this->latestReservation->created_at)->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
    @else      
    <div class="min-h-screen py-8 md:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                    Reservasi Tenant
                </h1>
            </div>

            <!-- Step Indicators -->
            <div class="mb-8">
                <div class="flex items-center justify-center">
                    <div class="flex items-center w-full max-w-2xl">
                        <!-- Step 1 -->
                        <div class="flex-1 text-center">
                            <div class="relative">
                                <div class="w-10 h-10 mx-auto rounded-full flex items-center justify-center text-white font-bold
                                    {{ $currentStep >= 1 ? 'bg-orange-500' : 'bg-gray-300' }}">
                                    1
                                </div>
                                <div class="absolute top-5 left-1/2 w-full h-0.5 bg-gray-300 -z-10 
                                    {{ $currentStep > 1 ? 'bg-orange-500' : 'bg-gray-300' }}"></div>
                            </div>
                            <p class="text-xs mt-2 {{ $currentStep >= 1 ? 'text-orange-500 font-semibold' : 'text-gray-500' }}">
                                Pilih Tenant & Tanggal
                            </p>
                        </div>
                        
                        <!-- Step 2 -->
                        <div class="flex-1 text-center">
                            <div class="relative">
                                <div class="w-10 h-10 mx-auto rounded-full flex items-center justify-center text-white font-bold
                                    {{ $currentStep >= 2 ? 'bg-orange-500' : 'bg-gray-300' }}">
                                    2
                                </div>
                                <div class="absolute top-5 left-1/2 w-full h-0.5 bg-gray-300 -z-10
                                    {{ $currentStep > 2 ? 'bg-orange-500' : 'bg-gray-300' }}"></div>
                            </div>
                            <p class="text-xs mt-2 {{ $currentStep >= 2 ? 'text-orange-500 font-semibold' : 'text-gray-500' }}">
                                Informasi Tenant
                            </p>
                        </div>
                        
                        <!-- Step 3 -->
                        <div class="flex-1 text-center">
                            <div class="w-10 h-10 mx-auto rounded-full flex items-center justify-center text-white font-bold
                                {{ $currentStep >= 3 ? 'bg-orange-500' : 'bg-gray-300' }}">
                                3
                            </div>
                            <p class="text-xs mt-2 {{ $currentStep >= 3 ? 'text-orange-500 font-semibold' : 'text-gray-500' }}">
                                Daftar Menu
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="bg-orange-500 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white">
                        Step {{ $currentStep }} dari 3
                    </h2>
                    <p class="text-orange-100 text-sm mt-1">
                        @if($currentStep == 1)
                            Pilih tenant dan tentukan masa reservasi
                        @elseif($currentStep == 2)
                            Isi informasi tentang tenant Anda
                        @else
                            Tambahkan menu yang akan dijual
                        @endif
                    </p>
                </div>
                
                <form wire:submit.prevent="submitReservation" class="p-6">
                    <!-- Step 1: Pilih Tenant & Tanggal -->
                    @if($currentStep == 1)
                        <div class="space-y-6">
                            <!-- Tenant Selection -->
                            <div>
                                <label class="block text-gray-700 font-semibold mb-3">
                                    Pilih Tenant <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <label class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer hover:bg-orange-50 transition
                                        {{ $tenant_code == 'A' ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                                        <input type="radio" wire:model="tenant_code" value="A" class="w-4 h-4 text-orange-500">
                                        <div class="ml-3">
                                            <span class="block font-semibold text-gray-800">Tenant A</span>
                                        </div>
                                    </label>
                                    
                                    <label class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer hover:bg-orange-50 transition
                                        {{ $tenant_code == 'B' ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                                        <input type="radio" wire:model="tenant_code" value="B" class="w-4 h-4 text-orange-500">
                                        <div class="ml-3">
                                            <span class="block font-semibold text-gray-800">Tenant B</span>
                                        </div>
                                    </label>
                                    
                                    <label class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer hover:bg-orange-50 transition
                                        {{ $tenant_code == 'C' ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                                        <input type="radio" wire:model="tenant_code" value="C" class="w-4 h-4 text-orange-500">
                                        <div class="ml-3">
                                            <span class="block font-semibold text-gray-800">Tenant C</span>
                                        </div>
                                    </label>
                                </div>
                                @error('tenant_code') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <!-- Date Selection -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Tanggal Mulai Sewa <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" wire:model="start_date" 
                                        min="{{ date('Y-m-d') }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                    @error('start_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Tanggal Selesai Sewa <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" wire:model="end_date" 
                                        min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                    @error('end_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                        </div>
                    @endif
                    
                    <!-- Step 2: Informasi Tenant -->
                    @if($currentStep == 2)
                        <div class="space-y-6">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    Nama tenant <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="store_name" 
                                    placeholder="Contoh: Warung Maku Bahagia"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                @error('store_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    Deskripsi tenant
                                </label>
                                <textarea wire:model="description" rows="4"
                                    placeholder="Jelaskan tentang tenant Anda, jenis makanan yang dijual, konsep, dll..."
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                                @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    Foto Tenant
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-orange-500 transition">
                                    <div class="space-y-1 text-center">
                                        @if($tenant_img)
                                            <div class="mb-3">
                                                <img src="{{ $tenant_img->temporaryUrl() }}" class="mx-auto h-32 w-auto rounded-lg">
                                            </div>
                                        @else
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        @endif
                                        <div class="flex justify-center text-sm text-gray-500">
                                            <label for="tenant_img" class="cursor-pointer bg-white rounded-md font-medium text-orange-500 hover:text-orange-500 focus-within:outline-none">
                                                Upload foto
                                                <input id="tenant_img" type="file" wire:model="tenant_img" class="sr-only" accept="image/*">
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, JPEG up to 2MB</p>
                                    </div>
                                </div>
                                @error('tenant_img') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif
                    
                    <!-- Step 3: Daftar Menu -->
                    @if($currentStep == 3)
                    <div class="space-y-6">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-800 mb-3">Tambah Menu Baru</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <input type="text" wire:model="temp_product_name" 
                                    placeholder="Nama Menu *" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                <select wire:model="temp_product_category"
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                    <option value="">-- Pilih Kategori (Opsional) --</option>
                                    @foreach($this->categories as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                
                                <textarea wire:model="temp_product_description" rows="2"
                                    placeholder="Deskripsi menu (opsional)" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                                
                                <div class="space-y-3">
                                    <input type="number" wire:model="temp_product_price" 
                                        placeholder="Harga *" min="0"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                    
                                    <div class="flex items-center gap-2 px-1 py-1">
                                        <input type="checkbox" id="temp_product_is_preorder" wire:model="temp_product_is_preorder"
                                            class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500 cursor-pointer">
                                        <label for="temp_product_is_preorder" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                                            Menu ini menggunakan sistem Pre-Order (PO)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm text-gray-500 mb-1">
                                        Foto Menu <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" wire:model="temp_product_img" accept="image/*" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                    
                                    @if($temp_product_img)
                                        <div class="mt-3">
                                            <p class="text-xs text-gray-500 mb-1">Preview Gambar:</p>
                                            <img src="{{ $temp_product_img->temporaryUrl() }}" class="h-24 w-auto rounded-lg border border-gray-200">
                                        </div>
                                    @elseif($isImageUploaded && $imagePreviewUrl)
                                        <div class="mt-3">
                                            <p class="text-xs text-gray-500 mb-1">Preview Gambar:</p>
                                            <img src="{{ $imagePreviewUrl }}" class="h-24 w-auto rounded-lg border border-gray-200">
                                        </div>
                                    @endif
                                    
                                    @error('temp_product_img') 
                                        <span class="text-red-500 text-sm">{{ $message }}</span> 
                                    @enderror
                                </div>
                            </div>
                            
                            <button type="button" wire:click="addProduct" 
                                wire:loading.attr="disabled"
                                @if(!$temp_product_img) disabled @endif
                                class="mt-3 px-4 py-2 rounded-lg transition flex items-center gap-2
                                {{ $temp_product_img ? 'bg-orange-500 hover:bg-orange-700 text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Tambah Menu
                            </button>
                            
                            @if(!$temp_product_img)
                                <p class="text-xs text-red-500 mt-2">* Upload foto menu terlebih dahulu</p>
                            @endif
                        </div>
                        
                        @if(count($products) > 0)
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3">Daftar Menu ({{ count($products) }})</h3>
                                <div class="space-y-2 max-h-96 overflow-y-auto">
                                    @foreach($products as $index => $product)
                                        <div class="bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between hover:shadow-md transition">
                                            <div class="flex items-center gap-3 flex-1">
                                                @if(isset($product['temp_image_url']) && $product['temp_image_url'])
                                                    <img src="{{ $product['temp_image_url'] }}" class="w-12 h-12 object-cover rounded">
                                                @else
                                                    <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                @endif
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <p class="font-semibold text-gray-800">{{ $product['name'] }}</p>
                                                        @if(isset($product['is_preorder']) && $product['is_preorder'])
                                                            <span class="bg-orange-100 text-orange-700 text-[10px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">
                                                                Pre-Order
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="text-sm text-gray-500 line-clamp-1">{{ $product['description'] ?? 'Tidak ada deskripsi' }}</p>
                                                    <p class="text-orange-500 font-bold text-sm">Rp {{ number_format($product['price'], 0, ',', '.') }}</p>
                                                </div>
                                            </div>
                                            <button type="button" wire:click="removeProduct({{ $index }})" 
                                                class="text-red-500 hover:text-red-700 p-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-lg">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <p class="text-gray-500">Belum ada menu. Silakan tambahkan menu di atas.</p>
                            </div>
                        @endif
                        
                        @error('products') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
                    
                    @if(session()->has('message'))
                        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                            {{ session('message') }}
                        </div>
                    @endif
                    
                    @if(session()->has('error'))
                        <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                            {{ session('error') }}
                        </div>
                    @endif
                    
                    <!-- Navigation Buttons -->
                    <div class="flex justify-between mt-8 pt-6 border-t border-gray-200">
                        @if($currentStep > 1)
                            <button type="button" wire:click="previousStep" 
                                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                                Kembali
                            </button>
                        @else
                            <div></div>
                        @endif
                        
                        @if($currentStep < 3)
                            <button type="button" wire:click="nextStep" 
                                class="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-700 transition">
                                Selanjutnya
                            </button>
                        @else
                            <button type="submit" wire:loading.attr="disabled"
                                class="px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-700 transition flex items-center gap-2">
                                {{-- <flux:icon.loading wire:loading/> --}}
                                Submit Reservasi
                            </button>
                        @endif
                    </div>
                </form>
            </div>        
        </div>
    </div>
    @endif
</div>