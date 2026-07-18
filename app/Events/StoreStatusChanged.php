<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tenantId;
    public bool $isOpen;
    public string $storeName;

    public function __construct(Tenant $tenant)
    {
        $this->tenantId  = $tenant->id;
        $this->isOpen    = $tenant->is_open;
        $this->storeName = $tenant->store_name;
    }

    /**
     * Channel publik per-tenant. Semua user (Admin/Tenant/User app)
     * yang subscribe channel ini akan menerima update realtime.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->tenantId . '.status'),
            new Channel('tenants.status'),
        ];
    }

    /**
     * Nama event yang diterima di sisi JS (Echo.listen('.store-status-changed', ...))
     */
    public function broadcastAs(): string
    {
        return 'store-status-changed';
    }

    /**
     * Payload yang dikirim ke client.
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id'  => $this->tenantId,
            'is_open'    => $this->isOpen,
            'store_name' => $this->storeName,
        ];
    }
}
