<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditSalesTable extends Migration
{
    public function up()
    {
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->string('customer');
            $table->string('customer_phone')->nullable();
            $table->decimal('total_amount',12,2);
            $table->decimal('amount_paid',12,2)->default(0);
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('credit_sales'); }
}
