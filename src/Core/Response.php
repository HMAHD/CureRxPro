<?php

namespace CureRx\Core;

class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        self::json(['status' => 'error', 'message' => $message, 'errors' => $errors], $status);
    }
}
