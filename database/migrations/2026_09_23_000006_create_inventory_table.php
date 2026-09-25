<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('inventory_date');
            $table->integer('beginning_stock')->default(0);
            $table->integer('stock_added')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->integer('damaged')->default(0);
            $table->integer('adjustments')->default(0);
            $table->integer('ending_stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'inventory_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
