<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_transaction_id')->nullable()->constrained('credit_transactions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->string('notes')->nullable();
            $table->dateTime('payment_date')->index();
            $table->timestamps();

            $table->index('credit_transaction_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
    }
};
