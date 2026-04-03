<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/ExcelController.php';

if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}
$allData = [];

foreach ($data as $row) {
  $allData[] = [
    $row['id'],
    $row['designation'],
    $row['quantity'],
    $row['prix'],
    $row['montant']
  ];
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
      data: <?= json_encode($allData) ?>,
      rowHeaders: false,
      colHeaders: ['ID', 'Designation', 'Quantité', 'Prix', 'Montant'],
      columns: [
        { readOnly: true },
        { type: 'text', wordWrap: true },
        { type: 'numeric' },
        { type: 'numeric' },
        { type: 'numeric' }
      ],
      minSpareRows: 1,
      licenseKey: 'non-commercial-and-evaluation',
      contextMenu: true,
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
      console.log(hot.getData());
      fetch('excel_save.php', {
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
            alert("Erreur: " + (res.message || ''));
          }
        })
        .catch(err => console.error(err));
    }
  </script>
</body>

</html>