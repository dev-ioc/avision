<?php
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Détail du Matériel', 'materiel_client');
$currentPage = 'materiel_client';

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

$materiel = $materiel ?? [];
$visibilites_champs = $visibilites_champs ?? [];
$attachments = $attachments ?? [];

?>
<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-hdd-network me-2 me-1"></i>Détail du Matériel
                    </h4>
                    <p class="text-muted mb-0">Consultation d'un équipement de votre parc</p>
                </div>
                <div>
                    <?php
                    // Construire l'URL de retour avec les paramètres de filtres
                    $returnParams = [];
                    if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                        $returnParams['site_id'] = $_GET['site_id'];
                    }
                    if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
                        $returnParams['salle_id'] = $_GET['salle_id'];
                    }
                    if (isset($_GET['search']) && !empty($_GET['search'])) {
                        $returnParams['search'] = $_GET['search'];
                    }
                    
                    $returnUrl = BASE_URL . 'materiel_client';
                    if (!empty($returnParams)) {
                        $returnUrl .= '?' . http_build_query($returnParams);
                    }
                    ?>
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2 me-1"></i>Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-body-secondary">
                    <h5 class="mb-0">Informations générales</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Marque</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['marque']) && !$visibilites_champs[$materiel['id']]['marque']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['marque']) && $visibilites_champs[$materiel['id']]['marque'] && !empty($materiel['marque'])): ?>
                                <?= h($materiel['marque']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Modèle</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['modele']) && !$visibilites_champs[$materiel['id']]['modele']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['modele']) && $visibilites_champs[$materiel['id']]['modele'] && !empty($materiel['modele'])): ?>
                                <?= h($materiel['modele']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['type_materiel']) && !$visibilites_champs[$materiel['id']]['type_materiel']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['type_materiel']) && $visibilites_champs[$materiel['id']]['type_materiel'] && !empty($materiel['type_materiel'])): ?>
                                <?= h($materiel['type_materiel']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Référence</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['reference']) && !$visibilites_champs[$materiel['id']]['reference']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['reference']) && $visibilites_champs[$materiel['id']]['reference'] && !empty($materiel['reference'])): ?>
                                <?= h($materiel['reference']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Usage/Description</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['usage_materiel']) && !$visibilites_champs[$materiel['id']]['usage_materiel']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['usage_materiel']) && $visibilites_champs[$materiel['id']]['usage_materiel'] && !empty($materiel['usage_materiel'])): ?>
                                <?= nl2br(h($materiel['usage_materiel'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Numéro de série</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['numero_serie']) && !$visibilites_champs[$materiel['id']]['numero_serie']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['numero_serie']) && $visibilites_champs[$materiel['id']]['numero_serie'] && !empty($materiel['numero_serie'])): ?>
                                <?= h($materiel['numero_serie']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Firmware</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['version_firmware']) && !$visibilites_champs[$materiel['id']]['version_firmware']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['version_firmware']) && $visibilites_champs[$materiel['id']]['version_firmware'] && !empty($materiel['version_firmware'])): ?>
                                <?= h($materiel['version_firmware']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Ancien Firmware</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['ancien_firmware']) && !$visibilites_champs[$materiel['id']]['ancien_firmware']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['ancien_firmware']) && $visibilites_champs[$materiel['id']]['ancien_firmware'] && !empty($materiel['ancien_firmware'])): ?>
                                <?= h($materiel['ancien_firmware']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Login</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['login']) && !$visibilites_champs[$materiel['id']]['login']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['login']) && $visibilites_champs[$materiel['id']]['login'] && !empty($materiel['login'])): ?>
                                <?= h($materiel['login']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Mot de passe</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['password']) && !$visibilites_champs[$materiel['id']]['password']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['password']) && $visibilites_champs[$materiel['id']]['password'] && !empty($materiel['password'])): ?>
                                <?= h($materiel['password']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Adresse IP</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && !$visibilites_champs[$materiel['id']]['adresse_ip']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && $visibilites_champs[$materiel['id']]['adresse_ip'] && !empty($materiel['adresse_ip'])): ?>
                                <?= h($materiel['adresse_ip']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Adresse MAC</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && !$visibilites_champs[$materiel['id']]['adresse_mac']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && $visibilites_champs[$materiel['id']]['adresse_mac'] && !empty($materiel['adresse_mac'])): ?>
                                <?= h($materiel['adresse_mac']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Masque</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['masque']) && !$visibilites_champs[$materiel['id']]['masque']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['masque']) && $visibilites_champs[$materiel['id']]['masque'] && !empty($materiel['masque'])): ?>
                                <?= h($materiel['masque']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Passerelle</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['passerelle']) && !$visibilites_champs[$materiel['id']]['passerelle']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['passerelle']) && $visibilites_champs[$materiel['id']]['passerelle'] && !empty($materiel['passerelle'])): ?>
                                <?= h($materiel['passerelle']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">ID Matériel</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['id_materiel']) && !$visibilites_champs[$materiel['id']]['id_materiel']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['id_materiel']) && $visibilites_champs[$materiel['id']]['id_materiel'] && !empty($materiel['id_materiel'])): ?>
                                <?= h($materiel['id_materiel']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Date fin maintenance</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && !$visibilites_champs[$materiel['id']]['date_fin_maintenance']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && $visibilites_champs[$materiel['id']]['date_fin_maintenance'] && !empty($materiel['date_fin_maintenance'])): ?>
                                <?= htmlspecialchars(formatDateFrench($materiel['date_fin_maintenance'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Date fin garantie</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && !$visibilites_champs[$materiel['id']]['date_fin_garantie']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && $visibilites_champs[$materiel['id']]['date_fin_garantie'] && !empty($materiel['date_fin_garantie'])): ?>
                                <?= htmlspecialchars(formatDateFrench($materiel['date_fin_garantie'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Date dernière intervention</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['date_derniere_inter']) && !$visibilites_champs[$materiel['id']]['date_derniere_inter']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['date_derniere_inter']) && $visibilites_champs[$materiel['id']]['date_derniere_inter'] && !empty($materiel['date_derniere_inter'])): ?>
                                <?= htmlspecialchars(formatDateFrench($materiel['date_derniere_inter'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Commentaire</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['commentaire']) && !$visibilites_champs[$materiel['id']]['commentaire']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['commentaire']) && $visibilites_champs[$materiel['id']]['commentaire'] && !empty($materiel['commentaire'])): ?>
                                <?= nl2br(h($materiel['commentaire'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header bg-body-secondary">
                    <h5 class="mb-0">Informations réseau avancées</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">IP Primaire</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['ip_primaire']) && !$visibilites_champs[$materiel['id']]['ip_primaire']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['ip_primaire']) && $visibilites_champs[$materiel['id']]['ip_primaire'] && !empty($materiel['ip_primaire'])): ?>
                                <?= h($materiel['ip_primaire']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">MAC Primaire</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['mac_primaire']) && !$visibilites_champs[$materiel['id']]['mac_primaire']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['mac_primaire']) && $visibilites_champs[$materiel['id']]['mac_primaire'] && !empty($materiel['mac_primaire'])): ?>
                                <?= h($materiel['mac_primaire']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">IP Secondaire</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['ip_secondaire']) && !$visibilites_champs[$materiel['id']]['ip_secondaire']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['ip_secondaire']) && $visibilites_champs[$materiel['id']]['ip_secondaire'] && !empty($materiel['ip_secondaire'])): ?>
                                <?= h($materiel['ip_secondaire']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">MAC Secondaire</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['mac_secondaire']) && !$visibilites_champs[$materiel['id']]['mac_secondaire']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['mac_secondaire']) && $visibilites_champs[$materiel['id']]['mac_secondaire'] && !empty($materiel['mac_secondaire'])): ?>
                                <?= h($materiel['mac_secondaire']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Stream AES67 Reçu</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['stream_aes67_recu']) && !$visibilites_champs[$materiel['id']]['stream_aes67_recu']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['stream_aes67_recu']) && $visibilites_champs[$materiel['id']]['stream_aes67_recu'] && !empty($materiel['stream_aes67_recu'])): ?>
                                <?= h($materiel['stream_aes67_recu']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Stream AES67 Transmis</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['stream_aes67_transmis']) && !$visibilites_champs[$materiel['id']]['stream_aes67_transmis']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['stream_aes67_transmis']) && $visibilites_champs[$materiel['id']]['stream_aes67_transmis'] && !empty($materiel['stream_aes67_transmis'])): ?>
                                <?= h($materiel['stream_aes67_transmis']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">SSID WiFi</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['ssid']) && !$visibilites_champs[$materiel['id']]['ssid']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['ssid']) && $visibilites_champs[$materiel['id']]['ssid'] && !empty($materiel['ssid'])): ?>
                                <?= h($materiel['ssid']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Type de cryptage WiFi</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['type_cryptage']) && !$visibilites_champs[$materiel['id']]['type_cryptage']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['type_cryptage']) && $visibilites_champs[$materiel['id']]['type_cryptage'] && !empty($materiel['type_cryptage'])): ?>
                                <?= h($materiel['type_cryptage']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Mot de passe WiFi</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['password_wifi']) && !$visibilites_champs[$materiel['id']]['password_wifi']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['password_wifi']) && $visibilites_champs[$materiel['id']]['password_wifi'] && !empty($materiel['password_wifi'])): ?>
                                <?= h($materiel['password_wifi']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Libellé PA Salle</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['libelle_pa_salle']) && !$visibilites_champs[$materiel['id']]['libelle_pa_salle']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['libelle_pa_salle']) && $visibilites_champs[$materiel['id']]['libelle_pa_salle'] && !empty($materiel['libelle_pa_salle'])): ?>
                                <?= h($materiel['libelle_pa_salle']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">N° Port Switch</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['numero_port_switch']) && !$visibilites_champs[$materiel['id']]['numero_port_switch']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['numero_port_switch']) && $visibilites_champs[$materiel['id']]['numero_port_switch'] && !empty($materiel['numero_port_switch'])): ?>
                                <?= h($materiel['numero_port_switch']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">VLAN</dt>
                        <dd class="col-sm-8 <?= (isset($visibilites_champs[$materiel['id']]['vlan']) && !$visibilites_champs[$materiel['id']]['vlan']) ? 'bg-warning bg-opacity-25' : '' ?>">
                            <?php if (isset($visibilites_champs[$materiel['id']]['vlan']) && $visibilites_champs[$materiel['id']]['vlan'] && !empty($materiel['vlan'])): ?>
                                <?= h($materiel['vlan']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>

        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-body-secondary">
                    <h5 class="mb-0">Pièces jointes</h5>
                </div>
                <div class="card-body <?= (isset($visibilites_champs[$materiel['id']]['pieces_jointes']) && !$visibilites_champs[$materiel['id']]['pieces_jointes']) ? 'bg-warning bg-opacity-25' : '' ?>">
                    <?php if (isset($visibilites_champs[$materiel['id']]['pieces_jointes']) && $visibilites_champs[$materiel['id']]['pieces_jointes'] && !empty($attachments)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($attachments as $att): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?> me-2 text-muted"></i>
                                                <span class="fw-medium"><?= h($att['nom_fichier']) ?></span>
                                            </div>
                                            <?php if (!empty($att['commentaire'])): ?>
                                                <small class="text-muted d-block"><?= h($att['commentaire']) ?></small>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <?= number_format(($att['taille_fichier'] ?? 0) / 1024, 1) ?> KB • 
                                                <?= date('d/m/Y H:i', strtotime($att['date_creation'])) ?>
                                            </small>
                                        </div>
                                        <div class="ms-3">
                                            <a href="<?= BASE_URL; ?>materiel_client/download/<?= $att['id'] ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Télécharger">
                                                <i class="bi bi-download me-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-3">
                                                            <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?> fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">
                                <?php if (isset($visibilites_champs[$materiel['id']]['pieces_jointes']) && !$visibilites_champs[$materiel['id']]['pieces_jointes']): ?>
                                    Pièces jointes non visibles
                                <?php else: ?>
                                    Aucune pièce jointe disponible
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 