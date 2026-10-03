<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('cashier_id');
            $table->date('business_date');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2);
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('cash_variance', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            // Penanda unik shift aktif per kasir; NULL saat closed (UNIQUE abaikan NULL).
            $table->string('active_guard', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['outlet_id', 'business_date']);
            $table->index(['cashier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
