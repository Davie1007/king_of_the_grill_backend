<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseItemsTable extends Migration
{
    public function up()
    {
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('kg');
            $table->decimal('stock',12,2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('warehouse_items'); }
}
