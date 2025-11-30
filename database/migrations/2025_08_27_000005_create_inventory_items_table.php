<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryItemsTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price',10,2);
            $table->decimal('stock',10,2)->default(0);
            $table->string('unit',10)->default('kg');
            $table->boolean('isButchery')->default(true);
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('inventory_items'); }
}
