<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../public/models/RoomModel.php';
require_once __DIR__ . '/../public/models/UserModel.php';
require_once __DIR__ . '/../public/models/ContactModel.php';
require_once __DIR__ . '/../public/cclasses/MailService.php';
require_once __DIR__ . '/../public/includes/functions.php';

$config = Config::getInstance();
$db = $config->getDb();

$roomModel = new RoomModel($db);
$userModel = new UserModel($db);
$contactModel = new ContactModel($db);
$mailService = new MailService($db);

$roomsToAlert = $roomModel->getRoomsNeedingInstallationAlert();

if (empty($roomsToAlert)) {
    echo "Aucune alerte à envoyer.\n";
    exit(0);
}

// Récupérer les admins
$admins = $userModel->getActiveAdmins();
$adminRecipients = [];
foreach ($admins as $admin) {
    $adminRecipients[] = [
        'email' => $admin['email'],
        'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))
    ];
}

foreach ($roomsToAlert as $room) {
    $recipients = $adminRecipients;

    // Ajouter le contact principal de la salle si renseigné et avec email
    if (!empty($room['main_contact_id'])) {
        $contact = $contactModel->getContactById($room['main_contact_id']);
        if ($contact && !empty($contact['email'])) {
            $recipients[] = [
                'email' => $contact['email'],
                'name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))
            ];
        }
    }

    if (empty($recipients)) {
        custom_log_mail("Aucun destinataire trouvé pour alerte salle " . $room['id'], 'WARNING');
        continue;
    }

    $success = $mailService->sendInstallationAlert($room, $recipients);

    if ($success) {
        $roomModel->markInstallationAlertSent($room['id']);
        echo "Alerte envoyée pour la salle #{$room['id']} ({$room['name']}).\n";
    } else {
        echo "Échec de l'envoi pour la salle #{$room['id']}.\n";
    }
}