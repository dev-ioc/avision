<?php
/**
 * Gestionnaire d'erreurs centralisé
 * 
 * Fournit une gestion uniforme des erreurs dans toute l'application :
 * - Logging structuré
 * - Affichage utilisateur approprié
 * - Gestion des différents types d'erreurs
 */
class ErrorHandler {
    /**
     * Niveaux de log
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    /**
     * Mode de l'application (dev ou prod)
     * @var string
     */
    private static $environment = 'dev';
    
    /**
     * Chemin du fichier de log
     * @var string
     */
    private static $logFile;
    
    /**
     * Initialise le gestionnaire d'erreurs
     * 
     * @param string $environment Mode de l'application ('dev' ou 'prod')
     * @param string|null $logFile Chemin du fichier de log (optionnel)
     */
    public static function init(string $environment = 'dev', ?string $logFile = null) {
        self::$environment = $environment;
        self::$logFile = $logFile ?? (defined('LOGS_PATH') ? LOGS_PATH . '/app.log' : __DIR__ . '/../../../logs/app.log');
        
        // Configurer les gestionnaires d'erreurs PHP
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Gère les erreurs PHP (E_ERROR, E_WARNING, etc.)
     * 
     * @param int $errno Niveau de l'erreur
     * @param string $errstr Message d'erreur
     * @param string $errfile Fichier où l'erreur s'est produite
     * @param int $errline Ligne où l'erreur s'est produite
     * @return bool True si l'erreur a été gérée
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Ignorer les erreurs qui ne doivent pas être loggées
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $level = self::mapErrorLevel($errno);
        $context = [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ];
        
        self::log($errstr, $level, $context);
        
        // En mode dev, afficher l'erreur
        if (self::$environment === 'dev') {
            echo "<div style='background: #fee; border: 1px solid #fcc; padding: 10px; margin: 10px;'>";
            echo "<strong>Erreur PHP ($level):</strong> $errstr<br>";
            echo "<small>Fichier: $errfile (ligne $errline)</small>";
            echo "</div>";
        }
        
        return true;
    }
    
    /**
     * Gère les exceptions non capturées
     * 
     * @param Throwable $exception L'exception à gérer
     */
    public static function handleException(Throwable $exception): void {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'type' => get_class($exception)
        ];
        
        // Déterminer le niveau selon le type d'exception
        $level = $exception instanceof PDOException ? self::LEVEL_CRITICAL : self::LEVEL_ERROR;
        
        self::log($exception->getMessage(), $level, $context);
        
        // En mode dev, afficher l'exception complète
        if (self::$environment === 'dev') {
            http_response_code(500);
            echo "<div style='background: #fee; border: 2px solid #f00; padding: 15px; margin: 10px; font-family: monospace;'>";
            echo "<h3>Exception non capturée</h3>";
            echo "<p><strong>Type:</strong> " . get_class($exception) . "</p>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>Fichier:</strong> {$exception->getFile()} (ligne {$exception->getLine()})</p>";
            echo "<pre style='background: #fff; padding: 10px; overflow: auto;'>";
            echo htmlspecialchars($exception->getTraceAsString());
            echo "</pre>";
            echo "</div>";
        } else {
            // En production, afficher un message générique
            http_response_code(500);
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo "<!DOCTYPE html><html><head><title>Erreur</title></head><body>";
            echo "<h1>Une erreur est survenue</h1>";
            echo "<p>Veuillez contacter l'administrateur si le problème persiste.</p>";
            echo "</body></html>";
        }
    }
    
    /**
     * Gère les erreurs fatales (shutdown)
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $context = [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ];
            
            self::log($error['message'], self::LEVEL_CRITICAL, $context);
            
            if (self::$environment === 'dev') {
                echo "<div style='background: #fee; border: 2px solid #f00; padding: 15px; margin: 10px;'>";
                echo "<h3>Erreur fatale</h3>";
                echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
                echo "<p><strong>Fichier:</strong> {$error['file']} (ligne {$error['line']})</p>";
                echo "</div>";
            }
        }
    }
    
    /**
     * Log une erreur avec contexte
     * 
     * @param string $message Message d'erreur
     * @param string $level Niveau de log
     * @param array $context Contexte supplémentaire
     */
    public static function log(string $message, string $level = self::LEVEL_ERROR, array $context = []): void {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $logMessage = "[$date][$level] $message$contextStr\n";
        
        // Utiliser la fonction custom_log si elle existe, sinon utiliser error_log
        if (function_exists('custom_log')) {
            custom_log($message, $level, $context);
        } else {
            error_log($logMessage, 3, self::$logFile);
        }
    }
    
    /**
     * Gère une exception et affiche un message à l'utilisateur
     * 
     * @param Throwable $exception L'exception à gérer
     * @param string|null $userMessage Message à afficher à l'utilisateur (optionnel)
     * @param string $redirectUrl URL de redirection (optionnel)
     */
    public static function handleUserException(Throwable $exception, ?string $userMessage = null, ?string $redirectUrl = null): void {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'type' => get_class($exception)
        ];
        
        self::log($exception->getMessage(), self::LEVEL_ERROR, $context);
        
        // Message utilisateur
        $message = $userMessage ?? "Une erreur est survenue. Veuillez réessayer.";
        
        if ($redirectUrl) {
            $_SESSION['error'] = $message;
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $_SESSION['error'] = $message;
        }
    }
    
    /**
     * Convertit le niveau d'erreur PHP en niveau de log
     * 
     * @param int $errno Niveau d'erreur PHP
     * @return string Niveau de log
     */
    private static function mapErrorLevel(int $errno): string {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
            case E_USER_ERROR:
                return self::LEVEL_CRITICAL;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::LEVEL_WARNING;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::LEVEL_INFO;
            default:
                return self::LEVEL_ERROR;
        }
    }
    
    /**
     * Retourne le mode de l'application
     * 
     * @return string
     */
    public static function getEnvironment(): string {
        return self::$environment;
    }
}
