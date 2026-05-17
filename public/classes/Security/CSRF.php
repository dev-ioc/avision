<?php
/**
 * Gestion de la protection CSRF (Cross-Site Request Forgery)
 * 
 * Génère et valide les tokens CSRF pour protéger les formulaires
 * contre les attaques CSRF.
 */
class CSRF
{
    /**
     * Nom de la clé de session pour stocker le token
     */
    private const SESSION_KEY = 'csrf_token';

    /**
     * Durée de vie du token en secondes (24 h pour éviter expiration sur pages laissées ouvertes, ex. modale envoi email)
     */
    private const TOKEN_LIFETIME = 86400;

    /**
     * Assure que la session est démarrée
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Génère un nouveau token CSRF et le stocke en session
     * 
     * @return string Le token CSRF généré
     */
    public static function generateToken(): string
    {
        self::ensureSession();

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
    public static function getToken(): string
    {
        self::ensureSession();

        // Si le token n'existe pas ou est expiré, en générer un nouveau
        if (
            !isset($_SESSION[self::SESSION_KEY]) ||
            !isset($_SESSION[self::SESSION_KEY]['created_at']) ||
            (time() - $_SESSION[self::SESSION_KEY]['created_at']) > self::TOKEN_LIFETIME
        ) {
            return self::generateToken();
        }

        return (string) $_SESSION[self::SESSION_KEY]['token'];
    }

    /**
     * Valide un token CSRF
     * 
     * @param string|null $token Le token à valider (peut être null)
     * @return bool True si le token est valide, false sinon
     */
    public static function validateToken(?string $token): bool
    {
        self::ensureSession();

        if (empty($token) || !is_string($token)) {
            return false;
        }

        $token = trim($token);

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // Compatibilité : session peut être un tableau OU une string directe
        $sessionData = $_SESSION[self::SESSION_KEY];

        if (is_array($sessionData)) {
            if (!isset($sessionData['token'])) {
                return false;
            }
            // Vérifier expiration
            if (
                isset($sessionData['created_at']) &&
                (time() - $sessionData['created_at']) > self::TOKEN_LIFETIME
            ) {
                unset($_SESSION[self::SESSION_KEY]);
                return false;
            }
            $sessionToken = trim((string) $sessionData['token']);
        } elseif (is_string($sessionData)) {
            // Ancien format : string directe, pas d'expiration possible
            $sessionToken = trim($sessionData);
        } else {
            return false;
        }

        if (strlen($token) !== 64 || strlen($sessionToken) !== 64) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Valide le token depuis la requête (POST ou header)
     * 
     * @return bool True si le token est valide, false sinon
     */
    public static function validateRequest(): bool
    {
        self::ensureSession();

        // Chercher le token dans POST d'abord
        $token = $_POST['csrf_token'] ?? null;

        // Si pas dans POST, chercher dans les headers (pour les requêtes AJAX)
        if (empty($token)) {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

            if (empty($headerToken) && function_exists('getallheaders')) {
                $headers = getallheaders();
                if ($headers) {
                    $headerToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
                }
            }

            $token = $headerToken;
        }

        // Si c'est un tableau (cas bizarre), le convertir en chaîne
        if (is_array($token)) {
            $token = reset($token);
        }

        // Log pour débogage
        if (function_exists('custom_log')) {
            if (empty($token)) {
                custom_log("CSRF: Token manquant dans la requête - URI: " . ($_SERVER['REQUEST_URI'] ?? ''), 'DEBUG');
            } else {
                custom_log("CSRF: Token trouvé dans la requête - Token: " . substr((string) $token, 0, 20) . "...", 'DEBUG');
            }
        }

        return self::validateToken($token ? (string) $token : null);
    }

    /**
     * Régénère le token CSRF (utile après une action sensible)
     * 
     * @return string Le nouveau token
     */
    public static function regenerateToken(): string
    {
        self::ensureSession();

        // Supprimer l'ancien token
        unset($_SESSION[self::SESSION_KEY]);

        // Générer un nouveau token
        return self::generateToken();
    }

    /**
     * Supprime le token CSRF de la session
     */
    public static function clearToken(): void
    {
        self::ensureSession();

        unset($_SESSION[self::SESSION_KEY]);
    }
}