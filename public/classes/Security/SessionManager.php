<?php
/**
 * Gestionnaire de session sécurisé
 * 
 * Améliore la sécurité des sessions avec :
 * - Régénération d'ID de session
 * - Timeout de session
 * - Sécurisation des cookies
 */
class SessionManager {
    /**
     * Durée de vie de la session en secondes (2 heures par défaut)
     */
    private const SESSION_LIFETIME = 7200;
    
    /**
     * Durée avant régénération de l'ID de session (30 minutes)
     */
    private const REGENERATE_INTERVAL = 1800;
    
    /**
     * Démarre une session sécurisée
     * 
     * @param array $options Options supplémentaires pour session_start()
     * @return bool True si la session a démarré avec succès
     */
    public static function start(array $options = []): bool {
        // Configuration de sécurité des sessions
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
        // Utiliser 'Lax' au lieu de 'Strict' pour permettre les requêtes AJAX same-origin
        // 'Lax' permet l'envoi de cookies dans les requêtes GET et POST same-origin
        // tout en protégeant contre les attaques CSRF cross-site
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_lifetime', self::SESSION_LIFETIME);
        
        // Options par défaut
        $defaultOptions = [
            'cookie_lifetime' => self::SESSION_LIFETIME,
            'cookie_httponly' => true,
            'cookie_secure' => self::isHttps(),
            'cookie_samesite' => 'Lax', // Changé de 'Strict' à 'Lax' pour les requêtes AJAX
            'use_strict_mode' => true,
            'gc_maxlifetime' => self::SESSION_LIFETIME
        ];
        
        // Fusionner avec les options fournies
        $options = array_merge($defaultOptions, $options);
        
        // Démarrer la session
        if (session_status() === PHP_SESSION_NONE) {
            session_start($options);
        }
        
        // Vérifier et régénérer l'ID si nécessaire
        self::regenerateIfNeeded();
        
        // Vérifier l'expiration de la session
        self::checkExpiration();
        
        return true;
    }
    
    /**
     * Régénère l'ID de session si nécessaire
     */
    private static function regenerateIfNeeded(): void {
        $lastRegeneration = $_SESSION['_last_regeneration'] ?? 0;
        $now = time();
        
        // Régénérer si l'intervalle est dépassé
        if (($now - $lastRegeneration) > self::REGENERATE_INTERVAL) {
            session_regenerate_id(true); // true = supprimer l'ancien ID
            $_SESSION['_last_regeneration'] = $now;
        }
    }
    
    /**
     * Vérifie si la session a expiré
     */
    private static function checkExpiration(): void {
        $lastActivity = $_SESSION['_last_activity'] ?? time();
        $now = time();
        
        // Si la session a expiré, la détruire
        if (($now - $lastActivity) > self::SESSION_LIFETIME) {
            self::destroy();
            return;
        }
        
        // Mettre à jour la dernière activité
        $_SESSION['_last_activity'] = $now;
    }
    
    /**
     * Régénère immédiatement l'ID de session
     * Utile après une action sensible (connexion, changement de mot de passe, etc.)
     * 
     * @return bool True si la régénération a réussi
     */
    public static function regenerate(): bool {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_last_regeneration'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * Détruit la session
     */
    public static function destroy(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Vider les données de session
            $_SESSION = [];
            
            // Supprimer le cookie de session
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            // Détruire la session
            session_destroy();
        }
    }
    
    /**
     * Vérifie si la connexion est en HTTPS
     * 
     * @return bool True si HTTPS
     */
    private static function isHttps(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }
    
    /**
     * Récupère une valeur de session
     * 
     * @param string $key La clé
     * @param mixed $default La valeur par défaut
     * @return mixed La valeur ou la valeur par défaut
     */
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Définit une valeur de session
     * 
     * @param string $key La clé
     * @param mixed $value La valeur
     */
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Supprime une valeur de session
     * 
     * @param string $key La clé
     */
    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }
    
    /**
     * Vérifie si une clé existe dans la session
     * 
     * @param string $key La clé
     * @return bool True si la clé existe
     */
    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }
}
