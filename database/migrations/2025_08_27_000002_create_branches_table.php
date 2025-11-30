<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchesTable extends Migration
{
    public function up()
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('gas');;
            $table->string('tillNumber')->unique();
            $table->string('roleConfig')->default('combined');
            $table->string('managerId')->default('OWNER');
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('branches'); }
}
