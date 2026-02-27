<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN for enum, we use a workaround
        // For MySQL/PostgreSQL, you would use:
        // DB::statement("ALTER TABLE wedding_sponsors MODIFY COLUMN statut ENUM('actif', 'inactif', 'en_attente') DEFAULT 'actif'");
        
        // For SQLite (used in this project):
        // The enum constraint isn't enforced strictly, so just ensure column accepts new value
        // by checking DB driver
        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE wedding_sponsors MODIFY statut ENUM('actif', 'inactif', 'en_attente') DEFAULT 'actif'");
        }
        // SQLite: no change needed - SQLite does not enforce enum constraints
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE wedding_sponsors MODIFY statut ENUM('actif', 'inactif') DEFAULT 'actif'");
        }
    }
};
