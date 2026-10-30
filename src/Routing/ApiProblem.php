<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Respuesta de error en formato JSON:API/Problem para APIs REST.
 *
 *   GET  /api/v1/todos/999  → 404 { "error": { "code": "not_found", ... } }
 */
final class ApiProblem
{
    /**
     * @param array<string, mixed> $details
     */
    public static function make(int $status, string $code, string $message, array $details = []): Response
    {
        return Response::json([
            'error' => [
                'status' => $status,
                'code' => $code,
                'message' => $message,
            ] + ($details === [] ? [] : ['details' => $details]),
        ], $status);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function validation(array $errors, string $message = 'The given data was invalid.'): Response
    {
        return self::make(422, 'validation_failed', $message, ['errors' => $errors]);
    }

    public static function notFound(string $message = 'Resource not found.'): Response
    {
        return self::make(404, 'not_found', $message);
    }

    /**
     * @param list<string> $allowed
     */
    public static function methodNotAllowed(array $allowed = []): Response
    {
        $response = self::make(405, 'method_not_allowed', 'Method not allowed.');

        return $allowed === [] ? $response : $response->withHeader('Allow', implode(', ', $allowed));
    }

    public static function internal(string $message = 'Internal server error.'): Response
    {
        return self::make(500, 'internal_error', $message);
    }

    /**
     * Regla HTTP → nivel de severidad para logs.
     */
    public static function severity(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status === 405, $status === 422 => 'warning',
            default => 'notice',
        };
    }
}
