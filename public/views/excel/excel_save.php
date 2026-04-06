<?php
header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ExcelController.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

$controller = new ExcelController();
$controller->save();