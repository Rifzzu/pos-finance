<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('postings', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 80)->unique();
            $table->string('trx_number', 50);
            $table->enum('entry_type', ['sale', 'reversal']);
            $table->unsignedBigInteger('outlet_id');
            $table->string('outlet_code', 20);
            $table->unsignedBigInteger('shift_id');
            $table->date('business_date');
            $table->enum('payment_method', ['cash', 'debit', 'qris']);
            $table->decimal('net_sales_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['outlet_id', 'business_date']);
            $table->index(['trx_number', 'entry_type']);
            $table->index('business_date');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_id')->constrained('postings')->cascadeOnDelete();
            $table->string('account_code', 10);
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('account_code')->references('code')->on('accounts');
            $table->index('posting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('postings');
        Schema::dropIfExists('accounts');
    }
};
