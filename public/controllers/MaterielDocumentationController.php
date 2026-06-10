<?php
// Pas de require_once ici - les modèles sont déjà chargés dans index.php

class MaterielDocumentationController
{
    private $materielModel;
    private $documentationModel;
    private $clientModel;
    private $siteModel;
    private $salleModel;
    private $buildingModel;

    public function __construct($db)
    {
        $this->materielModel = new MaterielModel($db);
        $this->documentationModel = new DocumentationModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->salleModel = new RoomModel($db);
        $this->buildingModel = new BuildingModel($db);
    }

    public function index()
    {
        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'building_id' => $_GET['building_id'] ?? null,
            'salle_id' => $_GET['salle_id'] ?? null
        ];

        // Récupérer les listes
        $materiel_list = $this->materielModel->getAllMateriel($filters);
        $documentation_list = $this->documentationModel->getAllWithFilters($filters);

        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClients();

        // Récupérer les sites (si un client est sélectionné)
        $sites = [];
        if (!empty($filters['client_id'])) {
            $sites = $this->siteModel->getSitesByClientId($filters['client_id']);
        }

        // Récupérer les bâtiments (si un site est sélectionné)
        $buildings = [];
        if (!empty($filters['site_id'])) {
            // Utiliser la méthode getBuildingsBySiteId du BuildingModel
            if (method_exists($this->buildingModel, 'getBuildingsBySiteId')) {
                $buildings = $this->buildingModel->getBuildingsBySiteId($filters['site_id']);
            } elseif (method_exists($this->buildingModel, 'getBySite')) {
                $buildings = $this->buildingModel->getBySite($filters['site_id']);
            }
        }

        // Récupérer les salles selon le filtre
        $salles = [];
        if (!empty($filters['building_id'])) {
            // Récupérer les salles par bâtiment
            if (method_exists($this->salleModel, 'getRoomsByBuildingId')) {
                $salles = $this->salleModel->getRoomsByBuildingId($filters['building_id']);
            } elseif (method_exists($this->salleModel, 'getByBuildingId')) {
                $salles = $this->salleModel->getByBuildingId($filters['building_id']);
            }
        } elseif (!empty($filters['site_id'])) {
            // Récupérer les salles par site
            if (method_exists($this->salleModel, 'getRoomsBySiteId')) {
                $salles = $this->salleModel->getRoomsBySiteId($filters['site_id']);
            } elseif (method_exists($this->salleModel, 'getBySiteId')) {
                $salles = $this->salleModel->getBySiteId($filters['site_id']);
            }
        }

        // Compter les pièces jointes
        $pieces_jointes_count = [];
        if (!empty($materiel_list)) {
            foreach ($materiel_list as $materiel) {
                $pieces_jointes_count[$materiel['id']] = $this->materielModel->getPiecesJointesCount($materiel['id']);
            }
        }

        // Inclure la vue
        extract([
            'materiel_list' => $materiel_list,
            'documentation_list' => $documentation_list,
            'clients' => $clients,
            'sites' => $sites,
            'buildings' => $buildings,
            'salles' => $salles,
            'filters' => $filters,
            'pieces_jointes_count' => $pieces_jointes_count,
            'materielModel' => $this->materielModel
        ]);

        // Utiliser le chemin absolu vers la vue
        $viewPath = __DIR__ . '/../views/materiel_documentation/index.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            die('Vue non trouvée : ' . $viewPath);
        }
    }
}