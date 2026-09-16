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

$roomModel = new RoomModel($db);
$userModel = new UserModel($db);
$mailService = new MailService($db);

echo "Models et MailService OK\n";

// Admins toujours nécessaires en secours (email manquant)
try {
    $admins = $userModel->getActiveAdmins();
} catch (Exception $e) {
    $msg = "ERREUR récupération des admins : " . $e->getMessage();
    echo $msg . "\n";
    exit(1);
}

$adminRecipients = array_map(function ($admin) {
    return [
        'email' => $admin['email'],
        'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))
    ];
}, $admins);

try {
    $roomsToAlert = $roomModel->getRoomsNeedingInstallationAlert();
} catch (Exception $e) {
    $msg = "ERREUR récupération des salles : " . $e->getMessage();
    echo $msg . "\n";
    exit(1);
}

if (empty($roomsToAlert)) {
    $msg = "Aucune alerte à envoyer.";
    echo $msg . "\n";
    echo "=== FIN CRON INSTALLATION ALERTS ===\n";
    exit(0);
}

echo "Nombre de salles à traiter : " . count($roomsToAlert) . "\n";

$roomsSucceeded = [];
$roomsFailed = [];

foreach ($roomsToAlert as $room) {

    echo "\n-----------------------------------\n";
    echo "Salle #{$room['id']} - {$room['name']}\n";

    $stageLabel = $roomModel->getAlertStageLabel((int) $room['installation_alert_stage']);
    echo "Palier : $stageLabel\n";

    $roomEmails = $mailService->parseAlertEmails($room['installation_alert_email']);

    try {
        if (empty($roomEmails)) {
            // Pas d'email configuré sur la salle -> notifier les admins
            echo "AUCUN EMAIL CONFIGURÉ sur la salle -> notification aux admins\n";
            $success = $mailService->sendMissingAlertEmailNotice($room, $adminRecipients);
        } else {
            echo "Envoi à : " . implode(', ', array_column($roomEmails, 'email')) . "\n";
            $success = $mailService->sendInstallationAlert($room, $roomEmails, $stageLabel);
        }

        if ($success) {
            echo "ENVOI RÉUSSI\n";
            $roomModel->advanceInstallationAlertStage($room['id'], (int) $room['installation_alert_stage']);
            $roomsSucceeded[] = $room['name'] . " ($stageLabel)";
        } else {
            echo "ECHEC — la salle ne sera pas avancée au palier suivant, retenté au prochain passage.\n";
            $roomsFailed[] = $room['name'];
        }

    } catch (Exception $e) {
        echo "EXCEPTION : " . $e->getMessage() . "\n";
        $roomsFailed[] = $room['name'];
    }
}

$totalProcessed = count($roomsToAlert);
$status = empty($roomsFailed) ? 'success' : (empty($roomsSucceeded) ? 'error' : 'warning');

$summary = sprintf(
    "%d salle(s) traitée(s) — %d réussi(s), %d échec(s).%s%s",
    $totalProcessed,
    count($roomsSucceeded),
    count($roomsFailed),
    !empty($roomsSucceeded) ? " Réussi : " . implode(', ', $roomsSucceeded) . "." : "",
    !empty($roomsFailed) ? " Échoué : " . implode(', ', $roomsFailed) . "." : ""
);

echo "\n=== FIN CRON INSTALLATION ALERTS ===\n";