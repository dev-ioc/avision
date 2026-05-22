<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';

if (!isset($_SESSION['user'])) {
	header('Location: ' . BASE_URL . 'auth/login');
	exit;
}

$userType = $_SESSION['user']['user_type'] ?? null;
$interventionId = isset($intervention['id']) ? $intervention['id'] : '';

setPageVariables(
	'Intervention',
	'interventions' . ($interventionId ? '_view_' . $interventionId : '')
);

$currentPage = 'interventions';

if (isset($intervention) && !empty($intervention)) {
	$GLOBALS['customBreadcrumbs'] = generateInterventionViewBreadcrumbs($intervention);
}

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

	<div class="d-flex bd-highlight mb-3">
		<div class="p-2 bd-highlight">
			<h4 class="py-4 mb-6">Détails de l'intervention</h4>
		</div>

		<div class="ms-auto p-2 bd-highlight">
			<?php
			$isPreventive = false;
			if (isset($intervention['priority_id']) && isset($preventivePriorityId) && $intervention['priority_id'] == $preventivePriorityId) {
				$isPreventive = true;
			}
			$defaultReturnUrl = $isPreventive ? BASE_URL . 'interventions/preventives' : BASE_URL . 'interventions/curatives';
			$returnUrl = $defaultReturnUrl;
			$returnText = 'Retour';

			if (isset($_GET['return_to']) && isset($_GET['client_id'])) {
				$returnTo = $_GET['return_to'];
				$clientId = $_GET['client_id'];
				$activeTab = $_GET['active_tab'] ?? '';
				if ($returnTo === 'client') {
					$returnUrl = BASE_URL . 'clients/view/' . $clientId;
					if ($activeTab)
						$returnUrl .= '?active_tab=' . $activeTab;
					$returnText = 'Retour au client';
				}
			}
			?>
			<a href="<?= $returnUrl ?>" class="btn btn-secondary me-2">
				<i class="bi bi-arrow-left me-1"></i>
				<?= $returnText ?>
			</a>

			<?php
			$user = $_SESSION['user'];
			$isAdmin = isAdmin();
			?>

			<a href="<?= BASE_URL ?>interventions/generateBon/<?= $intervention['id'] ?>" class="btn btn-info me-2">
				<i class="bi bi-file-pdf me-1"></i> Générer le bon d'intervention
			</a>

			<button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#sendEmailModal">
				<i class="bi bi-envelope me-1"></i> Envoyer un email
			</button>

			<?php if (canModifyInterventions()): ?>
				<a href="<?= BASE_URL ?>interventions/edit/<?= $intervention['id'] ?>" class="btn btn-warning me-2">
					<i class="bi bi-pencil me-1"></i> Modifier
				</a>

				<?php if ($intervention['status_id'] != 6): ?>

					<a href="<?= BASE_URL ?>interventions/assignToMe/<?= $intervention['id'] ?>" class="btn btn-success me-2">
						<i class="bi bi-person-plus me-1"></i> S'attribuer
					</a>

					<?php
					/* ── Conditions de fermeture ── */
					$canClose = true;
					$closeReason = [];

					if (empty($intervention['contract_id'])) {
						$canClose = false;
						$closeReason[] = "Aucun contrat sélectionné";
					}
					?>

					<?php if ($canClose): ?>
						<button type="button" class="btn btn-danger" id="btnOuvrirFermeture">
							<i class="bi bi-lock me-1"></i> Fermer l'intervention
						</button>
					<?php else: ?>
						<button type="button" class="btn btn-danger" disabled
							title="<?= htmlspecialchars(implode(', ', $closeReason)) ?>">
							<i class="bi bi-lock me-1"></i> Fermer l'intervention
						</button>
					<?php endif; ?>

				<?php else: ?>

					<button type="button" class="btn btn-secondary me-2" disabled>
						<i class="bi bi-check-circle me-1"></i> Intervention fermée
					</button>

					<button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#modalReouvrir">
						<i class="bi bi-arrow-repeat me-1"></i> Réouvrir l'intervention
					</button>

				<?php endif; ?>

			<?php else: ?>
				<button type="button" class="btn btn-warning me-2" disabled title="Vous n'avez pas les droits nécessaires">
					<i class="bi bi-pencil me-1"></i> Modifier
				</button>
			<?php endif; ?>

			<?php if ($isAdmin): ?>
				<?php
				$reference = $intervention['reference'] ?? ('ID ' . ($intervention['id'] ?? ''));
				$ticketsUsed = (float) ($intervention['tickets_used'] ?? 0);
				$isTicketContract = isInterventionLinkedToTicketContract($intervention['id']);

				$deleteWarningTitle = "Supprimer définitivement l'intervention {$reference} ?";
				$deleteWarningText = "Cette action est irréversible.";
				if ($isTicketContract && $ticketsUsed > 0) {
					$deleteWarningText .= " Cette intervention a consommé {$ticketsUsed} ticket(s) qui seront re-crédités au contrat lors de la suppression.";
				}
				?>
				<button type="button" class="btn btn-outline-danger me-2" data-bs-toggle="modal"
					data-bs-target="#deleteInterventionModal" title="Supprimer l'intervention">
					<i class="bi bi-trash"></i>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- ── Modale suppression ─────────────────────────────────────────────── -->
	<?php if ($isAdmin): ?>
		<div class="modal fade" id="deleteInterventionModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">
							<i class="bi bi-exclamation-triangle me-2"></i>Confirmation de suppression
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div class="alert alert-danger mb-0">
							<strong>
								<?= h($deleteWarningTitle) ?>
							</strong>
							<div class="mt-2">
								<?= h($deleteWarningText) ?>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
						<form method="POST" action="<?= BASE_URL ?>interventions/delete/<?= $intervention['id'] ?>"
							class="d-inline">
							<?= csrf_field() ?>
							<button type="submit" class="btn btn-danger">
								<i class="bi bi-trash me-1"></i>Oui, supprimer
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- ── Alertes session ───────────────────────────────────────────────── -->
	<?php if (isset($_SESSION['error'])): ?>
		<div class="alert alert-danger">
			<?= $_SESSION['error'];
			unset($_SESSION['error']); ?>
		</div>
	<?php endif; ?>
	<?php if (isset($_SESSION['success'])): ?>
		<div class="alert alert-success">
			<?= $_SESSION['success'];
			unset($_SESSION['success']); ?>
		</div>
	<?php endif; ?>
	<?php if (isset($_SESSION['info'])): ?>
		<div class="alert alert-info">
			<?= $_SESSION['info'];
			unset($_SESSION['info']); ?>
		</div>
	<?php endif; ?>

	<?php if ($intervention): ?>

		<!-- ── Carte principale ──────────────────────────────────────────────── -->
		<div class="card">
			<div class="card-header py-2">
				<div class="d-flex justify-content-between align-items-center">
					<h5 class="card-title mb-0">
						<span class="fw-bold me-3">
							<?= h($intervention['reference'] ?? '') ?>
						</span>
						<?= h($intervention['title'] ?? '') ?>
					</h5>
					<div class="d-flex align-items-center gap-2">
						<div class="text-muted me-2">
							<i class="bi bi-clock me-1"></i>
							<?= h($intervention['duration'] ?? '0') ?>h
						</div>
						<?php if (isInterventionLinkedToTicketContract($intervention['id'])): ?>
							<div class="text-muted me-2">
								<i class="bi bi-ticket-perforated me-1"></i>
								<?= h($intervention['tickets_used'] ?? '0') ?>
							</div>
						<?php endif; ?>
						<span class="badge rounded-pill"
							style="background-color: <?= h($intervention['status_color'] ?? '') ?>">
							<?= h($intervention['status_name'] ?? '') ?>
						</span>
					</div>
				</div>
			</div>
			<div class="card-body py-2">
				<div class="row g-3">

					<!-- Col 1 : Client / Site / Salle -->
					<div class="col-md-3">
						<div class="d-flex flex-column gap-2">
							<div>
								<label class="form-label fw-bold mb-0">Client</label>
								<p class="form-control-static mb-0">
									<?= h($intervention['client_name'] ?? '') ?>
								</p>
							</div>
							<?php if (!empty($intervention['site_name'])): ?>
								<div>
									<label class="form-label fw-bold mb-0">Site</label>
									<p class="form-control-static mb-0">
										<?= h($intervention['site_name']) ?>
									</p>
								</div>
							<?php endif; ?>
							<?php if (!empty($intervention['building_name'])): ?>
								<div>
									<label class="form-label fw-bold mb-0">Bâtiment</label>
									<p class="form-control-static mb-0">
										<?= h($intervention['building_name']) ?>
									</p>
								</div>
							<?php endif; ?>
							<?php if (!empty($intervention['room_name'])): ?>
								<div>
									<label class="form-label fw-bold mb-0">Salle</label>
									<p class="form-control-static mb-0">
										<?= h($intervention['room_name']) ?>
									</p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Col 2 : Type / Déplacement / Contrat -->
					<div class="col-md-3">
						<div class="d-flex flex-column gap-2">
							<div>
								<label class="form-label fw-bold mb-0">Type d'intervention</label>
								<p class="form-control-static mb-0">
									<?= h($intervention['type_name'] ?? '') ?>
								</p>
							</div>
							<div>
								<label class="form-label fw-bold mb-0">Déplacement</label>
								<p class="form-control-static mb-0">
									<?= isset($intervention['type_requires_travel']) && (int) $intervention['type_requires_travel'] === 1 ? 'Oui' : 'Non' ?>
								</p>
							</div>
							<?php if (!empty($intervention['contract_name']) && !empty($intervention['contract_type_id'])): ?>
								<div>
									<label class="form-label fw-bold mb-0">Contrat</label>
									<p class="form-control-static mb-0">
										<a href="#" class="text-decoration-none contract-info-link"
											data-contract-id="<?= $intervention['contract_id'] ?>"
											title="Voir les détails du contrat">
											<i class="bi bi-info-circle me-1"></i>
											<?= h($intervention['contract_name']) ?>
										</a>
									</p>
								</div>
							<?php elseif (!empty($intervention['contract_name'])): ?>
								<div>
									<label class="form-label fw-bold mb-0">Contrat</label>
									<p class="form-control-static mb-0">
										<?= h($intervention['contract_name']) ?>
									</p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Col 3 : Priorité / Date création -->
					<div class="col-md-3">
						<div class="d-flex flex-column gap-2">
							<div>
								<label class="form-label fw-bold mb-0">Priorité</label>
								<p class="form-control-static mb-0">
									<span class="badge"
										style="background-color: <?= h($intervention['priority_color'] ?? '') ?>">
										<?= h($intervention['priority_name'] ?? '') ?>
									</span>
								</p>
							</div>
							<div>
								<label class="form-label fw-bold mb-0">Date de création</label>
								<p class="form-control-static mb-0">
									<?= formatDateFrench($intervention['created_at']) ?>
								</p>
							</div>
							<?php if (!empty($intervention['closed_at'])): ?>
								<div>
									<label class="form-label fw-bold mb-0">Date de fermeture</label>
									<p class="form-control-static mb-0">
										<?= formatDateFrench($intervention['closed_at']) ?>
									</p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Col 4 : Date planifiée / Heure planifiée -->
					<div class="col-md-3">
						<div class="d-flex flex-column gap-2">
							<div>
								<label class="form-label fw-bold mb-0">Date planifiée</label>
								<p class="form-control-static mb-0">
									<?= !empty($intervention['date_planif']) ? formatDateFrench($intervention['date_planif']) : 'Non définie' ?>
								</p>
							</div>
							<div>
								<label class="form-label fw-bold mb-0">Heure planifiée</label>
								<p class="form-control-static mb-0">
									<?= !empty($intervention['heure_planif']) ? h($intervention['heure_planif']) : 'Non définie' ?>
								</p>
							</div>
						</div>
					</div>

				</div>

				<!-- Description -->
				<?php if (!empty($intervention['description'])): ?>
					<div class="col-12 mt-3">
						<div class="card">
							<div class="card-header py-2">
								<h6 class="card-title mb-0">Demande / description du problème</h6>
							</div>
							<div class="card-body py-2">
								<p class="mb-0">
									<?= nl2br(h($intervention['description'])) ?>
								</p>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- Infos contact -->
				<?php if (!empty($intervention['demande_par']) || !empty($intervention['ref_client']) || !empty($intervention['contact_client'])): ?>
					<div class="col-12 mt-3">
						<div class="card contact-info-card">
							<div class="card-header py-2 contact-info-header">
								<h6 class="card-title mb-0 fw-bold">
									<i class="bi bi-person-lines-fill me-2"></i>Informations de contact et demande
								</h6>
							</div>
							<div class="card-body py-3">
								<div class="row g-3">
									<?php if (!empty($intervention['demande_par'])): ?>
										<div class="col-md-6">
											<label class="form-label fw-bold mb-0">Demande par</label>
											<p class="mb-0">
												<?= h($intervention['demande_par']) ?>
											</p>
										</div>
									<?php endif; ?>
									<?php if (!empty($intervention['ref_client'])): ?>
										<div class="col-md-6">
											<label class="form-label fw-bold mb-0">Référence client</label>
											<p class="mb-0">
												<?= h($intervention['ref_client']) ?>
											</p>
										</div>
									<?php endif; ?>
									<?php if (!empty($intervention['contact_client'])): ?>
										<div class="col-md-6">
											<label class="form-label fw-bold mb-0">Contact client</label>
											<p class="mb-0">
												<i class="bi bi-envelope me-2"></i>
												<a href="mailto:<?= h($intervention['contact_client']) ?>">
													<?= h($intervention['contact_client']) ?>
												</a>
											</p>
										</div>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- ── Commentaires / Pièces jointes / Techniciens ───────────────────── -->
		<div class="row mt-4">

			<!-- Commentaires -->
			<div class="col-md-4">
				<div class="card mb-3 h-auto">
					<div class="card-header py-2 d-flex justify-content-between align-items-center">
						<h5 class="card-title mb-0">Compte-rendu / observations</h5>
						<?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
							<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
								data-bs-target="#addCommentModal">
								<i class="bi bi-plus me-1"></i> Ajouter
							</button>
						<?php endif; ?>
					</div>
					<div class="card-body py-2" style="max-height:500px;overflow-y:auto;">
						<?php if (!empty($comments)): ?>
							<?php foreach ($comments as $comment): ?>
								<div class="comment mb-3 p-3 border rounded">
									<div class="d-flex justify-content-between align-items-start mb-2">
										<div class="d-flex align-items-center gap-2">
											<strong>
												<?= h($comment['created_by_name'] ?? 'Utilisateur inconnu') ?>
											</strong>
											<small class="text-muted">
												<?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
											</small>
										</div>
										<div>
											<?php if ($comment['is_solution']): ?><span class="badge bg-success">Solution</span>
											<?php endif; ?>
											<?php if ($comment['is_observation']): ?><span
													class="badge bg-warning">Observation</span>
											<?php endif; ?>
											<?php if ($comment['visible_by_client']): ?>
												<span class="badge bg-info">Visible client</span>
											<?php else: ?>
												<span class="badge bg-secondary">Interne</span>
											<?php endif; ?>
											<?php if (canModifyInterventions()): ?>
												<button type="button" class="btn btn-sm btn-outline-warning btn-action"
													data-bs-toggle="modal" data-bs-target="#editCommentModal<?= $comment['id'] ?>"
													title="Modifier">
													<i class="bi bi-pencil"></i>
												</button>
												<a href="<?= BASE_URL ?>interventions/deleteComment/<?= $comment['id'] ?>"
													class="btn btn-sm btn-outline-danger btn-action"
													onclick="return confirm('Supprimer ce commentaire ?')" title="Supprimer">
													<i class="bi bi-trash"></i>
												</a>
											<?php endif; ?>
										</div>
									</div>
									<p class="mb-0">
										<?= nl2br(h($comment['comment'])) ?>
									</p>
								</div>

								<!-- Modale édition commentaire -->
								<?php if (canModifyInterventions()): ?>
									<div class="modal fade" id="editCommentModal<?= $comment['id'] ?>" tabindex="-1" aria-hidden="true">
										<div class="modal-dialog">
											<div class="modal-content">
												<form action="<?= BASE_URL ?>interventions/editComment/<?= $comment['id'] ?>"
													method="post">
													<?= csrf_field() ?>
													<div class="modal-header">
														<h5 class="modal-title">Modifier le commentaire</h5>
														<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
													</div>
													<div class="modal-body">
														<div class="mb-3">
															<label class="form-label">Commentaire</label>
															<textarea class="form-control" name="comment" rows="4"
																required><?= h($comment['comment']) ?></textarea>
														</div>
														<div class="form-check mb-2">
															<input class="form-check-input" type="checkbox" name="visible_by_client"
																id="vbc<?= $comment['id'] ?>" <?= $comment['visible_by_client'] ? 'checked' : '' ?>>
															<label class="form-check-label" for="vbc<?= $comment['id'] ?>">Visible par
																le client</label>
														</div>
														<div class="form-check mb-2">
															<input class="form-check-input" type="checkbox" name="is_solution"
																id="sol<?= $comment['id'] ?>" <?= $comment['is_solution'] ? 'checked' : '' ?>>
															<label class="form-check-label" for="sol<?= $comment['id'] ?>">Marquer comme
																solution</label>
														</div>
														<div class="form-check">
															<input class="form-check-input" type="checkbox" name="is_observation"
																id="obs<?= $comment['id'] ?>" <?= $comment['is_observation'] ? 'checked' : '' ?>>
															<label class="form-check-label" for="obs<?= $comment['id'] ?>">Marquer comme
																observation</label>
														</div>
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-secondary"
															data-bs-dismiss="modal">Annuler</button>
														<button type="submit" class="btn btn-primary">Enregistrer</button>
													</div>
												</form>
											</div>
										</div>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php else: ?>
							<p class="text-muted mb-0">Aucun commentaire pour le moment.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Pièces jointes -->
			<div class="col-md-4">
				<div class="card mb-3 h-auto">
					<div class="card-header py-2 d-flex justify-content-between align-items-center">
						<h5 class="card-title mb-0">Pièces jointes</h5>
						<?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
							<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
								data-bs-target="#addAttachmentModal">
								<i class="bi bi-plus me-1"></i> Ajouter
							</button>
						<?php endif; ?>
					</div>
					<div class="card-body py-2" style="max-height:500px;overflow-y:auto;">
						<?php if (empty($attachments)): ?>
							<p class="text-muted mb-0">Aucune pièce jointe pour le moment.</p>
						<?php else: ?>
							<?php
							usort($attachments, function ($a, $b) {
								$aIsBI = $a['type_liaison'] === 'bi';
								$bIsBI = $b['type_liaison'] === 'bi';
								if ($aIsBI && !$bIsBI)
									return -1;
								if (!$aIsBI && $bIsBI)
									return 1;
								return strtotime($b['date_creation']) - strtotime($a['date_creation']);
							});
							foreach ($attachments as $attachment):
								$isBI = $attachment['type_liaison'] === 'bi';
								$extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
								if (empty($extension) && !empty($attachment['type_fichier'])) {
									$extension = strtolower($attachment['type_fichier']);
								}
								$isPdf = $extension === 'pdf';
								$isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg']);
								?>
								<div class="card mb-2">
									<div class="card-header py-1 d-flex justify-content-between align-items-center">
										<div>
											<strong>
												<?= h($attachment['created_by_name'] ?? 'Utilisateur inconnu') ?>
											</strong>
											<small class="text-muted ms-2">
												<?= date('d/m/Y H:i', strtotime($attachment['date_creation'])) ?>
											</small>
										</div>
										<div>
											<?php if ($isPdf): ?>
												<button type="button" class="btn btn-sm btn-outline-info btn-action"
													onclick="openPdfViewer(<?= $attachment['id'] ?>, '<?= addslashes($attachment['nom_personnalise'] ?? $attachment['nom_fichier']) ?>')"
													title="Aperçu">
													<i class="bi bi-eye"></i>
												</button>
											<?php elseif ($isImage): ?>
												<button type="button" class="btn btn-sm btn-outline-info btn-action"
													onclick="openImageViewer(<?= $attachment['id'] ?>, '<?= addslashes($attachment['nom_personnalise'] ?? $attachment['nom_fichier']) ?>')"
													title="Aperçu">
													<i class="bi bi-eye"></i>
												</button>
											<?php else: ?>
												<a href="<?= BASE_URL ?>interventions/download/<?= $attachment['id'] ?>"
													class="btn btn-sm btn-outline-info btn-action" title="Télécharger" target="_blank">
													<i class="bi bi-download"></i>
												</a>
											<?php endif; ?>
											<a href="<?= BASE_URL ?>interventions/download/<?= $attachment['id'] ?>"
												class="btn btn-sm btn-outline-success btn-action" title="Télécharger">
												<i class="bi bi-download"></i>
											</a>
											<?php if (canDelete()): ?>
												<a href="<?= BASE_URL ?>interventions/deleteAttachment/<?= $attachment['id'] ?>"
													class="btn btn-sm btn-outline-danger btn-action" title="Supprimer"
													onclick="return confirm('Supprimer cette pièce jointe ?');">
													<i class="bi bi-trash"></i>
												</a>
											<?php endif; ?>
										</div>
									</div>
									<div class="card-body py-2">
										<div class="d-flex align-items-center">
											<?php if ($isBI): ?>
												<i class="bi bi-file-pdf text-danger me-2"></i>
												<span class="badge bg-info me-2">BI</span>
											<?php elseif ($isPdf): ?>
												<i class="bi bi-file-pdf text-danger me-2"></i>
											<?php elseif ($isImage): ?>
												<i class="bi bi-image-fill text-primary me-2"></i>
											<?php else: ?>
												<i class="bi bi-file-earmark text-secondary me-2"></i>
											<?php endif; ?>
											<div class="attachment-name flex-grow-1">
												<div class="display-name">
													<?= h($attachment['nom_personnalise'] ?? $attachment['nom_fichier']) ?>
												</div>
												<?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
													<div class="original-name text-muted small">
														<?= h($attachment['nom_fichier']) ?>
													</div>
												<?php endif; ?>
											</div>
											<?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
												<button type="button" class="btn btn-sm btn-outline-secondary"
													onclick="editAttachmentName(<?= $attachment['id'] ?>, '<?= h($attachment['nom_fichier']) ?>')"
													title="Modifier le nom">
													<i class="bi bi-pencil-square"></i>
												</button>
											<?php endif; ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Techniciens -->
			<div class="col-md-4">
				<div class="card mb-3">
					<div class="card-header py-2 d-flex justify-content-between align-items-center">
						<h5 class="card-title mb-0">
							<i class="bi bi-people"></i> Techniciens d'intervention
						</h5>
						<button class="btn btn-sm btn-primary" onclick="openTechModal(<?= $intervention['id'] ?>)">
							<i class="bi bi-plus me-1"></i> Ajouter
						</button>
					</div>
					<div class="card-body py-2" id="techniciansListContainer" style="max-height:500px;overflow-y:auto;">
						<div class="text-center py-3">
							<div class="spinner-border spinner-border-sm text-primary" role="status"></div>
							<p class="text-muted mt-2 mb-0">Chargement…</p>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /row -->

		<!-- ── Boutons flottants historique ─────────────────────────────────── -->
		<button type="button" class="btn btn-sm btn-outline-secondary position-fixed bottom-0 end-0 m-3"
			data-bs-toggle="modal" data-bs-target="#historyModal" title="Historique des modifications">
			<i class="bi bi-clock-history me-1"></i>
		</button>
		<button type="button" class="btn btn-sm btn-outline-primary position-fixed bottom-0 end-0 m-3"
			style="transform:translateX(-56px);" data-bs-toggle="modal" data-bs-target="#mailHistoryModal"
			title="Historique des emails">
			<i class="bi bi-envelope-paper me-1"></i>
		</button>

		<!-- ── Modale historique modifications ───────────────────────────────── -->
		<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><i class="bi bi-clock-history me-2"></i> Historique des modifications</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<?php if (empty($history)): ?>
							<p class="text-muted">Aucun historique disponible.</p>
						<?php else: ?>
							<div class="list-group list-group-flush">
								<?php foreach ($history as $entry): ?>
									<div class="list-group-item px-0">
										<small class="text-muted d-block ps-3">
											<?= date('d/m/Y H:i', strtotime($entry['created_at'])) ?>
											par
											<?= isset($entry['changed_by_name']) && $entry['changed_by_name'] !== null ? h($entry['changed_by_name']) : 'Utilisateur inconnu' ?>
										</small>
										<div class="mt-1 ps-3">
											<?= isset($entry['description']) && $entry['description'] !== null ? nl2br(h($entry['description'])) : 'Aucune description.' ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- ── Modale historique emails ──────────────────────────────────────── -->
		<div class="modal fade" id="mailHistoryModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-xl modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><i class="bi bi-envelope-paper me-2"></i> Historique des emails</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="mailHistoryLoading" class="text-center py-4">
							<div class="spinner-border text-primary" role="status"></div>
							<p class="mt-2 mb-0">Chargement…</p>
						</div>
						<div id="mailHistoryError" class="alert alert-danger" style="display:none;"></div>
						<div id="mailHistoryEmpty" class="text-muted" style="display:none;">Aucun email dans l'historique.
						</div>
						<div id="mailHistoryTableWrap" style="display:none;">
							<div class="table-responsive">
								<table class="table table-striped align-middle mb-0">
									<thead>
										<tr>
											<th style="width:170px;">Date</th>
											<th>Titre</th>
											<th>Destinataires</th>
										</tr>
									</thead>
									<tbody id="mailHistoryTbody"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<script>
			(function () {
				var interventionId = <?= (int) ($intervention['id'] ?? 0) ?>;
				var baseUrl = '<?= addslashes(BASE_URL) ?>';
				var modalEl = document.getElementById('mailHistoryModal');
				if (!modalEl) return;
				function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
				function setVisible(el, show) { if (el) el.style.display = show ? '' : 'none'; }
				function loadMailHistory() {
					var loading = document.getElementById('mailHistoryLoading');
					var err = document.getElementById('mailHistoryError');
					var empty = document.getElementById('mailHistoryEmpty');
					var wrap = document.getElementById('mailHistoryTableWrap');
					var tbody = document.getElementById('mailHistoryTbody');
					setVisible(err, false); setVisible(empty, false); setVisible(wrap, false); setVisible(loading, true);
					if (tbody) tbody.innerHTML = '';
					fetch(baseUrl + 'interventions/getMailHistory/' + interventionId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
						.then(function (r) { return r.json(); })
						.then(function (data) {
							setVisible(loading, false);
							if (!data || !data.success) { err.textContent = (data && (data.error || data.message)) || (data.error || 'Erreur.'); setVisible(err, true); return; }
							var items = data.items || [];
							if (!items.length) { setVisible(empty, true); return; }
							var html = '';
							items.forEach(function (it) {
								var dt = it.datetime ? esc(it.datetime) : '';
								if (it.datetime) { try { var d = new Date(it.datetime.replace(' ', 'T')); if (!isNaN(d.getTime())) dt = d.toLocaleString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); } catch (e) { } }
								var title = esc(it.title || '(sans sujet)');
								var tpl = it.template_name ? '<div class="text-muted small">Template: ' + esc(it.template_name) + '</div>' : '';
								html += '<tr><td><span class="text-muted small">' + dt + '</span></td><td><div class="fw-semibold">' + title + '</div>' + tpl + '</td><td>' + esc(it.recipients || '') + '</td></tr>';
							});
							tbody.innerHTML = html; setVisible(wrap, true);
						})
						.catch(function () { setVisible(loading, false); err.textContent = 'Erreur réseau.'; setVisible(err, true); });
				}
				modalEl.addEventListener('show.bs.modal', loadMailHistory);
			})();
		</script>

	<?php else: ?>
		<div class="alert alert-danger">Intervention introuvable.</div>
	<?php endif; ?>

