<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fiance_nom_complet', 200)->nullable();
            $table->string('fiancee_nom_complet', 200)->nullable();
            $table->decimal('budget_total', 15, 2)->default(0.00);
            $table->date('wedding_date');
            $table->timestamps();

            $table->index('user_id');
            $table->index('wedding_date');
        });

        Schema::create('wedding_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_dates_id')->constrained('wedding_dates')->cascadeOnDelete();
            $table->string('sponsor_nom_complet', 200);
            $table->string('sponsor_conjoint_nom_complet', 200);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('telephone', 20)->nullable();
            // ✅ Remplacé enum() par string() + default() — compatible PostgreSQL
            $table->string('role', 20)->default('parrain');
            $table->string('statut', 20)->default('actif');
            $table->timestamps();

            $table->index(['wedding_dates_id']);
            $table->index('statut');
        });

        Schema::create('sponsor_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_dates_id')->constrained('wedding_dates')->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained('wedding_sponsors')->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->cascadeOnDelete();
            $table->text('commentaire');
            // ✅ Remplacé enum() par string() + default() — compatible PostgreSQL
            $table->string('type_commentaire', 20)->default('general');
            $table->string('statut', 10)->default('public');
            $table->timestamps();

            $table->index('wedding_dates_id');
            $table->index('sponsor_id');
            $table->index('expense_id');
        });

        Schema::create('sponsor_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained('wedding_sponsors')->cascadeOnDelete();
            $table->foreignId('wedding_dates_id')->constrained('wedding_dates')->cascadeOnDelete();
            // ✅ Remplacé enum() par string() + default() — compatible PostgreSQL
            $table->string('action_type', 20)->default('consultation');
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['sponsor_id', 'created_at']);
            $table->index(['wedding_dates_id', 'created_at']);
            $table->index('action_type');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wedding_dates_id')->nullable()->constrained('wedding_dates')->cascadeOnDelete();
            $table->string('type_notification', 50);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->json('data')->nullable()->after('is_read');
            $table->index('wedding_dates_id');
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 255);
            // ✅ Remplacé enum() par string() + default() — compatible PostgreSQL
            $table->string('action_type', 10)->default('auth');
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('action_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('sponsor_activity_logs');
        Schema::dropIfExists('sponsor_comments');
        Schema::dropIfExists('wedding_sponsors');
        Schema::dropIfExists('wedding_dates');
    }
};