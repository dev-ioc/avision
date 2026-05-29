<?php
/**
 * Middleware CSRF
 * Vérifie la validité du token CSRF pour les requêtes POST, PUT, DELETE
 */

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger la classe CSRF si nécessaire
if (!class_exists('CSRF')) {
    require_once __DIR__ . '/../../classes/Security/CSRF.php';
}

/**
 * Vérifie et valide le token CSRF pour la requête courante
 * 
 * @return bool True si la validation réussit ou n'est pas nécessaire
 */
function checkCsrfOrFail(): bool
{
    // Méthodes HTTP à vérifier
    $methodsToCheck = ['POST', 'PUT', 'DELETE', 'PATCH'];
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Si ce n'est pas une méthode modifiante, on passe
    if (!in_array($requestMethod, $methodsToCheck)) {
        return true;
    }

    // Récupérer l'URI pour les logs
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    // Vérifier si la route est exemptée
    $exemptRoutes = [
        '/interventions/webhookSignature', // Exemple de route exemptée
    ];

    foreach ($exemptRoutes as $exemptRoute) {
        if (strpos($requestUri, $exemptRoute) !== false) {
            return true;
        }
    }

    // Valider le token CSRF
    if (!CSRF::validateRequest()) {
        // Log de sécurité
        if (function_exists('custom_log')) {
            custom_log("Tentative de requête CSRF bloquée - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - URI: " . $requestUri, 'SECURITY');
            custom_log("POST data: " . json_encode($_POST), 'DEBUG');
            custom_log("Session token: " . json_encode($_SESSION['csrf_token'] ?? 'NOT SET'), 'DEBUG');
        }

        // Vider les messages précédents
        unset($_SESSION['success']);

        // Définir le message d'erreur
        $_SESSION['error'] = "Erreur de sécurité : token CSRF invalide. Veuillez réessayer.";

        // Rediriger vers la page précédente ou le dashboard
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'dashboard';
        header('Location: ' . $referer);
        exit;
    }

    return true;
}

// Si le fichier est inclus et que la requête est POST, exécuter la vérification
// Mais attention : on ne doit l'exécuter qu'une seule fois
if (!defined('CSRF_MIDDLEWARE_RUN')) {
    define('CSRF_MIDDLEWARE_RUN', true);

    // Ne pas exécuter automatiquement, laisser le routeur décider
    // pour éviter la double validation
}