</div><!-- /container -->

<!-- ════════════════════════════════════════════════════════════════════════
		 MODALES GLOBALES
		 ════════════════════════════════════════════════════════════════════════ -->

<!-- Ajout commentaire -->
<div class="modal fade" id="addCommentModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<form action="<?= BASE_URL ?>interventions/addComment/<?= $intervention['id'] ?>" method="post">
				<?= csrf_field() ?>
				<div class="modal-header">
					<h5 class="modal-title">Ajouter un commentaire</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Commentaire</label>
						<textarea class="form-control" name="comment" rows="4" required></textarea>
					</div>
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="visible_by_client" name="visible_by_client">
						<label class="form-check-label" for="visible_by_client">Visible par le client</label>
					</div>
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="is_solution" name="is_solution">
						<label class="form-check-label" for="is_solution">Marquer comme solution</label>
					</div>
					<div class="form-check">
						<input class="form-check-input" type="checkbox" id="is_observation" name="is_observation">
						<label class="form-check-label" for="is_observation">Marquer comme observation</label>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
					<button type="submit" class="btn btn-primary">Ajouter</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Détails contrat -->
<div class="modal fade" id="contractDetailsModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i> Détails du contrat</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div id="contractDetailsContent">
					<div class="text-center">
						<div class="spinner-border text-primary" role="status"></div>
						<p class="mt-2">Chargement...</p>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
			</div>
		</div>
	</div>
