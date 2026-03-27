<?php
/**
 * Gestion de la protection CSRF (Cross-Site Request Forgery)
 * 
 * Génère et valide les tokens CSRF pour protéger les formulaires
 * contre les attaques CSRF.
 */
class CSRF {
    /**
     * Nom de la clé de session pour stocker le token
     */
    private const SESSION_KEY = 'csrf_token';
    
    /**
     * Durée de vie du token en secondes (24 h pour éviter expiration sur pages laissées ouvertes, ex. modale envoi email)
     */
    private const TOKEN_LIFETIME = 86400;
    
    /**
     * Génère un nouveau token CSRF et le stocke en session
     * 
     * @return string Le token CSRF généré
     */
    public static function generateToken(): string {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Générer un token aléatoire sécurisé
        $token = bin2hex(random_bytes(32));
        
        // Stocker le token avec un timestamp
        $_SESSION[self::SESSION_KEY] = [
            'token' => $token,
            'created_at' => time()
        ];
        
        return $token;
    }
    
    /**
     * Récupère le token CSRF actuel ou en génère un nouveau
     * 
     * @return string Le token CSRF
     */
    public static function getToken(): string {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Si le token n'existe pas ou est expiré, en générer un nouveau
        if (!isset($_SESSION[self::SESSION_KEY]) || 
            !isset($_SESSION[self::SESSION_KEY]['created_at']) ||
            (time() - $_SESSION[self::SESSION_KEY]['created_at']) > self::TOKEN_LIFETIME) {
            return self::generateToken();
        }
        
        return $_SESSION[self::SESSION_KEY]['token'];
    }
    
    /**
     * Valide un token CSRF
     * 
     * @param string|null $token Le token à valider (peut être null)
     * @return bool True si le token est valide, false sinon
     */
    public static function validateToken(?string $token): bool {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Vérifier que le token est fourni
        if (empty($token)) {
            return false;
        }
        
        // Vérifier que le token existe en session
        if (!isset($_SESSION[self::SESSION_KEY]) || 
            !isset($_SESSION[self::SESSION_KEY]['token'])) {
            return false;
        }
        
        // Vérifier que le token n'est pas expiré
        if (isset($_SESSION[self::SESSION_KEY]['created_at']) &&
            (time() - $_SESSION[self::SESSION_KEY]['created_at']) > self::TOKEN_LIFETIME) {
            // Token expiré, le supprimer
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }
        
        // Comparer les tokens de manière sécurisée (timing-safe)
        $sessionToken = $_SESSION[self::SESSION_KEY]['token'];
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Valide le token depuis la requête (POST ou header)
     * 
     * @return bool True si le token est valide, false sinon
     */
    public static function validateRequest(): bool {
        // Chercher le token dans POST d'abord
        $token = $_POST['csrf_token'] ?? null;
        
        // Si pas dans POST, chercher dans les headers (pour les requêtes AJAX)
        // Utiliser $_SERVER qui fonctionne dans tous les environnements (Apache, Nginx, FastCGI, etc.)
        if (empty($token)) {
            // Les headers HTTP sont préfixés avec HTTP_ et convertis en majuscules
            // X-CSRF-Token devient HTTP_X_CSRF_TOKEN
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            // Si pas trouvé, essayer getallheaders() en fallback (fonctionne sur Apache mod_php)
            if (empty($headerToken) && function_exists('getallheaders')) {
                $headers = getallheaders();
                if ($headers) {
                    $headerToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
                }
            }
            
            $token = $headerToken;
        }
        
        // Log pour débogage
        if (empty($token)) {
            custom_log("CSRF: Token manquant dans la requête - URI: " . ($_SERVER['REQUEST_URI'] ?? '') . " - Headers disponibles: " . json_encode(array_filter([
                'HTTP_X_CSRF_TOKEN' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
                'POST_csrf_token' => $_POST['csrf_token'] ?? null
            ])), 'DEBUG');
        }
        
        return self::validateToken($token);
    }
    
    /**
     * Régénère le token CSRF (utile après une action sensible)
     * 
     * @return string Le nouveau token
     */
    public static function regenerateToken(): string {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Supprimer l'ancien token
        unset($_SESSION[self::SESSION_KEY]);
        
        // Générer un nouveau token
        return self::generateToken();
    }
    
    /**
     * Supprime le token CSRF de la session
     */
    public static function clearToken(): void {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        unset($_SESSION[self::SESSION_KEY]);
    }
}
