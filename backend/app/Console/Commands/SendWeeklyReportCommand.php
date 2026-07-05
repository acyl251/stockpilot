<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReport;
use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReportCommand extends Command
{
    protected $signature = 'weekly:report
                            {--force : Envoie immédiatement avec la semaine en cours (pour tester)}';

    protected $description = 'Envoie le rapport hebdomadaire à tous les admins actifs';

    private string $tz = 'Africa/Tunis';

    public function handle(): int
    {
        $force = $this->option('force');
        $now   = Carbon::now($this->tz);

        if ($force) {
            $debut = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $fin   = $now->copy()->endOfDay();
            $this->info('[--force] Rapport sur la semaine en cours : ' . $debut->toDateString() . ' → ' . $fin->toDateString());
        } else {
            $debut = $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $fin   = $now->copy()->subWeek()->endOfWeek()->endOfDay();
        }

        $debutPrecedente = $debut->copy()->subWeek();
        $finPrecedente   = $fin->copy()->subWeek();

        $orgs = Organisation::where('actif', true)->get();
        $this->info("Organisations actives : {$orgs->count()}");

        $sent = 0;
        $skipped = 0;

        foreach ($orgs as $org) {
            $admin = User::where('organisation_id', $org->id)
                ->where('role', 'admin')
                ->where('actif', true)
                ->orderBy('id')
                ->first();

            if (! $admin || ! filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("  ⚠  [{$org->nom}] Pas d'admin valide — ignoré.");
                $skipped++;
                continue;
            }

            try {
                $data = $this->computeData($org->id, $debut, $fin, $debutPrecedente, $finPrecedente);
                Mail::to($admin->email)->send(new WeeklyReport($org, $admin, $data, $debut, $fin));
                $this->info("  ✓  [{$org->nom}] Rapport envoyé à {$admin->email}");
                $sent++;
            } catch (\Throwable $e) {
                $this->error("  ✗  [{$org->nom}] Erreur : " . $e->getMessage());
                \Log::error('WeeklyReport error', ['org' => $org->id, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("Terminé : {$sent} envoyés, {$skipped} ignorés.");
        return Command::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function computeData(int $orgId, Carbon $debut, Carbon $fin, Carbon $debutPrec, Carbon $finPrec): array
    {
        $d  = $debut->toDateTimeString();
        $f  = $fin->toDateTimeString();
        $dp = $debutPrec->toDateTimeString();
        $fp = $finPrec->toDateTimeString();

        // ── Section 1 : Performance semaine ──────────────────────────────────
        $ventes = DB::table('sales')
            ->where('organisation_id', $orgId)
            ->where('statut', '!=', 'annulee')
            ->whereBetween('date_vente', [$d, $f])
            ->selectRaw('SUM(total_ttc) as ca, COUNT(*) as nb, DATE(date_vente) as jour')
            ->groupBy('jour')
            ->get();

        $ca_semaine = (float) $ventes->sum('ca');
        $nb_ventes  = (int)   $ventes->sum('nb');
        $ticket_moyen = $nb_ventes > 0 ? $ca_semaine / $nb_ventes : 0.0;

        $meilleurJour = null;
        if ($ventes->isNotEmpty()) {
            $best = $ventes->sortByDesc('ca')->first();
            $date = Carbon::parse($best->jour, $this->tz);
            $jours_fr = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
            $mois_fr  = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
            $meilleurJour = $jours_fr[$date->dayOfWeek] . ' ' . $date->day . ' ' . $mois_fr[$date->month] . ' : ' . $this->formatDt($best->ca);
        }

        $ca_precedente = (float) DB::table('sales')
            ->where('organisation_id', $orgId)
            ->where('statut', '!=', 'annulee')
            ->whereBetween('date_vente', [$dp, $fp])
            ->sum('total_ttc');

        $variation_percent = null;
        if ($ca_precedente > 0) {
            $variation_percent = (($ca_semaine - $ca_precedente) / $ca_precedente) * 100;
        }

        // ── Section 2 : Top 5 plats ───────────────────────────────────────────
        $topPlats = DB::table('sale_items')
            ->join('sales',    'sale_items.sale_id',    '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sale_items.organisation_id', $orgId)
            ->where('sales.organisation_id',      $orgId)
            ->where('products.organisation_id',   $orgId)
            ->where('sales.statut',  '!=', 'annulee')
            ->where('products.type', 'compose')
            ->whereBetween('sales.date_vente', [$d, $f])
            ->selectRaw('sale_items.product_id, products.nom, SUM(sale_items.quantite) as nb_vendus, SUM(sale_items.total_ligne_ttc) as ca_genere')
            ->groupBy('sale_items.product_id', 'products.nom')
            ->orderByDesc('nb_vendus')
            ->limit(5)
            ->get();

        // ── Section 3 : Stock à commander ────────────────────────────────────
        $stockAlerte = DB::table('products')
            ->where('organisation_id', $orgId)
            ->where('actif', true)
            ->where('seuil_alerte', '>', 0)
            ->whereRaw('quantite <= seuil_alerte')
            ->where('type', 'simple')
            ->select('id', 'nom', 'quantite', 'seuil_alerte', 'unite_mesure')
            ->orderByRaw('(quantite / seuil_alerte) ASC')
            ->get();

        // ── Section 4 : Clients ───────────────────────────────────────────────
        $nbNouveauxClients = DB::table('clients')
            ->where('organisation_id', $orgId)
            ->whereBetween('created_at', [$d, $f])
            ->count();

        $ardoises = DB::table('sales')
            ->where('organisation_id', $orgId)
            ->where('statut', '!=', 'annulee')
            ->where('mode_paiement', 'credit')
            ->whereNotNull('client_id')
            ->whereRaw('montant_regle < total_ttc')
            ->selectRaw('client_id, SUM(total_ttc - montant_regle) as solde')
            ->groupBy('client_id')
            ->get();

        $nbClientsArdoise = $ardoises->count();
        $totalArdoises    = (float) $ardoises->sum('solde');

        // ── Section 5 : Conseils automatiques ────────────────────────────────
        $conseils = [];

        // Conseil performance
        if ($variation_percent !== null) {
            if ($variation_percent <= -20) {
                $conseils[] = [
                    'type' => 'danger',
                    'texte' => '📉 Le CA a baissé de ' . number_format(abs($variation_percent), 1, ',', '') . '% cette semaine. Vérifiez si des événements ont affecté votre activité.',
                ];
            } elseif ($variation_percent >= 20) {
                $conseils[] = [
                    'type' => 'success',
                    'texte' => '📈 Excellente semaine ! Le CA a augmenté de ' . number_format($variation_percent, 1, ',', '') . '% par rapport à la semaine précédente.',
                ];
            }
        }

        // Conseil food cost (produits composés uniquement)
        $composedProds = DB::table('products')
            ->where('organisation_id', $orgId)
            ->where('type', 'compose')
            ->where('actif', true)
            ->where('prix_vente_ht', '>', 0)
            ->get();

        foreach ($composedProds as $prod) {
            $ingredients = DB::table('compositions')
                ->join('products as p', 'compositions.composant_id', '=', 'p.id')
                ->where('compositions.organisation_id', $orgId)
                ->where('compositions.produit_compose_id', $prod->id)
                ->selectRaw('SUM(compositions.quantite * p.prix_achat_ht) as cout_revient')
                ->first();

            if ($ingredients && $ingredients->cout_revient > 0) {
                $foodCost = ($ingredients->cout_revient / $prod->prix_vente_ht) * 100;
                if ($foodCost > 45) {
                    $conseils[] = [
                        'type' => 'warning',
                        'texte' => '⚠ "' . $prod->nom . '" dépasse 45% de food cost (' . number_format($foodCost, 1, ',', '') . '%). Pensez à ajuster le prix de vente.',
                    ];
                }
            }
        }

        // Conseil stock critique (jours restants < 3)
        foreach ($stockAlerte as $prod) {
            $sortieSemaine = (float) DB::table('stock_movements')
                ->where('organisation_id', $orgId)
                ->where('product_id', $prod->id)
                ->where('type_mouvement', 'sortie')
                ->whereBetween('date_mouvement', [$d, $f])
                ->sum('quantite');

            if ($sortieSemaine > 0 && $prod->quantite > 0) {
                $joursRestants = ($prod->quantite / $sortieSemaine) * 7;
                if ($joursRestants < 3) {
                    $conseils[] = [
                        'type' => 'danger',
                        'texte' => '⚠ "' . $prod->nom . '" sera épuisé dans moins de 3 jours au rythme actuel. Commandez rapidement.',
                    ];
                }
            }
        }

        return compact(
            'ca_semaine', 'ca_precedente', 'variation_percent',
            'nb_ventes', 'ticket_moyen', 'meilleurJour',
            'topPlats', 'stockAlerte',
            'nbNouveauxClients', 'nbClientsArdoise', 'totalArdoises',
            'conseils'
        );
    }

    private function formatDt(float $amount): string
    {
        return number_format($amount, 3, ',', ' ') . ' DT';
    }
}