</div>

<!-- Pièces jointes drag & drop -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form action="<?= BASE_URL ?>interventions/addMultipleAttachments/<?= $intervention['id'] ?>" method="post"
				enctype="multipart/form-data" id="dragDropForm">
				<div class="modal-header">
					<h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i> Ajouter des pièces jointes</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="drop-zone" id="dropZone">
						<div class="drop-message">
							<i class="bi bi-cloud-upload"></i>
							Glissez-déposez vos fichiers ici<br>
							<small class="text-muted">ou cliquez pour sélectionner</small>
						</div>
						<input type="file" id="fileInput" multiple style="display:none;"
							accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">
						<div class="file-list" id="fileList"></div>
						<div class="stats" id="stats" style="display:none;">
							<div class="row">
								<div class="col-6"><strong>Valides :</strong> <span id="validCount">0</span></div>
								<div class="col-6"><strong>Rejetés :</strong> <span id="invalidCount">0</span></div>
							</div>
							<div class="progress-bar">
								<div class="progress-fill" id="progressFill"></div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
					<button type="button" class="btn btn-warning" id="clearAllBtn" style="display:none;">
						<i class="bi bi-trash me-1"></i> Tout effacer
					</button>
					<button type="submit" class="btn btn-primary" id="uploadValidBtn" style="display:none;">
						<i class="bi bi-upload me-1"></i> Uploader les fichiers valides
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Édition nom pièce jointe -->
<div class="modal fade" id="editAttachmentNameModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="editAttachmentNameForm">
				<div class="modal-header">
					<h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Modifier le nom du fichier</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label for="editAttachmentName" class="form-label">Nom du fichier</label>
						<input type="text" class="form-control" id="editAttachmentName" name="nom_fichier"
							placeholder="Nom personnalisé" maxlength="255" required>
						<div class="form-text">Le nom original sera conservé pour référence.</div>
					</div>
					<div class="mb-3">
						<label class="form-label">Nom original</label>
						<div class="form-control-plaintext text-muted small" id="editOriginalName"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
					<button type="submit" class="btn btn-primary"><i
							class="bi bi-check-lg me-1"></i>Sauvegarder</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
		 MODALE RÉOUVERTURE
		 ══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalReouvrir" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-warning-subtle">
				<h5 class="modal-title">
					<i class="bi bi-arrow-repeat me-2"></i>Réouvrir l'intervention ?
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<?php
				$ticketsUsedForReopen = (float) ($intervention['tickets_used'] ?? 0);
				$hasTicketContractReopen = !empty($intervention['contract_id'])
					&& isInterventionLinkedToTicketContract($intervention['id']);
				?>
				<?php if ($hasTicketContractReopen && $ticketsUsedForReopen > 0): ?>
					<div class="alert alert-danger mb-3">
						<i class="bi bi-exclamation-triangle-fill me-2"></i>
						<strong>Attention !</strong> Cette intervention a consommé
						<strong>
							<?= $ticketsUsedForReopen ?> ticket(s)
						</strong>.
						En la réouvrant, ces tickets seront <strong>re-crédités</strong> sur le contrat.
						Si vous la refermez ensuite, un nouveau décompte sera calculé.
					</div>
				<?php else: ?>
					<p>Confirmez-vous la réouverture de cette intervention ?</p>
				<?php endif; ?>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
				<form method="POST" action="<?= BASE_URL ?>interventions/reopen/<?= $intervention['id'] ?>"
					class="d-inline">
					<?= csrf_field() ?>
					<button type="submit" class="btn btn-warning">
						<i class="bi bi-arrow-repeat me-1"></i>
						Oui, réouvrir
						<?= ($hasTicketContractReopen && $ticketsUsedForReopen > 0)
							? ' et recréditer ' . $ticketsUsedForReopen . ' ticket(s)'
							: '' ?>
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
		 MODALE FERMETURE
		 ══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalFermerInter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
	data-bs-keyboard="false">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">
					<i class="bi bi-lock me-2"></i>Fermer l'intervention
					<small class="text-muted ms-2">
						<?= h($intervention['reference'] ?? '') ?>
					</small>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body" id="fermetureBody">
				<div class="text-center py-5" id="fermetureLoading">
					<div class="spinner-border text-primary" role="status"></div>
					<p class="mt-3 text-muted">Calcul du décompte en cours…</p>
				</div>
				<div id="fermetureContent" style="display:none;"></div>
			</div>
			<div class="modal-footer d-flex align-items-center">
				<div class="form-check me-auto" id="fermetureEmailCheck" style="display:none;">
					<input class="form-check-input" type="checkbox" id="sendEmailClose" checked>
					<label class="form-check-label" for="sendEmailClose">Envoyer une notification par email</label>
				</div>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
				<button type="button" class="btn btn-danger" id="fermetureConfirmer" disabled>
					<i class="bi bi-lock me-1"></i>Confirmer la fermeture
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Forcer tickets (admin) -->
<?php if ($isAdmin && $intervention['status_id'] == 6): ?>
	<div class="modal fade" id="forceTicketsModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<form action="<?= BASE_URL ?>interventions/forceTickets/<?= $intervention['id'] ?>" method="POST">
					<?= csrf_field() ?>
					<div class="modal-body">
						<div class="alert alert-warning">
							<i class="bi bi-exclamation-triangle me-2"></i>
							<strong>Attention !</strong> Cette action modifiera le nombre de tickets utilisés pour cette
							intervention fermée.
						</div>
						<div class="mb-3">
							<label class="form-label">Tickets utilisés actuels</label>
							<input type="text" class="form-control" value="<?= $intervention['tickets_used'] ?? 0 ?>"
								readonly>
						</div>
						<div class="mb-3">
							<label for="new_tickets" class="form-label">Nouveau nombre <span
									class="text-danger">*</span></label>
							<input type="number" class="form-control" id="new_tickets" name="tickets_used"
								value="<?= $intervention['tickets_used'] ?? 0 ?>" min="0" step="0.5" required
								data-current-tickets="<?= $intervention['tickets_used'] ?? 0 ?>"
								data-contract-remaining="<?= $intervention['contract_tickets_remaining'] ?? 0 ?>">
						</div>
						<div class="mb-3">
							<label for="reason" class="form-label">Raison <span class="text-danger">*</span></label>
							<textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
						</div>
						<div class="alert alert-info">
							<i class="bi bi-info-circle me-2"></i><strong>Impact contrat :</strong>
							<ul class="mb-0 mt-2">
								<li>Tickets restants actuels : <span id="current_remaining">—</span></li>
								<li>Tickets restants après modification : <span id="new_remaining">—</span></li>
							</ul>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
						<button type="submit" class="btn btn-info">
							<i class="bi bi-ticket-perforated me-1"></i>Forcer les tickets
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>


