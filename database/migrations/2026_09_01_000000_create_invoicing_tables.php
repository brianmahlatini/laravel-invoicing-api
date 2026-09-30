<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->char('currency', 3)->default('ZAR');
            $table->text('address')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'email']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete(); // never orphan an issued invoice
            $table->string('number', 32);
            $table->string('status', 16)->default('draft');
            $table->char('currency', 3);
            $table->date('issue_date');
            $table->date('due_date');
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'status', 'due_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedSmallInteger('tax_rate_bps')->default(0);
            $table->unsignedBigInteger('net_cents');
            $table->unsignedBigInteger('tax_cents');
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number');
            $table->primary(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customers');
    }
};
