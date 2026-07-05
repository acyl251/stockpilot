<?php

namespace App\Mail;

use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organisation $org,
        public User         $admin,
        public array        $data,
        public Carbon       $debut,
        public Carbon       $fin,
    ) {}

    public function envelope(): Envelope
    {
        $periode = $this->debut->format('d/m') . ' – ' . $this->fin->format('d/m/Y');
        return new Envelope(
            subject: "📊 Rapport hebdomadaire StockPilot — {$this->org->nom} ({$periode})",
        );
    }

    public function content(): Content
    {
        $pct = $this->data['variation_percent'];
        if ($pct === null)    { $varTexte = 'N/A';  $varCouleur = '#64748b'; }
        elseif ($pct > 0)     { $varTexte = '▲ +' . number_format($pct, 1, ',', '') . '%'; $varCouleur = '#16a34a'; }
        elseif ($pct < 0)     { $varTexte = '▼ '  . number_format($pct, 1, ',', '') . '%'; $varCouleur = '#dc2626'; }
        else                  { $varTexte = '= 0%'; $varCouleur = '#64748b'; }

        $fmt = fn (float $v) => number_format($v, 3, ',', ' ') . ' DT';

        return new Content(
            view: 'emails.weekly-report',
            with: [
                'varTexte'   => $varTexte,
                'varCouleur' => $varCouleur,
                'fmt'        => $fmt,
            ],
        );
    }
}
