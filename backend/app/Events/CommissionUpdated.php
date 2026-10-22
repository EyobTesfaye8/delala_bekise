<?php

namespace App\Events;

use App\Models\ProposalItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProposalItem $proposalItem,
        public int $oldAmountSantim,
        public int $newAmountSantim
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $chatId = $this->proposalItem->proposal->chat?->id;

        return $chatId ? [new PrivateChannel("chat.{$chatId}")] : [];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'CommissionUpdated';
    }
}
