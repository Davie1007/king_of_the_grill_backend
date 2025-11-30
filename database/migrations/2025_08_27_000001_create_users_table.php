<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('role')->default('Cashier');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('photo')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('users'); }
}
