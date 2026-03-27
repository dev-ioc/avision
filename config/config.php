<?php
/**
 * Classe de configuration de l'application
 */

class Config {
    private static $instance = null;
    private $settings = [];
    private $db = null;

    /**
     * Constructeur privé pour le pattern Singleton
     */
    private function __construct() {
        $this->initDatabase();
        $this->loadSettings();
    }

    /**
     * Initialise la connexion à la base de données
     */
    private function initDatabase() {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch (PDOException $e) {
            custom_log("Erreur de connexion à la base de données : " . $e->getMessage(), 'ERROR');
            throw new Exception("Impossible de se connecter à la base de données");
        }
    }

    /**
     * Charge les paramètres depuis la base de données
     */
    private function loadSettings() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
            $this->settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Vérifier que les paramètres requis existent
            $requiredSettings = ['site_url', 'site_name'];
            foreach ($requiredSettings as $setting) {
                if (!isset($this->settings[$setting])) {
                    throw new Exception("Paramètre requis manquant en base de données : $setting");
                }
            }
        } catch (PDOException $e) {
            custom_log("Erreur lors du chargement des paramètres : " . $e->getMessage(), 'ERROR');
            throw new Exception("Impossible de charger les paramètres de configuration");
        }
    }

    /**
     * Retourne l'instance unique de la classe (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupère un paramètre
     * @param string $key La clé du paramètre
     * @param mixed $default Valeur par défaut si le paramètre n'existe pas
     * @return mixed La valeur du paramètre
     */
    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Vérifie si un paramètre existe
     * @param string $key La clé du paramètre
     * @return bool True si le paramètre existe
     */
    public function has($key) {
        return isset($this->settings[$key]);
    }

    /**
     * Récupère l'URL de base de l'application
     * @return string L'URL de base
     */
    public function getBaseUrl() {
        return $this->get('site_url');
    }

    /**
     * Récupère le nom du site
     * @return string Le nom du site
     */
    public function getSiteName() {
        return $this->get('site_name');
    }
    
    /**
     * Récupère la connexion à la base de données
     * @return PDO La connexion à la base de données
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Définit un paramètre et le sauvegarde en base de données
     * @param string $key La clé du paramètre
     * @param mixed $value La valeur du paramètre
     * @return bool True si la sauvegarde a réussi
     */
    public function set($key, $value) {
        try {
            // Vérifier si le setting existe déjà
            $stmt = $this->db->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Mettre à jour le setting existant
                $stmt = $this->db->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // Créer un nouveau setting
                $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt->execute([$key, $value]);
            }
            
            // Mettre à jour le cache local
            $this->settings[$key] = $value;
            
            return true;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la sauvegarde du paramètre $key : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Recharge les paramètres depuis la base de données
     */
    public function reloadSettings() {
        $this->loadSettings();
    }
} 