<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->integer('frequency')->default(1);
            $table->boolean('paid')->default(false);
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('category_id');
            $table->index('paid');
            $table->index('payment_date');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
