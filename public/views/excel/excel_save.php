<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../includes/functions.php';

$host = 'localhost';
$db = 'avisiondb';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur de connexion BDD: ' . $e->getMessage()]);
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé - Utilisateur non connecté']);
    exit;
}

// Récupérer et décoder les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Données JSON invalides']);
    exit;
}

if (!isset($input['data']) || !is_array($input['data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Données invalides - champ "data" manquant ou non valide']);
    exit;
}

$data = $input['data'];
$updated = 0;
$errors = [];

// Requête UPDATE sans le champ equipement (qui n'existe pas dans la table)
$stmt = $pdo->prepare("
    UPDATE materiel SET
        marque = :marque,
        modele = :modele,
        type_materiel = :type_materiel,
        numero_serie = :numero_serie,
        version_firmware = :version_firmware,
        adresse_ip = :adresse_ip,
        adresse_mac = :adresse_mac,
        date_fin_maintenance = :date_fin_maintenance,
        reference = :reference,
        usage_materiel = :usage_materiel,
        ancien_firmware = :ancien_firmware,
        masque = :masque,
        passerelle = :passerelle,
        login = :login,
        password = :password,
        ip_primaire = :ip_primaire,
        mac_primaire = :mac_primaire,
        ip_secondaire = :ip_secondaire,
        mac_secondaire = :mac_secondaire,
        stream_aes67_recu = :stream_aes67_recu,
        stream_aes67_transmis = :stream_aes67_transmis,
        ssid = :ssid,
        type_cryptage = :type_cryptage,
        password_wifi = :password_wifi,
        libelle_pa_salle = :libelle_pa_salle,
        numero_port_switch = :numero_port_switch,
        vlan = :vlan,
        date_fin_garantie = :date_fin_garantie,
        date_derniere_inter = :date_derniere_inter,
        commentaire = :commentaire,
        url_github = :url_github
    WHERE id = :id
");

foreach ($data as $index => $row) {
    // Vérifier l'ID
    if (empty($row['id'])) {
        $errors[] = "Ligne " . ($index + 1) . ": ID manquant";
        continue;
    }

    $id = (int) $row['id'];
    if ($id <= 0) {
        $errors[] = "Ligne " . ($index + 1) . ": ID invalide";
        continue;
    }

    try {
        $stmt->execute([
            ':id' => $id,
            ':marque' => !empty($row['marque']) ? trim($row['marque']) : null,
            ':modele' => !empty($row['modele']) ? trim($row['modele']) : null,
            ':type_materiel' => !empty($row['type_materiel']) ? trim($row['type_materiel']) : null,
            ':numero_serie' => !empty($row['numero_serie']) ? trim($row['numero_serie']) : null,
            ':version_firmware' => !empty($row['version_firmware']) ? trim($row['version_firmware']) : null,
            ':adresse_ip' => !empty($row['adresse_ip']) ? trim($row['adresse_ip']) : null,
            ':adresse_mac' => !empty($row['adresse_mac']) ? trim($row['adresse_mac']) : null,
            ':date_fin_maintenance' => !empty($row['date_fin_maintenance']) ? formatDateForDb($row['date_fin_maintenance']) : null,
            ':reference' => !empty($row['reference']) ? trim($row['reference']) : null,
            ':usage_materiel' => !empty($row['usage_materiel']) ? trim($row['usage_materiel']) : null,
            ':ancien_firmware' => !empty($row['ancien_firmware']) ? trim($row['ancien_firmware']) : null,
            ':masque' => !empty($row['masque']) ? trim($row['masque']) : null,
            ':passerelle' => !empty($row['passerelle']) ? trim($row['passerelle']) : null,
            ':login' => !empty($row['login']) ? trim($row['login']) : null,
            ':password' => !empty($row['password']) ? trim($row['password']) : null,
            ':ip_primaire' => !empty($row['ip_primaire']) ? trim($row['ip_primaire']) : null,
            ':mac_primaire' => !empty($row['mac_primaire']) ? trim($row['mac_primaire']) : null,
            ':ip_secondaire' => !empty($row['ip_secondaire']) ? trim($row['ip_secondaire']) : null,
            ':mac_secondaire' => !empty($row['mac_secondaire']) ? trim($row['mac_secondaire']) : null,
            ':stream_aes67_recu' => !empty($row['stream_aes67_recu']) ? trim($row['stream_aes67_recu']) : null,
            ':stream_aes67_transmis' => !empty($row['stream_aes67_transmis']) ? trim($row['stream_aes67_transmis']) : null,
            ':ssid' => !empty($row['ssid']) ? trim($row['ssid']) : null,
            ':type_cryptage' => !empty($row['type_cryptage']) ? trim($row['type_cryptage']) : null,
            ':password_wifi' => !empty($row['password_wifi']) ? trim($row['password_wifi']) : null,
            ':libelle_pa_salle' => !empty($row['libelle_pa_salle']) ? trim($row['libelle_pa_salle']) : null,
            ':numero_port_switch' => !empty($row['numero_port_switch']) ? trim($row['numero_port_switch']) : null,
            ':vlan' => !empty($row['vlan']) ? trim($row['vlan']) : null,
            ':date_fin_garantie' => !empty($row['date_fin_garantie']) ? formatDateForDb($row['date_fin_garantie']) : null,
            ':date_derniere_inter' => !empty($row['date_derniere_inter']) ? formatDateForDb($row['date_derniere_inter']) : null,
            ':commentaire' => !empty($row['commentaire']) ? trim($row['commentaire']) : null,
            ':url_github' => !empty($row['url_github']) ? trim($row['url_github']) : null
        ]);

        $updated++;

    } catch (PDOException $e) {
        $errors[] = "ID {$id}: " . $e->getMessage();
    }
}

echo json_encode([
    'status' => empty($errors) ? 'success' : 'partial',
    'message' => $updated . " ligne(s) mise(s) à jour",
    'updated' => $updated,
    'total_rows' => count($data),
    'errors' => $errors
]);

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
?>