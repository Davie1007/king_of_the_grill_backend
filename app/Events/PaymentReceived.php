<?php

namespace App\Events;

use App\Models\Sale;
use App\Models\CreditSale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $model;
    public $amount;
    public $transactionId;
    public $receipt;
    public $cartRef;

    public function __construct($model, $amount, $transactionId, $receipt, $cartRef = null)
    {
        $this->model = $model;
        $this->amount = $amount;
        $this->transactionId = $transactionId;
        $this->receipt = $receipt;
        $this->cartRef = $cartRef;
    }

    public function broadcastOn()
    {
        return new Channel('payments.' . $this->model->branch_id);
    }

    public function broadcastWith()
    {
        $saleId = $this->model instanceof Sale ? $this->model->id : ($this->model->sale_id ?? null);
        $cartRef = $this->receipt['cart_ref'] ?? null;

        return [
            'type' => $this->model instanceof Sale ? 'sale' : 'credit',
            'amount' => $this->amount,
            'transaction_id' => $this->transactionId,
            'receipt' => $this->receipt,
            'sale_id' => $saleId,
            'cart_ref' => $cartRef,
        ];
    }

}
