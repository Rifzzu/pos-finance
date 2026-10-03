<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eod_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->string('outlet_code', 20)->nullable();
            $table->date('business_date');
            $table->enum('status', ['pending', 'matched', 'mismatch', 'resolved'])->default('pending');
            $table->decimal('pos_cash', 15, 2)->default(0);
            $table->decimal('pos_debit', 15, 2)->default(0);
            $table->decimal('pos_qris', 15, 2)->default(0);
            $table->decimal('fin_cash', 15, 2)->default(0);
            $table->decimal('fin_debit', 15, 2)->default(0);
            $table->decimal('fin_qris', 15, 2)->default(0);
            $table->integer('pos_trx_count')->default(0);
            $table->integer('fin_trx_count')->default(0);
            $table->decimal('cash_variance', 15, 2)->default(0);
            $table->json('mismatch_reasons')->nullable();
            $table->unsignedBigInteger('run_by')->nullable();
            $table->timestamp('run_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'business_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eod_reconciliations');
    }
};
