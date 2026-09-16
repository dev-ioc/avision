<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== DEBUT CRON INSTALLATION ALERTS ===\n";

/**
 * Chargement des fichiers nécessaires
 */
require_once __DIR__ . '/../public/includes/functions.php';
require_once __DIR__ . '/../public/includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Modèles et service mail
 */
require_once __DIR__ . '/../public/models/RoomModel.php';
require_once __DIR__ . '/../public/models/UserModel.php';
require_once __DIR__ . '/../public/models/ContactModel.php';
require_once __DIR__ . '/../public/classes/MailService.php';

echo "Fichiers chargés OK\n";

/**
 * Initialisation
 */
$config = Config::getInstance();
$db = $config->getDb();

echo "Configuration et DB OK\n";

/**
 * Initialisation des modèles/services
 */
$roomModel = new RoomModel($db);
$userModel = new UserModel($db);
$contactModel = new ContactModel($db);
$mailService = new MailService($db);

echo "Models et MailService OK\n";

/**
 * DESTINATAIRE DE TEST
 *
 * Pour ce test, le mail sera envoyé uniquement à :
 * dev_mdg@caspeo.fr
 */
$testRecipient = [
    'email' => 'dev_mdg@caspeo.fr',
    'name' => 'Dev MDG'
];

echo "Destinataire TEST : {$testRecipient['email']}\n";

/**
 * Récupérer les salles nécessitant une alerte
 */
try {

    $roomsToAlert = $roomModel->getRoomsNeedingInstallationAlert();

} catch (Exception $e) {

    echo "ERREUR récupération des salles : "
        . $e->getMessage()
        . "\n";

    exit(1);
}

/**
 * Vérifier s'il y a des salles à traiter
 */
if (empty($roomsToAlert)) {

    echo "Aucune alerte à envoyer.\n";
    echo "=== FIN CRON INSTALLATION ALERTS ===\n";

    exit(0);
}

echo "Nombre de salles à alerter : "
    . count($roomsToAlert)
    . "\n";

/**
 * Traiter chaque salle
 */
foreach ($roomsToAlert as $room) {

    echo "\n";
    echo "-----------------------------------\n";
    echo "Traitement salle #{$room['id']}\n";
    echo "Nom : {$room['name']}\n";
    echo "Delivery date : {$room['delivery_date']}\n";
    echo "Contact principal ID : "
        . ($room['main_contact_id'] ?? 'Aucun')
        . "\n";

    /**
     * Pour le test :
     * uniquement dev_mdg@caspeo.fr
     */
    $recipients = [
        $testRecipient
    ];

    echo "Nombre total de destinataires : "
        . count($recipients)
        . "\n";

    echo "Destinataire : "
        . $testRecipient['email']
        . "\n";

    /**
     * Envoyer le mail
     */
    echo "Tentative d'envoi du mail...\n";

    try {

        $success = $mailService->sendInstallationAlert(
            $room,
            $recipients
        );

        /**
         * Si l'envoi est réussi
         */
        if ($success) {

            echo "MAIL ENVOYÉ AVEC SUCCÈS\n";
            echo "Destinataire : {$testRecipient['email']}\n";

            /**
             * Marquer la salle comme alerte envoyée
             */
            $marked = $roomModel->markInstallationAlertSent(
                $room['id']
            );

            if ($marked) {

                echo "Salle marquée comme alerte envoyée.\n";

            } else {

                echo "ATTENTION : impossible de marquer la salle.\n";
            }

        } else {

            /**
             * Si l'envoi échoue
             */
            echo "ECHEC DE L'ENVOI DU MAIL\n";
            echo "Destinataire : {$testRecipient['email']}\n";
        }

    } catch (Exception $e) {

        echo "EXCEPTION LORS DE L'ENVOI DU MAIL : "
            . $e->getMessage()
            . "\n";
    }
}

echo "\n=== FIN CRON INSTALLATION ALERTS ===\n";