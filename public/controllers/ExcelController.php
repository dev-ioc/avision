<?php
require_once __DIR__ . '/../models/ExcelModel.php';

class ExcelController
{
    private $excelModel;
    private $db;

    public function __construct()
    {
        try {
            // Essayer d'utiliser la configuration existante
            if (class_exists('Config')) {
                $config = Config::getInstance();
                $this->db = $config->getDb();

                if (function_exists('log_debug')) {
                    log_debug("Connexion BDD récupérée via Config");
                }
            }
            // Fallback: utiliser les constantes définies
            elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                if (function_exists('log_debug')) {
                    log_debug("Utilisation des constantes DB_* pour la connexion");
                }

                $this->db = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    DB_OPTIONS ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            }
            $this->excelModel = new ExcelModel($this->db);

        } catch (Exception $e) {
            if (function_exists('log_error')) {
                log_error("Erreur de connexion BDD", $e->getMessage());
            }
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }

    public function processExcelUpdate($data)
    {
        if (function_exists('log_debug')) {
            log_debug("processExcelUpdate - " . count($data) . " lignes à traiter");
        }

        $result = $this->excelModel->updateMultipleMateriel($data);

        if (function_exists('log_debug')) {
            log_debug("processExcelUpdate terminé", $result);
        }

        return $result;
    }
}
?>