<?php

use App\Models\Order;
use Livewire\Component;
use Livewire\Attributes\Computed;


new class extends Component
{
    #[Computed]
    public function orderCounts()
    {
        $reservation = Auth::guard('tenant')->user()->reservation()->latest()->first();
        $tenant = $reservation ? $reservation->tenant : null;

        return Order::where('status', 'Pending')
        ->where('data_tenant->reservation_id', $reservation->id)
        ->count();
    }
};
?>

<div class="ml-auto">
    @if($this->orderCounts > 0)
        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full shadow-sm">
            {{ $this->orderCounts }}
        </span>
    @endif
</div>