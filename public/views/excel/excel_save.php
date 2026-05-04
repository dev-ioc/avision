<?php
header('Content-Type: application/json');
session_start();

if (!function_exists('log_debug')) {
    function log_debug($message, $data = null)
    {
        $logDir = __DIR__ . '/../../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . 'excel_save_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [DEBUG] " . $message;
        if ($data !== null) {
            $logEntry .= "\n" . print_r($data, true);
        }
        $logEntry .= "\n" . str_repeat('-', 80) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('log_error')) {
    function log_error($message, $error = null)
    {
        $logDir = __DIR__ . '/../../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . 'excel_save_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [ERROR] " . $message;
        if ($error !== null) {
            $logEntry .= "\n" . print_r($error, true);
        }
        $logEntry .= "\n" . str_repeat('-', 80) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Définir BASE_URL si non défini
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Fonction CSRF token (si non existante)
if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

require_once __DIR__ . '/../../controllers/ExcelController.php';

log_debug("=== DÉBUT DE L'EXÉCUTION ===");

if (!isset($_SESSION['user'])) {
    log_error("Utilisateur non connecté");
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé - Utilisateur non connecté']);
    exit;
}

log_debug("Utilisateur connecté", ['user_id' => $_SESSION['user']['id'] ?? 'unknown']);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

log_debug("Données reçues", $input);

if (!$input) {
    $jsonError = json_last_error_msg();
    log_error("JSON invalide", $jsonError);
    echo json_encode(['status' => 'error', 'message' => 'Données JSON invalides: ' . $jsonError]);
    exit;
}

if (isset($input['data']) && is_array($input['data'])) {
    $data = $input['data'];
} elseif (is_array($input)) {
    $data = $input;
} else {
    log_error("Format de données invalide", $input);
    echo json_encode(['status' => 'error', 'message' => 'Données invalides - format non reconnu']);
    exit;
}

if (empty($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune donnée à sauvegarder']);
    exit;
}

try {
    log_debug("Instanciation du contrôleur ExcelController");
    $excelController = new ExcelController();

    log_debug("Traitement des données - " . count($data) . " élément(s)");
    $result = $excelController->processExcelUpdate($data);

    log_debug("Résultat du traitement", $result);
    $status = empty($result['errors']) ? 'success' : 'partial';
    $message = $result['updated'] . " ligne(s) mise(s) à jour";

    if (!empty($result['errors'])) {
        $message .= " avec " . count($result['errors']) . " erreur(s)";
    }
    $response = [
        'status' => $status,
        'message' => $message,
        'updated' => $result['updated'],
        'total_rows' => $result['total'],
        'errors' => $result['errors']
    ];

    log_debug("Réponse envoyée", $response);
    echo json_encode($response);

} catch (Exception $e) {
    log_error("Exception capturée", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

log_debug("=== FIN DE L'EXÉCUTION ===");
?>