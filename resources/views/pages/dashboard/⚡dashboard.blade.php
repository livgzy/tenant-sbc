<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component
{
    public int $perPage = 10;

    #[Computed]
    public function tenant(): ?Tenant
    {
        $reservation = Auth::guard('tenant')->user()?->reservation()->latest()->first();
        return $reservation?->tenant;
    }

    private function ordersQuery()
    {
        $tenant = $this->tenant;

        if (!$tenant) {
            return Order::query()->whereRaw('1 = 0');
        }

        return Order::query()
            ->where('reservation_id', $tenant->reservation_id);
    }

    private function preOrderQuery()
    {
        return $this->ordersQuery()
            ->where('order_type', 'pre_order');
    }

    private function regularOrderQuery()
    {
        return $this->ordersQuery()
            ->where('order_type', 'reguler');
    }

    #[Computed]
    public function totalPreOrders(): int
    {
        return $this->preOrderQuery()->count();
    }

    #[Computed]
    public function totalRegularOrders(): int
    {
        return $this->regularOrderQuery()->count();
    }

    #[Computed]
    public function totalOrders(): int
    {
        return $this->ordersQuery()->count();
    }

    #[Computed]
    public function processingOrders(): int
    {
        return $this->ordersQuery()
            ->whereIn('status', ['Pending', 'Diproses'])
            ->count();
    }

    #[Computed]
    public function completedOrders(): int
    {
        return $this->ordersQuery()
            ->where('status', 'Selesai')
            ->count();
    }

    #[Computed]
    public function cancelledOrders(): int
    {
        return $this->ordersQuery()
            ->where('status', 'Dibatalkan')
            ->count();
    }

    #[Computed]
    public function preOrderRevenue(): float
    {
        return (float) $this->preOrderQuery()
            ->where('status', 'Selesai')
            ->sum('total_amount');
    }

    #[Computed]
    public function regularOrderRevenue(): float
    {
        return (float) $this->regularOrderQuery()
            ->where('status', 'Selesai')
            ->sum('total_amount');
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return (float) $this->ordersQuery()
            ->where('status', 'Selesai')
            ->sum('total_amount');
    }

    #[Computed]
    public function totalMenus(): int
    {
        $tenant = $this->tenant;

        if (!$tenant) {
            return 0;
        }

        return Product::query()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    #[Computed]
    public function recentOrders()
    {
        return $this->ordersQuery()
            ->latest()
            ->limit($this->perPage)
            ->get()
            ->map(fn ($order) => (object) [
                'type' => $order->order_type,
                'key' => 'order-' . $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at,
                'status' => $order->status,
                'payment_method' => $order->payment_status ?? $order->payment_method,
                'total_amount' => $order->total_amount ?? 0,
            ]);
    }

    public function render()
    {
        return $this->view()->layout('layouts::app', ['title' => 'Student Business Corner | Dashboard']);
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">
                Dashboard {{ $this->tenant?->store_name ? '— ' . $this->tenant->store_name : '' }}
            </h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($this->tenant)
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $this->tenant->is_open ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                    <span class="w-2 h-2 rounded-full {{ $this->tenant->is_open ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    {{ $this->tenant->is_open ? 'Tenant Buka' : 'Tenant Tutup' }}
                </span>
                <span class="inline-flex items-center rounded-full bg-purple-100 text-purple-800 px-3 py-1 text-xs font-semibold">
                    Tenant {{ $this->tenant->tenant_code }}
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-600 px-3 py-1 text-xs font-semibold">
                    Tenant belum siap
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Total Pesanan</p>
            <p class="text-xl font-bold text-gray-900">{{ $this->totalOrders }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Diproses (Pending+Diproses)</p>
            <p class="text-xl font-bold text-orange-600">{{ $this->processingOrders }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Selesai</p>
            <p class="text-xl font-bold text-green-600">{{ $this->completedOrders }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Dibatalkan</p>
            <p class="text-xl font-bold text-red-600">{{ $this->cancelledOrders }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Total Pendapatan</p>
            <p class="text-xl font-bold text-gray-900">Rp{{ number_format($this->totalRevenue, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                        Pre Order
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-2">{{ $this->totalPreOrders }} pesanan</p>
                <p class="text-lg font-bold text-gray-900">Rp{{ number_format($this->preOrderRevenue, 0, ',', '.') }}</p>
            </div>
            <a href="/dashboard/order?type=pre_order" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">Kelola →</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                        Reguler
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-2">{{ $this->totalRegularOrders }} pesanan</p>
                <p class="text-lg font-bold text-gray-900">Rp{{ number_format($this->regularOrderRevenue, 0, ',', '.') }}</p>
            </div>
            <a href="/dashboard/order?type=reguler" class="text-xs font-semibold text-green-600 hover:text-green-700">Kelola →</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Akses Cepat</h3>
                        <p class="text-xs text-gray-500 mt-1">Menuju halaman penting tenant Anda</p>
                    </div>
                    <span class="text-xs font-semibold px-2 py-1 rounded-full bg-orange-50 text-orange-700">{{ $this->totalMenus }} menu</span>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3">
                    <a href="/dashboard/order" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 hover:bg-orange-50 transition">
                        <span class="text-sm font-medium text-gray-800">Pesanan Masuk</span>
                        <span class="text-xs text-gray-500">Kelola status</span>
                    </a>
                    <a href="/dashboard/menu" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 hover:bg-orange-50 transition">
                        <span class="text-sm font-medium text-gray-800">Kelola Menu</span>
                        <span class="text-xs text-gray-500">Edit ketersediaan</span>
                    </a>
                    <a href="/dashboard/report" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 hover:bg-orange-50 transition">
                        <span class="text-sm font-medium text-gray-800">Laporan Penjualan</span>
                        <span class="text-xs text-gray-500">Grafik & ringkasan</span>
                    </a>
                    <a href="/dashboard/tenant/profile" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 hover:bg-orange-50 transition">
                        <span class="text-sm font-medium text-gray-800">Profil Tenant</span>
                        <span class="text-xs text-gray-500">Atur jam buka</span>
                    </a>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-800">Catatan</h3>
                <ul class="mt-3 space-y-2 text-sm text-gray-600">
                    <li class="flex gap-2">
                        <span class="mt-1 w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                        Pastikan status tenant sesuai jadwal agar pelanggan bisa memesan.
                    </li>
                </ul>
            </div>
        </div>

        <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Pesanan Terbaru</h3>
                </div>
                <a href="/dashboard/report" class="text-sm font-semibold text-orange-600 hover:text-orange-700">Lihat semua →</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">No.</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bayar</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->recentOrders as $order)
                            <tr wire:key="dash-recent-{{ $order->key }}" class="hover:bg-orange-50/40">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800">{{ $order->order_number }}</td>
                                <td class="px-5 py-3 text-sm">
                                    @if($order->type === 'pre_order')
                                        <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                                            Pre Order
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                            Reguler
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-700">{{ $order->status }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ $order->payment_method }}</td>
                                <td class="px-5 py-3 text-sm text-right font-semibold text-gray-900">Rp{{ number_format($order->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada pesanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>