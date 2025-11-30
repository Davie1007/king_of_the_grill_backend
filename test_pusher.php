<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Models\Branch;
use App\Events\PaymentReceived;

// Get the most recent sale for branch 2
$sale = Sale::where('branch_id', 2)->latest()->first();

if (!$sale) {
    echo "❌ No sales found for branch 2. Create a sale first.\n";
    exit(1);
}

echo "📡 Testing broadcast for Sale #{$sale->id} on branch {$sale->branch_id}\n";
echo "Channel: payments.{$sale->branch_id}\n";

$receipt = [
    'sale_id' => $sale->id,
    'branch_id' => $sale->branch_id,
    'total_amount' => $sale->total_amount,
    'payment_method' => 'M-Pesa',
    'transaction_id' => 'TEST_' . time(),
    'timestamp' => now()->toISOString(),
];

broadcast(new PaymentReceived($sale, $sale->total_amount, 'TEST_' . time(), $receipt))->toOthers();

echo "✅ Broadcast sent successfully!\n";
echo "Check your frontend browser console for the event.\n";
