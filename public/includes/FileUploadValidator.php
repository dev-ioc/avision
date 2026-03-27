<?php
/**
 * Classe pour la validation des uploads de fichiers
 */
class FileUploadValidator {
    
    // Extensions interdites (non modifiables)
    private static $BLACKLISTED_EXTENSIONS = [
        // Exécutables Windows
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'msi', 'msu',
        
        // Exécutables Unix/Linux
        'sh', 'bash', 'zsh', 'csh', 'ksh', 'tcsh',
        
        // Scripts serveur
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml',
        'asp', 'aspx', 'jsp', 'jspx', 'pl', 'py', 'rb', 'ps1', 'psm1',
        
        // Bibliothèques système
        'dll', 'so', 'dylib', 'sys', 'drv',
        
        // Fichiers de configuration système
        'htaccess', 'htpasswd', 'ini', 'conf', 'config', 'cfg',
        
        // Autres dangereux
        'cgi', 'cgi-bin', 'vbs', 'js', 'jar', 'war', 'ear',
        
        // Fichiers de base de données sensibles
        'db', 'sqlite', 'sqlite3', 'mdb', 'accdb'
    ];
    
    /**
     * Vérifie si une extension est dans la liste noire
     */
    public static function isExtensionBlacklisted($extension) {
        return in_array(strtolower($extension), self::$BLACKLISTED_EXTENSIONS);
    }
    
    /**
     * Retourne la liste des extensions interdites
     */
    public static function getBlacklistedExtensions() {
        return self::$BLACKLISTED_EXTENSIONS;
    }
    
    /**
     * Vérifie si une extension est autorisée (pas blacklistée ET dans la table)
     */
    public static function isExtensionAllowed($extension, $db) {
        $extension = strtolower($extension);
        
        // Vérifier si l'extension est blacklistée
        if (self::isExtensionBlacklisted($extension)) {
            return false;
        }
        
        // Vérifier si l'extension est dans la liste autorisée
        $stmt = $db->prepare("SELECT is_active FROM settings_allowed_extensions WHERE extension = ?");
        $stmt->execute([$extension]);
        $result = $stmt->fetch();
        
        return $result && $result['is_active'] == 1;
    }
    
    /**
     * Retourne toutes les extensions autorisées actives
     */
    public static function getAllowedExtensions($db) {
        $stmt = $db->query("SELECT id, extension, mime_type, description, is_active FROM settings_allowed_extensions WHERE is_active = 1 ORDER BY extension");
        return $stmt->fetchAll();
    }
    
    /**
     * Retourne toutes les extensions (actives et inactives)
     */
    public static function getAllExtensions($db) {
        $stmt = $db->query("SELECT id, extension, mime_type, description, is_active FROM settings_allowed_extensions ORDER BY extension");
        return $stmt->fetchAll();
    }
    
    /**
     * Retourne la liste des extensions autorisées pour l'attribut accept
     */
    public static function getAcceptAttribute($db) {
        $extensions = self::getAllowedExtensions($db);
        $acceptList = [];
        
        foreach ($extensions as $ext) {
            $acceptList[] = '.' . $ext['extension'];
        }
        
        return implode(',', $acceptList);
    }
    
    /**
     * Retourne la liste des extensions autorisées pour l'affichage
     */
    public static function getExtensionsForDisplay($db) {
        $extensions = self::getAllowedExtensions($db);
        $extList = [];
        
        foreach ($extensions as $ext) {
            $extList[] = strtoupper($ext['extension']);
        }
        
        return implode(', ', $extList);
    }
} 