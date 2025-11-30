<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class UnreachablePosApiNotification extends Notification
{
    public $system;

    public function __construct($system)
    {
        $this->system = $system;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "{$this->system} POS is unreachable.",
            'link' => null,
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
            Log::error("Failed to broadcast UnreachablePosApiNotification: {$e->getMessage()}");
            return null;
        }
    }
}