<?php

namespace Sigma\Core\Http\Controllers;

use App\Helpers\Traits\CanRedirect;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Class ApiController
 */
class ApiController extends Controller
{
    use CanRedirect;

    /**
     * @param string $message
     * @param array $payload
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function response(string $message, array $payload = [], int $status = Response::HTTP_OK)
    {
        return response()->json([
            'message' => $message,
        ] + $payload, $status);
    }

    /**
     * Response data
     * @param array $data
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseData(array $data = [], int $status = Response::HTTP_OK)
    {
        return response()->json([
            'data' => $data,
        ], $status);
    }

    /**
     * @param string $route
     * @return \Illuminate\Http\JsonResponse
     */
    protected function redirect(string $route)
    {
        return response()->json([
            'redirect' => $route,
        ], Response::HTTP_OK);
    }
}
