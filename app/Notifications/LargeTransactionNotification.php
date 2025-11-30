<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LargeTransactionNotification extends Notification
{
    public $sale;

    public function __construct($sale)
    {
        $this->sale = $sale;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "Large transaction of {$this->sale->amount} KES (Sale #{$this->sale->id}) at Branch {$this->sale->branch->name}.",
            'link' => "/sale/{$this->sale->id}",
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
            Log::error("Failed to broadcast LargeTransactionNotification: {$e->getMessage()}");
            return null;
        }
    }
}