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
      contextMenu: {
        items: {
          'row_above': { name: 'Insérer ligne au-dessus' },
          'row_below': { name: 'Insérer ligne en-dessous' },
          'separator': Handsontable.plugins.ContextMenu.SEPARATOR,
          'remove_row': {
            name: 'Supprimer la/les ligne(s)',
            callback: function (key, selection) {
              let rowsToRemove = [];
              selection.forEach(sel => {
                for (let r = sel.start.row; r <= sel.end.row; r++) {
                  rowsToRemove.push(r);
                }
              });
              rowsToRemove = [...new Set(rowsToRemove)].sort((a, b) => b - a);
              const idsToDelete = [];
              const rowsWithoutId = [];

              rowsToRemove.forEach(row => {
                const id = hot.getDataAtCell(row, 0);
                if (id) {
                  idsToDelete.push({ row, id });
                } else {
                  rowsWithoutId.push(row);
                }
              });
              rowsWithoutId.forEach(row => hot.alter('remove_row', row));
              if (idsToDelete.length === 0) return;

              fetch('<?= BASE_URL ?>views/excel/excel_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: idsToDelete.map(r => r.id) })
              })
                .then(res => res.json())
                .then(json => {
                  if (json.status === 'success') {
                    idsToDelete.forEach(({ row }) => hot.alter('remove_row', row));
                  } else {
                    alert('Erreur suppression: ' + (json.message || ''));
                  }
                });
            }
          }
        }
      },
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
      const rows = hot.getData().filter(row => row.some(cell => cell !== null));
      console.log("Données envoyées:", rows);

      fetch('<?= BASE_URL ?>views/excel/excel_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data: rows })
      })
        .then(res => res.text())
        .then(text => {
          console.log("Réponse brute:", text);
          try {
            const json = JSON.parse(text);
            if (json.status === 'success') {
              alert("Sauvegarde réussie");
              location.reload();
            } else {
              alert("Erreur: " + (json.message || ''));
            }
          } catch (e) {
            alert("Réponse invalide du serveur — voir console");
          }
        })
        .catch(err => console.error(err));
    }
  </script>
</body>

</html>