<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== DEBUT CRON INSTALLATION ALERTS ===\n";

require_once __DIR__ . '/../public/includes/functions.php';
require_once __DIR__ . '/../public/includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../public/models/RoomModel.php';
require_once __DIR__ . '/../public/models/UserModel.php';
require_once __DIR__ . '/../public/models/ContactModel.php';
require_once __DIR__ . '/../public/classes/MailService.php';

echo "Fichiers chargés OK\n";

$config = Config::getInstance();
$db = $config->getDb();

echo "Configuration et DB OK\n";

$roomModel = new RoomModel($db);
$userModel = new UserModel($db);
$contactModel = new ContactModel($db);
$mailService = new MailService($db);

echo "Models et MailService OK\n";

/**
 * Récupération des vrais destinataires : les administrateurs actifs
 */
try {
    $admins = $userModel->getActiveAdmins();
} catch (Exception $e) {
    echo "ERREUR récupération des admins : " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($admins)) {
    echo "AUCUN ADMIN TROUVÉ — impossible d'envoyer les alertes. Vérifiez la table users.\n";
    exit(1);
}

$recipients = array_map(function ($admin) {
    return [
        'email' => $admin['email'],
        'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))
    ];
}, $admins);

echo "Nombre d'admins destinataires : " . count($recipients) . "\n";

try {
    $roomsToAlert = $roomModel->getRoomsNeedingInstallationAlert();
} catch (Exception $e) {
    echo "ERREUR récupération des salles : " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($roomsToAlert)) {
    echo "Aucune alerte à envoyer.\n";
    exit(0);
}

echo "Nombre de salles à alerter : " . count($roomsToAlert) . "\n";

foreach ($roomsToAlert as $room) {

    echo "\n-----------------------------------\n";
    echo "Traitement salle #{$room['id']}\n";
    echo "Nom : {$room['name']}\n";
    echo "Delivery date : {$room['delivery_date']}\n";
    echo "Contact principal ID : " . ($room['main_contact_id'] ?? 'Aucun') . "\n";
    echo "Tentative d'envoi du mail à " . count($recipients) . " admin(s)...\n";

    try {
        $success = $mailService->sendInstallationAlert($room, $recipients);

        if ($success) {
            echo "MAIL ENVOYÉ AVEC SUCCÈS à tous les admins\n";

            $marked = $roomModel->markInstallationAlertSent($room['id']);

            echo $marked
                ? "Salle marquée comme alerte envoyée.\n"
                : "ATTENTION : impossible de marquer la salle.\n";

        } else {
            echo "ECHEC PARTIEL OU TOTAL DE L'ENVOI, la salle ne sera PAS marquée, nouvel essai au prochain passage du cron.\n";
        }

    } catch (Exception $e) {
        echo "EXCEPTION LORS DE L'ENVOI DU MAIL : " . $e->getMessage() . "\n";
    }
}

echo "\n=== FIN CRON INSTALLATION ALERTS ===\n";