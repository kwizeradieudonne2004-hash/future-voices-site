<?php
// Database connection. Keep real credentials out of version control —
// load them from environment variables in production.

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('FVSS_DB_HOST') ?: '127.0.0.1';
        $port = getenv('FVSS_DB_PORT') ?: '3306';
        $name = getenv('FVSS_DB_NAME') ?: 'fvss';
        $user = getenv('FVSS_DB_USER') ?: 'root';
        $pass = getenv('FVSS_DB_PASS') ?: '';
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function json_input(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function json_out($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
