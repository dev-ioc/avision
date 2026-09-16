<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Fonctions utilisées par database.php et config.php
require_once __DIR__ . '/../public/includes/functions.php';
require_once __DIR__ . '/../public/includes/init.php';
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
 * Destinataire de TEST uniquement
 */
$testRecipient = [
    'email' => 'dev_mdg@caspeo.fr',
    'name' => 'Dev MDG'
];

/**
 * Récupérer les salles nécessitant une alerte
 */
$roomsToAlert = $roomModel->getRoomsNeedingInstallationAlert();

if (empty($roomsToAlert)) {
    echo "Aucune alerte à envoyer.\n";
    exit(0);
}

echo "Nombre de salles à alerter : " . count($roomsToAlert) . "\n";
echo "Mode TEST : envoi uniquement à {$testRecipient['email']}\n";

/**
 * Traiter chaque salle
 */
foreach ($roomsToAlert as $room) {

    echo "\n-----------------------------------\n";
    echo "Traitement salle #{$room['id']}\n";
    echo "Nom : {$room['name']}\n";
    echo "Delivery date : {$room['delivery_date']}\n";
    echo "Destinataire TEST : {$testRecipient['email']}\n";
    echo "Tentative d'envoi du mail...\n";

    /**
     * IMPORTANT :
     * On envoie uniquement au destinataire de test.
     */
    $success = $mailService->sendInstallationAlert(
        $room,
        [$testRecipient]
    );

    if ($success) {

        echo "MAIL ENVOYÉ AVEC SUCCÈS à {$testRecipient['email']}\n";

        /*
         * Pour le test, on ne marque PAS encore la salle
         * comme "alerte envoyée".
         *
         * Cela permet de refaire le test plusieurs fois.
         */
        echo "Salle NON marquée comme alerte envoyée (mode TEST).\n";

    } else {

        echo "ECHEC DE L'ENVOI DU MAIL à {$testRecipient['email']}\n";
    }
}

echo "\n=== FIN CRON INSTALLATION ALERTS ===\n";