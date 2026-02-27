<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'type',
        'details',
    ];

    // Méthode statique appelée partout dans le code
    public static function log(
        ?int $userId,
        string $action,
        string $type = 'info',
        ?string $details = null
    ): void {
        try {
            static::create([
                'user_id' => $userId,
                'action'  => $action,
                'type'    => $type,
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            // Silencieux — les logs ne doivent jamais bloquer l'application
        }
    }
}