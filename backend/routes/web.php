<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SPA fallback
|--------------------------------------------------------------------------
| Every non-API, non-health route returns the built Vue single-page app
| (public/index.html). This lets Vue Router (history mode) handle client-side
| routes like /login, /app/products, etc. — including on a hard refresh.
|
| The regex excludes `api` and `up` so the JSON API and the health check keep
| their own handlers. Real static files (assets, favicon) are served directly
| by the web server before ever reaching this route.
*/

Route::get('/{any?}', function () {
    $index = public_path('index.html');

    abort_unless(file_exists($index), 404, "Le frontend n'a pas été compilé. Lancez `npm run build`.");

    return response()->file($index, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
        'Pragma'        => 'no-cache',
    ]);
})->where('any', '^(?!api|up).*$');
