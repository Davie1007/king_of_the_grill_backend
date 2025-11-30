<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleItemsTable extends Migration
{
    public function up()
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity',10,2);
            $table->decimal('price',10,2);
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('sale_items'); }
}
