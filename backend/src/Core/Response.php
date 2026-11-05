<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        bool $success = true,
        mixed $errors = null,
        ?array $meta = null
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, ?array $meta = null): void
    {
        self::json($data, $message, $statusCode, true, null, $meta);
    }

    public static function created(mixed $data = null, string $message = 'Resource created successfully'): void
    {
        self::json($data, $message, 201, true, null);
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): void
    {
        self::json(null, $message, $statusCode, false, $errors);
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::json(null, $message, 404, false);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::json(null, $message, 401, false);
    }

    public static function forbidden(string $message = 'Forbidden: insufficient permissions'): void
    {
        self::json(null, $message, 403, false);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::json(null, $message, 422, false, $errors);
    }
}
