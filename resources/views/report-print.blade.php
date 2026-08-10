<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan - {{ $tenant->store_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: A4; margin: 14mm; }
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        body { font-family: ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    {{-- Toolbar, hilang otomatis saat dicetak --}}
    <div class="no-print sticky top-0 z-10 bg-white border-b px-6 py-3 flex items-center justify-between">
        <p class="text-sm text-gray-500">Pratinjau laporan — gunakan tombol di kanan untuk mencetak atau simpan sebagai PDF.</p>
        <button onclick="window.print()" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition">
            Cetak / Simpan PDF
        </button>
    </div>

    <div class="max-w-3xl mx-auto bg-white my-6 p-8 shadow-sm print:shadow-none print:my-0 print:max-w-none">

        {{-- Kop Laporan --}}
        <div class="text-center border-b pb-4 mb-6">
            {{-- Logo di atas teks, ukuran disesuaikan menjadi h-16 (lebih proporsional untuk susunan vertikal) --}}
            <h1 class="text-lg font-bold text-gray-900">STUDENT BUSINESS CORNER</h1>
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url('logo/logo_ucic.png') }}" 
                 alt="Logo" 
                 class="h-16 w-auto mx-auto object-contain mb-3">
                 
            <p class="text-sm text-gray-600">Universitas Catur Insan Cendekia (UCIC)</p>
            <p class="text-sm font-semibold text-gray-800 mt-2">Laporan Penjualan Tenant</p>
        </div>

        {{-- Info Tenant --}}
        <div class="grid grid-cols-2 gap-4 text-sm mb-6">
            <div>
                <p class="text-xs text-gray-500">Nama Tenant</p>
                <p class="font-medium">{{ $tenant->store_name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Kode Tenant</p>
                <p class="font-medium">{{ $tenant->tenant_code }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Periode Laporan</p>
                <p class="font-medium">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Tanggal Dicetak</p>
                <p class="font-medium">{{ now()->format('d M Y H:i') }}</p>
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="grid grid-cols-5 gap-2 text-center mb-6">
            <div class="border rounded-lg p-2">
                <p class="text-[10px] text-gray-500">Total Pesanan</p>
                <p class="text-sm font-bold">{{ $totalOrders }}</p>
            </div>
            <div class="border rounded-lg p-2">
                <p class="text-[10px] text-gray-500">Selesai</p>
                <p class="text-sm font-bold">{{ $completedOrders }}</p>
            </div>
            <div class="border rounded-lg p-2">
                <p class="text-[10px] text-gray-500">Dibatalkan</p>
                <p class="text-sm font-bold">{{ $cancelledOrders }}</p>
            </div>
            <div class="border rounded-lg p-2">
                <p class="text-[10px] text-gray-500">Rata-rata</p>
                <p class="text-sm font-bold">Rp{{ number_format($avgOrderValue, 0, ',', '.') }}</p>
            </div>
            <div class="border rounded-lg p-2 bg-orange-50">
                <p class="text-[10px] text-gray-500">Total Pendapatan</p>
                <p class="text-sm font-bold text-orange-700">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Tabel Riwayat --}}
        <table class="w-full text-xs border-collapse mb-6">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border px-2 py-1.5 text-left w-8">No</th>
                    <th class="border px-2 py-1.5 text-left">No. Pesanan</th>
                    <th class="border px-2 py-1.5 text-left">Jenis</th>
                    <th class="border px-2 py-1.5 text-left">Tanggal</th>
                    <th class="border px-2 py-1.5 text-left">Status</th>
                    <th class="border px-2 py-1.5 text-left">Bayar</th>
                    <th class="border px-2 py-1.5 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $i => $order)
                    <tr class="break-inside-avoid">
                        <td class="border px-2 py-1.5">{{ $i + 1 }}</td>
                        <td class="border px-2 py-1.5">{{ $order->order_number }}</td>
                        <td class="border px-2 py-1.5">{{ $order->type === 'preorder' ? 'Pre Order' : 'Langsung' }}</td>
                        <td class="border px-2 py-1.5">{{ $order->created_at->format('d/m/y H:i') }}</td>
                        <td class="border px-2 py-1.5">{{ $order->status }}</td>
                        <td class="border px-2 py-1.5">{{ $order->payment_method }}</td>
                        <td class="border px-2 py-1.5 text-right">Rp{{ number_format($order->total_amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border px-2 py-6 text-center text-gray-400">Tidak ada transaksi pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($history->isNotEmpty())
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td colspan="6" class="border px-2 py-1.5 text-right">Total Keseluruhan</td>
                        <td class="border px-2 py-1.5 text-right">Rp{{ number_format($history->sum('total_amount'), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        {{-- Tanda Tangan --}}
        <div class="grid grid-cols-2 gap-6 mt-10 text-sm">
            <div></div>
            <div class="text-center">
                <p>{{ now()->format('d F Y') }}</p>
                <p class="mt-1">Pemilik Tenant,</p>
                <div class="h-16"></div>
                <p class="font-semibold border-t inline-block pt-1 px-6">{{ $tenant->store_name }}</p>
            </div>
        </div>
    </div>
</body>
</html>