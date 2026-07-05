<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rapport hebdomadaire — chaque lundi à 08h00 (heure de Tunis)
Schedule::command('weekly:report')
    ->weeklyOn(1, '08:00')
    ->timezone('Africa/Tunis')
    ->withoutOverlapping()
    ->runInBackground();
