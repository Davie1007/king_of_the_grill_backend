<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('payment_method');
            $table->decimal('total',10,2);
            $table->string('seller_id')->nullable();
            $table->decimal('cash_tendered',10,2)->nullable();
            $table->decimal('change',10,2)->nullable();
            $table->string('payment_status')->default('Unpaid');
            $table->string('customer_name')->nullable();
            $table->string('customer_id_number')->nullable();
            $table->string('customer_telephone_number')->nullable();
            $table->foreignId('credit_sale_id')->nullable()->constrained('credit_sales')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('sales'); }
}
