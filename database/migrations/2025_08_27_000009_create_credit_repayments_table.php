<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditRepaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('credit_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_sale_id')->constrained('credit_sales')->cascadeOnDelete();
            $table->decimal('amount',12,2);
            $table->string('payment_method')->default('Cash');
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('credit_repayments'); }
}
