<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\QuickOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantReportPrintController extends Controller
{
    public function __invoke(Request $request)
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation?->tenant;

        abort_unless($tenant, 403);

        $from = Carbon::parse($request->query('from') ?: now()->subDays(6))->startOfDay();
        $to = Carbon::parse($request->query('to') ?: now())->endOfDay();

        $orders = Order::query()
            ->where('data_tenant->reservation_id', $reservation->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $quickOrders = QuickOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $history = $orders->map(fn ($order) => (object) [
                'type' => 'preorder',
                'order_number' => $order->order_number,
                'created_at' => $order->created_at,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'total_amount' => (float) $order->total_amount,
            ])
            ->concat($quickOrders->map(fn ($order) => (object) [
                'type' => 'quick',
                'order_number' => $order->order_number,
                'created_at' => $order->created_at,
                'status' => 'Selesai',
                'payment_method' => 'Dibayar di Tempat',
                'total_amount' => (float) $order->total_amount,
            ]))
            ->sortBy('created_at')
            ->values();

        $completedOrders = $orders->where('status', 'Selesai')->count() + $quickOrders->count();
        $cancelledOrders = $orders->where('status', 'Dibatalkan')->count();
        $totalRevenue = (float) $orders->where('status', 'Selesai')->sum('total_amount') + (float) $quickOrders->sum('total_amount');
        $avgOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;

        return view('report-print', [
            'tenant' => $tenant,
            'from' => $from,
            'to' => $to,
            'history' => $history,
            'totalOrders' => $history->count(),
            'totalRevenue' => $totalRevenue,
            'completedOrders' => $completedOrders,
            'cancelledOrders' => $cancelledOrders,
            'avgOrderValue' => $avgOrderValue,
        ]);
    }
}