<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class SystemSecurityNotification extends Notification
{
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => $this->message,
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
            Log::error("Failed to broadcast SystemSecurityNotification: {$e->getMessage()}");
            return null;
        }
    }
}
