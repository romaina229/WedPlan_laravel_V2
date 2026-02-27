<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('color', 7)->default('#3498db');
            $table->string('icon', 50)->default('fas fa-folder');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
