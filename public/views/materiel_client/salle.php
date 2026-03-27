<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matériel - <?php echo h($salle['site_name']); ?> - <?php echo h($salle['salle_name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #007bff; margin: 0; font-size: 24px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #007bff; color: white; font-weight: bold; }
        .table tr:nth-child(even) { background-color: #f2f2f2; }
        .table tr:hover { background-color: #e9ecef; }
        .alert { padding: 12px; border-radius: 4px; margin: 20px 0; }
        .alert-info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        @media print { body { margin: 0; } .container { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Matériel - <?php echo h($salle['site_name']); ?> - <?php echo h($salle['salle_name']); ?></h1>
        </div>
        
        <?php if (empty($materiel_list)): ?>
            <div class="alert alert-info">
                Aucun matériel trouvé dans cette salle.
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Marque</th>
                        <th>Modèle</th>
                        <th>Référence</th>
                        <th>Numéro de série</th>
                        <th>Adresse IP</th>
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiel_list as $materiel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($materiel['type'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($materiel['marque'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($materiel['modele'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($materiel['reference'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($materiel['numero_serie'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($materiel['adresse_ip'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($materiel['commentaire'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>


