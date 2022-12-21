<?php

namespace App\Helpers\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Trait CanRedirect
 * @package App\Helpers\Traits
 */
trait CanRedirect
{
    /**
     * @param string $route
     * @param array $params
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectToRoute(string $route, array $params = []): RedirectResponse
    {
        return redirect()->route($route, $params);
    }

    /**
     * Get route
     * @param string $route
     * @param array $params
     * @return string
     */
    protected function route(string $route, array $params = []): string
    {
        if (isDevServer()) {
            return $this->devRoute($route, $params);
        }

        return route($route, $params);
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    private function devRoute(string $route, array $params = []): string
    {
        $request       = Request::createFromGlobals();
        $forwardedHost = $request->server('HTTP_X_FORWARDED_HOST');
        $host          = $request->server('HTTP_HOST');

        return str_ireplace($host, $forwardedHost, route($route, $params));
    }
}
