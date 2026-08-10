<?php

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\FonnteService;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = 'Pending';
    public string $search = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function tenant()
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;
        return $tenant;
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->where('data_tenant->reservation_id', $this->tenant?->reservation_id)
            ->when($this->statusFilter !== 'Semua', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($q) => $q->where('order_number', 'like', "%{$this->search}%"))
            ->with(['user', 'items'])
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function statusCounts()
    {
        return Order::query()
            ->where('data_tenant->reservation_id', $this->tenant?->reservation_id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    public function acceptOrder(int $orderId): void
    {
        $order = $this->findTenantOrder($orderId);

        if (! $order || $order->status !== 'Pending') {
            $this->dispatch('notify', type: 'error', message: 'Pesanan tidak ditemukan atau sudah diproses.');
            return;
        }

        $order->update([
            'status' => 'Diproses',
            'payment_status' => 'Sudah Dibayar',
        ]);

        // TODO: trigger notifikasi WhatsApp ke customer via Fonnte (pesanan diterima)
        FonnteService::notifyCustomerStatus($order);

        $this->dispatch('notify', type: 'success', message: "Pesanan {$order->order_number} diterima.");
    }

    public function cancelOrder(int $orderId): void
    {
        $order = $this->findTenantOrder($orderId);

        if (! $order || $order->status !== 'Pending') {
            $this->dispatch('notify', type: 'error', message: 'Hanya pesanan berstatus Pending yang bisa dibatalkan di sini.');
            return;
        }

        $order->update(['status' => 'Dibatalkan']);

        // TODO: trigger notifikasi WhatsApp ke customer via Fonnte (pesanan dibatalkan)
        FonnteService::notifyCustomerStatus($order);

        $this->dispatch('notify', type: 'success', message: "Pesanan {$order->order_number} dibatalkan.");
    }

    public function updateStatus(int $orderId, string $newStatus): void
    {
        $order = $this->findTenantOrder($orderId);

        if (! $order) {
            $this->dispatch('notify', type: 'error', message: 'Pesanan tidak ditemukan.');
            return;
        }

        $allowedTransitions = [
            'Diproses' => ['Siap Diambil', 'Dibatalkan'],
            'Siap Diambil' => ['Selesai'],
        ];

        if (! isset($allowedTransitions[$order->status]) || ! in_array($newStatus, $allowedTransitions[$order->status], true)) {
            $this->dispatch('notify', type: 'error', message: 'Perubahan status tidak valid.');
            return;
        }

        $order->update(['status' => $newStatus]);

        // TODO: trigger notifikasi WhatsApp ke customer sesuai status baru
        if ($order->status !== 'Selesai') {
            FonnteService::notifyCustomerStatus($order);
        }


        $this->dispatch('notify', type: 'success', message: "Status pesanan {$order->order_number} diubah menjadi {$newStatus}.");
    }

    private function findTenantOrder(int $orderId): ?Order
    {
        return Order::query()
            ->where('id', $orderId)
            ->where('data_tenant->reservation_id', $this->tenant?->reservation_id)
            ->first();
    }

    public function render()
    {
        return $this->view([])->layout('layouts::app', ['title' => 'Student Business Corner | Dashboard Order']); 
    }
};
?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Kelola Pesanan Pre-Order</h2>
        </div>

        <div class="relative w-full sm:w-72 group">
            <!-- Icon Kaca Pembesar -->
            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            
            <!-- Input Field -->
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari nomor pesanan..."
                class="w-full py-2.5 pl-10 pr-4 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl shadow-sm transition-all duration-300 hover:border-gray-300  focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400"
            />
        </div>
    </div>

    {{-- Tab status --}}
    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
        @foreach (['Semua', 'Pending', 'Diproses', 'Siap Diambil', 'Selesai', 'Dibatalkan'] as $status)
            <button
                wire:click="$set('statusFilter', '{{ $status }}')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition
                    {{ $statusFilter === $status ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
            >
                {{ $status }}
                @if ($status !== 'Semua' && ($this->statusCounts[$status] ?? 0) > 0)
                    <span class="ml-1 text-xs opacity-80">({{ $this->statusCounts[$status] }})</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- List pesanan --}}
    <div class="space-y-4">
        @forelse ($this->orders as $order)
            <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-gray-900">{{ $order->order_number }}</span>

                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-yellow-100 text-yellow-700' => $order->status === 'Pending',
                                'bg-indigo-100 text-indigo-700' => $order->status === 'Diproses',
                                'bg-orange-100 text-orange-700' => $order->status === 'Siap Diambil',
                                'bg-green-100 text-green-700' => $order->status === 'Selesai',
                                'bg-red-100 text-red-700' => $order->status === 'Dibatalkan',
                            ])>
                                {{ $order->status }}
                            </span>
                            @if ($order->status !== 'Dibatalkan')                            
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                
                                'bg-green-100 text-green-700' => $order->payment_method === 'Tunai' || ($order->payment_method === 'Non Tunai' && $order->payment_status === 'Sudah Dibayar'),
                                'bg-red-100 text-red-600' => $order->payment_method === 'Non Tunai' && $order->payment_status === 'Belum Dibayar',
                                'bg-orange-100 text-orange-700' => $order->payment_method === 'Non Tunai' && $order->payment_status === 'Menunggu Konfirmasi',
                            ])>
                                {{ $order->payment_method === 'Tunai' ? 'Bayar di Tempat (Tunai)' : $order->payment_status }}
                            </span>
                            @endif

                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                {{ $order->payment_method }}
                            </span>
                        </div>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ $order->user->name }} &middot; {{ $order->user->phone ?? '-' }}
                        </p>

                        {{-- Perubahan di sini: Menambahkan Tanggal Order Dibuat --}}
                        <p class="text-xs text-gray-400">
                            Dibuat: {{ \Carbon\Carbon::parse($order->created_at)->translatedFormat('d M Y, H:i') }}
                        </p>

                        <p class="mt-1 text-sm text-gray-500">
                            Ambil: {{ $order->data_pickup_slot['dayPickup'] ?? '-' }},
                            {{ \Carbon\Carbon::parse($order->pickup_time)->format('H:i') }}
                            ({{ $order->data_pickup_slot['start_time'] ?? '' }}-{{ $order->data_pickup_slot['end_time'] ?? '' }})
                        </p>

                        @if ($order->payment_method === 'Non Tunai' && ($order->data_payment_method['name_payment'] ?? null))
                            <p class="text-sm text-gray-500">
                                Metode: {{ $order->data_payment_method['name_payment'] }}
                            </p>
                        @endif
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-500">Total</p>
                        <p class="text-lg font-semibold text-gray-900">
                            Rp{{ number_format($order->total_amount, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                {{-- Item pesanan --}}
                <div class="mt-3 divide-y divide-gray-100 rounded-lg bg-gray-50 px-3">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-800">
                                    {{ $item->product->name ?? 'Produk' }}
                                </span>
                                <span class="text-gray-500">&times; {{ $item->quantity }}</span>

                                @if ($item->product->is_preorder ?? false)
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">Pre-order</span>
                                @endif

                                @if ($item->notes)
                                    <p class="text-xs italic text-gray-400">Catatan: {{ $item->notes }}</p>
                                @endif
                            </div>
                            <span class="text-gray-600">
                                Rp{{ number_format(($item->data_product['price'] ?? 0) * $item->quantity, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                {{-- Bukti bayar non tunai --}}
                @if ($order->payment_method === 'Non Tunai' && $order->payment_proof_img)
                    <div class="mt-3">
                        <p class="mb-1 text-xs font-medium text-gray-500">Bukti Pembayaran</p>
                        <div x-data="{ showImagePreview: false }">
                            <button type="button" @click="showImagePreview = true" class="group relative inline-block">
                                <img src="{{ Storage::disk('public')->url($order->payment_proof_img) }}"
                                    alt="Bukti Pembayaran"
                                    class="max-h-48 rounded-xl border border-gray-200 transition group-hover:brightness-90">
                                <span class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/20 rounded-xl transition">
                                    <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.35 4.35a7.5 7.5 0 0012.3 12.3zM10.5 7.5v6m-3-3h6"></path>
                                    </svg>
                                </span>
                            </button>
                            <div
                                x-show="showImagePreview"
                                x-cloak
                                @keydown.escape.window="showImagePreview = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="fixed inset-0 z-[60] flex items-center justify-center p-4"
                                style="display: none;"
                            >
                                <!-- Backdrop -->
                                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showImagePreview = false"></div>
                                
                                <!-- Konten Image -->
                                <div
                                    x-show="showImagePreview"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="relative max-w-3xl w-full"
                                >
                                    <!-- Tombol Close -->
                                    <button type="button" @click="showImagePreview = false"
                                            class="absolute -top-10 right-0 text-white/80 hover:text-white transition">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>

                                    <!-- Gambar Full -->
                                    <img src="{{ Storage::disk('public')->url($order->payment_proof_img) }}"
                                        alt="Bukti Pembayaran"
                                        class="w-full max-h-[85vh] object-contain rounded-xl shadow-2xl">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Aksi --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($order->status === 'Pending')
                        <button
                            wire:click="acceptOrder({{ $order->id }})"
                            wire:confirm="Terima pesanan {{ $order->order_number }}? Status pembayaran akan diubah menjadi Sudah Dibayar."
                            class="rounded-lg bg-green-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Terima Pesanan
                        </button>
                        <button
                            wire:click="cancelOrder({{ $order->id }})"
                            wire:confirm="Batalkan pesanan {{ $order->order_number }}?"
                            class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors duration-200 hover:bg-red-600 hover:text-white"
                        >
                            Batalkan
                        </button>
                    @elseif ($order->status === 'Diproses')
                        <button
                            wire:click="updateStatus({{ $order->id }}, 'Siap Diambil')"
                            class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700"
                        >
                            Tandai Siap Diambil
                        </button>
                    @elseif ($order->status === 'Siap Diambil')
                        <button
                            wire:click="updateStatus({{ $order->id }}, 'Selesai')"
                            class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Tandai Selesai
                        </button>
                    @else
                        <span class="text-sm text-gray-400">Tidak ada aksi tersedia.</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 py-12 text-center text-gray-400">
                Tidak ada pesanan untuk status ini.
            </div>
        @endforelse
    </div>

    <div>
        {{ $this->orders->links() }}
    </div>
</div>