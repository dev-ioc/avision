<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../controllers/ExcelController.php';

$controller = new ExcelController($pdo);
$action = $_GET['action'] ?? 'index';

if ($action === 'save') {
    $controller->save();
} else {
    $controller->index();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Gestion Matériel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <style>
        body {
            font-family: Arial;
            padding: 20px;
        }

        button {
            padding: 10px 15px;
            margin-top: 10px;
            cursor: pointer;
        }
    </style>
</head>

<bod>

    <h2>Gestion du matériel</h2>
    <div id="excelTable"></div>
    <button onclick="saveData()">Enregistrer</button>
    <button onclick="addColumn()">Ajouter colonne</button>

    <script>
        const container = document.getElementById('excelTable');
        const hot = new Handsontable(container, {
            data: <?= json_encode($data) ?>,
            rowHeaders: false,
            colHeaders: ['ID', 'Designation', 'Quantité', 'Prix', 'Montant'],
            minSpareRows: 1,
            licenseKey: 'non-commercial-and-evaluation',
            contextMenu: true,
            columns: [
                { readOnly: true },
                { type: 'text', wordWrap: true },
                { type: 'numeric' },
                { type: 'numeric' },
                { type: 'numeric' }
            ],
            hiddenColumns: { columns: [0], indicators: false },
            autoWrapRow: true,
            autoRowSize: true
        });

        function saveData() {
            fetch('index.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data: hot.getData() })
            })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        alert("Sauvegarde réussie");
                        location.reload();
                    } else {
                        alert("Erreur");
                    }
                });
        }

        function addColumn() {
            const index = hot.countCols();
            hot.alter('insert_col', index);
            hot.updateSettings({
                colHeaders: hot.getColHeader().concat(['Nouvelle colonne']),
                columns: hot.getSettings().columns.concat({ type: 'text', wordWrap: true })
            });
        }
    </script>

    </body>

</html>