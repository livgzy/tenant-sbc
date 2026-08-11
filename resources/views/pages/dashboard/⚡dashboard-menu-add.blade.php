<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Categorie;

new class extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public string $description = '';

    #[Validate('required|numeric|min:0|max:99999999')]
    public string $price = '';

    #[Validate('required|exists:categories,id')]
    public int $category_id;

    public bool $is_available = true;

    public bool $is_preorder = false;

    #[Validate('required|image|max:2048')]
    public $product_img = null;

    #[Computed]
    public function slugPreview(): string
    {
        return $this->name !== '' ? Str::slug($this->name) : '-';
    }

    public function with(): array
    {
        return [
            'categories' => Categorie::orderBy('name')->get(),
        ];
    }

    public function removeImage(): void
    {
        $this->reset('product_img');
    }

    public function save()
    {
        $this->validate();

        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        $imagePath = $this->product_img?->store('products', 'public');

        $slug = Str::slug($this->name);
        if (Product::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }

        Product::create([
            'tenant_id'    => $tenant->id,
            'category_id'  => $this->category_id,
            'name'         => $this->name,
            'slug'         => $slug,
            'description'  => $this->description,
            'price'        => $this->price,
            'is_available' => $this->is_available,
            'is_preorder'  => $this->is_preorder,
            'product_img'  => $imagePath,
        ]);

        session()->flash('success', 'Menu "' . $this->name . '" berhasil ditambahkan.');

        return redirect()->route('tenant.menu');
    }

    public function render()
    {
        return $this->view([])
            ->layout('layouts::app', ['title' => 'Student Business Corner | Dashboard Menu Add']);
    }
};
?>

<div>
    {{-- Header halaman --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="{{ route('tenant.menu') }}" wire:navigate class="hover:text-orange-500">Kelola Menu</a>
                <span class="mx-1">/</span>
                <span class="text-slate-500">Tambah Menu</span>
            </nav>
            <h1 class="text-xl font-semibold text-slate-800">Tambah Menu Baru</h1>
        </div>

        <a href="{{ route('tenant.menu') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </a>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Kolom kiri: foto menu --}}
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <label class="block text-sm font-medium text-slate-700 mb-3">Foto Menu<span class="text-red-500">*</span></label>
                @if ($product_img)
                    <div class="relative rounded-xl overflow-hidden border border-slate-200 aspect-square">
                        <img src="{{ $product_img->temporaryUrl() }}" class="w-full h-full object-cover" alt="Preview menu">
                        <button type="button" wire:click="removeImage"
                                class="absolute top-2 right-2 size-8 flex items-center justify-center rounded-full bg-white/90 text-slate-600 hover:text-red-600 shadow">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @else
                    <label for="product_img"
                           class="flex flex-col items-center justify-center gap-2 aspect-square rounded-xl border-2 border-dashed border-slate-200 text-slate-400 cursor-pointer hover:border-orange-300 hover:text-orange-400 transition-colors"
                           wire:loading.class="opacity-50" wire:target="product_img">
                        <svg class="size-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 7.5L12 3m0 0L7.5 7.5M12 3v13.5" />
                        </svg>
                        <span class="text-sm font-medium">Unggah foto</span>
                        <span class="text-xs">PNG / JPG, maks 2MB</span>
                    </label>
                @endif

                <input type="file" id="product_img" wire:model="product_img" accept="image/*" class="hidden">

                <div wire:loading wire:target="product_img" class="mt-2 text-xs text-orange-500">
                    Mengunggah foto…
                </div>

                @error('product_img')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Status menu --}}
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm mt-6 space-y-4">
                <label class="flex items-center justify-between cursor-pointer">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">Tersedia</span>
                        <span class="block text-xs text-slate-400">Langsung tampil &amp; bisa dipesan</span>
                    </span>
                    <input type="checkbox" wire:model="is_available" class="peer sr-only">
                    <span class="relative w-10 h-6 rounded-full bg-slate-200 peer-checked:bg-orange-500 transition-colors
                                 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-transform
                                 peer-checked:after:translate-x-4"
                          ></span>
                </label>

                <label class="flex items-center justify-between cursor-pointer">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">Pre-order</span>
                        <span class="block text-xs text-slate-400">Apakah Menu Merupakan Menu Pre-Order?</span>
                    </span>
                    <input type="checkbox" wire:model="is_preorder" class="peer sr-only">
                    <span class="relative w-10 h-6 rounded-full bg-slate-200 peer-checked:bg-orange-500 transition-colors
                                 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-transform
                                 peer-checked:after:translate-x-4"
                          ></span>
                </label>
            </div>
        </div>

        {{-- Kolom kanan: detail menu --}}
        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">

                <div>
                    <label for="name" class="flex justify-between items-center text-sm font-medium text-slate-700 mb-1.5">
                        <span>Nama Menu<span class="text-red-500">*</span></span>
                        <span class="text-xs font-normal text-red-500">* Wajib Diisi</span>
                    </label>
                    <input type="text" id="name" wire:model.blur.live="name" placeholder="Contoh: Nasi Goreng Spesial"
                           class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 @error('name') border-red-400 @enderror">
                    <p class="mt-1.5 text-xs text-slate-400">
                        Slug: <span class="font-mono text-slate-500">{{ $this->slugPreview }}</span>
                    </p>
                    @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-slate-700 mb-1.5">Kategori<span class="text-red-500">*</span></label>
                        <select id="category_id" wire:model="category_id"
                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 @error('category_id') border-red-400 @enderror">
                            <option value="">Pilih kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="price"  class="block text-sm font-medium text-slate-700 mb-1.5">Harga<span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-slate-400">Rp</span>
                            <input type="number" id="price" wire:model="price" min="0" step="500" placeholder="15000"
                                   class="w-full rounded-lg border border-slate-200 pl-10 pr-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 @error('price') border-red-400 @enderror">
                        </div>
                        @error('price')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Deskripsi</label>
                    <textarea id="description" wire:model="description" rows="4" placeholder="Ceritakan menu ini, bahan, porsi, atau hal lain yang pembeli perlu tahu."
                              class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 @error('description') border-red-400 @enderror"></textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="submit"
                        wire:loading.attr="disabled" wire:target="save"
                        class="px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 shadow-sm shadow-orange-500/30 disabled:opacity-60 transition-colors">
                    <flux:icon.loading wire:loading wire:target="save" class="size-5"/>
                    <span>Simpan Menu</span>
                </button>
            </div>
        </div>
    </form>
</div>