<?php

use App\Http\Middleware\AuthenticateTenant;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.tenant'  => AuthenticateTenant::class,
            'super.admin'  => EnsureSuperAdmin::class,
        ]);

        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Uniform JSON error envelope: { success: false, message, errors }
        $envelope = fn (string $message, int $status, $errors = null) => response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);

        // Only intercept API / JSON requests; let web requests use default rendering.
        $wantsJson = fn ($request) => $request->is('api/*') || $request->expectsJson();

        // 422 — validation errors (per-field detail)
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            return $envelope('Les données saisies sont invalides.', 422, $e->errors());
        });

        // 401 — not authenticated
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            return $envelope('Vous devez être authentifié pour accéder à cette ressource.', 401);
        });

        // 403 — authorization (Gate / policy / abort 403)
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            return $envelope($e->getMessage() ?: "Vous n'avez pas l'autorisation d'effectuer cette action.", 403);
        });
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            return $envelope($e->getMessage() ?: "Vous n'avez pas l'autorisation d'effectuer cette action.", 403);
        });

        // 404 — Eloquent model not found (contextual message per resource)
        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            $message = match (class_basename($e->getModel())) {
                'Category'      => "La catégorie demandée n'existe pas.",
                'Product'       => "Le produit demandé n'existe pas.",
                'User'          => "L'utilisateur demandé n'existe pas.",
                'Organisation'  => "L'organisation demandée n'existe pas.",
                'Plan'          => "Le plan demandé n'existe pas.",
                'ProductType'   => "Le type de produit demandé n'existe pas.",
                'StockMovement' => "Le mouvement de stock demandé n'existe pas.",
                'DemoRequest'   => "La demande de démonstration demandée n'existe pas.",
                'TypeAttribute' => "L'attribut demandé n'existe pas.",
                default         => "La ressource demandée n'existe pas.",
            };
            return $envelope($message, 404);
        });

        // 404 — unknown route / missing endpoint
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            return $envelope("La ressource ou l'URL demandée est introuvable.", 404);
        });

        // 405 / 429 / other HTTP exceptions — keep their status code & message
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            return $envelope($e->getMessage() ?: 'La requête ne peut pas être traitée.', $e->getStatusCode());
        });

        // 500 — any other uncaught exception
        $exceptions->renderable(function (\Throwable $e, $request) use ($envelope, $wantsJson) {
            if (! $wantsJson($request)) return null;
            if (config('app.debug')) {
                return $envelope($e->getMessage(), 500, [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);
            }
            return $envelope('Une erreur interne est survenue. Veuillez réessayer plus tard.', 500);
        });
    })
    ->create();
