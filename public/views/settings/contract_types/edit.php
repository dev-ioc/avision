<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-pencil me-2 me-1"></i>Modifier le type de contrat
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings/contractTypes" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2 me-1"></i>Retour à la liste
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

    <div class="card">
        <div class="card-body">
            <form action="<?= BASE_URL ?>settings/contractTypes/update/<?= $contractType['id'] ?>" method="POST" id="contractTypeForm">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Nom du type de contrat <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control bg-body text-body" 
                                   id="name" 
                                   name="name" 
                                   required 
                                   value="<?= htmlspecialchars($formData['name'] ?? $contractType['name']) ?>"
                                   placeholder="Ex: Maintenance préventive">
                            <div class="form-text">Nom unique pour identifier ce type de contrat</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control bg-body text-body" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="Description détaillée du type de contrat..."><?= htmlspecialchars($formData['description'] ?? $contractType['description']) ?></textarea>
                            <div class="form-text">Description optionnelle pour expliquer ce type de contrat</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="default_tickets" class="form-label">
                                Nombre de tickets par défaut <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control bg-body text-body" 
                                   id="default_tickets" 
                                   name="default_tickets" 
                                   required 
                                   min="0"
                                   value="<?= htmlspecialchars($formData['default_tickets'] ?? $contractType['default_tickets']) ?>"
                                   placeholder="0">
                            <div class="form-text">Nombre de tickets inclus par défaut dans ce type de contrat</div>
                        </div>

                        <div class="mb-3">
                            <label for="nb_inter_prev" class="form-label">
                                Nombre d'interventions préventives <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control bg-body text-body" 
                                   id="nb_inter_prev" 
                                   name="nb_inter_prev" 
                                   required 
                                   min="0"
                                   value="<?= htmlspecialchars($formData['nb_inter_prev'] ?? $contractType['nb_inter_prev']) ?>"
                                   placeholder="0">
                            <div class="form-text">Nombre d'interventions préventives incluses dans ce type de contrat</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2 me-1"></i>
                            <strong>Informations :</strong>
                            <ul class="mb-0 mt-2">
                                <li>Le nombre de tickets par défaut sera automatiquement attribué lors de la création d'un contrat de ce type</li>
                                <li>Le nombre d'interventions préventives peut être utilisé pour planifier automatiquement les interventions de maintenance</li>
                                <li>Ces valeurs peuvent être modifiées individuellement pour chaque contrat</li>
                                <li>La modification de ce type de contrat n'affectera pas les contrats existants</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-end">
                            <a href="<?= BASE_URL ?>settings/contractTypes" class="btn btn-secondary me-2">
                                <i class="bi bi-x-lg me-2 me-1"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2 me-1"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('contractTypeForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const defaultTickets = parseInt(document.getElementById('default_tickets').value);
    const nbInterPrev = parseInt(document.getElementById('nb_inter_prev').value);

    if (name === '') {
        e.preventDefault();
        alert('Le nom du type de contrat est obligatoire.');
        document.getElementById('name').focus();
        return;
    }

    if (defaultTickets < 0) {
        e.preventDefault();
        alert('Le nombre de tickets par défaut ne peut pas être négatif.');
        document.getElementById('default_tickets').focus();
        return;
    }

    if (nbInterPrev < 0) {
        e.preventDefault();
        alert('Le nombre d\'interventions préventives ne peut pas être négatif.');
        document.getElementById('nb_inter_prev').focus();
        return;
    }
});
</script> 