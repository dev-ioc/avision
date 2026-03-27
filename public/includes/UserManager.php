<?php

class UserManager {
    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Config::getInstance()->getDb();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Vérifie si l'utilisateur a accès à une localisation spécifique
     * @param int $locationId ID de la localisation à vérifier
     * @return bool True si l'utilisateur a accès à la localisation, false sinon
     */
    public function hasLocationAccess($locationId) {
        return true; // Pour l'instant, on autorise l'accès à toutes les localisations
    }
} 