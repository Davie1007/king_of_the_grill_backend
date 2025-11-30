<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('supplier')->nullable();
            $table->string('reference_no')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('category')->nullable(); // e.g. Fuel, Utilities
            $table->text('description')->nullable();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
