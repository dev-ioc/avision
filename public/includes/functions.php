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
function csrf_field(): string {
    $token = CSRF::getToken();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * Génère le token CSRF (pour les requêtes AJAX)
 * 
 * @return string Le token CSRF
 */
function csrf_token(): string {
    return CSRF::getToken();
}
