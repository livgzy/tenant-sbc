<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductAvailabilityChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $productId;
    public bool $isAvailable;
    /**
     * Create a new event instance.
     */
    public function __construct(Product $product)
    {
        $this->productId = $product->id;
        $this->isAvailable = $product->is_available;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('products'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'productId'   => $this->productId,
            'isAvailable' => $this->isAvailable,
        ];
    }
}
