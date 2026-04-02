<?php
$host = 'localhost';
$db = 'avision';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['data'])) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    $data = $input['data'];
    $existingIds = $pdo->query("SELECT id FROM excel_tables")->fetchAll(PDO::FETCH_COLUMN);
    $receivedIds = [];

    foreach ($data as $row) {
        $id = $row[0] ?? null;
        $designation = trim($row[1] ?? '');
        $quantity = is_numeric($row[2]) ? (int) $row[2] : 0;
        $prix = is_numeric($row[3]) ? (float) $row[3] : 0;
        $montant = $quantity * $prix;

        if ($designation === '')
            continue;

        if (!empty($id) && is_numeric($id)) {
            $stmt = $pdo->prepare("UPDATE excel_tables SET designation=?, quantity=?, prix=?, montant=? WHERE id=?");
            $stmt->execute([$designation, $quantity, $prix, $montant, $id]);
            $receivedIds[] = (int) $id;
        } else {
            $check = $pdo->prepare("SELECT id FROM excel_tables WHERE designation = ?");
            $check->execute([$designation]);
            $existing = $check->fetchColumn();
            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO excel_tables (designation, quantity, prix, montant) VALUES (?, ?, ?, ?)");
                $stmt->execute([$designation, $quantity, $prix, $montant]);
                $receivedIds[] = (int) $pdo->lastInsertId();
            }
        }
    }
    $idsToDelete = array_diff($existingIds, $receivedIds);
    if (!empty($idsToDelete)) {
        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM excel_tables WHERE id IN ($placeholders)");
        $stmt->execute(array_values($idsToDelete));
    }

    echo json_encode(['status' => 'success']);
    exit;
}
$stmt = $pdo->query("SELECT id, designation, quantity, prix, montant FROM excel_tables");
$data = [];
while ($row = $stmt->fetch()) {
    $data[] = [$row['id'], $row['designation'], $row['quantity'], $row['prix'], $row['montant']];
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

<body>

    <h2>Gestion du matériel</h2>

    <div id="excelTable"></div>

    <button onclick="saveData()">Enregistrer</button>

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
                { type: 'numeric', readOnly: true }
            ],
            autoWrapRow: true,
            autoRowSize: true,

            afterChange: function (changes, source) {
                if (!changes) return;
                changes.forEach(([row, prop, oldValue, newValue]) => {
                    if (prop === 2 || prop === 3) {
                        const qty = parseFloat(this.getDataAtCell(row, 2)) || 0;
                        const price = parseFloat(this.getDataAtCell(row, 3)) || 0;
                        this.setDataAtCell(row, 4, qty * price);
                    }
                });
            }
        });

        function saveData() {
            const allData = hot.getData();
            fetch('test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data: allData })
            })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        alert("Sauvegarde réussie");
                        location.reload();
                    } else {
                        alert("Erreur lors de la sauvegarde");
                    }
                })
                .catch(err => console.error("Erreur :", err));
        }
    </script>

</body>

</html>