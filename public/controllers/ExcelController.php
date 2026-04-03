<?php
// public/controllers/ExcelController.php
require_once __DIR__ . '/../models/ExcelModel.php';

class ExcelController
{
    private $model;
    private $db;

    public function __construct()
    {
        // Instancier le modèle ici avec la connexion PDO
        global $db;
        $this->db = $db;
        $this->model = new ExcelModel($this->db);
    }

    public function index()
    {
        $data = $this->model->getAll();
        require __DIR__ . '/../views/excel/index.php';
    }

    public function save()
    {
        header('Content-Type: application/json'); // toujours JSON

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['data'])) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune donnée reçue']);
            exit;
        }

        $rows = $input['data'];

        foreach ($rows as $row) {
            if ($row[0] === null) {
                $this->model->insert($row[1], $row[2], $row[3], $row[4]);
            } else {
                $this->model->update($row[0], [
                    'designation' => $row[1],
                    'quantite' => $row[2],
                    'prix' => $row[3],
                    'montant' => $row[4]
                ]);
            }
        }

        echo json_encode(['status' => 'success']);
        exit;
    }
}