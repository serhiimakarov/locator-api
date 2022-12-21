<?php

if (!function_exists('isDevServer')) {
    /**
     * @return bool
     */
    function isDevServer()
    {
        $request = \Illuminate\Http\Request::createFromGlobals();

        $forwardedHost = $request->server('HTTP_X_FORWARDED_HOST', false);
        $host          = $request->server('HTTP_HOST', false);

        return (bool) $forwardedHost || (bool) substr_count($host, ':808');
    }
}
