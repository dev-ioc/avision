<?php
/**
 * Vue du formulaire de génération d'interventions préventives
 * Permet de configurer les paramètres avant de programmer les interventions
 */
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">
                    <i class="bi bi-calendar-check me-2"></i>
                    Générer des interventions préventives
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>contracts">Contrats</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>">
                                <?php echo h($contract['name']); ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Générer des interventions préventives</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        Configuration des interventions préventives
                    </h5>
                </div>
                <div class="card-body py-2">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2 me-1"></i>
                        <strong>Informations :</strong>
                        <ul class="mb-0 mt-2">
                            <li>Les interventions préventives seront créées pour chaque salle associée au contrat</li>
                            <li>Les dates seront réparties uniformément sur la durée du contrat</li>
                            <li>Les weekends et jours fériés seront automatiquement évités</li>
                            <li>Les interventions seront créées avec le statut "Nouveau" et la priorité "Préventif"</li>
                        </ul>
                    </div>

                    <form action="<?php echo BASE_URL; ?>contracts/generatePreventiveInterventions/<?php echo $contract['id']; ?>" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Contrat</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Nom :</strong> <?= h($contract['name']) ?></li>
                                    <li><strong>Période :</strong> <?= formatDateFrench($contract['start_date']) ?> - <?= formatDateFrench($contract['end_date']) ?></li>
                                    <li><strong>Type :</strong> <?= h($contract['contract_type_name']) ?></li>
                                    <li><strong>Client :</strong> <?= h($contract['client_name']) ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Salles associées</h6>
                                <?php if (!empty($contractRooms)): ?>
                                    <ul class="list-unstyled">
                                        <?php foreach ($contractRooms as $room): ?>
                                            <li><i class="bi bi-building me-1"></i><?= h($room['site_name']) ?> : <?= h($room['room_name']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Aucune salle associée à ce contrat
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="nb_interventions" class="form-label">Nombre d'interventions préventives *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="nb_interventions" 
                                           name="nb_interventions" 
                                           min="1" 
                                           max="12" 
                                           value="4" 
                                           required>
                                    <div class="form-text">Entre 1 et 12 interventions réparties sur la durée du contrat</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="default_hour" class="form-label">Heure par défaut</label>
                                    <input type="time" 
                                           class="form-control" 
                                           id="default_hour" 
                                           name="default_hour" 
                                           value="09:00" 
                                           required>
                                    <div class="form-text">Heure de début pour toutes les interventions</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="intervention_comment" class="form-label">Commentaire additionnel</label>
                                    <textarea class="form-control" 
                                              id="intervention_comment" 
                                              name="intervention_comment" 
                                              rows="3" 
                                              placeholder="Commentaire qui sera ajouté à toutes les interventions..."></textarea>
                                    <div class="form-text">Optionnel</div>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($contractRooms)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Attention :</strong> Ce contrat n'a pas de salles associées. Impossible de créer des interventions préventives.
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Retour au contrat
                            </a>
                            <?php if (!empty($contractRooms)): ?>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-calendar-check me-1"></i> Programmer les interventions
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div> 