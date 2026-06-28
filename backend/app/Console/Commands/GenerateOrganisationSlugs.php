<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateOrganisationSlugs extends Command
{
    protected $signature   = 'organisations:generate-slugs';
    protected $description = 'Generate URL slugs for all organisations that do not have one yet.';

    public function handle(): int
    {
        $orgs = Organisation::whereNull('slug')->orWhere('slug', '')->get();

        if ($orgs->isEmpty()) {
            $this->info('All organisations already have a slug.');
            return 0;
        }

        $bar = $this->output->createProgressBar($orgs->count());
        $bar->start();

        foreach ($orgs as $org) {
            $org->slug = Organisation::uniqueSlug($org->nom, $org->id);
            $org->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Generated slugs for {$orgs->count()} organisation(s).");

        return 0;
    }
}