<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="modal fade" id="techModal" tabindex="-1">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-people"></i> Affecter des techniciens</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="intervention_id">
				<input type="hidden" id="selected_technician_id">
				<input type="hidden" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

				<div class="mb-3">
					<label class="form-label fw-bold">Technicien</label>
					<select id="techSelect" class="form-select">
						<option value="">-- Rechercher ou sélectionner --</option>
					</select>
					<small class="text-muted">Sélectionnez un technicien pour afficher/modifier ses détails</small>
				</div>

				<div id="technicianDetails">
					<hr>
					<h6 class="mb-3">Détails pour <span id="selectedTechnicianName" class="fw-bold">---</span></h6>

					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label">Date et heure de début</label>
							<input type="datetime-local" id="start_time" class="form-control">
							<small class="text-muted">Optionnel</small>
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">Date et heure de fin</label>
							<input type="datetime-local" id="end_time" class="form-control">
							<small class="text-muted">Optionnel</small>
						</div>
					</div>

					<div class="row">
						<div class="col-md-4 mb-3">
							<label class="form-label">Durée</label>
							<div class="input-group">
								<input type="number" id="temps_passe" class="form-control" min="0" step="30"
									placeholder="Ex: 120" value="0">
								<span class="input-group-text">min</span>
							</div>
							<div id="roundedTimeDisplay" class="mt-2 p-2 bg-light rounded" style="display:none;"></div>
						</div>
						<div class="col-md-4 mb-3">
							<label class="form-label">Déplacement</label>
							<select id="deplacement" class="form-select">
								<option value="0">Non</option>
								<option value="1">Oui (+1 ticket)</option>
							</select>
						</div>
						<div class="col-md-4 mb-3">
							<label class="form-label">Technicien qualifié ?</label>
							<select id="is_qualified" class="form-select">
								<option value="0">Non</option>
								<option value="1">Oui (1ère h = 2 tickets)</option>
							</select>
							<small class="text-muted">La 1ère heure complète compte double.</small>
						</div>
					</div>

					<div class="mb-3">
						<label class="form-label">Commentaire</label>
						<textarea id="commentaire" class="form-control" rows="3"
							placeholder="Commentaire sur l'intervention de ce technicien…"></textarea>
					</div>

					<div class="d-flex gap-2">
						<button type="button" class="btn btn-danger" onclick="removeCurrentTechnician()"
							id="btnRemoveCurrent" style="display:none;">
							<i class="bi bi-trash"></i> Retirer ce technicien
						</button>
					</div>
					<hr>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
				<button type="button" class="btn btn-primary" onclick="saveAllTechnicians()">
					<i class="bi bi-save me-1"></i> Enregistrer
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Envoyer un email -->
<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Envoyer un email</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div id="sendEmailModalLoading" class="text-center py-4">
					<div class="spinner-border text-primary" role="status"></div>
					<p class="mt-2 mb-0">Chargement…</p>
				</div>
				<div id="sendEmailModalContent" style="display:none;">
					<p class="mb-3"><strong>Destinataire :</strong> <span id="sendEmailRecipient"></span></p>
					<div id="sendEmailTestModeBlock" class="alert alert-warning mb-3 py-2" style="display:none;"
						role="status">
						<i class="bi bi-info-circle me-2"></i>
						<strong>Mode test</strong> : envoi vers <strong id="sendEmailTestAddress"></strong>
					</div>
					<div class="mb-3">
						<label class="form-label">Type d'envoi</label>
						<div class="d-flex gap-3">
							<div class="form-check">
								<input class="form-check-input" type="radio" name="email_mode" id="emailModeTemplate"
									value="template" checked>
								<label class="form-check-label" for="emailModeTemplate">Utiliser un template</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="email_mode" id="emailModeCustom"
									value="custom">
								<label class="form-check-label" for="emailModeCustom">Message personnalisé</label>
							</div>
						</div>
					</div>
					<div id="sendEmailTemplateBlock" class="mb-3">
						<label for="sendEmailTemplateId" class="form-label">Template</label>
						<select class="form-select" id="sendEmailTemplateId" name="template_id">
							<option value="">-- Choisir un template --</option>
						</select>
						<button type="button" class="btn btn-sm btn-outline-secondary mt-2"
							id="sendEmailPreviewBtn">Aperçu</button>
						<div id="sendEmailPreview" class="mt-2 small border rounded p-2 bg-light" style="display:none;">
						</div>
					</div>
					<div id="sendEmailCustomBlock" class="mb-3" style="display:none;">
						<label for="sendEmailSubject" class="form-label">Sujet</label>
						<input type="text" class="form-control" id="sendEmailSubject" name="subject"
							placeholder="Sujet de l'email">
						<label for="sendEmailMessage" class="form-label mt-2">Message</label>
						<textarea class="form-control" id="sendEmailMessage" name="message" rows="5"
							placeholder="Corps du message"></textarea>
					</div>
					<div class="mb-3" id="sendEmailAttachmentsBlock">
						<label class="form-label">Pièces jointes (optionnel)</label>
						<div id="sendEmailAttachmentsList" class="border rounded p-2 bg-light"
							style="max-height:150px;overflow-y:auto;"></div>
					</div>
					<div id="sendEmailModalError" class="alert alert-danger mt-2" style="display:none;"></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
				<button type="button" class="btn btn-primary" id="sendEmailSubmitBtn" disabled>
					<i class="bi bi-send me-1"></i>Envoyer
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Viewers PDF / Image -->
<div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-file-pdf text-danger me-2"></i><span id="pdfFileName">Document
						PDF</span></h5>
				<div class="ms-auto me-3">
					<button type="button" class="btn btn-sm btn-outline-secondary" id="pdfZoomOutBtn"><i
							class="bi bi-zoom-out"></i></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="pdfZoomInBtn"><i
							class="bi bi-zoom-in"></i></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="pdfResetZoomBtn"><i
							class="bi bi-arrow-repeat"></i></button>
					<span id="pdfPageInfo" class="mx-2"></span>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="pdfPrevPageBtn"><i
							class="bi bi-chevron-left"></i></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="pdfNextPageBtn"><i
							class="bi bi-chevron-right"></i></button>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body p-0">
				<div id="pdfViewerContainer" style="height:calc(100vh - 120px);overflow:auto;background:#525659;">
					<canvas id="pdfCanvas" style="display:block;margin:0 auto;"></canvas>
				</div>
			</div>
			<div class="modal-footer">
				<a href="#" id="pdfDownloadLink" class="btn btn-primary" target="_blank"><i
						class="bi bi-download me-1"></i>
					Télécharger</a>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-image-fill text-primary me-2"></i><span
						id="imageFileName">Image</span>
				</h5>
				<div class="ms-auto me-3">
					<button type="button" class="btn btn-sm btn-outline-secondary" id="imageZoomOutBtn"><i
							class="bi bi-zoom-out"></i></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="imageZoomInBtn"><i
							class="bi bi-zoom-in"></i></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="imageResetZoomBtn"><i
							class="bi bi-arrow-repeat"></i></button>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body p-0" style="background:#f5f5f5;">
				<div id="imageViewerContainer" style="height:calc(100vh - 120px);overflow:auto;text-align:center;">
					<img id="viewerImage" src="" alt=""
						style="max-width:100%;height:auto;cursor:zoom-in;transition:transform 0.1s ease;">
				</div>
			</div>
			<div class="modal-footer">
				<a href="#" id="imageDownloadLink" class="btn btn-primary" target="_blank"><i
						class="bi bi-download me-1"></i>
					Télécharger</a>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
			</div>
		</div>
	</div>
