<?php
// public/controllers/ExcelController.php
require_once __DIR__ . '/../models/ExcelModel.php';

class ExcelController
{
    private $model;
    private $db;

    public function __construct($pdo)
    {
        global $db;
        $this->db = $db;

    }

    public function index()
    {
        $data = $this->model->getAll();
        require __DIR__ . '/../views/excel/index.php';
    }

    public function save()
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['data'])) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune donnée reçue']);
            exit;
        }

        $data = $input['data'];
        $existingIds = array_column($this->model->getAll(), 'id');
        $receivedIds = [];

        foreach ($data as $row) {
            $id = $row[0] ?? null;
            $designation = trim($row[1] ?? '');
            $quantity = is_numeric($row[2]) ? (int) $row[2] : 0;
            $prix = is_numeric($row[3]) ? (float) $row[3] : 0;
            $montant = $quantity * $prix;

            if ($designation === '')
                continue;

            if (!empty($id)) {
                $this->model->update($id, $designation, $quantity, $prix, $montant);
                $receivedIds[] = (int) $id;
            } else {
                if (!$this->model->exists($designation)) {
                    $newId = $this->model->insert($designation, $quantity, $prix, $montant);
                    $receivedIds[] = (int) $newId;
                }
            }
        }

        // Supprimer les lignes supprimées
        $idsToDelete = array_diff($existingIds, $receivedIds);
        $this->model->deleteByIds($idsToDelete);

        echo json_encode(['status' => 'success']);
        exit;
    }
}