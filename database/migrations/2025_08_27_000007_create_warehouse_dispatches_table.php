<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseDispatchesTable extends Migration
{
    public function up()
    {
        Schema::create('warehouse_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_item_id')->constrained('warehouse_items')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('quantity',12,2);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('warehouse_dispatches'); }
}
