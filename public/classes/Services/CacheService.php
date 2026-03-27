<?php
/**
 * Service de cache pour données statiques
 * 
 * Fournit un système de cache simple pour les données qui changent rarement :
 * - Types de contrats
 * - Statuts d'interventions
 * - Niveaux d'accès
 * - Extensions de fichiers autorisées
 * - Etc.
 */
class CacheService {
    /**
     * Durée de vie du cache par défaut (en secondes)
     * @var int
     */
    private const DEFAULT_TTL = 3600; // 1 heure
    
    /**
     * Chemin du répertoire de cache
     * @var string
     */
    private static $cacheDir;
    
    /**
     * Cache en mémoire (pour éviter les lectures disque répétées)
     * @var array
     */
    private static $memoryCache = [];
    
    /**
     * Initialise le service de cache
     * 
     * @param string|null $cacheDir Chemin du répertoire de cache (optionnel)
     */
    public static function init(?string $cacheDir = null): void {
        self::$cacheDir = $cacheDir ?? (defined('ROOT_PATH') ? ROOT_PATH . '/cache' : __DIR__ . '/../../../cache');
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Récupère une valeur du cache
     * 
     * @param string $key Clé du cache
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @return mixed La valeur en cache ou la valeur par défaut
     */
    public static function get(string $key, $default = null) {
        // Vérifier d'abord le cache mémoire
        if (isset(self::$memoryCache[$key])) {
            $cached = self::$memoryCache[$key];
            if ($cached['expires'] > time()) {
                return $cached['value'];
            } else {
                unset(self::$memoryCache[$key]);
            }
        }
        
        // Vérifier le cache fichier
        $cacheFile = self::getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            $data = @unserialize(file_get_contents($cacheFile));
            
            if ($data !== false && isset($data['value'], $data['expires'])) {
                // Vérifier si le cache n'a pas expiré
                if ($data['expires'] > time()) {
                    // Mettre en cache mémoire
                    self::$memoryCache[$key] = $data;
                    return $data['value'];
                } else {
                    // Cache expiré, supprimer le fichier
                    @unlink($cacheFile);
                }
            }
        }
        
        return $default;
    }
    
    /**
     * Stocke une valeur dans le cache
     * 
     * @param string $key Clé du cache
     * @param mixed $value Valeur à stocker
     * @param int|null $ttl Durée de vie en secondes (optionnel, utilise DEFAULT_TTL si non fourni)
     * @return bool True en cas de succès, false sinon
     */
    public static function set(string $key, $value, ?int $ttl = null): bool {
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $expires = time() + $ttl;
        
        $data = [
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        // Mettre en cache mémoire
        self::$memoryCache[$key] = $data;
        
        // Sauvegarder dans le fichier
        $cacheFile = self::getCacheFilePath($key);
        $result = @file_put_contents($cacheFile, serialize($data), LOCK_EX);
        
        return $result !== false;
    }
    
    /**
     * Supprime une clé du cache
     * 
     * @param string $key Clé à supprimer
     * @return bool True si la clé existait et a été supprimée
     */
    public static function delete(string $key): bool {
        // Supprimer du cache mémoire
        unset(self::$memoryCache[$key]);
        
        // Supprimer le fichier
        $cacheFile = self::getCacheFilePath($key);
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        
        return false;
    }
    
    /**
     * Supprime toutes les clés correspondant à un préfixe
     * 
     * @param string $prefix Préfixe des clés à supprimer
     * @return int Nombre de clés supprimées
     */
    public static function deleteByPrefix(string $prefix): int {
        $count = 0;
        
        // Supprimer du cache mémoire
        foreach (array_keys(self::$memoryCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset(self::$memoryCache[$key]);
                $count++;
            }
        }
        
        // Supprimer les fichiers
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '/' . $prefix . '*');
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Vide tout le cache
     * 
     * @return bool True en cas de succès
     */
    public static function clear(): bool {
        // Vider le cache mémoire
        self::$memoryCache = [];
        
        // Supprimer tous les fichiers de cache
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Vérifie si une clé existe dans le cache et n'est pas expirée
     * 
     * @param string $key Clé à vérifier
     * @return bool True si la clé existe et n'est pas expirée
     */
    public static function has(string $key): bool {
        // Vérifier le cache mémoire
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key]['expires'] > time();
        }
        
        // Vérifier le cache fichier
        $cacheFile = self::getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            $data = @unserialize(file_get_contents($cacheFile));
            
            if ($data !== false && isset($data['expires'])) {
                if ($data['expires'] > time()) {
                    // Mettre en cache mémoire
                    self::$memoryCache[$key] = $data;
                    return true;
                } else {
                    // Cache expiré, supprimer
                    @unlink($cacheFile);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Récupère une valeur du cache ou l'exécute et la met en cache
     * 
     * @param string $key Clé du cache
     * @param callable $callback Fonction à exécuter si le cache n'existe pas
     * @param int|null $ttl Durée de vie en secondes (optionnel)
     * @return mixed La valeur en cache ou le résultat du callback
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null) {
        if (self::has($key)) {
            return self::get($key);
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Retourne le chemin du fichier de cache pour une clé
     * 
     * @param string $key Clé du cache
     * @return string Chemin du fichier
     */
    private static function getCacheFilePath(string $key): string {
        // Nettoyer la clé pour éviter les problèmes de fichiers
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::$cacheDir . '/' . $safeKey . '.cache';
    }
}
