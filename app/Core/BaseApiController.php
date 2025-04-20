<?php

namespace App\Core;

use App\Http\Controllers\Controller;

class BaseApiController extends Controller
{
    public static function success(string $message = "Operation is successfully", $data = null, int $status = 200)
    {
        return response()->json([
            'is_success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message = "Something went wrong!", int $status = 500, $data = null)
    {
        return response()->json([
            'is_success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}