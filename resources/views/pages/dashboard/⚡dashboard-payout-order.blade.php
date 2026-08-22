<?php

use App\Models\Order;
use App\Services\FonnteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

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
        return $reservation?->tenant;
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->where('reservation_id', $this->tenant?->reservation_id)
            ->when(
                $this->statusFilter !== 'Semua',
                fn ($q) => $q->where('status', $this->statusFilter)
            )
            ->when(
                $this->search !== '',
                fn ($q) => $q->where('order_number', 'like', "%{$this->search}%")
            )
            ->with(['user', 'items.product', 'paymentBatch'])
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function statusCounts()
    {
        return Order::query()
            ->where('reservation_id', $this->tenant?->reservation_id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }
    
    public function cancelOrder(int $orderId): void
    {
        $order = $this->findTenantOrder($orderId);

        if (!$order || $order->status !== 'Pending') {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Hanya pesanan berstatus Pending yang bisa dibatalkan.'
            );
            return;
        }

        $order->update([
            'status' => 'Dibatalkan',
        ]);

        FonnteService::notifyCustomerStatus($order);

        $this->dispatch(
            'notify',
            type: 'success',
            message: "Pesanan {$order->order_number} dibatalkan."
        );
    }

    public function updateStatus(int $orderId, string $newStatus): void
    {
        $order = $this->findTenantOrder($orderId);

        if (!$order) {
            $this->dispatch('notify', type: 'error', message: 'Pesanan tidak ditemukan.');
            return;
        }

        $allowedTransitions = [
            'Pending' => ['Diproses', 'Dibatalkan'],
            'Diproses' => ['Selesai', 'Dibatalkan'],
        ];

        if (
            !isset($allowedTransitions[$order->status]) ||
            !in_array($newStatus, $allowedTransitions[$order->status], true)
        ) {
            $this->dispatch('notify', type: 'error', message: 'Perubahan status tidak valid.');
            return;
        }

        $order->update([
            'status' => $newStatus,
        ]);

        FonnteService::notifyCustomerStatus($order);

        $this->dispatch(
            'notify',
            type: 'success',
            message: "Status pesanan {$order->order_number} diubah menjadi {$newStatus}."
        );
    }

    private function findTenantOrder(int $orderId): ?Order
    {
        return Order::query()
            ->where('id', $orderId)
            ->where('reservation_id', $this->tenant?->reservation_id)
            ->with(['user', 'items.product', 'paymentBatch'])
            ->first();
    }

    public function render()
    {
        return $this->view([])->layout(
            'layouts::app',
            ['title' => 'Student Business Corner | Dashboard Order']
        );
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Kelola Pesanan</h2>
            <p class="mt-1 text-sm text-gray-500">Kelola pesanan reguler dan pre-order dari customer.</p>
        </div>
        <div class="relative w-full sm:w-72">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari nomor pesanan..."
                class="w-full py-2.5 pl-10 pr-4 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl shadow-sm transition-all duration-300 hover:border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400"
            />
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
        @foreach (['Semua', 'Pending', 'Diproses', 'Selesai', 'Dibatalkan'] as $status)
            <button
                wire:click="$set('statusFilter', '{{ $status }}')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $statusFilter === $status ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
            >
                {{ $status }}
                @if ($status !== 'Semua' && ($this->statusCounts[$status] ?? 0) > 0)
                    <span class="ml-1 text-xs opacity-80">({{ $this->statusCounts[$status] }})</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($this->orders as $order)
            <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-gray-900">{{ $order->order_number }}</span>

                            @if ($order->order_type === 'pre_order')
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    Pre-Order
                                </span>
                            @else
                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    Reguler
                                </span>
                            @endif

                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-yellow-100 text-yellow-700' => $order->status === 'Pending',
                                'bg-indigo-100 text-indigo-700' => $order->status === 'Diproses',
                                'bg-green-100 text-green-700' => $order->status === 'Selesai',
                                'bg-red-100 text-red-700' => $order->status === 'Dibatalkan',
                            ])>
                                {{ $order->status }}
                            </span>

                            @if ($order->status !== 'Dibatalkan')
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-medium',
                                    'bg-green-100 text-green-700' => $order->payment_status === 'Sudah Dibayar',
                                    'bg-red-100 text-red-600' => $order->payment_status === 'Belum Dibayar',
                                    'bg-orange-100 text-orange-700' => $order->payment_status === 'Menunggu Konfirmasi',
                                ])>
                                    @if ($order->payment_method === 'Tunai')
                                        Bayar di Tempat
                                    @else
                                        {{ $order->payment_status }}
                                    @endif
                                </span>
                            @endif

                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                {{ $order->payment_method }}
                            </span>
                        </div>

                        <p class="mt-2 text-sm text-gray-500">
                            {{ $order->user->name ?? 'Customer' }} · {{ $order->user->phone ?? '-' }}
                        </p>

                        <p class="text-xs text-gray-400">
                            Dibuat: {{ $order->created_at?->translatedFormat('d M Y, H:i') ?? '-' }}
                        </p>

                        @if ($order->order_type === 'pre_order')
                            <div class="mt-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2">
                                <p class="text-xs font-medium text-amber-700">Jadwal Pengambilan Pre-Order</p>
                                <p class="mt-0.5 text-sm font-medium text-amber-900">
                                    {{ $order->data_pickup_slot['dayPickup'] ?? '-' }}
                                    @if ($order->pickup_time)
                                        · {{ \Carbon\Carbon::parse($order->pickup_time)->format('H:i') }}
                                    @endif
                                </p>
                                @if (!empty($order->data_pickup_slot['start_time']) && !empty($order->data_pickup_slot['end_time']))
                                    <p class="text-xs text-amber-700">
                                        {{ $order->data_pickup_slot['start_time'] }} - {{ $order->data_pickup_slot['end_time'] }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="mt-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2">
                                <p class="text-xs font-medium text-blue-700">Jenis Pesanan</p>
                                <p class="text-sm text-blue-900">Pesanan reguler · Tidak menggunakan jadwal pre-order.</p>
                            </div>
                        @endif

                        @if ($order->payment_method === 'Non Tunai' && $order->paymentBatch)
                            <div class="mt-2 text-sm text-gray-500">
                                Pembayaran:
                                <span class="font-medium text-gray-700">{{ $order->paymentBatch->batch_number }}</span>
                                <span class="text-gray-400">·</span>
                                <span class="font-medium {{ $order->paymentBatch->status === 'Berhasil' ? 'text-green-600' : ($order->paymentBatch->status === 'Pending' ? 'text-orange-600' : 'text-red-600') }}">
                                    {{ $order->paymentBatch->status }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-500">Total</p>
                        <p class="text-lg font-semibold text-gray-900">
                            Rp{{ number_format($order->total_amount, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 divide-y divide-gray-100 rounded-lg bg-gray-50 px-3">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-800">
                                    {{ $item->product->name ?? 'Produk' }}
                                </span>
                                <span class="text-gray-500">× {{ $item->quantity }}</span>

                                @if ($order->order_type === 'pre_order')
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">
                                        Pre-order
                                    </span>
                                @endif

                                @if ($item->notes)
                                    <p class="text-xs italic text-gray-400">
                                        Catatan: {{ $item->notes }}
                                    </p>
                                @endif
                            </div>

                            <span class="whitespace-nowrap text-gray-600">
                                Rp{{ number_format(($item->data_product['price'] ?? 0) * $item->quantity, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($order->status === 'Pending')
                        {{-- @if ($order->payment_method === 'Tunai' || ($order->payment_method === 'Non Tunai' && $order->paymentBatch?->status === 'Berhasil'))
                            <button
                                wire:click="acceptOrder({{ $order->id }})"
                                wire:confirm="Terima pesanan {{ $order->order_number }}?"
                                class="rounded-lg bg-green-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                            >
                                Terima Pesanan
                            </button>
                        @elseif ($order->payment_method === 'Non Tunai')
                            <span class="rounded-lg bg-orange-50 px-3 py-1.5 text-sm text-orange-600">
                                Menunggu pembayaran
                            </span>
                        @endif --}}

                        <button
                            wire:click="cancelOrder({{ $order->id }})"
                            wire:confirm="Batalkan pesanan {{ $order->order_number }}?"
                            class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors duration-200 hover:bg-red-600 hover:text-white"
                        >
                            Batalkan
                        </button>
                    @elseif ($order->status === 'Diproses')
                        <button
                            wire:click="updateStatus({{ $order->id }}, 'Selesai')"
                            wire:confirm="Tandai pesanan {{ $order->order_number }} sebagai selesai?"
                            class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Tandai Selesai
                        </button>
                    @elseif ($order->status === 'Selesai')
                        <span class="text-sm text-gray-400">Pesanan selesai.</span>
                    @elseif ($order->status === 'Dibatalkan')
                        <span class="text-sm text-gray-400">Pesanan dibatalkan.</span>
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