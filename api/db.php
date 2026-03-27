<?php
function getDB() {
    $pdo = new PDO('mysql:host=localhost;dbname=deposito;charset=utf8mb4', 'deposito', 'deposito123');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
