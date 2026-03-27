<?php
/**
 * Fonctions de génération des breadcrumbs (fil d'Ariane)
 * Génération automatique et personnalisée des breadcrumbs
 */

/**
 * Génère les breadcrumbs (fil d'Ariane) basés sur l'URL et la page actuelle
 * @param array $customBreadcrumbs Tableau optionnel de breadcrumbs personnalisés [['label' => '...', 'url' => '...'], ...]
 * @return array Tableau de breadcrumbs
 */
function generateBreadcrumbs($customBreadcrumbs = []) {
    global $pageTitle, $currentPageName;
    
    $breadcrumbs = [];
    
    // Toujours commencer par le dashboard
    $breadcrumbs[] = [
        'label' => '<i class="bi bi-house me-1"></i>Tableau de bord',
        'url' => BASE_URL . 'dashboard'
    ];
    
    // Vérifier si des breadcrumbs personnalisés sont définis via variable globale
    if (empty($customBreadcrumbs) && isset($GLOBALS['customBreadcrumbs']) && !empty($GLOBALS['customBreadcrumbs'])) {
        $customBreadcrumbs = $GLOBALS['customBreadcrumbs'];
    }
    
    // Si des breadcrumbs personnalisés sont fournis, les utiliser
    if (!empty($customBreadcrumbs)) {
        $breadcrumbs = array_merge($breadcrumbs, $customBreadcrumbs);
        return $breadcrumbs;
    }
    
    // Sinon, générer automatiquement basé sur l'URL
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
    $basePath = parse_url(BASE_URL, PHP_URL_PATH);
    $relativePath = $basePath ? str_replace($basePath, '', $currentPath) : $currentPath;
    $relativePath = trim($relativePath, '/');
    $pathParts = explode('/', $relativePath);
    
    // Mapping des routes vers les labels
    $routeLabels = [
        'dashboard' => 'Tableau de bord',
        'interventions' => 'Interventions',
        'interventions_client' => 'Mes interventions',
        'contracts' => 'Contrats',
        'contracts_client' => 'Mes contrats',
        'clients' => 'Clients',
        'client' => 'Client',
        'materiel' => 'Matériel',
        'materiel_client' => 'Mon matériel',
        'documentation' => 'Documentation',
        'documentation_client' => 'Mes documents',
        'users' => 'Utilisateurs',
        'user' => 'Utilisateur',
        'settings' => 'Paramètres',
        'profile' => 'Profil',
        'profile_client' => 'Mon profil',
        'add' => 'Ajouter',
        'edit' => 'Modifier',
        'view' => 'Détails'
    ];
    
    $currentPath = '';
    foreach ($pathParts as $index => $part) {
        if (empty($part) || $part === 'index.php') continue;
        
        $currentPath .= ($currentPath ? '/' : '') . $part;
        $label = $routeLabels[$part] ?? ucfirst(str_replace('_', ' ', $part));
        
        // Ne pas ajouter le dernier élément s'il correspond au titre de la page
        if ($index === count($pathParts) - 1 && $label === $pageTitle) {
            continue;
        }
        
        $breadcrumbs[] = [
            'label' => $label,
            'url' => BASE_URL . $currentPath
        ];
    }
    
    // Ajouter le titre de la page comme dernier élément (actif)
    if (!empty($pageTitle) && $pageTitle !== 'Videosonic') {
        $breadcrumbs[] = [
            'label' => $pageTitle,
            'url' => null, // null = élément actif (pas de lien)
            'active' => true
        ];
    }
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour la vue client
 * Format: Tableau de bord - Clients - Nom du client
 * @param array $client Tableau contenant les informations du client (name, id)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateClientViewBreadcrumbs($client) {
    $breadcrumbs = [];
    
    // Clients (liste)
    $breadcrumbs[] = [
        'label' => 'Clients',
        'url' => BASE_URL . 'clients'
    ];
    
    // Nom du client (actif) - utiliser le nom ou un fallback
    $clientName = !empty($client['name']) ? h($client['name']) : 'Client #' . ($client['id'] ?? '');
    $breadcrumbs[] = [
        'label' => $clientName,
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour la vue contrat
 * Format: Tableau de bord - Liste des contrats - Nom du client - Nom du contrat
 * @param array $contract Tableau contenant les informations du contrat (client_name, name, id, client_id)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateContractViewBreadcrumbs($contract) {
    $breadcrumbs = [];
    
    // Liste des contrats
    $breadcrumbs[] = [
        'label' => 'Liste des contrats',
        'url' => BASE_URL . 'contracts'
    ];
    
    // Nom du client (si disponible)
    if (!empty($contract['client_name'])) {
        $clientId = $contract['client_id'] ?? null;
        if ($clientId) {
            $breadcrumbs[] = [
                'label' => h($contract['client_name']),
                'url' => BASE_URL . 'clients/view/' . $clientId
            ];
        } else {
            $breadcrumbs[] = [
                'label' => h($contract['client_name']),
                'url' => null
            ];
        }
    }
    
    // Nom du contrat (actif) - utiliser le nom ou un fallback
    $contractName = !empty($contract['name']) ? h($contract['name']) : 'Contrat #' . ($contract['id'] ?? '');
    $breadcrumbs[] = [
        'label' => $contractName,
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour l'ajout de site
 * Format: Tableau de bord - Clients - Nom du client - Ajouter un site
 * @param array $client Tableau contenant les informations du client (name, id)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateSiteAddBreadcrumbs($client) {
    $breadcrumbs = [];
    
    // Clients (liste)
    $breadcrumbs[] = [
        'label' => 'Clients',
        'url' => BASE_URL . 'clients'
    ];
    
    // Nom du client
    if (!empty($client['name'])) {
        $breadcrumbs[] = [
            'label' => h($client['name']),
            'url' => BASE_URL . 'clients/view/' . ($client['id'] ?? '')
        ];
    }
    
    // Ajouter un site (actif)
    $breadcrumbs[] = [
        'label' => 'Ajouter un site',
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour l'ajout de salle
 * Format: Tableau de bord - Clients - Nom du client - Nom du site - Ajouter une salle
 * @param array $client Tableau contenant les informations du client (name, id)
 * @param array|null $site Tableau contenant les informations du site (name, id) ou null
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateRoomAddBreadcrumbs($client, $site = null) {
    $breadcrumbs = [];
    
    // Clients (liste)
    $breadcrumbs[] = [
        'label' => 'Clients',
        'url' => BASE_URL . 'clients'
    ];
    
    // Nom du client
    if (!empty($client['name'])) {
        $breadcrumbs[] = [
            'label' => h($client['name']),
            'url' => BASE_URL . 'clients/view/' . ($client['id'] ?? '')
        ];
    }
    
    // Nom du site (si disponible)
    if (!empty($site) && !empty($site['name'])) {
        $breadcrumbs[] = [
            'label' => h($site['name']),
            'url' => BASE_URL . 'clients/view/' . ($client['id'] ?? '') . '?active_tab=sites-tab#sites'
        ];
    }
    
    // Ajouter une salle (actif)
    $breadcrumbs[] = [
        'label' => 'Ajouter une salle',
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour l'ajout de contact
 * Format: Tableau de bord - Clients - Nom du client - Ajouter un contact
 * @param array $client Tableau contenant les informations du client (name, id)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateContactAddBreadcrumbs($client) {
    $breadcrumbs = [];
    
    // Clients (liste)
    $breadcrumbs[] = [
        'label' => 'Clients',
        'url' => BASE_URL . 'clients'
    ];
    
    // Nom du client
    if (!empty($client['name'])) {
        $breadcrumbs[] = [
            'label' => h($client['name']),
            'url' => BASE_URL . 'clients/view/' . ($client['id'] ?? '')
        ];
    }
    
    // Ajouter un contact (actif)
    $breadcrumbs[] = [
        'label' => 'Ajouter un contact',
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour l'ajout de contrat
 * Format: Tableau de bord - Clients - Nom du client - Ajouter un contrat
 * @param array|null $client Tableau contenant les informations du client (name, id) ou null
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateContractAddBreadcrumbs($client) {
    $breadcrumbs = [];
    
    // Clients (liste)
    $breadcrumbs[] = [
        'label' => 'Clients',
        'url' => BASE_URL . 'clients'
    ];
    
    // Nom du client (si disponible)
    if (!empty($client) && !empty($client['name'])) {
        $breadcrumbs[] = [
            'label' => h($client['name']),
            'url' => BASE_URL . 'clients/view/' . ($client['id'] ?? '')
        ];
    }
    
    // Ajouter un contrat (actif)
    $breadcrumbs[] = [
        'label' => 'Ajouter un contrat',
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour la vue intervention
 * Format: Tableau de bord - Interventions - Nom du client - Référence de l'intervention
 * @param array $intervention Tableau contenant les informations de l'intervention (client_name, client_id, reference, id)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateInterventionViewBreadcrumbs($intervention) {
    $breadcrumbs = [];
    
    // Interventions (liste)
    $breadcrumbs[] = [
        'label' => 'Interventions',
        'url' => BASE_URL . 'interventions'
    ];
    
    // Nom du client (si disponible)
    if (!empty($intervention['client_name']) && !empty($intervention['client_id'])) {
        $breadcrumbs[] = [
            'label' => h($intervention['client_name']),
            'url' => BASE_URL . 'clients/view/' . $intervention['client_id']
        ];
    }
    
    // Référence de l'intervention (actif) - utiliser la référence ou un fallback
    $interventionLabel = !empty($intervention['reference']) ? h($intervention['reference']) : 'Intervention #' . ($intervention['id'] ?? '');
    $breadcrumbs[] = [
        'label' => $interventionLabel,
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour l'édition d'intervention
 * Format: Tableau de bord - Interventions - Nom du client - Référence de l'intervention - Modifier
 * @param array $intervention Tableau contenant les informations de l'intervention (client_name, client_id, reference, id)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateInterventionEditBreadcrumbs($intervention) {
    $breadcrumbs = [];
    
    // Interventions (liste)
    $breadcrumbs[] = [
        'label' => 'Interventions',
        'url' => BASE_URL . 'interventions'
    ];
    
    // Nom du client (si disponible)
    if (!empty($intervention['client_name']) && !empty($intervention['client_id'])) {
        $breadcrumbs[] = [
            'label' => h($intervention['client_name']),
            'url' => BASE_URL . 'clients/view/' . $intervention['client_id']
        ];
    }
    
    // Référence de l'intervention (lien vers la vue)
    $interventionLabel = !empty($intervention['reference']) ? h($intervention['reference']) : 'Intervention #' . ($intervention['id'] ?? '');
    $breadcrumbs[] = [
        'label' => $interventionLabel,
        'url' => BASE_URL . 'interventions/view/' . ($intervention['id'] ?? '')
    ];
    
    // Modifier (actif)
    $breadcrumbs[] = [
        'label' => 'Modifier',
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour les pages interventions curatives/préventives
 * Format: Tableau de bord - Interventions - Curatives/Préventives
 * @param bool $isPreventive true si page préventives, false si page curatives
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateInterventionsListBreadcrumbs($isPreventive = false) {
    $breadcrumbs = [];
    
    // Interventions (liste générale)
    $breadcrumbs[] = [
        'label' => 'Interventions',
        'url' => BASE_URL . 'interventions/curatives'
    ];
    
    // Type d'intervention (actif)
    $typeLabel = $isPreventive ? 'Préventives' : 'Curatives';
    $breadcrumbs[] = [
        'label' => $typeLabel,
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour l'ajout d'intervention
 * Format: Tableau de bord - Interventions - [Nom du client] - Ajouter une intervention
 * @param array|null $client Tableau contenant les informations du client (name, id) ou null
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateInterventionAddBreadcrumbs($client = null) {
    $breadcrumbs = [];
    
    // Interventions (liste)
    $breadcrumbs[] = [
        'label' => 'Interventions',
        'url' => BASE_URL . 'interventions'
    ];
    
    // Nom du client (si disponible)
    if (!empty($client) && !empty($client['name'])) {
        $breadcrumbs[] = [
            'label' => h($client['name']),
            'url' => BASE_URL . 'clients/view/' . ($client['id'] ?? '')
        ];
    }
    
    // Ajouter une intervention (actif)
    $breadcrumbs[] = [
        'label' => 'Ajouter une intervention',
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour la vue matériel
 * Format: Tableau de bord - Matériel - Nom du client - Nom du matériel
 * @param array $materiel Tableau contenant les informations du matériel (client_nom, client_id, marque, modele)
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateMaterielViewBreadcrumbs($materiel) {
    $breadcrumbs = [];
    
    // Matériel (liste)
    $breadcrumbs[] = [
        'label' => 'Matériel',
        'url' => BASE_URL . 'materiel'
    ];
    
    // Nom du client (si disponible)
    if (!empty($materiel['client_nom']) && !empty($materiel['client_id'])) {
        $breadcrumbs[] = [
            'label' => h($materiel['client_nom']),
            'url' => BASE_URL . 'clients/view/' . $materiel['client_id']
        ];
    }
    
    // Nom du matériel (actif) - utiliser marque + modèle ou un fallback
    $materielName = '';
    if (!empty($materiel['modele']) && !empty($materiel['marque'])) {
        $materielName = h($materiel['modele']) . ' - ' . h($materiel['marque']);
    } elseif (!empty($materiel['marque'])) {
        $materielName = h($materiel['marque']);
    } elseif (!empty($materiel['modele'])) {
        $materielName = h($materiel['modele']);
    } elseif (!empty($materiel['equipement'])) {
        $materielName = h($materiel['equipement']);
    } else {
        $materielName = 'Matériel #' . ($materiel['id'] ?? '');
    }
    
    $breadcrumbs[] = [
        'label' => $materielName,
        'url' => null,
        'active' => true
    ];
    
    return $breadcrumbs;
}

/**
 * Génère les breadcrumbs personnalisés pour la page matériel index
 * Format: Matériel - [Client] - [Site] - [Salle] (selon les filtres sélectionnés)
 * @param array $filters Tableau contenant les filtres (client_id, site_id, salle_id)
 * @param array $clients Liste des clients
 * @param array $sites Liste des sites
 * @param array $salles Liste des salles
 * @return array Tableau de breadcrumbs personnalisés
 */
function generateMaterielIndexBreadcrumbs($filters, $clients = [], $sites = [], $salles = []) {
    $breadcrumbs = [];
    
    // Si aucun filtre n'est sélectionné, retourner un tableau vide
    if (empty($filters['client_id']) && empty($filters['site_id']) && empty($filters['salle_id'])) {
        return $breadcrumbs;
    }
    
    // Client (si sélectionné)
    if (!empty($filters['client_id'])) {
        $clientName = null;
        foreach ($clients as $client) {
            if ($client['id'] == $filters['client_id']) {
                $clientName = $client['name'];
                break;
            }
        }
        if ($clientName) {
            // Si c'est le seul filtre ou si aucun site/salle n'est sélectionné, le client est actif
            $isActive = empty($filters['site_id']) && empty($filters['salle_id']);
            $breadcrumbs[] = [
                'label' => h($clientName),
                'url' => $isActive ? null : BASE_URL . 'clients/view/' . $filters['client_id'],
                'active' => $isActive
            ];
        }
    }
    
    // Site (si sélectionné)
    if (!empty($filters['site_id'])) {
        $siteName = null;
        foreach ($sites as $site) {
            if ($site['id'] == $filters['site_id']) {
                $siteName = $site['name'];
                break;
            }
        }
        if ($siteName) {
            // Si aucune salle n'est sélectionnée, le site est actif
            $isActive = empty($filters['salle_id']);
            // Construire l'URL avec les filtres précédents
            $url = null;
            if (!$isActive) {
                $url = BASE_URL . 'materiel?client_id=' . $filters['client_id'];
                if (!empty($filters['site_id'])) {
                    $url .= '&site_id=' . $filters['site_id'];
                }
            }
            $breadcrumbs[] = [
                'label' => h($siteName),
                'url' => $url,
                'active' => $isActive
            ];
        }
    }
    
    // Salle (si sélectionnée) - toujours actif
    if (!empty($filters['salle_id'])) {
        $salleName = null;
        foreach ($salles as $salle) {
            if ($salle['id'] == $filters['salle_id']) {
                $salleName = $salle['name'];
                break;
            }
        }
        if ($salleName) {
            $breadcrumbs[] = [
                'label' => h($salleName),
                'url' => null,
                'active' => true
            ];
        }
    }
    
    return $breadcrumbs;
}
