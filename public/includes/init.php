<?php
/**
 * Fichier d'initialisation de l'application
 */

// Démarrage de la session sécurisée
require_once __DIR__ . '/../classes/Security/SessionManager.php';
SessionManager::start();

// Définition des constantes
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', PUBLIC_PATH . '/includes');
define('MODELS_PATH', PUBLIC_PATH . '/models');
define('CONTROLLERS_PATH', PUBLIC_PATH . '/controllers');
define('VIEWS_PATH', PUBLIC_PATH . '/views');
define('ASSETS_PATH', PUBLIC_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Configuration de l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Paris');

// Fonction de log personnalisée
function custom_log($message, $level = 'INFO', $context = []) {
    $log_file = LOGS_PATH . '/app.log';
    $date = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? json_encode($context) : '';
    $log_message = "[$date][$level] $message $context_str\n";
    error_log($log_message, 3, $log_file);
}

/**
 * Log dédié aux mails (config SMTP, envois, erreurs connexion).
 * Écrit dans logs/mail.log pour faciliter le diagnostic.
 */
function custom_log_mail($message, $level = 'INFO', $context = []) {
    $log_file = LOGS_PATH . '/mail.log';
    $date = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? json_encode($context) : '';
    $log_message = "[$date][$level] $message $context_str\n";
    error_log($log_message, 3, $log_file);
}

// Initialisation du gestionnaire d'erreurs centralisé
require_once __DIR__ . '/../classes/Error/ErrorHandler.php';
// Déterminer l'environnement (dev ou prod) - peut être défini dans config.php
$environment = defined('APP_ENV') ? APP_ENV : 'dev';
ErrorHandler::init($environment);

// Initialisation du service de cache
require_once __DIR__ . '/../classes/Services/CacheService.php';
CacheService::init();

// Chargement de la configuration de la base de données
require_once CONFIG_PATH . '/database.php';

// Chargement de la configuration principale
require_once CONFIG_PATH . '/config.php';
$config = Config::getInstance();

// Définition des constantes de configuration
define('BASE_URL', $config->getBaseUrl());
define('SITE_NAME', $config->getSiteName()); 