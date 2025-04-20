<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Throwable;

abstract class Controller
{
    public function handleException(Throwable $exception, string $message, array $context = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $context) , 500);
    }

    public function handleNotFoundException(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 404);
    }

    public function handleUnauthorizedException(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 401);
    }   

    public function handleBadRequestException(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 400);
    }

    public function handleForbiddenException(string $message, array $data = []  ): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 403);
    }   

    public function handleInternalServerError(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 500);
    }

    public function handleUnprocessableEntity(string $message, array $data = [] ): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 422);
    }

    public function handleConflict(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 409);
    }

    public function handleCreated(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 201);
    }

    public function handleNoContent(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 204);
    }   

    public function handleSuccess(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 200);
    }

    public function handleAccepted(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 202);
    }

    public function handleBadGateway(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 502);
    }

    public function handleGatewayTimeout(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 504);
    }

    public function handleServiceUnavailable(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 503);
    }

    public function handleTooManyRequests(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 429);
    }   

    public function handleUnauthorized(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 401);
    }

    public function handleForbidden(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data) , 403);
    } 

    
}