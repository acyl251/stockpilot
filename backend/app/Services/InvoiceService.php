<?php

namespace App\Services;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Generate (and cache) the legal invoice PDF for a sale.
     * Assigns a sequential invoice number on first generation.
     */
    public function pdf(Sale $sale)
    {
        $sale->loadMissing(['items', 'client', 'user']);
        $this->assignNumero($sale);

        $org = $sale->organisation_id
            ? \App\Models\Organisation::find($sale->organisation_id)
            : app('current_user')->organisation;

        // TVA regroupée par taux pour le pied de facture.
        $tvaParTaux = $sale->items
            ->groupBy(fn($i) => (string) (float) $i->taux_tva)
            ->map(fn($lignes, $taux) => [
                'taux'    => (float) $taux,
                'base_ht' => round($lignes->sum(fn($l) => (float) $l->prix_unitaire_ht * (float) $l->quantite), 3),
                'montant' => round($lignes->sum(fn($l) => (float) $l->prix_unitaire_ht * (float) $l->quantite * (float) $l->taux_tva / 100), 3),
            ])
            ->values();

        $pdf = Pdf::loadView('invoices.facture', [
            'sale'       => $sale,
            'org'        => $org,
            'tvaParTaux' => $tvaParTaux,
            'enLettres'  => $this->montantEnLettres((float) $sale->total_ttc),
        ])->setPaper('a4');

        return $pdf;
    }

    /** Assign a sequential per-org, per-year invoice number (FAC-YYYY-NNNN). */
    public function assignNumero(Sale $sale): void
    {
        if ($sale->numero_facture) {
            return;
        }

        DB::transaction(function () use ($sale) {
            $year   = now()->format('Y');
            $prefix = "FAC-{$year}-";

            $count = Sale::whereNotNull('numero_facture')
                ->where('numero_facture', 'like', $prefix . '%')
                ->lockForUpdate()
                ->count();

            $sale->numero_facture = $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
            $sale->save();
        });
    }

    // ── Montant en toutes lettres (dinars / millimes) ───────────────────────
    public function montantEnLettres(float $montant): string
    {
        $dinars   = (int) floor($montant);
        $millimes = (int) round(($montant - $dinars) * 1000);

        $txt = $this->enLettres($dinars) . ' dinar' . ($dinars > 1 ? 's' : '');
        if ($millimes > 0) {
            $txt .= ' et ' . $this->enLettres($millimes) . ' millime' . ($millimes > 1 ? 's' : '');
        }

        return ucfirst($txt);
    }

    private function enLettres(int $n): string
    {
        if ($n === 0) return 'zéro';

        $millions  = intdiv($n, 1000000);
        $reste     = $n % 1000000;
        $milliers  = intdiv($reste, 1000);
        $unites    = $reste % 1000;

        $parts = [];
        if ($millions) {
            $parts[] = $millions === 1 ? 'un million' : $this->sousMille($millions) . ' millions';
        }
        if ($milliers) {
            $parts[] = $milliers === 1 ? 'mille' : $this->sousMille($milliers) . ' mille';
        }
        if ($unites) {
            $parts[] = $this->sousMille($unites);
        }

        return implode(' ', $parts);
    }

    private function sousMille(int $n): string
    {
        if ($n < 100) return $this->sousCent($n);

        $c = intdiv($n, 100);
        $r = $n % 100;
        $cent = $c === 1 ? 'cent' : $this->sousCent($c) . ' cent';

        if ($r === 0) {
            return $c > 1 ? $cent . 's' : $cent;
        }
        return $cent . ' ' . $this->sousCent($r);
    }

    private function sousCent(int $n): string
    {
        $u = [
            'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
            'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
            'dix-sept', 'dix-huit', 'dix-neuf',
        ];
        $d = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

        if ($n < 20) return $u[$n];

        $t = intdiv($n, 10);
        $r = $n % 10;

        // 70-79 et 90-99 : base soixante / quatre-vingt + (dix..dix-neuf)
        if ($t === 7 || $t === 9) {
            $base = $t === 7 ? 'soixante' : 'quatre-vingt';
            if ($t === 7 && $r === 1) return 'soixante et onze';
            return $base . '-' . $u[10 + $r];
        }

        if ($t === 8) {
            return $r === 0 ? 'quatre-vingts' : 'quatre-vingt-' . $u[$r];
        }

        $base = $d[$t];
        if ($r === 0) return $base;
        if ($r === 1) return $base . ' et un';
        return $base . '-' . $u[$r];
    }
}
