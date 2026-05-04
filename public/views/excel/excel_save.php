<?php
// Fichier: public/views/excel/excel_save.php
error_reporting(0); // Désactiver l'affichage des erreurs
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

// Log simple
function simple_log($msg, $data = null)
{
    $logFile = __DIR__ . '/../../logs/excel_save_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $content = "[{$timestamp}] " . $msg;
    if ($data !== null)
        $content .= "\n" . print_r($data, true);
    $content .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents($logFile, $content, FILE_APPEND | LOCK_EX);
}

simple_log("=== DÉBUT ===");

// Inclure l'initialisation
require_once __DIR__ . '/../../includes/init.php';

// Vérifier l'utilisateur
if (!isset($_SESSION['user'])) {
    simple_log("Utilisateur non connecté");
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

// Récupérer les données
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

simple_log("Input reçu", ['raw_length' => strlen($rawInput), 'has_data' => isset($input['data'])]);

if (!$input || !isset($input['data']) || !is_array($input['data'])) {
    simple_log("Données invalides", $input);
    echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
    exit;
}

try {
    // Utiliser Config pour la connexion
    $config = Config::getInstance();
    $db = $config->getDb();
    simple_log("Connexion BDD OK");

    // Mise à jour directe sans passer par ExcelModel (pour debug)
    $data = $input['data'];
    $updated = 0;
    $errors = [];

    foreach ($data as $index => $row) {
        if (empty($row['id'])) {
            $errors[] = "Ligne " . ($index + 1) . ": ID manquant";
            continue;
        }

        $id = (int) $row['id'];
        simple_log("Traitement ID: $id");

        try {
            // Mise à jour simple
            $updateFields = [];
            $params = [];

            if (!empty($row['marque'])) {
                $updateFields[] = "marque = :marque";
                $params[':marque'] = trim($row['marque']);
            }
            if (!empty($row['modele'])) {
                $updateFields[] = "modele = :modele";
                $params[':modele'] = trim($row['modele']);
            }
            if (!empty($row['type_materiel'])) {
                $updateFields[] = "type_materiel = :type_materiel";
                $params[':type_materiel'] = trim($row['type_materiel']);
            }
            if (!empty($row['numero_serie'])) {
                $updateFields[] = "numero_serie = :numero_serie";
                $params[':numero_serie'] = trim($row['numero_serie']);
            }
            if (!empty($row['version_firmware'])) {
                $updateFields[] = "version_firmware = :version_firmware";
                $params[':version_firmware'] = trim($row['version_firmware']);
            }
            if (!empty($row['adresse_ip'])) {
                $updateFields[] = "adresse_ip = :adresse_ip";
                $params[':adresse_ip'] = trim($row['adresse_ip']);
            }
            if (!empty($row['adresse_mac'])) {
                $updateFields[] = "adresse_mac = :adresse_mac";
                $params[':adresse_mac'] = trim($row['adresse_mac']);
            }

            if (empty($updateFields)) {
                $errors[] = "Ligne " . ($index + 1) . " (ID {$id}): Aucune donnée";
                continue;
            }

            $params[':id'] = $id;
            $sql = "UPDATE materiel SET " . implode(', ', $updateFields) . " WHERE id = :id";

            simple_log("SQL", ['sql' => $sql, 'params' => array_keys($params)]);

            $stmt = $db->prepare($sql);
            if ($stmt->execute($params)) {
                $updated++;
                simple_log("Mise à jour réussie ID: $id");
            } else {
                $errors[] = "Ligne " . ($index + 1) . " (ID {$id}): Échec";
            }

        } catch (Exception $e) {
            simple_log("Erreur", $e->getMessage());
            $errors[] = "Ligne " . ($index + 1) . " (ID {$id}): " . $e->getMessage();
        }
    }

    $response = [
        'status' => empty($errors) ? 'success' : 'partial',
        'message' => $updated . " ligne(s) mise(s) à jour",
        'updated' => $updated,
        'total_rows' => count($data),
        'errors' => $errors
    ];

    simple_log("Réponse", $response);
    echo json_encode($response);

} catch (Exception $e) {
    simple_log("Exception générale", $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}

simple_log("=== FIN ===");
?>