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

$input = json_decode(file_get_contents('php://input'), true);
$ids = [];
if (isset($input['ids']) && is_array($input['ids'])) {
    $ids = array_filter(array_map('intval', $input['ids']));
} elseif (isset($input['id'])) {
    $ids = [(int) $input['id']];
}

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucun ID valide']);
    exit;
}

$controller = new ExcelController();
$controller->delete($ids);