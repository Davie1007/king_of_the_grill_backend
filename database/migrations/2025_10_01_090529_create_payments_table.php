<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();   // Mpesa receipt / TransID
            $table->decimal('amount', 12, 2);
            $table->string('phone')->nullable();
            $table->string('method')->default('M-Pesa');  // could be M-Pesa, cash, etc.
            $table->boolean('used')->default(false);      // consumed for sale/credit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
