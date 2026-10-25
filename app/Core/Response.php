<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data, string $message = 'Sucesso', int $status = 200): void
    {
        self::json(['status' => 'success', 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $response = ['status' => 'error', 'message' => $message];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        self::json($response, $status);
    }
}
