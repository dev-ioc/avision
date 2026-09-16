<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== DEBUT CRON INSTALLATION ALERTS ===\n";

// Fonctions utilisées par database.php et config.php
require_once __DIR__ . '/../public/includes/functions.php';
require_once __DIR__ . '/../public/includes/init.php';
// Configuration DB : définit DB_HOST, DB_NAME, DB_USER, DB_PASS
require_once __DIR__ . '/../config/database.php';

// Configuration générale
require_once __DIR__ . '/../config/config.php';

// Modèles et service mail
require_once __DIR__ . '/../public/models/RoomModel.php';

require_once __DIR__ . '/../public/models/UserModel.php';
require_once __DIR__ . '/../public/models/ContactModel.php';

require_once __DIR__ . '/../public/classes/MailService.php';

$config = Config::getInstance();

$db = $config->getDb();


$roomModel = new RoomModel($db);
$userModel = new UserModel($db);
$contactModel = new ContactModel($db);
$mailService = new MailService($db);

/**
 * Récupérer les salles nécessitant une alerte
 */
$roomsToAlert = $roomModel->getRoomsNeedingInstallationAlert();

echo "Nombre de salles à alerter : " . count($roomsToAlert) . "\n";

if (empty($roomsToAlert)) {
    echo "Aucune alerte à envoyer.\n";
    exit(0);
}


/**
 * Afficher les salles trouvées
 */
foreach ($roomsToAlert as $room) {
    echo "Salle trouvée : #{$room['id']} - {$room['name']}\n";
    echo "Delivery date : {$room['delivery_date']}\n";
}


/**
 * Récupérer les admins
 */
$admins = $userModel->getActiveAdmins();

echo "Nombre d'admins récupérés : " . count($admins) . "\n";


$adminRecipients = [];

foreach ($admins as $admin) {

    if (!empty($admin['email'])) {

        $adminRecipients[] = [
            'email' => $admin['email'],
            'name' => trim(
                ($admin['first_name'] ?? '') . ' ' .
                ($admin['last_name'] ?? '')
            )
        ];

        echo "Admin destinataire : {$admin['email']}\n";
    }
}


/**
 * Traiter chaque salle
 */
foreach ($roomsToAlert as $room) {

    echo "\n-----------------------------------\n";
    echo "Traitement salle #{$room['id']}\n";
    echo "Nom : {$room['name']}\n";

    $recipients = $adminRecipients;


    /**
     * Ajouter le contact principal
     */
    if (!empty($room['main_contact_id'])) {

        echo "Contact principal ID : {$room['main_contact_id']}\n";

        $contact = $contactModel->getContactById(
            $room['main_contact_id']
        );

        if ($contact && !empty($contact['email'])) {

            $recipients[] = [
                'email' => $contact['email'],
                'name' => trim(
                    ($contact['first_name'] ?? '') . ' ' .
                    ($contact['last_name'] ?? '')
                )
            ];

            echo "Contact ajouté : {$contact['email']}\n";

        } else {

            echo "Aucun email trouvé pour le contact principal.\n";
        }
    }


    /**
     * Vérifier les destinataires
     */
    echo "Nombre total de destinataires : " . count($recipients) . "\n";

    if (empty($recipients)) {

        echo "ERREUR : aucun destinataire.\n";

        continue;
    }


    /**
     * Envoyer le mail
     */
    echo "Tentative d'envoi du mail...\n";

    $success = $mailService->sendInstallationAlert(
        $room,
        $recipients
    );


    if ($success) {

        $marked = $roomModel->markInstallationAlertSent(
            $room['id']
        );

        if ($marked) {
            echo "Salle marquée comme alerte envoyée.\n";
        } else {
            echo "ATTENTION : impossible de marquer la salle.\n";
        }

    } else {

        echo "ECHEC DE L'ENVOI DU MAIL\n";
    }
}

echo "\n=== FIN CRON INSTALLATION ALERTS ===\n";