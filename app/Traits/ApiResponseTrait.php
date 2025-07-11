<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * Success Response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
            'data' => $data,
            'errors' => null
        ], $statusCode);
    }

    /**
     * Error Response
     *
     * @param string $message
     * @param mixed $errors
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($message = 'Error', $errors = null, $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $statusCode);
    }


    /**
     * Validation Error Response
     *
     * @param mixed $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationErrorResponse($errors)
    {
        // If $errors is a MessageBag (typical for validation), get all messages as a flat array
        if (method_exists($errors, 'all')) {
            $errors = $errors->all();
        } elseif (is_array($errors)) {
            // If it is an array keyed by fields, flatten it to just messages
            $errors = collect($errors)->flatten()->all();
        }

        return response()->json([
            'success' => false,
            'status' => 422,
            'message' => 'Validation error',
            'data' => null,
            'errors' => $errors,
        ], 422);
    }


    /**
     * Not Found Response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found')
    {
        return $this->errorResponse($message, null, 404);
    }

    /**
     * Unauthorized Response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        return $this->errorResponse($message, null, 401);
    }

    /**
     * Forbidden Response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden')
    {
        return $this->errorResponse($message, null, 403);
    }

    /**
     * Server Error Response
     *
     * @param string $message
     * @param mixed $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function serverErrorResponse(string $message = 'Server error', $errors = null)
    {
        return $this->errorResponse($message, $errors, 500);
    }

    /**
     * Paginated Response
     *
     * @param mixed $data
     * @param mixed $pagination
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedResponse($data, $pagination, string $message = 'Success')
    {
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total_rows' => $pagination->total(),
                'current_page' => $pagination->currentPage(),
                'per_page' => $pagination->perPage(),
                'total_pages' => $pagination->lastPage(),
                'has_more_pages' => $pagination->hasMorePages(),
            ],
            'errors' => null
        ], 200);
    }
}
