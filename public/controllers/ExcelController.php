<?php
// public/controllers/ExcelController.php
require_once __DIR__ . '/../models/ExcelModel.php';

class ExcelController
{
    private $model;
    private $db;

    public function __construct()
    {
        global $db;

        if ($db === null) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Connexion DB échouée']);
            exit;
        }

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
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['data'])) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune donnée reçue']);
            exit;
        }

        foreach ($input['data'] as $row) {
            $designation = trim($row[1] ?? '');
            if ($designation === '')
                continue;

            if (empty($row[0])) {
                $this->model->insert($row[1], $row[2], $row[3], $row[4]);
            } else {
                $this->model->update($row[0], [
                    'designation' => $row[1],
                    'quantity' => $row[2],
                    'prix' => $row[3],
                    'montant' => $row[4]
                ]);
            }
        }

        echo json_encode(['status' => 'success']);
        exit;
    }
    public function delete(array $ids)
    {
        header('Content-Type: application/json');
        $this->model->deleteByIds($ids);
        echo json_encode(['status' => 'success']);
        exit;
    }
}