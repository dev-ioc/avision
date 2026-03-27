<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bi bi-building me-2"></i>
                        Matériel - <?php echo h($site['name']); ?> - <?php echo h($salle['name']); ?>
                    </h4>
                    <div>
                        <a href="<?php echo BASE_URL; ?>materiel" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($materiel_list)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun matériel trouvé dans cette salle.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Marque</th>
                                        <th>Modèle</th>
                                        <th>Référence</th>
                                        <th>Numéro de série</th>
                                        <th>Adresse IP</th>
                                        <th>Commentaire</th>
                                        <?php if (canModifyMateriel()): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
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
                                            <?php if (canModifyMateriel()): ?>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>materiel/edit/<?php echo $materiel['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i> Éditer
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