</div>
<style>
	.drop-zone {
		border: 2px dashed var(--bs-border-color);
		border-radius: 8px;
		padding: 30px;
		text-align: center;
		background-color: var(--bs-body-bg);
		transition: all .3s ease;
		min-height: 150px;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
	}

	.drop-zone.dragover {
		border-color: var(--bs-primary);
		background-color: var(--bs-primary-bg-subtle);
	}

	.drop-message {
		font-size: 1.1em;
		color: var(--bs-secondary-color);
		margin-bottom: 15px;
	}

	.drop-message i {
		font-size: 2.5em;
		margin-bottom: 10px;
		display: block;
	}

	.file-list {
		margin-top: 15px;
		max-height: 200px;
		overflow-y: auto;
	}

	.file-item {
		display: flex;
		align-items: center;
		padding: 8px;
		margin: 3px 0;
		border-radius: 5px;
		border: 1px solid var(--bs-border-color);
		background-color: var(--bs-body-bg);
		gap: 10px;
	}

	.file-item.valid {
		background-color: var(--bs-success-bg-subtle);
		border-color: var(--bs-success-border-subtle);
	}

	.file-item.invalid {
		background-color: var(--bs-danger-bg-subtle);
		border-color: var(--bs-danger-border-subtle);
	}

	.stats {
		margin-top: 10px;
		padding: 8px;
		background-color: var(--bs-secondary-bg);
		border-radius: 5px;
		font-size: .9em;
	}

	.progress-bar {
		height: 3px;
		background-color: var(--bs-secondary-bg);
		border-radius: 2px;
		overflow: hidden;
		margin-top: 8px;
	}

	.progress-fill {
		height: 100%;
		background-color: var(--bs-primary);
		width: 0%;
		transition: width .3s ease;
	}

	.attachment-name {
		display: flex;
		flex-direction: column;
	}

	.attachment-name .display-name {
		font-weight: 500;
		color: var(--bs-body-color);
	}

	.attachment-name .original-name {
		font-size: .75em;
		margin-top: 2px;
		opacity: .7;
		font-style: italic;
	}
</style>

<script>
	window.CSRF_TOKEN = '<?= addslashes(csrf_token()) ?>';
	window.BASE_URL = '<?= addslashes(BASE_URL) ?>';
</script>

