<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateProductReferences extends Command
{
    protected $signature   = 'products:generate-references {--reset : Reset all references to null before regenerating}';
    protected $description = 'Assign auto-incremented numeric references to products that have none.';

    public function handle(): int
    {
        if ($this->option('reset')) {
            DB::table('products')->update(['reference' => null]);
            $this->info('All references reset to null.');
        }

        // Bypass TenantScope — we need to touch every organisation's products.
        $products = DB::table('products')
            ->where(fn($q) => $q->whereNull('reference')->orWhere('reference', ''))
            ->get(['id', 'nom', 'organisation_id']);

        if ($products->isEmpty()) {
            $this->info('All products already have a reference — nothing to do.');
            return 0;
        }

        // Pre-load the current max numeric reference per organisation.
        $maxPerOrg = DB::table('products')
            ->whereNotNull('reference')
            ->where('reference', '!=', '')
            ->get(['reference', 'organisation_id'])
            ->groupBy('organisation_id')
            ->map(fn($rows) =>
                $rows->pluck('reference')
                    ->filter(fn($r) => is_numeric($r) && (int) $r > 0)
                    ->map(fn($r) => (int) $r)
                    ->max() ?? 0
            );

        $rows = [];
        foreach ($products as $p) {
            $current = $maxPerOrg->get($p->organisation_id, 0);
            $next    = $current + 1;
            $maxPerOrg->put($p->organisation_id, $next);

            DB::table('products')->where('id', $p->id)->update(['reference' => (string) $next]);

            $rows[] = ['id' => $p->id, 'nom' => $p->nom, 'reference' => (string) $next];
        }

        $this->table(['ID', 'Nom', 'Référence'], $rows);
        $this->info(count($rows) . ' référence(s) générée(s).');

        return 0;
    }
}
