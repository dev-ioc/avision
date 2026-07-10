<?php
/**
 * Endpoint appelé en POST par DataTablePersistence.syncToServer()
 * À brancher sur votre routeur existant, ex: BASE_URL . 'preferences/save'
 * (même logique que les autres actions AJAX du projet : interventions/getCloseDetails,
 * interventions/sendEmail, etc. — adaptez selon que vous utilisez des fichiers
 * de vue directs ou des méthodes de contrôleur).
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Vérification CSRF (même principe que les autres endpoints du projet,
// ex: X-CSRF-Token comparé à $_SESSION['csrf_token'])
$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$key = $input['key'] ?? '';
$value = $input['value'] ?? '';

// On restreint les clés acceptées à un format prévisible pour éviter
// qu'un appel malveillant n'écrive n'importe quelle clé en base.
if (!preg_match('/^datatable_[a-zA-Z0-9]+_[a-zA-Z0-9]+$/', $key)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Clé de préférence invalide']);
    exit;
}

$value = (string) $value;
if (strlen($value) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valeur trop longue']);
    exit;
}

$ok = setUserPreference($key, $value);

echo json_encode(['success' => $ok]);