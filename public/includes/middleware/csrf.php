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
function csrfMiddleware(bool $regenerateAfterValidation = true): bool {
    // Charger la classe CSRF
    require_once __DIR__ . '/../../classes/Security/CSRF.php';
    
    // Ne vérifier que pour les méthodes qui modifient les données
    $methodsToCheck = ['POST', 'PUT', 'DELETE', 'PATCH'];
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if (!in_array($requestMethod, $methodsToCheck)) {
        return true; // Pas besoin de vérifier pour GET, HEAD, OPTIONS
    }
    
    // Exceptions : certaines routes peuvent être exemptées (API publiques, webhooks, etc.)
    $exemptRoutes = [
        // Ajouter ici les routes exemptées si nécessaire
        // Exemple: '/api/webhook'
    ];
    
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    foreach ($exemptRoutes as $exemptRoute) {
        if (strpos($requestUri, $exemptRoute) !== false) {
            return true; // Route exemptée
        }
    }
    
    // Valider le token CSRF
    if (!CSRF::validateRequest()) {
        // Log de sécurité
        custom_log("Tentative de requête CSRF bloquée - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - URI: " . ($_SERVER['REQUEST_URI'] ?? ''), 'SECURITY');
        
        // Répondre selon le type de requête
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // Requête AJAX
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Token CSRF invalide ou expiré. Veuillez recharger la page.'
            ]);
            exit;
        } else {
            // Requête normale - Nettoyer les messages de session pour éviter les conflits
            unset($_SESSION['success']);
            $_SESSION['error'] = "Erreur de sécurité : token CSRF invalide. Veuillez réessayer.";
            
            // Essayer de rediriger vers la page précédente si possible
            $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'dashboard';
            header('Location: ' . $referer);
            exit;
        }
    }
    
    // Si la validation est réussie et qu'on doit régénérer, le faire
    // MAIS ne pas régénérer immédiatement pour éviter d'invalider les tokens des autres onglets
    // On régénère seulement après certaines actions sensibles (login, changement de mot de passe, etc.)
    if ($regenerateAfterValidation) {
        // Ne pas régénérer automatiquement après chaque requête POST
        // Cela permet aux utilisateurs d'avoir plusieurs onglets ouverts et de soumettre des formulaires
        // Le token sera régénéré automatiquement après 30 minutes (TOKEN_LIFETIME)
        
        // Régénérer seulement pour certaines actions sensibles
        $sensitiveActions = [
            '/auth/login',
            '/auth/logout',
            '/user/changePassword',
            '/user/updatePassword'
        ];
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isSensitiveAction = false;
        foreach ($sensitiveActions as $action) {
            if (strpos($requestUri, $action) !== false) {
                $isSensitiveAction = true;
                break;
            }
        }
        
        // Régénérer seulement pour les actions sensibles
        if ($isSensitiveAction) {
            CSRF::regenerateToken();
        }
    }
    
    return true;
}