<script src="<?= BASE_URL ?>assets/js/pages/interventions.js"
	onerror="console.error('ERREUR: interventions.js introuvable.');"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
	/* ── PDF / Image viewers ─────────────────────────────────────────────────── */
	pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
	var pdfDoc = null, currentPdfPage = 1, pdfScale = 1.5, pdfCanvas = null, pdfCtx = null, currentImageScale = 1;

	function openPdfViewer(id, name) {
		document.getElementById('pdfFileName').innerText = name;
		document.getElementById('pdfDownloadLink').href = window.BASE_URL + 'interventions/download/' + id;
		currentPdfPage = 1; pdfScale = 1.5;
		var modal = new bootstrap.Modal(document.getElementById('pdfViewerModal')); modal.show();
		var c = document.getElementById('pdfViewerContainer');
		c.innerHTML = '<div class="text-center text-white py-5"><div class="spinner-border text-light" role="status"></div><p class="mt-3">Chargement…</p></div>';
		fetch(window.BASE_URL + 'interventions/getFileData/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.arrayBuffer(); })
			.then(function (d) { return pdfjsLib.getDocument({ data: d }).promise; })
			.then(function (pdf) {
				pdfDoc = pdf;
				document.getElementById('pdfPageInfo').innerText = 'Page ' + currentPdfPage + ' / ' + pdfDoc.numPages;
				c.innerHTML = '<canvas id="pdfCanvas" style="display:block;margin:0 auto;max-width:100%;height:auto;"></canvas>';
				pdfCanvas = document.getElementById('pdfCanvas'); pdfCtx = pdfCanvas.getContext('2d');
				renderPdfPage(currentPdfPage);
			})
			.catch(function (e) { c.innerHTML = '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle"></i> ' + e.message + '<br><br><a href="' + window.BASE_URL + 'interventions/download/' + id + '" class="btn btn-primary" target="_blank"><i class="bi bi-download"></i> Télécharger</a></div>'; });
	}
	function renderPdfPage(num) {
		if (!pdfDoc) return;
		pdfDoc.getPage(num).then(function (page) {
			var vp = page.getViewport({ scale: pdfScale });
			pdfCanvas.height = vp.height; pdfCanvas.width = vp.width;
			page.render({ canvasContext: pdfCtx, viewport: vp });
			document.getElementById('pdfPageInfo').innerText = 'Page ' + num + ' / ' + pdfDoc.numPages;
		});
	}
	function openImageViewer(id, name) {
		document.getElementById('imageFileName').innerText = name;
		document.getElementById('imageDownloadLink').href = window.BASE_URL + 'interventions/download/' + id;
		currentImageScale = 1;
		var modal = new bootstrap.Modal(document.getElementById('imageViewerModal')); modal.show();
		var c = document.getElementById('imageViewerContainer');
		c.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
		var img2 = new Image();
		img2.onload = function () { c.innerHTML = ''; c.appendChild(img2); img2.id = 'viewerImage'; img2.style.cssText = 'max-width:100%;height:auto;cursor:zoom-in;transition:transform .1s ease;'; initImageControls(); };
		img2.onerror = function () { c.innerHTML = '<div class="alert alert-danger m-3">Erreur de chargement.<br><a href="' + window.BASE_URL + 'interventions/download/' + id + '" class="btn btn-primary" target="_blank">Télécharger</a></div>'; };
		img2.src = window.BASE_URL + 'interventions/getFileData/' + id + '?t=' + Date.now();
	}
	function initImageControls() {
		var img2 = document.getElementById('viewerImage');
		document.getElementById('imageZoomInBtn').onclick = function () { currentImageScale = Math.min(currentImageScale + .25, 3); if (img2) img2.style.transform = 'scale(' + currentImageScale + ')'; };
		document.getElementById('imageZoomOutBtn').onclick = function () { currentImageScale = Math.max(currentImageScale - .25, .5); if (img2) img2.style.transform = 'scale(' + currentImageScale + ')'; };
		document.getElementById('imageResetZoomBtn').onclick = function () { currentImageScale = 1; if (img2) img2.style.transform = 'scale(1)'; };
	}
	document.getElementById('pdfPrevPageBtn')?.addEventListener('click', function () { if (pdfDoc && currentPdfPage > 1) { currentPdfPage--; renderPdfPage(currentPdfPage); } });
	document.getElementById('pdfNextPageBtn')?.addEventListener('click', function () { if (pdfDoc && currentPdfPage < pdfDoc.numPages) { currentPdfPage++; renderPdfPage(currentPdfPage); } });
	document.getElementById('pdfZoomInBtn')?.addEventListener('click', function () { if (pdfDoc) { pdfScale += .25; renderPdfPage(currentPdfPage); } });
	document.getElementById('pdfZoomOutBtn')?.addEventListener('click', function () { if (pdfDoc && pdfScale > .5) { pdfScale -= .25; renderPdfPage(currentPdfPage); } });
	document.getElementById('pdfResetZoomBtn')?.addEventListener('click', function () { if (pdfDoc) { pdfScale = 1.5; renderPdfPage(currentPdfPage); } });
	document.getElementById('pdfViewerModal')?.addEventListener('hidden.bs.modal', function () { pdfDoc = null; currentPdfPage = 1; pdfScale = 1.5; });
</script>

<script>
	/* ── Envoi email ─────────────────────────────────────────────────────────── */
	(function () {
		var interventionId = <?= (int) ($intervention['id'] ?? 0) ?>;
		var baseUrl = window.BASE_URL || '<?= addslashes(BASE_URL) ?>';
		var modalEl = document.getElementById('sendEmailModal');
		if (!modalEl) return;
		function showLoading(s) { document.getElementById('sendEmailModalLoading').style.display = s ? 'block' : 'none'; document.getElementById('sendEmailModalContent').style.display = s ? 'none' : 'block'; }
		function validateForm() { if (document.getElementById('emailModeTemplate').checked) return document.getElementById('sendEmailTemplateId').value !== ''; return document.getElementById('sendEmailSubject').value.trim() !== '' && document.getElementById('sendEmailMessage').value.trim() !== ''; }
		function toggleMode() { var t = document.getElementById('emailModeTemplate').checked; document.getElementById('sendEmailTemplateBlock').style.display = t ? 'block' : 'none'; document.getElementById('sendEmailCustomBlock').style.display = t ? 'none' : 'block'; document.getElementById('sendEmailSubmitBtn').disabled = !validateForm(); }
		function loadEmailData() {
			showLoading(true);
			document.getElementById('sendEmailModalError').style.display = 'none';
			fetch(baseUrl + 'interventions/getEmailData/' + interventionId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.success) { document.getElementById('sendEmailModalError').textContent = data.error || 'Erreur'; document.getElementById('sendEmailModalError').style.display = 'block'; showLoading(false); document.getElementById('sendEmailModalContent').style.display = 'block'; return; }
					document.getElementById('sendEmailRecipient').textContent = data.recipient_email || '(aucun email renseigné)';
					var tb = document.getElementById('sendEmailTestModeBlock'), ta = document.getElementById('sendEmailTestAddress');
					if (data.test_email && data.test_email.trim()) { ta.textContent = data.test_email.trim(); tb.style.display = 'block'; } else tb.style.display = 'none';
					var sel = document.getElementById('sendEmailTemplateId'); sel.innerHTML = '<option value="">-- Choisir un template --</option>';
					(data.templates || []).forEach(function (t) { var o = document.createElement('option'); o.value = t.id; o.textContent = t.name || 'Template #' + t.id; sel.appendChild(o); });
					var list = document.getElementById('sendEmailAttachmentsList'); list.innerHTML = '';
					if (data.attachments && data.attachments.length) { data.attachments.forEach(function (a) { var lbl = document.createElement('label'); lbl.className = 'd-block mb-1'; var cb = document.createElement('input'); cb.type = 'checkbox'; cb.name = 'attachments[]'; cb.value = a.id; cb.className = 'form-check-input me-2'; lbl.appendChild(cb); lbl.appendChild(document.createTextNode(a.nom_personnalise || a.nom_fichier || '#' + a.id)); list.appendChild(lbl); }); } else list.innerHTML = '<span class="text-muted">Aucune pièce jointe</span>';
					document.getElementById('sendEmailSubject').value = ''; document.getElementById('sendEmailMessage').value = ''; document.getElementById('sendEmailPreview').style.display = 'none';
					showLoading(false); toggleMode(); document.getElementById('sendEmailSubmitBtn').disabled = !validateForm();
				})
				.catch(function () { document.getElementById('sendEmailModalError').textContent = 'Erreur réseau.'; document.getElementById('sendEmailModalError').style.display = 'block'; showLoading(false); document.getElementById('sendEmailModalContent').style.display = 'block'; });
		}
		modalEl.addEventListener('show.bs.modal', loadEmailData);
		document.getElementById('emailModeTemplate').addEventListener('change', toggleMode);
		document.getElementById('emailModeCustom').addEventListener('change', toggleMode);
		document.getElementById('sendEmailTemplateId').addEventListener('change', function () { document.getElementById('sendEmailSubmitBtn').disabled = !validateForm(); });
		document.getElementById('sendEmailSubject').addEventListener('input', function () { document.getElementById('sendEmailSubmitBtn').disabled = !validateForm(); });
		document.getElementById('sendEmailMessage').addEventListener('input', function () { document.getElementById('sendEmailSubmitBtn').disabled = !validateForm(); });
		document.getElementById('sendEmailPreviewBtn').addEventListener('click', function () {
			var tid = document.getElementById('sendEmailTemplateId').value; if (!tid) return;
			fetch(baseUrl + 'interventions/previewEmailTemplate/' + interventionId + '?template_id=' + encodeURIComponent(tid), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (data) { var p = document.getElementById('sendEmailPreview'); if (data.success) { var s = (data.subject || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); p.innerHTML = '<strong>Sujet :</strong> ' + s + '<br><strong>Corps :</strong><div class="mt-2 pt-2 border-top">' + (data.body || '') + '</div>'; p.style.display = 'block'; } else { p.innerHTML = data.error || 'Erreur'; p.style.display = 'block'; } });
		});
		document.getElementById('sendEmailSubmitBtn').addEventListener('click', function () {
			var btn = this; btn.disabled = true;
			document.getElementById('sendEmailModalError').style.display = 'none';
			var token = window.CSRF_TOKEN || '';
			var fd = new FormData(); fd.append('csrf_token', token);
			var tid = document.getElementById('sendEmailTemplateId').value;
			if (document.getElementById('emailModeTemplate').checked && tid) { fd.append('template_id', tid); }
			else { fd.append('subject', document.getElementById('sendEmailSubject').value.trim()); fd.append('message', document.getElementById('sendEmailMessage').value.trim()); }
			document.querySelectorAll('#sendEmailAttachmentsList input[name="attachments[]"]:checked').forEach(function (cb) { fd.append('attachments[]', cb.value); });
			fetch(baseUrl + 'interventions/sendEmail/' + interventionId, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token } })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success) { var mi = typeof bootstrap !== 'undefined' && bootstrap.Modal && bootstrap.Modal.getInstance(modalEl); if (mi) mi.hide(); if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'success', title: 'Envoyé', text: data.message || 'Email envoyé.' }); } else alert(data.message || 'Email envoyé.'); }
					else { document.getElementById('sendEmailModalError').textContent = data.error || 'Échec'; document.getElementById('sendEmailModalError').style.display = 'block'; btn.disabled = false; }
				})
				.catch(function () { document.getElementById('sendEmailModalError').textContent = 'Erreur réseau.'; document.getElementById('sendEmailModalError').style.display = 'block'; btn.disabled = false; });
		});
	})();
</script>

