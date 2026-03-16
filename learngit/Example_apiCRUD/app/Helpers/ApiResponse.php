<?php
namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, $message = 'Thành công', $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null
        ], $code);
    }

    public static function error($message = 'Lỗi', $code = 500, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors
        ], $code);
    }
}