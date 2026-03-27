<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Matériel et Documentation',
    'materiel_test'
);

// Définir la page courante pour le menu
$currentPage = 'materiel_test';

// Récupérer les données depuis le contrôleur
$clients = $clients ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];
$materiel_list = $materiel_list ?? [];
$documentation_list = $documentation_list ?? [];
$pieces_jointes_count = $pieces_jointes_count ?? [];
$filters = $filters ?? [];
$selectedSite = $selectedSite ?? null;
$selectedRoom = $selectedRoom ?? null;

// Debug: vérifier les données
error_log("VUE - materiel_list count: " . count($materiel_list));
error_log("VUE - documentation_list count: " . count($documentation_list));
error_log("VUE - selectedRoom: " . ($selectedRoom ? 'yes' : 'no'));
error_log("VUE - filters: " . json_encode($filters));
error_log("VUE - isset materiel_list: " . (isset($materiel_list) ? 'yes' : 'no'));
error_log("VUE - isset documentation_list: " . (isset($documentation_list) ? 'yes' : 'no'));

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<style>
    .materiel-test-container {
        max-width: 1400px;
        margin: 0 auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    /* En-tête */
    .materiel-test-header {
        padding: 24px;
        border-bottom: 1px solid #e0e0e0;
    }

    .materiel-test-header h1 {
        font-size: 24px;
        margin-bottom: 20px;
        color: #333;
    }

    /* Filtres */
    .materiel-test-filters {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-group label {
        font-size: 12px;
        font-weight: 500;
        color: #666;
        text-transform: uppercase;
    }

    .filter-group select {
        padding: 8px 32px 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        cursor: pointer;
        min-width: 180px;
    }

    .filter-group select:hover {
        border-color: #2196F3;
    }

    /* Section documents de la salle */
    .room-docs-section {
        border-bottom: 1px solid #e0e0e0;
        background: #fefefe;
    }

    .room-docs-header {
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8f9fa;
        cursor: pointer;
    }

    .room-docs-header:hover {
        background: #f0f2f5;
    }

    .room-docs-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .room-docs-title h3 {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin: 0;
    }

    .room-docs-count {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;
    }

    .room-docs-content {
        padding: 0;
        max-height: 500px;
        overflow-y: auto;
    }

    .room-docs-content.collapsed {
        display: none;
    }

    /* Barre d'outils documents */
    .docs-toolbar {
        padding: 16px 24px;
        border-bottom: 1px solid #e0e0e0;
        background: white;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .docs-search-box {
        position: relative;
        margin-bottom: 12px;
    }

    .docs-search-box input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    .docs-search-box::before {
        content: '🔍';
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
    }

    .docs-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .doc-filter-btn {
        padding: 6px 14px;
        border: 1px solid #ddd;
        border-radius: 16px;
        background: white;
        cursor: pointer;
        font-size: 13px;
        color: #666;
        transition: all 0.2s;
    }

    .doc-filter-btn:hover {
        background: #f5f5f5;
        border-color: #2196F3;
    }

    .doc-filter-btn.active {
        background: #2196F3;
        color: white;
        border-color: #2196F3;
    }

    /* Liste documents avec catégories */
    .docs-list {
        padding: 20px 24px;
    }

    .docs-category {
        margin-bottom: 24px;
    }

    .docs-category:last-child {
        margin-bottom: 0;
    }

    .category-header {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e0e0e0;
    }

    .docs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 10px;
    }

    .doc-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }

    .doc-card:hover {
        border-color: #2196F3;
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.15);
    }

    .doc-icon {
        font-size: 28px;
        flex-shrink: 0;
    }

    .doc-info {
        flex: 1;
        min-width: 0;
    }

    .doc-name {
        font-size: 14px;
        font-weight: 500;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .doc-meta {
        font-size: 12px;
        color: #666;
        margin-top: 2px;
    }

    /* Barre d'outils */
    .toolbar {
        padding: 16px 24px;
        background: #fafafa;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .toolbar-left {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        padding: 8px 12px 8px 36px;
        border: 1px solid #ddd;
        border-radius: 6px;
        width: 300px;
        font-size: 14px;
    }

    .search-box::before {
        content: '🔍';
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
    }

    .btn {
        padding: 8px 16px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn:hover {
        background: #f5f5f5;
    }

    .results-count {
        color: #666;
        font-size: 14px;
    }

    /* Tableau */
    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead {
        background: #fafafa;
        position: sticky;
        top: 0;
    }

    th {
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        border-bottom: 2px solid #e0e0e0;
        white-space: nowrap;
    }

    th.sortable {
        cursor: pointer;
        user-select: none;
    }

    th.sortable:hover {
        background: #f0f0f0;
    }

    th.sortable::after {
        content: ' ⇅';
        opacity: 0.3;
    }

    td {
        padding: 16px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
        color: #333;
    }

    tr:hover {
        background: #f9f9f9;
    }

    tr.expandable {
        cursor: pointer;
    }

    /* Badges et statuts */
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .badge-success {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .badge-warning {
        background: #fff3e0;
        color: #f57c00;
    }

    .badge-danger {
        background: #ffebee;
        color: #c62828;
    }

    /* Documents */
    .docs-count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #2196F3;
        cursor: pointer;
        font-weight: 500;
    }

    .docs-count:hover {
        text-decoration: underline;
    }

    /* Ligne étendue */
    .expanded-row {
        background: #f9f9f9;
        display: none;
    }

    .expanded-row.show {
        display: table-row;
    }

    .expanded-content {
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .info-group h4 {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .info-group p {
        font-size: 14px;
        color: #333;
        margin-bottom: 4px;
    }
</style>

<div class="layout-content">
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="materiel-test-container">
            <!-- En-tête avec filtres -->
            <div class="materiel-test-header">
                <h1>Gestion du Matériel et Documentation</h1>
                <div class="materiel-test-filters">
                    <div class="filter-group">
                        <label>Client</label>
                        <select id="clientFilter">
                            <option value="">Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($filters['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                    <?= h($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Site</label>
                        <select id="siteFilter" <?= !$filters['client_id'] ? 'disabled' : '' ?>>
                            <option value="">Tous les sites</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= ($filters['site_id'] == $site['id']) ? 'selected' : '' ?>>
                                    <?= h($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Salle</label>
                        <select id="roomFilter" <?= !$filters['site_id'] ? 'disabled' : '' ?>>
                            <option value="">Toutes les salles</option>
                            <?php foreach ($salles as $salle): ?>
                                <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] == $salle['id']) ? 'selected' : '' ?>>
                                    <?= h($salle['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Debug info -->
            <div style="padding: 10px; background: #f0f0f0; margin: 10px; border-radius: 4px; font-size: 12px;">
                <strong>Debug:</strong><br>
                salle_id: <?= $filters['salle_id'] ?? 'N/A' ?><br>
                selectedRoom: <?= $selectedRoom ? 'OUI' : 'NON' ?><br>
                materiel_list count: <?= count($materiel_list) ?><br>
                documentation_list count: <?= count($documentation_list) ?><br>
            </div>

            <!-- Section documents de la salle -->
            <?php if (!empty($selectedRoom) && !empty($filters['salle_id'])): ?>
            <div class="room-docs-section" id="roomDocsSection">
                <div class="room-docs-header">
                    <div class="room-docs-title">
                        <h3>📁 Documents de la salle <?= h($selectedRoom['name']) ?></h3>
                        <span class="room-docs-count" id="roomDocsCount"><?= count($documentation_list) ?> documents</span>
                    </div>
                    <button class="btn" onclick="toggleRoomDocs()">
                        <span id="roomDocsToggleIcon">▼</span> Masquer
                    </button>
                </div>
                <div class="room-docs-content" id="roomDocsContent">
                    <!-- Barre de recherche et filtres pour les documents -->
                    <div class="docs-toolbar">
                        <div class="docs-search-box">
                            <input type="text" placeholder="Rechercher dans les documents..." id="docsSearch">
                        </div>
                        <div class="docs-filters">
                            <button class="doc-filter-btn active" data-category="all">Tous (<?= count($documentation_list) ?>)</button>
                            <?php
                            // Organiser les documents par catégorie
                            $docsByCategory = [];
                            foreach ($documentation_list as $doc) {
                                $category = $doc['file_type'] ?? 'other';
                                if (!isset($docsByCategory[$category])) {
                                    $docsByCategory[$category] = [];
                                }
                                $docsByCategory[$category][] = $doc;
                            }
                            $categoryLabels = [
                                'pdf' => 'PDF',
                                'dwg' => 'Plans',
                                'vsd' => 'Schémas',
                                'doc' => 'Documents',
                                'xls' => 'Tableaux',
                                'image' => 'Photos',
                                'video' => 'Vidéos',
                                'other' => 'Autres'
                            ];
                            foreach ($docsByCategory as $category => $docs): 
                                if (count($docs) > 0):
                            ?>
                                <button class="doc-filter-btn" data-category="<?= $category ?>">
                                    <?= $categoryLabels[$category] ?? ucfirst($category) ?> (<?= count($docs) ?>)
                                </button>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>

                    <!-- Liste des documents -->
                    <div class="docs-list">
                        <?php foreach ($docsByCategory as $category => $docs): ?>
                            <?php if (count($docs) > 0): ?>
                            <div class="docs-category" data-category="<?= $category ?>">
                                <div class="category-header">
                                    <?php
                                    $icons = [
                                        'pdf' => '📄',
                                        'dwg' => '📐',
                                        'vsd' => '📊',
                                        'doc' => '📄',
                                        'xls' => '📊',
                                        'image' => '📷',
                                        'video' => '🎥',
                                        'other' => '📦'
                                    ];
                                    echo $icons[$category] ?? '📄';
                                    ?>
                                    <?= $categoryLabels[$category] ?? ucfirst($category) ?> (<?= count($docs) ?>)
                                </div>
                                <div class="docs-grid">
                                    <?php foreach ($docs as $doc): ?>
                                        <div class="doc-card" data-category="<?= $category ?>" data-name="<?= strtolower(h($doc['nom_fichier'] ?? $doc['nom_personnalise'] ?? '')) ?>">
                                            <div class="doc-icon">
                                                <?= $icons[$category] ?? '📄' ?>
                                            </div>
                                            <div class="doc-info">
                                                <div class="doc-name"><?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Sans nom') ?></div>
                                                <div class="doc-meta">
                                                    <?= date('d/m/Y', strtotime($doc['date_creation'])) ?> • 
                                                    <?= formatFileSize($doc['taille_fichier'] ?? 0) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Barre d'outils -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <div class="search-box">
                        <input type="text" placeholder="Rechercher du matériel..." id="materielSearch">
                    </div>
                </div>
                <div class="results-count">
                    <strong><?= count($materiel_list) ?></strong> équipements
                </div>
            </div>

            <!-- Tableau -->
            <?php if (!empty($filters['salle_id'])): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th class="sortable">Référence</th>
                            <th class="sortable">Désignation</th>
                            <th class="sortable">Catégorie</th>
                            <th class="sortable">État</th>
                            <th class="sortable">Localisation</th>
                            <th class="sortable">Date création</th>
                            <th>Documents</th>
                            <th class="sortable">Garantie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materiel_list)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                    Aucun matériel dans cette salle
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materiel_list as $materiel): ?>
                                <tr class="expandable" onclick="toggleRow(this)">
                                    <td>▶</td>
                                    <td><strong><?= h($materiel['reference'] ?? 'N/A') ?></strong></td>
                                    <td><?= trim(($materiel['marque'] ?? '') . ' ' . ($materiel['modele'] ?? '')) ?: ($materiel['usage_materiel'] ?? $materiel['reference'] ?? 'N/A') ?></td>
                                    <td><?= h($materiel['type_materiel'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        // Déterminer l'état basé sur les dates de maintenance/garantie
                                        $etat = 'Opérationnel';
                                        $badgeClass = 'badge-success';
                                        
                                        if ($materiel['date_fin_maintenance']) {
                                            $dateFinMaintenance = strtotime($materiel['date_fin_maintenance']);
                                            if ($dateFinMaintenance < time()) {
                                                $etat = 'Maintenance requise';
                                                $badgeClass = 'badge-warning';
                                            }
                                        }
                                        
                                        if ($materiel['date_fin_garantie']) {
                                            $dateFinGarantie = strtotime($materiel['date_fin_garantie']);
                                            if ($dateFinGarantie < time()) {
                                                $etat = 'Garantie expirée';
                                                $badgeClass = 'badge-danger';
                                            }
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= h($etat) ?></span>
                                    </td>
                                    <td><?= h($materiel['site_nom'] ?? '') ?> - <?= h($materiel['salle_nom'] ?? '') ?></td>
                                    <td><?= $materiel['created_at'] ? date('d/m/Y', strtotime($materiel['created_at'])) : 'N/A' ?></td>
                                    <td>
                                        <span class="docs-count">
                                            📎 <?= $pieces_jointes_count[$materiel['id']] ?? 0 ?> docs
                                        </span>
                                    </td>
                                    <td><?= $materiel['date_fin_garantie'] ? date('d/m/Y', strtotime($materiel['date_fin_garantie'])) : 'N/A' ?></td>
                                </tr>
                                <tr class="expanded-row">
                                    <td colspan="9">
                                        <div class="expanded-content">
                                            <div class="info-group">
                                                <h4>Informations techniques</h4>
                                                <?php if ($materiel['numero_serie']): ?>
                                                    <p><strong>Numéro de série:</strong> <?= h($materiel['numero_serie']) ?></p>
                                                <?php endif; ?>
                                                <?php if ($materiel['marque']): ?>
                                                    <p><strong>Marque:</strong> <?= h($materiel['marque']) ?></p>
                                                <?php endif; ?>
                                                <?php if ($materiel['modele']): ?>
                                                    <p><strong>Modèle:</strong> <?= h($materiel['modele']) ?></p>
                                                <?php endif; ?>
                                                <?php if ($materiel['adresse_ip']): ?>
                                                    <p><strong>IP:</strong> <?= h($materiel['adresse_ip']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="info-group">
                                                <h4>Localisation</h4>
                                                <p><strong>Client:</strong> <?= h($materiel['client_nom'] ?? 'N/A') ?></p>
                                                <p><strong>Site:</strong> <?= h($materiel['site_nom'] ?? 'N/A') ?></p>
                                                <p><strong>Salle:</strong> <?= h($materiel['salle_nom'] ?? 'N/A') ?></p>
                                            </div>
                                            <div class="info-group">
                                                <h4>Documents</h4>
                                                <p>📎 <?= $pieces_jointes_count[$materiel['id']] ?? 0 ?> document(s) disponible(s)</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #999;">
                <p>Sélectionnez une salle pour afficher le matériel et la documentation</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Gestion des filtres client/site/salle
    document.getElementById('clientFilter').addEventListener('change', function(e) {
        const clientId = e.target.value;
        const siteFilter = document.getElementById('siteFilter');
        const roomFilter = document.getElementById('roomFilter');
        
        if (clientId) {
            // Charger les sites du client
            fetch('<?= BASE_URL ?>materiel_test/get_sites?client_id=' + clientId)
                .then(response => response.json())
                .then(sites => {
                    siteFilter.innerHTML = '<option value="">Tous les sites</option>';
                    sites.forEach(site => {
                        siteFilter.innerHTML += `<option value="${site.id}">${site.name}</option>`;
                    });
                    siteFilter.disabled = false;
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des sites:', error);
                });
        } else {
            siteFilter.innerHTML = '<option value="">Tous les sites</option>';
            siteFilter.disabled = true;
            roomFilter.innerHTML = '<option value="">Toutes les salles</option>';
            roomFilter.disabled = true;
        }
        
        // Recharger la page avec le nouveau filtre
        const url = new URL(window.location);
        if (clientId) {
            url.searchParams.set('client_id', clientId);
        } else {
            url.searchParams.delete('client_id');
        }
        url.searchParams.delete('site_id');
        url.searchParams.delete('salle_id');
        window.location.href = url.toString();
    });

    document.getElementById('siteFilter').addEventListener('change', function(e) {
        const siteId = e.target.value;
        const roomFilter = document.getElementById('roomFilter');
        
        if (siteId) {
            // Charger les salles du site
            fetch('<?= BASE_URL ?>materiel_test/get_rooms?site_id=' + siteId)
                .then(response => response.json())
                .then(salles => {
                    roomFilter.innerHTML = '<option value="">Toutes les salles</option>';
                    salles.forEach(salle => {
                        roomFilter.innerHTML += `<option value="${salle.id}">${salle.name}</option>`;
                    });
                    roomFilter.disabled = false;
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des salles:', error);
                });
        } else {
            roomFilter.innerHTML = '<option value="">Toutes les salles</option>';
            roomFilter.disabled = true;
        }
        
        // Recharger la page avec le nouveau filtre
        const url = new URL(window.location);
        const clientId = document.getElementById('clientFilter').value;
        if (clientId) {
            url.searchParams.set('client_id', clientId);
        }
        if (siteId) {
            url.searchParams.set('site_id', siteId);
        } else {
            url.searchParams.delete('site_id');
        }
        url.searchParams.delete('salle_id');
        window.location.href = url.toString();
    });

    document.getElementById('roomFilter').addEventListener('change', function(e) {
        const roomId = e.target.value;
        const url = new URL(window.location);
        const clientId = document.getElementById('clientFilter').value;
        const siteId = document.getElementById('siteFilter').value;
        
        // Construire l'URL avec tous les paramètres
        url.search = ''; // Réinitialiser les paramètres
        if (clientId) {
            url.searchParams.set('client_id', clientId);
        }
        if (siteId) {
            url.searchParams.set('site_id', siteId);
        }
        if (roomId) {
            url.searchParams.set('salle_id', roomId);
        } else {
            url.searchParams.delete('salle_id');
        }
        
        // Recharger la page
        window.location.href = url.toString();
    });

    function toggleRow(row) {
        const expandedRow = row.nextElementSibling;
        const arrow = row.querySelector('td:first-child');
        
        if (expandedRow.classList.contains('show')) {
            expandedRow.classList.remove('show');
            arrow.textContent = '▶';
        } else {
            expandedRow.classList.add('show');
            arrow.textContent = '▼';
        }
    }

    function toggleRoomDocs() {
        const content = document.getElementById('roomDocsContent');
        const icon = document.getElementById('roomDocsToggleIcon');
        const btn = event.target.closest('button');
        
        if (content.classList.contains('collapsed')) {
            content.classList.remove('collapsed');
            icon.textContent = '▼';
            btn.innerHTML = '<span id="roomDocsToggleIcon">▼</span> Masquer';
        } else {
            content.classList.add('collapsed');
            icon.textContent = '▶';
            btn.innerHTML = '<span id="roomDocsToggleIcon">▶</span> Afficher';
        }
    }

    // Filtrage des documents par catégorie
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('doc-filter-btn')) {
            // Retirer l'état actif de tous les boutons
            document.querySelectorAll('.doc-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Activer le bouton cliqué
            e.target.classList.add('active');
            
            const category = e.target.dataset.category;
            const categories = document.querySelectorAll('.docs-category');
            
            if (category === 'all') {
                // Afficher toutes les catégories
                categories.forEach(cat => cat.style.display = 'block');
            } else {
                // Masquer toutes les catégories
                categories.forEach(cat => cat.style.display = 'none');
                
                // Afficher uniquement les cartes de la catégorie sélectionnée
                const matchingCards = document.querySelectorAll(`.doc-card[data-category="${category}"]`);
                if (matchingCards.length > 0) {
                    // Trouver la catégorie parente et l'afficher
                    matchingCards[0].closest('.docs-category').style.display = 'block';
                }
            }
        }
    });

    // Recherche dans les documents
    document.getElementById('docsSearch')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const allCards = document.querySelectorAll('.doc-card');
        const categories = document.querySelectorAll('.docs-category');
        
        if (searchTerm === '') {
            // Réinitialiser : tout afficher
            allCards.forEach(card => card.style.display = 'flex');
            categories.forEach(cat => cat.style.display = 'block');
        } else {
            // Filtrer les documents
            allCards.forEach(card => {
                const docName = card.dataset.name || '';
                if (docName.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Masquer les catégories vides
            categories.forEach(cat => {
                const visibleCards = cat.querySelectorAll('.doc-card[style*="display: flex"]');
                if (visibleCards.length === 0) {
                    cat.style.display = 'none';
                } else {
                    cat.style.display = 'block';
                }
            });
        }
    });

    // Recherche dans le matériel
    document.getElementById('materielSearch')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr.expandable');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
