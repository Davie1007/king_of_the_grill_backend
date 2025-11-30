<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('idNumber')->unique();
            $table->string('position');
            $table->float('experience')->default(0);
            $table->boolean('suspended')->default(false);
            $table->string('status')->default('active');
            $table->timestamp('suspensionDate')->nullable();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('employees'); }
}
