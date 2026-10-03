<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->enum('role', ['superadmin', 'kasir', 'supervisor_pos', 'staff_finance', 'manager_finance']);
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('role');
            $table->index('outlet_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
