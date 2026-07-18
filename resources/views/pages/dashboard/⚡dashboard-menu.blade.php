<?php

use Livewire\Component;
use App\Models\Product;
use App\Models\Categorie;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use App\Events\ProductAvailabilityChanged;


new class extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $statusFilter = 'all';
    public $perPage = 10;

    public function getTenantProperty()
    {

        return Tenant::where('reservation_id', Auth::guard('tenant')->user()->reservation()->latest()->first()->id )->first();
    }
    
    public function getProductsProperty()
    {
        $tenant = $this->tenant;
        
        if (!$tenant) {
            return collect();
        }
        
        $query = Product::with('category')
            ->where('tenant_id', $tenant->id);
        
        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }
        
        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }
        
        if ($this->statusFilter === 'available') {
            $query->where('is_available', 1);
        } elseif ($this->statusFilter === 'unavailable') {
            $query->where('is_available', 0);
        }
        
        return $query->latest()->paginate($this->perPage);
    }
    
    public function getCategoriesProperty()
    {
        return Categorie::all();
    }

    public function toggleStatus($productId)
    {
        $tenant = $this->tenant;

        if (!$tenant) return;

        // if ($tenant->is_open) {
        //     $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
        //     return;
        // }

        $product = Product::where('id', $productId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $product->update(['is_available' => !$product->is_available]);

        broadcast(new ProductAvailabilityChanged($product))->toOthers();

        $status = $product->is_available ? 'Tersedia' : 'Tidak Tersedia';
        // session()->flash('message', "Status menu \"{$product->name}\" diubah menjadi {$status}");
        $this->dispatch('toast', message: "Status menu \"{$product->name}\" diubah menjadi {$status}");

    }

    public function menuDelete($slug)
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        if ($tenant->is_open) {
            $this->dispatch('error', message: 'Tenant harus ditutup terlebih dahulu', type: 'error');
            return;
        }
        
        $menu = Product::where('slug', $slug)
            ->where('tenant_id', $tenant->id)
            ->first();

        
        if (!$menu) {
            abort(403, 'Aksi tidak diizinkan atau menu tidak ditemukan.');
        }

        if ($menu->product_img && Storage::disk('public')->exists($menu->product_img)) {
            Storage::disk('public')->delete($menu->product_img);
        }

        $menu->delete();

        session()->flash('message', 'Menu berhasil dihapus.');

    }

    public function render()
    {
        return $this->view([
            'products' => $this->products,
            'categories' => $this->categories,
            'tenant' => $this->tenant,
        ])->layout('layouts::app', ['title' => 'Student Business Corner | Dashboard Menu']); 
    }
};
?>

<div>
    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Kelola Menu</h2>
            <p class="text-gray-500 text-sm mt-1">Atur daftar menu yang dijual di tenant Anda</p>
        </div>
        <a href="/dashboard/menu/add"
                class="px-4 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Tambah Menu
        </a>
    </div>

    <!-- Search and Filter -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search"
                           placeholder="Cari menu..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                </div>
            </div>
            <div class="w-full md:w-48">
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-40">
                <select wire:model.live="statusFilter" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500">
                    <option value="all">Semua Status</option>
                    <option value="available">Tersedia</option>
                    <option value="unavailable">Tidak Tersedia</option>
                </select>
            </div>
            <div class="w-full md:w-32">
                <select wire:model.live="perPage" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500">
                    <option value="10">10 data</option>
                    <option value="25">25 data</option>
                    <option value="50">50 data</option>
                    <option value="100">100 data</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Products Grid --}}
    <div class="mb-4 flex items-center justify-between">
        <span class="text-sm text-gray-500">
            Menampilkan {{ $products->firstItem() }}–{{ $products->lastItem() }} dari {{ $products->total() }} menu
        </span>
    </div>

    @if($products->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-16 flex flex-col items-center gap-3 text-gray-400">
            <svg class="w-14 h-14 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
            <p class="text-gray-500">Belum ada menu</p>
            <a href="/dashboard/menu/add" class="text-orange-500 hover:text-orange-600 text-sm">Tambah menu sekarang</a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($products as $product)
                <div class="bg-white rounded-xl border border-gray-300 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow">

                    {{-- Gambar --}}
                    <div class="relative h-40 bg-gray-50">
                        @if($product->product_img)
                            <img src="{{ Storage::url($product->product_img) }}" 
                            class="w-full h-full object-contain bgi" 
                            alt="{{ $product->name }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif

                        
                        <button wire:click="toggleStatus({{ $product->id }})"
                                class="absolute top-2 right-2 flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium transition cursor-pointer
                                {{ $product->is_available
                                    ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                    : 'bg-red-100 text-red-700 hover:bg-red-200' }}">
                            @if($product->is_available)
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Tersedia
                            @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Habis
                            @endif
                        </button>
                    </div>

                    {{-- Konten --}}
                    <div class="p-3 flex flex-col gap-2 flex-1">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $product->name }}</p>
                        <p class="text-xs text-gray-400 line-clamp-2 leading-relaxed min-h-[2.5rem]">
                            {{ $product->description ?? '-' }}
                        </p>

                        {{-- Badge kategori & tipe --}}
                        <div class="flex flex-wrap gap-1">
                            <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">
                                {{ $product->category->name ?? '-' }}
                            </span>
                            @if($product->is_preorder)
                                <span class="flex items-center gap-0.5 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Pre-Order @if (!$product->tenant->pick_slot->isNotEmpty())<span><a href="/dashboard/tenant/profile?tab=open" class="underline hover:text-sky-600">Blm Diatur</a></span>@endif
                                </span>
                            @endif
                        </div>

                        {{-- Harga & aksi --}}
                        <div class="flex items-center justify-between mt-auto pt-2 border-t border-gray-100">
                            <span class="text-sm font-bold text-orange-600">
                                Rp {{ number_format($product->price, 0, ',', '.') }}
                            </span>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('tenant.menu.edit', $product) }}" wire:navigate
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-indigo-500 hover:bg-indigo-50 hover:border-indigo-200 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <button wire:confirm='Apakah Anda yakin ingin menghapus menu?' wire:click="menuDelete('{{ $product->slug }}')"
                                        class="cursor-pointer w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-red-500 hover:bg-red-50 hover:border-red-200 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6 px-1">
            {{ $products->links() }}
        </div>
    @endif
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