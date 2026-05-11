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
            if (!empty($row['reference'])) {
                $updateFields[] = "reference = :reference";
                $params[':reference'] = trim($row['reference']);
            }
            if (!empty($row['usage_materiel'])) {
                $updateFields[] = "usage_materiel = :usage_materiel";
                $params[':usage_materiel'] = trim($row['usage_materiel']);
            }
            if (!empty($row['ancien_firmware'])) {
                $updateFields[] = "ancien_firmware = :ancien_firmware";
                $params[':ancien_firmware'] = trim($row['ancien_firmware']);
            }
            if (!empty($row['masque'])) {
                $updateFields[] = "masque = :masque";
                $params[':masque'] = trim($row['masque']);
            }
            if (!empty($row['passerelle'])) {
                $updateFields[] = "passerelle = :passerelle";
                $params[':passerelle'] = trim($row['passerelle']);
            }
            if (!empty($row['login'])) {
                $updateFields[] = "login = :login";
                $params[':login'] = trim($row['login']);
            }
            if (!empty($row['password'])) {
                $updateFields[] = "password = :password";
                $params[':password'] = trim($row['password']);
            }
            if (!empty($row['ip_primaire'])) {
                $updateFields[] = "ip_primaire = :ip_primaire";
                $params[':ip_primaire'] = trim($row['ip_primaire']);
            }
            if (!empty($row['mac_primaire'])) {
                $updateFields[] = "mac_primaire = :mac_primaire";
                $params[':mac_primaire'] = trim($row['mac_primaire']);
            }
            if (!empty($row['ip_secondaire'])) {
                $updateFields[] = "ip_secondaire = :ip_secondaire";
                $params[':ip_secondaire'] = trim($row['ip_secondaire']);
            }
            if (!empty($row['mac_secondaire'])) {
                $updateFields[] = "mac_secondaire = :mac_secondaire";
                $params[':mac_secondaire'] = trim($row['mac_secondaire']);
            }
            if (!empty($row['stream_aes67_recu'])) {
                $updateFields[] = "stream_aes67_recu = :stream_aes67_recu";
                $params[':stream_aes67_recu'] = trim($row['stream_aes67_recu']);
            }
            if (!empty($row['stream_aes67_transmis'])) {
                $updateFields[] = "stream_aes67_transmis = :stream_aes67_transmis";
                $params[':stream_aes67_transmis'] = trim($row['stream_aes67_transmis']);
            }
            if (!empty($row['ssid'])) {
                $updateFields[] = "ssid = :ssid";
                $params[':ssid'] = trim($row['ssid']);
            }
            if (!empty($row['type_cryptage'])) {
                $updateFields[] = "type_cryptage = :type_cryptage";
                $params[':type_cryptage'] = trim($row['type_cryptage']);
            }
            if (!empty($row['password_wifi'])) {
                $updateFields[] = "password_wifi = :password_wifi";
                $params[':password_wifi'] = trim($row['password_wifi']);
            }
            if (!empty($row['libelle_pa_salle'])) {
                $updateFields[] = "libelle_pa_salle = :libelle_pa_salle";
                $params[':libelle_pa_salle'] = trim($row['libelle_pa_salle']);
            }
            if (!empty($row['numero_port_switch'])) {
                $updateFields[] = "numero_port_switch = :numero_port_switch";
                $params[':numero_port_switch'] = trim($row['numero_port_switch']);
            }
            if (!empty($row['vlan'])) {
                $updateFields[] = "vlan = :vlan";
                $params[':vlan'] = trim($row['vlan']);
            }
            if (!empty($row['date_fin_garantie'])) {
                $updateFields[] = "date_fin_garantie = :date_fin_garantie";
                $params[':date_fin_garantie'] = formatDateForDb($row['date_fin_garantie']);
            }
            if (!empty($row['date_derniere_inter'])) {
                $updateFields[] = "date_derniere_inter = :date_derniere_inter";
                $params[':date_derniere_inter'] = formatDateForDb($row['date_derniere_inter']);
            }
            if (!empty($row['commentaire'])) {
                $updateFields[] = "commentaire = :commentaire";
                $params[':commentaire'] = trim($row['commentaire']);
            }
            if (!empty($row['url_github'])) {
                $updateFields[] = "url_github = :url_github";
                $params[':url_github'] = trim($row['url_github']);
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
function formatDateForDb($date)
{
    if (empty($date))
        return null;
    $date = trim($date);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
        return $date;
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {
        return checkdate($m[2], $m[1], $m[3]) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
        return checkdate($m[2], $m[1], $m[3]) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
    }
    return null;
}

simple_log("=== FIN ===");
?>