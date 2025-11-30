<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\PaymentReceived;
use App\Models\Sale;
use App\Models\Branch;

class TestBroadcast extends Command
{
    protected $signature = 'test:broadcast {branch_id}';
    protected $description = 'Test broadcasting PaymentReceived event';

    public function handle()
    {
        $branchId = $this->argument('branch_id');
        $branch = Branch::find($branchId);

        if (!$branch) {
            $this->error("Branch $branchId not found");
            return;
        }

        $this->info("Broadcasting to channel: payments.$branchId");

        // Create a fake sale object for the event
        $sale = new Sale();
        $sale->id = 99999;
        $sale->branch_id = $branchId;
        $sale->total = 100;
        $sale->payment_method = 'M-Pesa';

        $receipt = [
            'receipt_no' => 'TEST123456',
            'branch' => ['name' => $branch->name],
            'timestamp' => now()->toDateTimeString(),
            'payment_method' => 'M-Pesa',
            'total' => 100,
            'amount_paid' => 100,
            'customer_telephone' => '254700000000',
            'transactionId' => 'TEST123456',
            'sale_id' => 99999,
        ];

        broadcast(new PaymentReceived($sale, 100, 'TEST123456', $receipt));

        $this->info("Event broadcasted!");
    }
}
