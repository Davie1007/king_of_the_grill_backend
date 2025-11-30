<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cartRef;
    public $errorCode;
    public $errorMessage;

    public function __construct($cartRef, $errorCode, $errorMessage)
    {
        $this->cartRef = $cartRef;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public function broadcastOn()
    {
        return new Channel('payments.' . $this->cartRef);
    }

    public function broadcastWith()
    {
        return [
            'type' => 'payment_failed',
            'cart_ref' => $this->cartRef,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }
}