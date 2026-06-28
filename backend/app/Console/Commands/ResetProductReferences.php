<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetProductReferences extends Command
{
    protected $signature   = 'products:reset-references';
    protected $description = 'Reset all references to null then renumber active products 1…N per org (id ASC).';

    public function handle(): int
    {
        // Step 1 — wipe all references (active + inactive)
        DB::table('products')->update(['reference' => null]);
        $this->info('All references reset to null.');

        // Step 2 — renumber only active products, ordered by id ASC, per organisation
        $products = DB::table('products')
            ->where('actif', true)
            ->orderBy('organisation_id')
            ->orderBy('id')
            ->get(['id', 'nom', 'organisation_id']);

        $counters = [];
        $rows     = [];

        foreach ($products as $p) {
            $counters[$p->organisation_id] = ($counters[$p->organisation_id] ?? 0) + 1;
            $ref = (string) $counters[$p->organisation_id];

            DB::table('products')->where('id', $p->id)->update(['reference' => $ref]);

            $rows[] = ['id' => $p->id, 'nom' => $p->nom, 'ref' => $ref, 'org' => $p->organisation_id];
        }

        $this->table(['ID', 'Nom', 'Référence', 'Org'], $rows);
        $this->info(count($rows) . ' produit(s) renuméroté(s).');

        return 0;
    }
}
