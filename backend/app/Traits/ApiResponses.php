<?php

namespace App\Traits;

trait ApiResponses
{
    protected function successResponse($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message, string $error = null, int $code = 400)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($error) {
            $response['error'] = $error;
        }
        return response()->json($response, $code);
    }

    protected function validationErrorResponse($errors, int $code = 422)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], $code);
    }
}
