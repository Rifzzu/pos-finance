<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('trx_number', 50)->unique();
            $table->unsignedBigInteger('outlet_id');
            $table->string('outlet_code', 20);
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('cashier_id');
            $table->date('business_date');
            $table->enum('status', ['pending', 'paid', 'void'])->default('pending');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('grand_total', 15, 2);
            $table->enum('payment_method', ['cash', 'debit', 'qris'])->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->enum('finance_sync_status', ['none', 'pending', 'synced', 'failed'])->default('none');
            $table->timestamp('finance_synced_at')->nullable();
            $table->text('finance_last_error')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'business_date']);
            $table->index(['shift_id', 'status']);
            $table->index(['cashier_id', 'status']);
            $table->index('status');
            $table->index('finance_sync_status');
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 50);
            $table->string('product_name', 200);
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('transaction_id');
        });

        Schema::create('trx_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->date('business_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['outlet_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_sequences');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};