<script>
	/* ── MODALE FERMETURE ────────────────────────────────────────────────────── */
	(function () {
		'use strict';
		var interventionId = <?= (int) ($intervention['id'] ?? 0) ?>;
		var baseUrl = window.BASE_URL || '<?= addslashes(BASE_URL) ?>';

		var btn = document.getElementById('btnOuvrirFermeture');
		if (!btn) return;

		function getCsrfToken() {
			if (window.CSRF_TOKEN && window.CSRF_TOKEN.length > 0) return window.CSRF_TOKEN;
			var el = document.getElementById('csrf_token');
			if (el && el.value) return el.value;
			return '';
		}

		function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

		function showFermetureLoading(show) {
			document.getElementById('fermetureLoading').style.display = show ? 'block' : 'none';
			document.getElementById('fermetureContent').style.display = show ? 'none' : 'block';
		}

		function resetFermetureModal() {
			document.getElementById('fermetureLoading').style.display = 'block';
			document.getElementById('fermetureContent').style.display = 'none';
			document.getElementById('fermetureContent').innerHTML = '';
			document.getElementById('fermetureConfirmer').disabled = true;
			document.getElementById('fermetureEmailCheck').style.display = 'none';
		}

		btn.addEventListener('click', function () {
			resetFermetureModal();
			var modal = new bootstrap.Modal(document.getElementById('modalFermerInter'));
			modal.show();

			fetch(baseUrl + 'interventions/getCloseDetails/' + interventionId, {
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			})
				.then(function (r) {
					if (!r.ok) throw new Error('HTTP ' + r.status);
					return r.json();
				})
				.then(function (data) {
					showFermetureLoading(false);
					if (!data.success) {
						document.getElementById('fermetureContent').innerHTML =
							'<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>'
							+ esc(data.error || 'Une erreur est survenue.')
							+ '</div>';
						return;
					}
					renderFermeture(data);
				})
				.catch(function (e) {
					showFermetureLoading(false);
					document.getElementById('fermetureContent').innerHTML =
						'<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>'
						+ 'Impossible de fermer cette intervention : Aucun technicien n\'est affecté à cette intervention. Veuillez d\'abord affecter un technicien.'
						+ '</div>';
				});
		});

		function renderFermeture(data) {
			var html = '';
			html += '<table class="table table-sm table-bordered align-middle mb-3">';
			html += '<thead class="table-light"><tr><th>Technicien</th><th>Durée</th><th>Dépl.</th><th>Qualifié</th><th>Détail calcul</th><th class="text-end">Tickets</th></tr></thead><tbody>';
			data.technicians.forEach(function (t) {
				var qualifiedBadge = t.is_qualified ? '<i class="bi bi-check-circle-fill text-success"></i>' : '-';
				html += '<tr>';
				html += '<td>' + esc(t.name) + '</td>';
				html += '<td>' + t.duration_minutes + ' min<br><small class="text-muted">' + t.duration_hours.toFixed(2) + 'h</small></td>';
				html += '<td class="text-center">' + (t.has_travel ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>') + '</td>';
				html += '<td class="text-center">' + qualifiedBadge + '</td>';
				html += '<td><small class="text-muted font-monospace">' + esc(t.formula) + '</small></td>';
				html += '<td class="text-end fw-bold">' + t.tickets_rounded + '</td>';
				html += '</tr>';
			});
			html += '</tbody>';
			html += '<tfoot><tr class="table-secondary fw-bold"><td colspan="5" class="text-end">Total proposé</td><td class="text-end">' + data.total_tickets + '</td></tr></tfoot>';
			html += '</table>';

			if (data.contract) {
				var after = data.contract.tickets_after_close;
				var cls = after > 3 ? 'success' : (after > 0 ? 'warning' : 'danger');
				html += '<div class="alert alert-light border mb-3 py-2">';
				html += '<strong>Contrat :</strong> ' + esc(data.contract.name);
				html += ' &nbsp;|&nbsp; Solde actuel : <strong>' + data.contract.tickets_remaining + '</strong>';
				html += ' &nbsp;→&nbsp; Après fermeture : <span class="badge bg-' + cls + '">' + after + '</span>';
				if (after < 0) {
					html += '<div class="alert alert-danger mt-2 mb-0 py-1">';
					html += '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
					html += '<strong>⚠️ Attention !</strong> Cette fermeture rendrait le solde du contrat négatif.';
					html += '<br>Veuillez réduire le nombre de tickets ou ajouter des tickets au contrat.';
					html += '</div>';
				}
				html += '</div>';
			}

			html += '<div class="mb-2">'
				+ '<label for="ticketsManuel" class="form-label fw-bold">'
				+ 'Tickets à déduire'
				+ '<small class="text-muted fw-normal ms-2">(modifiez si nécessaire)</small>'
				+ '</label>'
				+ '<input type="number" class="form-control" id="ticketsManuel" min="0" step="0.5" value="' + data.total_tickets + '">'
				+ '<div id="ticketsWarning" class="form-text" style="display:none; color: #dca235;">'
				+ '<i class="bi bi-exclamation-triangle-fill me-1"></i> <span id="ticketsWarningText"></span>'
				+ '</div>'
				+ '</div>';

			document.getElementById('fermetureContent').innerHTML = html;
			document.getElementById('fermetureConfirmer').disabled = false;
			document.getElementById('fermetureEmailCheck').style.display = 'flex';

			var ticketsInput = document.getElementById('ticketsManuel');
			if (ticketsInput && data.contract) {
				var currentRemaining = data.contract.tickets_remaining;
				function checkSolde() {
					var value = parseFloat(ticketsInput.value) || 0;
					var newRemaining = currentRemaining - value;
					if (newRemaining < 0) {
						document.getElementById('ticketsWarning').style.display = 'block';
						document.getElementById('ticketsWarningText').innerHTML = 'Attention : Le solde deviendrait négatif (' + newRemaining.toFixed(2) + '). Veuillez réduire le nombre de tickets à ' + currentRemaining.toFixed(2) + ' maximum.';
						document.getElementById('fermetureConfirmer').disabled = true;
					} else {
						document.getElementById('ticketsWarning').style.display = 'none';
						document.getElementById('fermetureConfirmer').disabled = false;
					}
				}
				ticketsInput.addEventListener('input', checkSolde);
				ticketsInput.addEventListener('change', checkSolde);
				checkSolde();
			}

			document.getElementById('fermetureConfirmer').onclick = function () {
				var tickets = parseFloat(document.getElementById('ticketsManuel').value) || 0;
				var sendEmail = document.getElementById('sendEmailClose').checked ? 1 : 0;
				var token = getCsrfToken();

				if (!token) {
					alert('Token CSRF manquant. Rechargez la page et réessayez.');
					return;
				}
				if (data.contract && tickets > data.contract.tickets_remaining) {
					alert('Erreur : Le nombre de tickets à déduire (' + tickets + ') dépasse le solde disponible (' + data.contract.tickets_remaining + ').');
					document.getElementById('fermetureConfirmer').disabled = false;
					document.getElementById('fermetureConfirmer').innerHTML = '<i class="bi bi-lock me-1"></i>Confirmer la fermeture';
					return;
				}

				document.getElementById('fermetureConfirmer').disabled = true;
				document.getElementById('fermetureConfirmer').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Fermeture…';

				var fd = new FormData();
				fd.append('csrf_token', token);
				fd.append('tickets_used', tickets);
				fd.append('send_email', sendEmail);

				fetch(baseUrl + 'interventions/close/' + interventionId, {
					method: 'POST',
					body: fd,
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				})
					.then(function (r) {
						if (!r.ok) throw new Error('HTTP ' + r.status);
						return r.json();
					})
					.then(function (result) {
						if (result && result.success === false) {
							alert('Erreur : ' + (result.error || 'Fermeture impossible.'));
							document.getElementById('fermetureConfirmer').disabled = false;
							document.getElementById('fermetureConfirmer').innerHTML = '<i class="bi bi-lock me-1"></i>Confirmer la fermeture';
						} else {
							window.location.reload();
						}
					})
					.catch(function () {
						window.location.reload();
					});
			};
		}
	})();
</script>
<script>
	document.addEventListener('click', function (e) {
		var link = e.target.closest('.contract-info-link');
		if (link) {
			e.preventDefault();
			e.stopPropagation();
			var contractId = link.getAttribute('data-contract-id');
			if (contractId) {
				var modalElement = document.getElementById('contractDetailsModal');
				if (modalElement) {
					// Nettoyer les backdrops existants avant d'ouvrir
					cleanupModals();

					var modal = new bootstrap.Modal(modalElement, {
						backdrop: true,
						keyboard: true
					});
					modal.show();
					loadContractDetails(contractId);

					// Nettoyer après fermeture
					modalElement.addEventListener('hidden.bs.modal', function onHidden() {
						modalElement.removeEventListener('hidden.bs.modal', onHidden);
						cleanupModals();
						// Restaurer le scroll
						document.body.style.overflow = '';
						document.body.style.position = '';
						document.body.style.paddingRight = '';
					});
				}
			}
		}
	});

	function cleanupModals() {
		// Supprimer les backdrops orphelins
		var backdrops = document.querySelectorAll('.modal-backdrop');
		backdrops.forEach(function (backdrop) {
			backdrop.remove();
		});
		// Restaurer la classe body
		document.body.classList.remove('modal-open');
		document.body.style.overflow = '';
		document.body.style.position = '';
		document.body.style.paddingRight = '';
	}
	loadTechniciansInPage(); 
</script>
<script>
	/* ── Techniciens ────────────────────────────────────────────────────────── */
	var assignedTechnicians = [];
	var currentEditId = null;

	function loadTechniciansInPage() {
		var container = document.getElementById('techniciansListContainer');
		if (!container) return;
		var interventionId = <?= (int) ($intervention['id'] ?? 0) ?>;
		fetch(window.BASE_URL + 'interventions/interventionsTechnician?id=' + interventionId, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var assigned = data.data?.assigned || data.assigned || [];
				if (assigned.length === 0) { container.innerHTML = '<div class="text-center py-3 text-muted">Aucun technicien affecté</div>'; return; }
				var html = '<div class="list-group list-group-flush">';
				assigned.forEach(function (tech) {
					var name = tech.full_name || (tech.first_name + ' ' + tech.last_name);
					var st = tech.start_time ? new Date(tech.start_time).toLocaleString('fr-FR') : 'Non défini';
					var et = tech.end_time ? new Date(tech.end_time).toLocaleString('fr-FR') : 'Non défini';
					var tp = tech.temps_passe ? tech.temps_passe + ' min' : 'Non défini';
					var dep = tech.deplacement == 1 ? 'Oui' : 'Non';
					var qual = tech.is_qualified == 1 ? '<span class="badge bg-success text-dark">Qualifié</span>' : '<span class="badge bg-secondary">Non qualifié</span>';
					html += '<div class="list-group-item"><div class="d-flex justify-content-between align-items-start"><div style="flex:1;"><strong>' + escapeHtml(name) + '</strong> ' + qual + '<br><small>Début: ' + st + '<br>Fin: ' + et + '<br>Durée: ' + tp + '<br>Déplacement: ' + dep + '</small>' + (tech.commentaire ? '<br><small class="text-info">' + escapeHtml(tech.commentaire.substring(0, 100)) + '</small>' : '') + '</div><div class="d-flex gap-1"><button class="btn btn-sm btn-outline-primary" onclick="sendEmailToTechnician(' + tech.technicien_id + ',\'' + escapeHtml(name) + '\')"><i class="bi bi-envelope"></i></button><button class="btn btn-sm btn-outline-danger" onclick="removeTechnicianFromPage(' + tech.technicien_id + ')"><i class="bi bi-trash"></i></button></div></div></div>';
				});
				html += '</div>';
				container.innerHTML = html;
			})
			.catch(function (e) { console.error(e); container.innerHTML = '<div class="text-center py-3 text-danger">Erreur de chargement</div>'; });
	}

	function removeTechnicianFromPage(technicianId) {
		var interventionId = <?= (int) ($intervention['id'] ?? 0) ?>;
		if (!confirm('Retirer ce technicien de cette intervention ?')) return;
		fetch(window.BASE_URL + 'interventions/removeTechnician', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
			body: JSON.stringify({ intervention_id: interventionId, technician_id: technicianId })
		})
			.then(function (r) { return r.json(); })
			.then(function (result) {
				if (result.success) { loadTechniciansInPage(); alert(result.message); }
				else { alert('Erreur: ' + (result.error || 'Suppression impossible')); loadTechniciansInPage(); }
			})
			.catch(function (error) { console.error(error); alert('Erreur réseau'); loadTechniciansInPage(); });
	}

	function openTechModal(id) {
		if (!id) { alert('ID intervention manquant'); return; }
		assignedTechnicians = []; currentEditId = null;
		document.getElementById('intervention_id').value = id;
		resetTechnicianForm();
		var sel = document.getElementById('techSelect');
		sel.innerHTML = '<option value="">Chargement…</option>';
		fetch(window.BASE_URL + 'interventions/interventionsTechnician?id=' + id, {
			method: 'GET',
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				sel.innerHTML = '<option value="">-- Sélectionner un technicien --</option>';
				var technicians = data.data?.technicians || data.technicians || [];
				var assigned = data.data?.assigned || data.assigned || [];
				assigned.forEach(function (a) {
					var tech = technicians.find(function (t) { return t.id == a.technicien_id; });
					if (tech) {
						assignedTechnicians.push({
							id: tech.id, name: tech.full_name || (tech.first_name + ' ' + tech.last_name),
							start_time: a.start_time || '', end_time: a.end_time || '', temps_passe: a.temps_passe || '',
							deplacement: a.deplacement || 0, is_qualified: a.is_qualified || 0, commentaire: a.commentaire || ''
						});
					}
				});
				if (technicians.length === 0) { sel.innerHTML = '<option value="">Aucun technicien disponible</option>'; }
				else { technicians.forEach(function (t) { var o = document.createElement('option'); o.value = t.id; o.text = t.full_name || (t.first_name + ' ' + t.last_name); sel.appendChild(o); }); }
				if (typeof $ !== 'undefined' && $('#techSelect').select2) { $('#techSelect').select2({ placeholder: 'Rechercher un technicien', allowClear: true, width: '100%', dropdownParent: $('#techModal') }); }
				var me = document.getElementById('techModal'); if (me) new bootstrap.Modal(me).show();
			})
			.catch(function (e) { alert('Erreur chargement: ' + e.message); sel.innerHTML = '<option value="">Erreur</option>'; });
	}

	function resetTechnicianForm() {
		document.getElementById('selected_technician_id').value = '';
		document.getElementById('selectedTechnicianName').textContent = '---';
		document.getElementById('start_time').value = '';
		document.getElementById('end_time').value = '';
		document.getElementById('temps_passe').value = '';
		document.getElementById('deplacement').value = '0';
		document.getElementById('is_qualified').value = '0';
		document.getElementById('commentaire').value = '';
		document.getElementById('technicianDetails').style.display = 'block';
		document.getElementById('btnRemoveCurrent').style.display = 'none';
		currentEditId = null;
	}

	function roundToHalfHour(m) { if (!m || m <= 0) return 0; var r = Math.round(m / 30) * 30; return r === 0 && m > 0 ? 30 : r; }

	function displayRoundedTime() {
		var v = parseInt(document.getElementById('temps_passe').value) || 0;
		var r = roundToHalfHour(v);
		var d = document.getElementById('roundedTimeDisplay');
		if (v > 0) {
			var h2 = Math.floor(r / 60), m2 = r % 60;
			var ft = h2 > 0 && m2 > 0 ? h2 + 'h' + m2 : (h2 > 0 ? h2 + 'h' : m2 + 'min');
			d.innerHTML = '<i class="bi bi-calculator-fill text-primary"></i> <strong>Saisie :</strong> ' + v + ' min<br><strong>Après arrondi :</strong> ' + ft;
			d.style.display = 'block';
		} else d.style.display = 'none';
	}

	document.getElementById('techSelect')?.addEventListener('change', function () {
		var tid = this.value;
		if (!tid) { resetTechnicianForm(); currentEditId = null; return; }
		document.getElementById('selectedTechnicianName').textContent = this.options[this.selectedIndex].text;
		document.getElementById('selected_technician_id').value = tid;
		var ex = assignedTechnicians.find(function (t) { return t.id == tid; });
		if (ex) {
			document.getElementById('start_time').value = ex.start_time || '';
			document.getElementById('end_time').value = ex.end_time || '';
			document.getElementById('temps_passe').value = ex.temps_passe || '';
			document.getElementById('deplacement').value = ex.deplacement || '0';
			document.getElementById('is_qualified').value = ex.is_qualified || '0';
			document.getElementById('commentaire').value = ex.commentaire || '';
			document.getElementById('btnRemoveCurrent').style.display = 'inline-block';
			currentEditId = tid;
		} else {
			document.getElementById('start_time').value = '';
			document.getElementById('end_time').value = '';
			document.getElementById('temps_passe').value = '';
			document.getElementById('deplacement').value = '0';
			document.getElementById('is_qualified').value = '0';
			document.getElementById('commentaire').value = '';
			document.getElementById('btnRemoveCurrent').style.display = 'none';
			currentEditId = null;
		}
		document.getElementById('technicianDetails').style.display = 'block';
	});

	function removeCurrentTechnician() {
		var tid = document.getElementById('selected_technician_id').value;
		if (!tid) return;
		var tech = assignedTechnicians.find(function (t) { return t.id == tid; });
		if (tech && confirm('Retirer ' + tech.name + ' de cette intervention ?')) {
			assignedTechnicians = assignedTechnicians.filter(function (t) { return t.id != tid; });
			resetTechnicianForm();
			document.getElementById('techSelect').value = '';
			if (typeof $ !== 'undefined' && $('#techSelect').select2) { $('#techSelect').val('').trigger('change'); }
			loadTechniciansInPage();
		}
	}

	function saveAllTechnicians() {
		var interventionId = document.getElementById('intervention_id').value;
		if (!interventionId) { alert('ID intervention manquant'); return; }
		var toSave = [];
		for (var i = 0; i < assignedTechnicians.length; i++) {
			var existingTech = assignedTechnicians[i];
			toSave.push({ technicien_id: parseInt(existingTech.id), start_time: existingTech.start_time || null, end_time: existingTech.end_time || null, temps_passe: existingTech.temps_passe || null, deplacement: existingTech.deplacement || 0, is_qualified: existingTech.is_qualified || 0, commentaire: existingTech.commentaire || '', notify_technician: 0 });
		}
		var sel = document.getElementById('techSelect');
		var selectedValue = sel.value;
		if (selectedValue) {
			var st = document.getElementById('start_time').value, et = document.getElementById('end_time').value, tp = parseInt(document.getElementById('temps_passe').value) || 0, dep = parseInt(document.getElementById('deplacement').value) || 0, iq = parseInt(document.getElementById('is_qualified').value) || 0, comment = document.getElementById('commentaire').value;
			if (st && et && new Date(st) >= new Date(et)) { alert('La date de fin doit être postérieure à la date de début.'); return; }
			if (tp > 0) tp = roundToHalfHour(tp) || 30;
			var existingIndex = toSave.findIndex(function (t) { return t.technicien_id == selectedValue; });
			var techData = { technicien_id: parseInt(selectedValue), start_time: st || null, end_time: et || null, temps_passe: tp || null, deplacement: dep, is_qualified: iq, commentaire: comment, notify_technician: 1 };
			if (existingIndex >= 0) { toSave[existingIndex] = techData; } else { toSave.push(techData); }
		}
		if (toSave.length === 0) { alert('Veuillez sélectionner au moins un technicien'); return; }
		var saveBtn = document.querySelector('#techModal .btn-primary');
		var originalText = saveBtn ? saveBtn.innerHTML : '';
		if (saveBtn) { saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Enregistrement...'; saveBtn.disabled = true; }
		fetch(window.BASE_URL + 'interventions/assignTechnicians', {
			method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
			body: JSON.stringify({ intervention_id: parseInt(interventionId), technicians: toSave, replace: true })
		})
			.then(function (r) { return r.json(); })
			.then(function (result) {
				if (saveBtn) { saveBtn.innerHTML = originalText; saveBtn.disabled = false; }
				if (result.success) { alert('Techniciens affectés avec succès !'); var m = bootstrap.Modal.getInstance(document.getElementById('techModal')); if (m) m.hide(); location.reload(); }
				else { alert('Erreur: ' + (result.error || 'Inconnue')); }
			})
			.catch(function (e) { if (saveBtn) { saveBtn.innerHTML = originalText; saveBtn.disabled = false; } alert('Erreur réseau: ' + e.message); });
	}

	async function sendEmailToTechnician(technicianId, technicianName) {
		var interventionId = <?= (int) ($intervention['id'] ?? 0) ?>;
		if (!confirm('Envoyer un email de notification à ' + technicianName + ' ?')) return;
		try {
			var fd = new URLSearchParams();
			fd.append('intervention_id', interventionId);
			fd.append('technician_id', technicianId);
			fd.append('csrf_token', window.CSRF_TOKEN || '');
			var r = await fetch(window.BASE_URL + 'interventions/sendTechnicianEmail', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
			var data = await r.json();
			if (data.success) alert('Email envoyé à ' + technicianName);
			else alert('Erreur: ' + (data.error || 'Échec'));
		} catch (e) { alert(' Erreur: ' + e.message); }
	}

	function escapeHtml(s) { if (!s) return ''; return s.replace(/[&<>]/g, function (m) { if (m === '&') return '&amp;'; if (m === '<') return '&lt;'; if (m === '>') return '&gt;'; return m; }); }

	document.addEventListener('DOMContentLoaded', function () {
		loadTechniciansInPage();
		var tp = document.getElementById('temps_passe');
		if (tp) { tp.addEventListener('input', displayRoundedTime); tp.addEventListener('blur', function () { var v = parseInt(this.value) || 0; var r = roundToHalfHour(v); if (r !== v && r > 0) { this.value = r; displayRoundedTime(); } }); }
	});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>