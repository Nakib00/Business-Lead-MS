<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        // Handle validation errors
        if ($e instanceof ValidationException) {
            return $this->convertValidationExceptionToResponse($e, $request);
        }

        // Handle 404 errors
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Resource not found',
                'data' => null,
                'errors' => 'The requested resource was not found.',
            ], 404);
        }

        // Handle token expiration or missing token errors
        if ($e instanceof AuthenticationException || $e instanceof UnauthorizedHttpException) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Something went wrong',
                'data' => null,
                'errors' => 'Route [login] not defined.',
            ], 401);
        }

        // Handle all other exceptions
        return response()->json([
            'success' => false,
            'status' => 500,
            'message' => 'Something went wrong',
            'data' => null,
            'errors' => $e->getMessage(),
        ], 500);
    }

    /**
     * Convert a validation exception into a JSON response.
     *
     * @param  \Illuminate\Validation\ValidationException  $e
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, $request): JsonResponse
    {
        // Get the first error message
        $errorMessage = $e->validator->errors()->first();

        return response()->json([
            'success' => false,
            'status' => 422,
            'message' => 'Validation failed',
            'data' => null,
            'errors' => $errorMessage,
        ], 422);
    }
}
