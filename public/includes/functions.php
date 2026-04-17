<?php
/**
 * Fichier principal de fonctions utilitaires
 * Charge tous les modules de fonctions
 * 
 * Structure modulaire :
 * - auth.php : Authentification et session
 * - permissions.php : Permissions et autorisations
 * - access.php : Vérifications d'accès avec redirection
 * - locations.php : Gestion des localisations
 * - formatting.php : Formatage (dates, montants, HTML)
 * - breadcrumbs.php : Génération des breadcrumbs
 * - tickets.php : Gestion des tickets/contrats
 * - files.php : Utilitaires fichiers
 * - ui.php : Utilitaires UI (icônes, pages)
 */

// Charger tous les modules
require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/permissions.php';
require_once __DIR__ . '/functions/access.php';
require_once __DIR__ . '/functions/locations.php';
require_once __DIR__ . '/functions/formatting.php';
require_once __DIR__ . '/functions/breadcrumbs.php';
require_once __DIR__ . '/functions/tickets.php';
require_once __DIR__ . '/functions/files.php';
require_once __DIR__ . '/functions/ui.php';

// Charger la classe CSRF pour les helpers
require_once __DIR__ . '/../classes/Security/CSRF.php';

/**
 * Génère un champ hidden avec le token CSRF pour les formulaires
 * 
 * @return string HTML du champ hidden avec le token CSRF
 */
function csrf_field(): string
{
    // S'assurer que la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Générer un token s'il n'existe pas ou si c'est un tableau
    if (empty($_SESSION['csrf_token']) || is_array($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

/**
 * Génère le token CSRF (pour les requêtes AJAX)
 * 
 * @return string Le token CSRF
 */
function csrf_token(): string
{
    // S'assurer que la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Générer un token s'il n'existe pas ou si c'est un tableau
    if (empty($_SESSION['csrf_token']) || is_array($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF
 * 
 * @param string|null $token Le token à vérifier (null pour prendre de $_POST)
 * @return bool True si le token est valide
 */
function csrf_verify(?string $token = null): bool
{
    // S'assurer que la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (empty($token) || empty($_SESSION['csrf_token']) || is_array($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}