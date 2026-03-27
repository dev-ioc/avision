<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-tags me-2 me-1"></i>Types de contrats
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <button type="button" class="btn btn-success me-2" onclick="saveOrder()">
                <i class="bi bi-check-lg me-2 me-1"></i>Sauvegarder l'ordre
            </button>
            <a href="<?= BASE_URL ?>settings/contractTypes/add" class="btn btn-primary">
                <i class="bi bi-plus me-2 me-1"></i>Ajouter un type
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="contractTypesTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Ordre</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Tickets par défaut</th>
                            <th>Interventions préventives</th>
                            <th>Contrats utilisant ce type</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sortableContractTypes">
                        <?php if (empty($contractTypes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="bi bi-info-circle me-2 me-1"></i>Aucun type de contrat trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contractTypes as $type): ?>
                                <tr data-id="<?= $type['id'] ?>" class="sortable-row">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-grip-vertical text-muted me-2" style="cursor: move;"></i>
                                            <span class="badge bg-secondary"><?= $type['ordre_affichage'] ?? 0 ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= h($type['name']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($type['description'])): ?>
                                            <?= h($type['description']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Aucune description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= $type['default_tickets'] ?> tickets
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <?= $type['nb_inter_prev'] ?> interventions
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $contractCount = $this->contractTypeModel->getContractCountByType($type['id']);
                                        if ($contractCount > 0): ?>
                                            <span class="badge bg-success">
                                                <?= $contractCount ?> contrat(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Aucun contrat</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($type['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td class="actions">
                                        <a href="<?= BASE_URL ?>settings/contractTypes/edit/<?= $type['id'] ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Modifier">
                                            <i class="bi bi-pencil me-1"></i>
                                        </a>
                                        <?php if ($contractCount == 0): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    title="Supprimer"
                                                    onclick="confirmDelete(<?= $type['id'] ?>, '<?= h($type['name']) ?>')">
                                                <i class="bi bi-trash me-1"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary" 
                                                    title="Impossible de supprimer - utilisé par des contrats"
                                                    disabled>
                                                <i class="bi bi-lock me-1"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// Initialiser le tri par glisser-déposer
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('sortableContractTypes');
    if (tbody) {
        new Sortable(tbody, {
            animation: 150,
            handle: '.bi-grip-vertical',
            onEnd: function() {
                updateOrderNumbers();
            }
        });
    }
});

function updateOrderNumbers() {
    const rows = document.querySelectorAll('#sortableContractTypes .sortable-row');
    rows.forEach((row, index) => {
        const badge = row.querySelector('.badge');
        if (badge) {
            badge.textContent = index + 1;
        }
    });
}

function saveOrder() {
    const rows = document.querySelectorAll('#sortableContractTypes .sortable-row');
    const orders = {};
    
    rows.forEach((row, index) => {
        const id = row.getAttribute('data-id');
        orders[id] = index + 1;
    });

    // Envoyer les données au serveur
    fetch('<?= BASE_URL ?>settings/contractTypes/updateOrder', {
        method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
        body: 'orders=' + encodeURIComponent(JSON.stringify(orders))
    })
    .then(response => response.text())
    .then(() => {
        window.location.reload();
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la sauvegarde de l\'ordre');
    });
}

function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer le type de contrat "${name}" ?\n\nCette action est irréversible.`)) {
        window.location.href = '<?= BASE_URL ?>settings/contractTypes/delete/' + id;
    }
}
</script> 