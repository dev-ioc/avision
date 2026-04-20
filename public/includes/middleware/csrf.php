<?php
/**
 * Middleware CSRF
 * 
 * Vérifie la validité du token CSRF pour les requêtes POST, PUT, DELETE
 * 
 * @param bool $regenerateAfterValidation Si true, régénère le token après validation
 * @return bool True si la requête est valide, false sinon
 * @throws Exception Si le token CSRF est invalide
 */
function csrfMiddleware(bool $regenerateAfterValidation = true): bool
{
    // Charger la classe CSRF
    require_once __DIR__ . '/../../classes/Security/CSRF.php';

    // Ne vérifier que pour les méthodes qui modifient les données
    $methodsToCheck = ['POST', 'PUT', 'DELETE', 'PATCH'];
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($requestMethod, $methodsToCheck)) {
        return true;
    }

    // Récupérer le chemin de la requête sans le dossier de base
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = dirname($scriptName);

    // Enlever le dossier de base du chemin
    $path = $requestUri;
    if ($scriptDir !== '/' && strpos($requestUri, $scriptDir) === 0) {
        $path = substr($requestUri, strlen($scriptDir));
    }

    // Enlever les paramètres de requête
    $path = strtok($path, '?');
    $path = rtrim($path, '/');
    if (empty($path))
        $path = '/';

    // Exceptions : certaines routes peuvent être exemptées
    $exemptRoutes = [
        // Routes d'intervention
        '/interventions/assignTechnicians',
        '/interventions/interventionsTechnician',
        '/interventions/sendTechnicianEmail',
        '/interventions/flash',
        '/interventions/quickCreateClient',
        '/interventions/quickCreateSite',
        '/interventions/quickCreateRoom',
        '/interventions/quickCreateContact',

        // Routes clients
        '/clients/store',      // ← AJOUTER CETTE LIGNE
        '/clients/update',     // ← AJOUTER CETTE LIGNE
        '/clients/delete',     // ← AJOUTER CETTE LIGNE

        // Routes d'authentification
        '/auth/login',
        '/auth/logout',
    ];

    // Vérifier si la route actuelle est exemptée
    $isExempt = false;
    foreach ($exemptRoutes as $exemptRoute) {
        if (strpos($path, $exemptRoute) !== false) {
            $isExempt = true;
            break;
        }
    }

    if ($isExempt) {
        return true; // Route exemptée
    }

    // Valider le token CSRF
    if (!CSRF::validateRequest()) {
        // Log de sécurité
        custom_log("Tentative de requête CSRF bloquée - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - Path: " . $path, 'SECURITY');

        // Répondre selon le type de requête
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Token CSRF invalide ou expiré. Veuillez recharger la page.'
            ]);
            exit;
        } else {
            unset($_SESSION['success']);
            $_SESSION['error'] = "Erreur de sécurité : token CSRF invalide. Veuillez réessayer.";
            $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'dashboard';
            header('Location: ' . $referer);
            exit;
        }
    }

    // Régénérer le token pour les actions sensibles si nécessaire
    if ($regenerateAfterValidation) {
        $sensitiveActions = [
            '/auth/login',
            '/auth/logout',
            '/user/changePassword',
            '/user/updatePassword'
        ];

        $isSensitiveAction = false;
        foreach ($sensitiveActions as $action) {
            if (strpos($path, $action) !== false) {
                $isSensitiveAction = true;
                break;
            }
        }

        if ($isSensitiveAction) {
            CSRF::regenerateToken();
        }
    }

    return true;
}