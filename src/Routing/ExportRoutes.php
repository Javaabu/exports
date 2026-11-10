<?php

namespace Javaabu\Exports\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Javaabu\Exports\Http\Controllers\ExportsController;

/**
 * Registers the exports routes for `Route::exports()`
 *
 * ```php
 * Route::exports();                                                  // /exports, exports.*, auth
 * Route::exports(['guard' => 'web_admin', 'prefix' => 'admin/exports', 'name' => 'admin.exports']);
 * ```
 */
class ExportRoutes
{
    /** The route action key that holds the guard the routes were registered for */
    public const string GUARD_ACTION = 'exports_guard';

    /**
     * @param  array{
     *     guard?: string|null,
     *     prefix?: string,
     *     name?: string,
     *     middleware?: string|array<int, string>|null,
     *     controller?: class-string|null,
     *     signed?: bool,
     *     signed_middleware?: string|array<int, string>
     * }  $options
     */
    public static function register(Router $router, array $options = []): void
    {
        $guard = $options['guard'] ?? null;
        $prefix = trim($options['prefix'] ?? 'exports', '/');
        $name = rtrim($options['name'] ?? 'exports', '.');
        $middleware = $options['middleware'] ?? [$guard ? "auth:{$guard}" : 'auth'];
        $controller = $options['controller'] ?? config('exports.controllers.exports', ExportsController::class);

        $router->group([
            'prefix' => $prefix,
            'as' => "{$name}.",
            self::GUARD_ACTION => $guard,
        ], function (Router $router) use ($controller, $middleware, $options) {
            $router->group(['middleware' => $middleware], function (Router $router) use ($controller) {
                $router->get('/', [$controller, 'index'])->name('index');
                $router->post('/', [$controller, 'store'])->name('store');
                $router->get('/{export}', [$controller, 'show'])->name('show');
                $router->get('/{export}/download', [$controller, 'download'])->name('download');
                $router->post('/{export}/cancel', [$controller, 'cancel'])->name('cancel');
                $router->delete('/{export}', [$controller, 'destroy'])->name('destroy');
            });

            if ($options['signed'] ?? true) {
                $router->get('/{export}/signed-download', [$controller, 'signedDownload'])
                    ->middleware($options['signed_middleware'] ?? ['signed'])
                    ->name('signed-download');
            }
        });
    }

    /**
     * The guard the current route was registered for, or null for the default guard
     */
    public static function guard(Request $request): ?string
    {
        return $request->route()?->getAction(self::GUARD_ACTION);
    }

    /**
     * The route name prefix of the current route, e.g. `admin.exports`
     */
    public static function namePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        return str_contains($name, '.')
            ? substr($name, 0, strrpos($name, '.'))
            : 'exports';
    }
}
