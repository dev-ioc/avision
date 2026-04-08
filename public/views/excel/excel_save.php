<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../includes/functions.php';

$host = 'localhost';
$db = 'avision';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
    exit;
}

$data = $input['data'];

$updated = 0;
$errors = [];

$stmt = $pdo->prepare("
    UPDATE materiel SET
        marque = :marque,
        type_nom = :type_nom,
        numero_serie = :numero_serie,
        version_firmware = :version_firmware,
        adresse_ip = :adresse_ip,
        adresse_mac = :adresse_mac,
        date_fin_maintenance = :date_fin_maintenance,
        reference = :reference,
        usage_materiel = :usage_materiel,
        modele = :modele,
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

foreach ($data as $row) {

    // 🔴 skip si pas d'id
    if (empty($row['id'])) {
        $errors[] = "ID manquant";
        continue;
    }

    try {
        $stmt->execute([
            ':id' => $row['id'],
            ':marque' => $row['marque'] ?? null,
            ':type_nom' => $row['type_nom'] ?? null,
            ':numero_serie' => $row['numero_serie'] ?? null,
            ':version_firmware' => $row['version_firmware'] ?? null,
            ':adresse_ip' => $row['adresse_ip'] ?? null,
            ':adresse_mac' => $row['adresse_mac'] ?? null,
            ':date_fin_maintenance' => $row['date_fin_maintenance'] ?: null,
            ':reference' => $row['reference'] ?? null,
            ':usage_materiel' => $row['usage_materiel'] ?? null,
            ':modele' => $row['modele'] ?? null,
            ':ancien_firmware' => $row['ancien_firmware'] ?? null,
            ':masque' => $row['masque'] ?? null,
            ':passerelle' => $row['passerelle'] ?? null,
            ':login' => $row['login'] ?? null,
            ':password' => $row['password'] ?? null,
            ':ip_primaire' => $row['ip_primaire'] ?? null,
            ':mac_primaire' => $row['mac_primaire'] ?? null,
            ':ip_secondaire' => $row['ip_secondaire'] ?? null,
            ':mac_secondaire' => $row['mac_secondaire'] ?? null,
            ':stream_aes67_recu' => $row['stream_aes67_recu'] ?? null,
            ':stream_aes67_transmis' => $row['stream_aes67_transmis'] ?? null,
            ':ssid' => $row['ssid'] ?? null,
            ':type_cryptage' => $row['type_cryptage'] ?? null,
            ':password_wifi' => $row['password_wifi'] ?? null,
            ':libelle_pa_salle' => $row['libelle_pa_salle'] ?? null,
            ':numero_port_switch' => $row['numero_port_switch'] ?? null,
            ':vlan' => $row['vlan'] ?? null,
            ':date_fin_garantie' => $row['date_fin_garantie'] ?: null,
            ':date_derniere_inter' => $row['date_derniere_inter'] ?: null,
            ':commentaire' => $row['commentaire'] ?? null,
            ':url_github' => $row['url_github'] ?? null
        ]);

        $updated++;

    } catch (PDOException $e) {
        $errors[] = "ID {$row['id']} : " . $e->getMessage();
    }
}

echo json_encode([
    'status' => count($errors) ? 'partial' : 'success',
    'message' => "$updated ligne(s) mise(s) à jour",
    'updated' => $updated,
    'errors' => $errors
]);