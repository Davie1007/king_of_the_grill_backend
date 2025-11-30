<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LowStockNotification extends Notification
{
    public $item;

    public function __construct($item)
    {
        $this->item = $item;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "{$this->item->name} stock at Branch {$this->item->branch->name} is below 10 units.",
            'link' => "/inventory/{$this->item->id}",
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        try {
            return (new BroadcastMessage($this->toArray($notifiable)))
                ->onConnection('database') // Use your queue connection (e.g., redis, database)
                ->onQueue('notifications');
        } catch (\Exception $e) {
            Log::error("Failed to broadcast LowStockNotification: {$e->getMessage()}");
            return null;
        }
    }
}