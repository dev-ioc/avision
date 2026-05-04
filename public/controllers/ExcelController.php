<?php
require_once __DIR__ . '/../models/ExcelModel.php';

class ExcelController
{
    private $excelModel;
    private $db;

    public function __construct()
    {
        try {
            $host = 'localhost';
            $dbname = 'avisiondb';
            $user = 'root';
            $pass = '';

            log_debug("Tentative de connexion BDD");

            $this->db = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            log_debug("Connexion BDD réussie");

            $this->excelModel = new ExcelModel($this->db);

        } catch (PDOException $e) {
            log_error("Erreur de connexion BDD", $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        } catch (Exception $e) {
            log_error("Erreur générale", $e->getMessage());
            throw $e;
        }
    }

    public function processExcelUpdate($data)
    {
        log_debug("processExcelUpdate - " . count($data) . " lignes à traiter");
        $result = $this->excelModel->updateMultipleMateriel($data);
        log_debug("processExcelUpdate terminé", $result);
        return $result;
    }
}
?>