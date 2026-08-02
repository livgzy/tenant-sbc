<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\QuickOrder;
use App\Models\QuickOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $period = 'custom';

    public string $customFrom = '';
    public string $customTo = '';

    public bool $showQuickOrder = false;
    public array $cart = [];
    public string $quickPaymentMethod = 'Tunai';

    public int $historyPage = 1;

    public bool $showOrderDetail = false;
    public ?array $selectedOrder = null;

    private const HISTORY_PER_PAGE = 15;

    public function mount()
    {
        $this->customFrom = now()->subDays(6)->format('Y-m-d');
        $this->customTo = now()->format('Y-m-d');

        if (! $this->tenant) {
            return redirect()->route('home');
        }
    }

    #[Computed]
    public function tenant()
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        return $reservation?->tenant;
    }

    private function dateRange(): array
    {
        return match ($this->period) {
            'custom' => [
                \Carbon\Carbon::parse($this->customFrom ?: now())->startOfDay(),
                \Carbon\Carbon::parse($this->customTo ?: now())->endOfDay(),
            ],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
        };
    }

    private function ordersQuery()
    {
        [$from, $to] = $this->dateRange();

        return Order::query()
            ->where('data_tenant->reservation_id', $this->tenant->reservation_id)
            ->whereBetween('created_at', [$from, $to]);
    }

    private function quickOrdersQuery()
    {
        [$from, $to] = $this->dateRange();

        return QuickOrder::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereBetween('created_at', [$from, $to]);
    }

    #[Computed]
    public function summary(): array
    {
        $totalOrders = $this->ordersQuery()->count();
        $validOrders = $this->ordersQuery()->where('status', 'Selesai')->count();
        $totalRevenue = (float) $this->ordersQuery()->where('status', 'Selesai')->sum('total_amount');
        $completedOrders = $this->ordersQuery()->where('status', 'Selesai')->count();
        $cancelledOrders = $this->ordersQuery()->where('status', 'Dibatalkan')->count();

        $quickCount = $this->quickOrdersQuery()->count();
        $quickRevenue = (float) $this->quickOrdersQuery()->sum('total_amount');

        $totalOrders += $quickCount;
        $validOrders += $quickCount;
        $totalRevenue += $quickRevenue;
        $completedOrders += $quickCount;

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'avg_order_value' => $validOrders > 0 ? $totalRevenue / $validOrders : 0,
        ];
    }

    #[Computed]
    public function chartData(): array
    {
        [$from, $to] = $this->dateRange();

        $rows = $this->ordersQuery()
            ->where('status', 'Selesai')
            ->selectRaw('DATE(created_at) as tanggal, SUM(total_amount) as total, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $quickRows = $this->quickOrdersQuery()
            ->selectRaw('DATE(created_at) as tanggal, SUM(total_amount) as total, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $labels = [];
        $revenue = [];
        $count = [];

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('d M');
            $revenue[] = (float) ($rows[$key]->total ?? 0) + (float) ($quickRows[$key]->total ?? 0);
            $count[] = (int) ($rows[$key]->jumlah ?? 0) + (int) ($quickRows[$key]->jumlah ?? 0);
            $cursor->addDay();

            if (count($labels) >= 62) {
                break;
            }
        }

        return compact('labels', 'revenue', 'count');
    }

    #[Computed]
    public function topProducts()
    {
        $products = Product::query()->where('tenant_id', $this->tenant->id)->get();

        $items = OrderItem::query()
            ->whereHas('order', function ($q) {
                $q->where('data_tenant->reservation_id', $this->tenant->reservation_id)
                  ->where('status', '!=', 'Dibatalkan');
            })
            ->get()
            ->groupBy('product_id');

        $quickItems = QuickOrderItem::query()
            ->whereHas('order', function ($q) {
                $q->where('tenant_id', $this->tenant->id);
            })
            ->get()
            ->groupBy('product_id');

        return $products->map(function ($product) use ($items, $quickItems) {
            $productItems = $items->get($product->id, collect());
            $quickProductItems = $quickItems->get($product->id, collect());

            $qty = $productItems->sum('quantity') + $quickProductItems->sum('quantity');
            $revenue = $productItems->sum(fn ($i) => $i->quantity * (float) data_get($i->data_product, 'price', 0))
                + $quickProductItems->sum(fn ($i) => $i->quantity * (float) $i->price);

            return [
                'name' => $product->name,
                'is_preorder' => (bool) $product->is_preorder,
                'qty' => $qty,
                'revenue' => $revenue,
            ];
        })
        ->sortByDesc('qty')
        ->values();
    }

    #[Computed]
    public function orderHistory(): array
    {
        $orders = $this->ordersQuery()->get()->map(fn ($order) => (object) [
            'type' => 'preorder',
            'id' => $order->id,
            'key' => 'order-' . $order->id,
            'order_number' => $order->order_number,
            'created_at' => $order->created_at,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'total_amount' => $order->total_amount,
        ]);

        $quickOrders = $this->quickOrdersQuery()->get()->map(fn ($order) => (object) [
            'type' => 'quick',
            'id' => $order->id,
            'key' => 'quick-' . $order->id,
            'order_number' => $order->order_number,
            'created_at' => $order->created_at,
            'status' => 'Selesai',
            'payment_method' => $order->payment_method,
            'total_amount' => $order->total_amount,
        ]);

        $merged = $orders->concat($quickOrders)->sortByDesc('created_at')->values();

        $total = $merged->count();
        $lastPage = max(1, (int) ceil($total / self::HISTORY_PER_PAGE));
        $currentPage = min(max(1, $this->historyPage), $lastPage);

        $items = $merged->slice(($currentPage - 1) * self::HISTORY_PER_PAGE, self::HISTORY_PER_PAGE)->values();

        return [
            'items' => $items,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'total' => $total,
        ];
    }

    public function setHistoryPage(int $page): void
    {
        $this->historyPage = max(1, $page);
        unset($this->orderHistory);
    }

    public function viewOrderDetail(string $type, int $id): void
    {
        $tenant = $this->tenant;

        if ($type === 'preorder') {
            $order = Order::with(['items', 'user'])
                ->where('data_tenant->reservation_id', $this->tenant->reservation_id)
                ->find($id);

            if (! $order) {
                $this->dispatch('notify', type: 'error', message: 'Pesanan tidak ditemukan.');
                return;
            }

            $this->selectedOrder = [
                'type' => 'preorder',
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'status' => $order->status,
                'customer_name' => $order->user->name ?? '-',
                'customer_phone' => $order->user->phone ?? null,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'payment_type' => $this->formatPaymentType(data_get($order->data_payment_method, 'type')),
                'payment_name' => data_get($order->data_payment_method, 'name_payment'),
                'payment_proof_url' => $order->payment_proof_img,
                'total_amount' => $order->total_amount,
                'pickup_day' => data_get($order->data_pickup_slot, 'dayPickup'),
                'pickup_start' => data_get($order->data_pickup_slot, 'start_time'),
                'pickup_end' => data_get($order->data_pickup_slot, 'end_time'),
                'pickup_time' => $order->pickup_time
                    ? \Carbon\Carbon::parse($order->pickup_time)->format('H:i')
                    : null,
                'items' => $order->items->map(fn ($item) => [
                    'name' => data_get($item->data_product, 'name', '-'),
                    'category' => data_get($item->data_product, 'category_name'),
                    'is_preorder' => (bool) data_get($item->data_product, 'is_preorder', false),
                    'price' => (float) data_get($item->data_product, 'price', 0),
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'subtotal' => $item->quantity * (float) data_get($item->data_product, 'price', 0),
                ])->toArray(),
            ];
        } else {
            $order = QuickOrder::with('items.product')
                ->where('tenant_id', $tenant->id)
                ->find($id);

            if (! $order) {
                $this->dispatch('notify', type: 'error', message: 'Pesanan tidak ditemukan.');
                return;
            }

            $this->selectedOrder = [
                'type' => 'quick',
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'status' => 'Selesai',

                'customer_name' => null,
                'customer_phone' => null,

                'payment_method' => $order->payment_method,
                'payment_status' => 'Sudah Dibayar',
                'payment_type' => null,
                'payment_name' => null,
                'payment_proof_url' => null,

                'total_amount' => $order->total_amount,

                'pickup_day' => null,
                'pickup_start' => null,
                'pickup_end' => null,
                'pickup_time' => null,

                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product->name ?? '-',
                    'category' => $item->product->category->name ?? null,
                    'is_preorder' => (bool) ($item->product->is_preorder ?? false),
                    'price' => (float) $item->price,
                    'quantity' => $item->quantity,
                    'notes' => null,
                    'subtotal' => $item->quantity * (float) $item->price,
                ])->toArray(),
            ];
        }

        $this->showOrderDetail = true;
    }

    private function formatPaymentType(?string $type): ?string
    {
        return match ($type) {
            'e_wallet' => 'E Wallet',
            'bank_transfer' => 'Bank Transfer',
            'qris' => 'QRIS',
            default => $type ? Str::headline($type) : null,
        };
    }

    public function closeOrderDetail(): void
    {
        $this->showOrderDetail = false;
        $this->selectedOrder = null;
    }

    public function printReport(): void
    {
        $this->dispatch('open-print-report', url: route('report.print', [
            'from' => $this->customFrom,
            'to' => $this->customTo,
        ]));
    }

    #[Computed]
    public function availableProducts()
    {
        return Product::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('is_preorder', false)
            ->where('is_available', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function cartItems()
    {
        if (empty($this->cart)) {
            return collect();
        }

        $products = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)
            ->map(function ($qty, $productId) use ($products) {
                $product = $products->get($productId);
                if (! $product) {
                    return null;
                }
                return [
                    'product' => $product,
                    'qty' => $qty,
                    'price' => $product->price,
                    'subtotal' => $product->price * $qty,
                ];
            })
            ->filter()
            ->values();
    }

    #[Computed]
    public function cartTotal(): float
    {
        return (float) $this->cartItems->sum('subtotal');
    }

    public function openQuickOrder(): void
    {
        if ($this->availableProducts->isEmpty()) {
            $this->dispatch('notify', type: 'error', message: 'Tidak ada produk non pre-order yang tersedia.');
            return;
        }

        $this->showQuickOrder = true;
    }

    public function closeQuickOrder(): void
    {
        $this->showQuickOrder = false;
        $this->cart = [];
        $this->quickPaymentMethod = 'Tunai';
    }

    public function incrementQty(int $productId): void
    {
        if (! $this->availableProducts->firstWhere('id', $productId)) {
            return;
        }

        $this->cart[$productId] = ($this->cart[$productId] ?? 0) + 1;
        unset($this->cartItems, $this->cartTotal);
    }

    public function decrementQty(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]--;

        if ($this->cart[$productId] <= 0) {
            unset($this->cart[$productId]);
        }

        unset($this->cartItems, $this->cartTotal);
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
        unset($this->cartItems, $this->cartTotal);
    }

    public function submitQuickOrder(): void
    {
        $tenant = $this->tenant;

        if (empty($this->cart)) {
            $this->dispatch('notify', type: 'error', message: 'Keranjang masih kosong.');
            return;
        }

        if (! in_array($this->quickPaymentMethod, ['Tunai', 'Non Tunai'], true)) {
            $this->dispatch('notify', type: 'error', message: 'Metode pembayaran tidak valid.');
            return;
        }

        if (! $tenant->is_open) {
            $this->dispatch('notify', type: 'error', message: 'Tenant harus berstatus buka untuk membuat pesanan langsung.');
            return;
        }

        $products = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        foreach ($this->cart as $productId => $qty) {
            $product = $products->get($productId);
            if (! $product || $product->tenant_id !== $tenant->id || $product->is_preorder || ! $product->is_available || $qty < 1) {
                $this->dispatch('notify', type: 'error', message: 'Salah satu produk di keranjang sudah tidak valid.');
                return;
            }
        }

        $orderNumber = DB::transaction(function () use ($tenant, $products) {
            $totalAmount = 0;
            foreach ($this->cart as $productId => $qty) {
                $totalAmount += $products[$productId]->price * $qty;
            }

            $quick_order = QuickOrder::create([
                'order_number' => $this->generateOrderNumber($tenant),
                'tenant_id' => $tenant->id,
                'total_amount' => $totalAmount,
                'payment_method' => $this->quickPaymentMethod,
            ]);

            foreach ($this->cart as $productId => $qty) {
                $quick_order->items()->create([
                    'product_id' => $products[$productId]->id,
                    'price' => $products[$productId]->price,
                    'quantity' => $qty,
                ]);
            }

            return $quick_order->order_number;
        });

        $this->cart = [];
        $this->showQuickOrder = false;
        $this->quickPaymentMethod = 'Tunai';
        $this->historyPage = 1;

        unset($this->summary, $this->chartData, $this->topProducts, $this->orderHistory, $this->cartItems, $this->cartTotal);

        $this->dispatch('notify', type: 'success', message: "Pesanan langsung {$orderNumber} berhasil dibuat.");
    }

    private function generateOrderNumber(Tenant $tenant): string
    {
        do {
            $orderNumber = 'ORDQ-' . strtoupper($tenant->tenant_code) . '-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        } while (QuickOrder::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function render()
    {
        return $this->view()->layout('layouts::app', ['title' => 'Student Business Corner | Dashboard Report Order']);
    }

    public function updated($name, $value)
    {
        if (! in_array($name, ['customFrom', 'customTo'], true)) {
            return;
        }

        $this->historyPage = 1;
        unset($this->orderHistory);

        $data = $this->chartData;

        $this->dispatch('chart-update',
            labels: $data['labels'] ?? [],
            revenue: $data['revenue'] ?? [],
            count: $data['count'] ?? [],
        );
    }
};
?>

<div
    x-data
    x-on:open-print-report.window="window.open($event.detail.url, '_blank')"
    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Dashboard Laporan Pesanan</h1>
            <p class="text-gray-500 mt-1">Ringkasan penjualan tenant {{ $this->tenant->store_name }}</p>
        </div>
    </div>

    {{-- Filter Tanggal + Laporan Penjualan --}}
    <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                <input type="date" wire:model.live="customFrom" class="rounded-lg border-gray-300 text-sm focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                <input type="date" wire:model.live="customTo" class="rounded-lg border-gray-300 text-sm focus:ring-2 focus:ring-orange-500">
            </div>
        </div>

        <button
            wire:click="printReport"
            class="inline-flex items-center gap-x-1.5 rounded-lg bg-orange-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-orange-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 transition-colors duration-200"
        >
            <flux:icon.document class="size-5"/>
            <span>Laporan Penjualan</span>
        </button>
    </div>

        {{-- Input Pesanan Langsung --}}
    @if ($this->availableProducts->isNotEmpty())
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Pesanan Langsung di Tempat</h3>
            <p class="text-xs text-gray-500 mt-1">Untuk pelanggan yang memesan langsung di lokasi (bukan pre-order).</p>
        </div>
        <button wire:click="openQuickOrder" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition">
            + Buat Pesanan
        </button>
    </div>
    @else
    <div class="rounded-xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-400">
        Tenant belum memiliki produk non pre-order yang tersedia untuk pesanan langsung.
    </div>
    @endif

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Total Pesanan</p>
            <p class="text-xl font-bold text-gray-800">{{ $this->summary['total_orders'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Total Pendapatan</p>
            <p class="text-xl font-bold text-green-600">Rp{{ number_format($this->summary['total_revenue'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Selesai</p>
            <p class="text-xl font-bold text-gray-800">{{ $this->summary['completed_orders'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Dibatalkan</p>
            <p class="text-xl font-bold text-red-500">{{ $this->summary['cancelled_orders'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Rata-rata / Pesanan</p>
            <p class="text-xl font-bold text-gray-800">Rp{{ number_format($this->summary['avg_order_value'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Grafik Penjualan --}}
        <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Grafik Penjualan & Total Pesanan</h3>
                    <p class="text-xs text-gray-500">Pendapatan (Kiri/Rp) & Jumlah Pesanan Selesai (Kanan)</p>
                </div>
            </div>

            <div class="relative">
                <canvas
                    id="salesChart"
                    class="w-full"
                    height="140"
                    wire:ignore
                    data-chart-labels="{{ json_encode($this->chartData['labels'] ?? []) }}"
                    data-chart-revenue="{{ json_encode($this->chartData['revenue'] ?? []) }}"
                    data-chart-count="{{ json_encode($this->chartData['count'] ?? []) }}"
                ></canvas>
            </div>
        </div>

        {{-- Semua Produk (Dengan Badge Status Pre-Order) --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex flex-col h-full max-h-[400px]">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 mb-4">Performa Semua Produk</h3>
            </div>
            <div class="space-y-3 overflow-y-auto pr-2 flex-1">
                @forelse ($this->topProducts as $product)
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-gray-800 truncate max-w-[140px]" title="{{ $product['name'] }}">
                                    {{ $product['name'] }}
                                </p>
                                @if ($product['is_preorder'])
                                    <span class="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                        PO
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                        Ready
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $product['qty'] }} terjual</p>
                        </div>
                        <span class="text-gray-600 font-medium">Rp{{ number_format($product['revenue'], 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada produk yang terdaftar.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Riwayat Pesanan (Pre Order + Quick Order) --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b">
            <h3 class="text-sm font-semibold text-gray-800">Riwayat Pesanan</h3>
            <p class="text-xs text-gray-500 mt-0.5">Gabungan pesanan pre-order dan pesanan langsung. Klik baris untuk melihat detail.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No. Pesanan</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bayar</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->orderHistory['items'] as $order)
                        <tr
                            wire:key="report-order-{{ $order->key }}"
                            wire:click="viewOrderDetail('{{ $order->type }}', {{ $order->id }})"
                            class="cursor-pointer hover:bg-gray-50 transition"
                        >
                            <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ $order->order_number }}</td>
                            <td class="px-4 py-2 text-sm">
                                @if ($order->type === 'preorder')
                                    <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Pre Order</span>
                                @else
                                    <span class="inline-flex items-center rounded bg-orange-50 px-1.5 py-0.5 text-[10px] font-medium text-orange-700 ring-1 ring-inset ring-orange-600/20">Langsung</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $order->status }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $order->payment_method }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-800">Rp{{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada pesanan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->orderHistory['total'] > 0)
            <div class="px-5 py-3 border-t flex items-center justify-between text-xs text-gray-500">
                <span>
                    Halaman {{ $this->orderHistory['current_page'] }} dari {{ $this->orderHistory['last_page'] }}
                    ({{ $this->orderHistory['total'] }} pesanan)
                </span>
                <div class="flex items-center gap-2">
                    <button
                        wire:click="setHistoryPage({{ $this->orderHistory['current_page'] - 1 }})"
                        @disabled($this->orderHistory['current_page'] <= 1)
                        class="rounded-lg border border-gray-200 px-2.5 py-1 font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Sebelumnya
                    </button>
                    <button
                        wire:click="setHistoryPage({{ $this->orderHistory['current_page'] + 1 }})"
                        @disabled($this->orderHistory['current_page'] >= $this->orderHistory['last_page'])
                        class="rounded-lg border border-gray-200 px-2.5 py-1 font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Modal Quick Order --}}
    <div wire:show="showQuickOrder" wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50" wire:click="closeQuickOrder"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-auto max-h-[90vh] flex flex-col">
                <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Buat Pesanan Langsung</h3>
                    <button wire:click="closeQuickOrder" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto grid grid-cols-1 md:grid-cols-3 gap-0 items-start">
                    <div class="md:col-span-2 p-6 grid grid-cols-2 sm:grid-cols-3 gap-3 content-start">
                        @foreach ($this->availableProducts as $product)
                            <div wire:key="qo-product-{{ $product->id }}" class="rounded-xl border border-gray-200 overflow-hidden">
                                <img src="{{ Storage::url($product->product_img) }}" alt="{{ $product->name }}" class="h-20 w-full object-contain">
                                <div class="p-2">
                                    <p class="text-xs font-medium text-gray-800 truncate">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">Rp{{ number_format($product->price, 0, ',', '.') }}</p>
                
                                    @php $qty = $this->cart[$product->id] ?? 0; @endphp
                                    <div class="mt-2 flex items-center justify-between">
                                        <button wire:click="decrementQty({{ $product->id }})" class="w-6 h-6 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200">-</button>
                                        <span class="text-sm font-medium">{{ $qty }}</span>
                                        <button wire:click="incrementQty({{ $product->id }})" class="w-6 h-6 rounded-full bg-orange-500 text-white hover:bg-orange-600">+</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>     
                    <div class="border-t md:border-t-0 md:border-l border-gray-200 p-6 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800">Keranjang</h4>
                
                        @forelse ($this->cartItems as $item)
                            <div wire:key="qo-cart-{{ $item['product']->id }}" class="flex items-center justify-between text-sm">
                                <div>
                                    <p class="text-gray-800">{{ $item['product']->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $item['qty'] }} x Rp{{ number_format($item['product']->price, 0, ',', '.') }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-700">Rp{{ number_format($item['subtotal'], 0, ',', '.') }}</span>
                                    <button wire:click="removeFromCart({{ $item['product']->id }})" class="text-red-500 hover:text-red-700 text-xs">Hapus</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Belum ada produk dipilih.</p>
                        @endforelse
                
                        <div>
                            <p class="text-xs font-medium text-gray-500 mb-2">Metode Pembayaran</p>
                            <div class="flex gap-2">
                                @foreach (['Tunai', 'Non Tunai'] as $method)
                                    <label class="flex-1 cursor-pointer">
                                        <input
                                            type="radio"
                                            wire:model.live="quickPaymentMethod"
                                            value="{{ $method }}"
                                            class="peer sr-only"
                                        >
                                        <div class="text-center px-3 py-2 rounded-lg text-sm font-medium border transition-all duration-200
                                            bg-white text-gray-600 border-gray-200 shadow-sm
                                            peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 peer-checked:shadow-md
                                            hover:bg-gray-50">
                                            {{ $method }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                
                        <div class="border-t pt-3 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-800">Total</span>
                            <span class="text-lg font-bold text-gray-900">Rp{{ number_format($this->cartTotal, 0, ',', '.') }}</span>
                        </div>
                
                        <button
                            wire:click="submitQuickOrder"
                            wire:confirm="Buat pesanan langsung senilai Rp{{ number_format($this->cartTotal, 0, ',', '.') }}?"
                            class="w-full rounded-lg bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600 transition"
                        >
                            Buat Pesanan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detail Pesanan --}}
    <div wire:show="showOrderDetail" wire:cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50" wire:click="closeOrderDetail"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full mx-auto max-h-[90vh] flex flex-col">
                <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Detail Pesanan</h3>
                    <button wire:click="closeOrderDetail" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                @if ($selectedOrder)
                    <div class="flex-1 overflow-y-auto p-6 space-y-4">

                        {{-- Header --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $selectedOrder['order_number'] }}</p>
                                <p class="text-xs text-gray-500">{{ $selectedOrder['created_at'] }}</p>
                            </div>
                            @if ($selectedOrder['type'] === 'preorder')
                                <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Pre Order</span>
                            @else
                                <span class="inline-flex items-center rounded bg-orange-50 px-1.5 py-0.5 text-[10px] font-medium text-orange-700 ring-1 ring-inset ring-orange-600/20">Langsung</span>
                            @endif
                        </div>

                        {{-- Pelanggan --}}
                        @if ($selectedOrder['customer_name'])
                            <div class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="text-xs text-gray-500">Pelanggan</p>
                                <p class="text-sm font-medium text-gray-800">
                                    {{ $selectedOrder['customer_name'] }}
                                    @if ($selectedOrder['customer_phone'])
                                        &middot; {{ $selectedOrder['customer_phone'] }}
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Status & Pembayaran --}}
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-500">Status Pesanan</p>
                                <p @class([
                                    'font-medium inline-flex items-center rounded px-1.5 py-0.5 text-xs ring-1 ring-inset mt-0.5',
                                    'bg-yellow-50 text-yellow-700 ring-yellow-600/20' => $selectedOrder['status'] === 'Pending',
                                    'bg-blue-50 text-blue-700 ring-blue-600/20' => $selectedOrder['status'] === 'Diproses',
                                    'bg-purple-50 text-purple-700 ring-purple-600/20' => $selectedOrder['status'] === 'Siap Diambil',
                                    'bg-green-50 text-green-700 ring-green-600/20' => $selectedOrder['status'] === 'Selesai',
                                    'bg-red-50 text-red-700 ring-red-600/20' => $selectedOrder['status'] === 'Dibatalkan',
                                ])>{{ $selectedOrder['status'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Status Bayar</p>
                                <p @class([
                                    'font-medium inline-flex items-center rounded px-1.5 py-0.5 text-xs ring-1 ring-inset mt-0.5',
                                    'bg-red-50 text-red-700 ring-red-600/20' => $selectedOrder['payment_status'] === 'Belum Dibayar',
                                    'bg-yellow-50 text-yellow-700 ring-yellow-600/20' => $selectedOrder['payment_status'] === 'Menunggu Konfirmasi',
                                    'bg-green-50 text-green-700 ring-green-600/20' => $selectedOrder['payment_status'] === 'Sudah Dibayar',
                                ])>{{ $selectedOrder['payment_status'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Metode Pembayaran</p>
                                <p class="font-medium text-gray-800">{{ $selectedOrder['payment_method'] }}</p>
                            </div>
                            @if ($selectedOrder['payment_type'] || $selectedOrder['payment_name'])
                                <div>
                                    <p class="text-xs text-gray-500">Detail Pembayaran</p>
                                    <p class="font-medium text-gray-800">
                                        {{ $selectedOrder['payment_type'] }}
                                        @if ($selectedOrder['payment_name']) &middot; {{ $selectedOrder['payment_name'] }} @endif
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Bukti Pembayaran (Non Tunai) --}}
                        @if ($selectedOrder['payment_proof_url'])
                        <div x-data="{ showImagePreview: false }">
                            <p class="text-xs text-gray-500 mb-1">Bukti Pembayaran</p>

                            <button type="button" @click="showImagePreview = true" class="group relative inline-block">
                                <img src="{{ Storage::url($selectedOrder['payment_proof_url']) }}"
                                    alt="Bukti Pembayaran"
                                    class="w-full max-h-56 object-contain rounded-lg border border-gray-200 transition group-hover:brightness-90">
                                <span class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/20 rounded-lg transition">
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
                                {{-- Backdrop --}}
                                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showImagePreview = false"></div>

                                {{-- Konten Image --}}
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
                                    {{-- Tombol Close --}}
                                    <button type="button" @click="showImagePreview = false"
                                            class="absolute -top-10 right-0 text-white/80 hover:text-white transition">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>

                                    {{-- Gambar Full --}}
                                    <img src="{{ Storage::url($selectedOrder['payment_proof_url']) }}"
                                        alt="Bukti Pembayaran"
                                        class="w-full max-h-[85vh] object-contain rounded-xl shadow-2xl">
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Jadwal Pengambilan --}}
                        @if ($selectedOrder['pickup_day'])
                            <div>
                                <p class="text-xs text-gray-500">Jadwal Pengambilan</p>
                                <p class="font-medium text-gray-800">
                                    {{ $selectedOrder['pickup_day'] }}, {{ $selectedOrder['pickup_start'] }} - {{ $selectedOrder['pickup_end'] }}
                                    @if ($selectedOrder['pickup_time'])
                                        <span class="text-xs text-gray-500">(dipilih: {{ $selectedOrder['pickup_time'] }})</span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Item Pesanan --}}
                        <div class="border-t pt-3 space-y-3">
                            <p class="text-xs font-medium text-gray-500 uppercase">Item Pesanan</p>
                            @foreach ($selectedOrder['items'] as $item)
                                <div class="flex items-start justify-between text-sm">
                                    <div class="flex-1 pr-2">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <p class="text-gray-800 font-medium">{{ $item['name'] }}</p>
                                            @if ($item['is_preorder'])
                                                <span class="inline-flex items-center rounded bg-amber-50 px-1 py-0.5 text-[9px] font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">PO</span>
                                            @endif
                                        </div>
                                        @if ($item['category'])
                                            <p class="text-[11px] text-gray-400">{{ $item['category'] }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500">{{ $item['quantity'] }} x Rp{{ number_format($item['price'], 0, ',', '.') }}</p>
                                        @if ($item['notes'])
                                            <p class="text-xs text-gray-500 italic mt-0.5">Catatan: {{ $item['notes'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-gray-700 font-medium whitespace-nowrap">Rp{{ number_format($item['subtotal'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="border-t pt-3 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-800">Total</span>
                            <span class="text-lg font-bold text-gray-900">Rp{{ number_format($selectedOrder['total_amount'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Chart.js Script --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const init = () => {
                const canvas = document.getElementById('salesChart');
                if (!canvas || !window.Chart) return;

                const getLabels = () => { try { return JSON.parse(canvas.getAttribute('data-chart-labels') || '[]'); } catch (e) { return []; } };
                const getRevenue = () => { try { return JSON.parse(canvas.getAttribute('data-chart-revenue') || '[]'); } catch (e) { return []; } };
                const getCount = () => { try { return JSON.parse(canvas.getAttribute('data-chart-count') || '[]'); } catch (e) { return []; } };

                const ctx = canvas.getContext('2d');
                window.__salesChart && window.__salesChart.destroy();

                window.__salesChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: getLabels(),
                        datasets: [
                            {
                                label: 'Pendapatan (Rp)',
                                data: getRevenue(),
                                backgroundColor: 'rgba(249, 115, 22, 0.6)',
                                borderColor: 'rgba(249, 115, 22, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Total Pesanan',
                                data: getCount(),
                                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const value = context.parsed.y || 0;
                                        if (context.datasetIndex === 0) return ' Pendapatan: Rp' + Number(value).toLocaleString('id-ID');
                                        return ' Total Pesanan: ' + value + ' pesanan';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
                            y: { type: 'linear', position: 'left', beginAtZero: true, ticks: { callback: (val) => 'Rp' + Number(val).toLocaleString('id-ID') } },
                            y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0, callback: (val) => val + ' pesanan' } }
                        }
                    }
                });
            };

            init();
            window.addEventListener('chart-update', (e) => {
                const detail = e.detail || {};
                if (!window.__salesChart) init();
                if (!window.__salesChart) return;
                window.__salesChart.data.labels = detail.labels || [];
                window.__salesChart.data.datasets[0].data = detail.revenue || [];
                window.__salesChart.data.datasets[1].data = detail.count || [];
                window.__salesChart.update();
            });

            if (window.Livewire) {
                window.Livewire.hook('message.processed', () => {
                    const canvas = document.getElementById('salesChart');
                    if (!canvas) return;
                    if (!window.__salesChart) { init(); return; }
                    try { window.__salesChart.destroy(); } catch (e) {}
                    init();
                });
                window.Livewire.on('chart-update', ({ labels, revenue, count }) => {
                    window.dispatchEvent(new CustomEvent('chart-update', { detail: { labels, revenue, count } }));
                });
            }
            document.addEventListener('livewire:navigated', () => init());
        })();
    </script>

    {{-- Toast Notification --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="
            show = false;
            $nextTick(() => {
                message = $event.detail.message;
                type = $event.detail.type ?? 'success';
                show = true;
                setTimeout(() => show = false, 3000);
            });
        "
        x-show="show"
        x-transition
        x-cloak
        class="fixed bottom-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white"
        :class="type === 'error' ? 'bg-red-500' : 'bg-green-500'">
        <span x-text="message"></span>
    </div>
</div>