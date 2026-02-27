<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $code
    ) {}

    public function build()
    {
        return $this
            ->subject('💍 WedPlan — Code de réinitialisation')
            ->html("
                <div style='font-family:Georgia,serif;max-width:500px;margin:0 auto;padding:40px 20px;'>
                    <h1 style='color:#7c3aed;font-size:1.5rem;margin-bottom:8px;'>💍 WedPlan</h1>
                    <p style='color:#334155;'>Bonjour <strong>{$this->name}</strong>,</p>
                    <p style='color:#334155;'>Votre code de réinitialisation est :</p>
                    <div style='background:#f5f3ff;border:2px solid #7c3aed;border-radius:12px;padding:24px;text-align:center;margin:24px 0;'>
                        <span style='font-size:2.5rem;font-weight:900;letter-spacing:12px;color:#7c3aed;'>{$this->code}</span>
                    </div>
                    <p style='color:#64748b;font-size:0.875rem;'>Ce code expire dans <strong>15 minutes</strong>.</p>
                    <p style='color:#64748b;font-size:0.875rem;'>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                    <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0;'>
                    <p style='color:#94a3b8;font-size:0.75rem;text-align:center;'>WedPlan — Planification de mariage</p>
                </div>
            ");
    }
}
