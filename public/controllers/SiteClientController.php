<?php
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/BuildingModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class SiteClientController
{
    use AccessControlTrait;
    private $db;
    private $siteModel;
    private $buildingModel;
    private $roomModel;
    private $clientModel;

    public function __construct($db = null)
    {
        global $db;
        $this->db = $db ?? $db;
        $this->siteModel = new SiteModel($this->db);
        $this->buildingModel = new BuildingModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }

    /**
     * Affiche la liste des sites, bâtiments et salles du client
     */
    public function index()
    {
        $this->checkClientAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocationsFormatted();

        // Fallback: si aucune localisation explicite n'est définie, donner accès à tout le client de l'utilisateur
        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (empty($userLocations) && $clientId) {
            $userLocations = [
                $clientId => []
            ];
        }

        // Debug
        custom_log('SiteClientController::index - DB set: ' . (!empty($this->db) ? 'yes' : 'no'), 'DEBUG');
        custom_log('SiteClientController::index - clientId from session: ' . ($clientId ?? 'null'), 'DEBUG');
        custom_log('SiteClientController::index - userLocations: ' . json_encode($userLocations), 'DEBUG');

        try {
            // Récupération des sites selon les localisations autorisées
            $sites = $this->getSitesByLocations($userLocations);
            if (!empty($sites)) {
                $uniqueSites = [];
                foreach ($sites as $site) {
                    if (!isset($uniqueSites[$site['id']])) {
                        $uniqueSites[$site['id']] = $site;
                    }
                }
                $sites = array_values($uniqueSites);
                custom_log('SiteClientController::index - sites après dédoublonnage: ' . json_encode(array_column($sites, 'id')), 'DEBUG');
            }

            foreach ($sites as $index => $site) {
                $sites[$index]['buildings'] = $this->getBuildingsBySiteAndLocations($site['id'], $userLocations);
                if (!empty($sites[$index]['buildings'])) {
                    foreach ($sites[$index]['buildings'] as $buildingIndex => $building) {
                        $sites[$index]['buildings'][$buildingIndex]['rooms'] = $this->getRoomsByBuildingAndLocations($building['id'], $userLocations);
                    }
                }
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $sites = [];
            custom_log("Erreur lors du chargement des sites client : " . $e->getMessage(), 'ERROR');
        }

        // Debug résultat
        custom_log('SiteClientController::index - sites count: ' . (is_array($sites) ? count($sites) : 0), 'DEBUG');

        // Définir la page courante pour le menu
        $currentPage = 'sites_client';
        $pageTitle = 'Mes Sites, Bâtiments et Salles';

        // Inclure la vue
        require_once __DIR__ . '/../views/client_client/index.php';
    }

    /**
     * Affiche les détails d'un site spécifique
     */
    public function view($siteId)
    {
        $this->checkClientAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocationsFormatted();

        // Fallback: si aucune localisation explicite n'est définie, donner accès à tout le client de l'utilisateur
        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (empty($userLocations) && $clientId) {
            $userLocations = [
                $clientId => []
            ];
        }

        try {
            // Vérifier que l'utilisateur a accès à ce site
            if (!$this->hasAccessToSite($siteId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas accès à ce site.";
                header('Location: ' . BASE_URL . 'sites_client');
                exit;
            }

            // Récupérer les détails du site
            $site = $this->siteModel->getSiteById($siteId);
            if (!$site) {
                $_SESSION['error'] = "Site non trouvé.";
                header('Location: ' . BASE_URL . 'sites_client');
                exit;
            }

            // Récupérer les bâtiments du site
            $buildings = $this->getBuildingsBySiteAndLocations($siteId, $userLocations);

            // Pour chaque bâtiment, récupérer les salles
            foreach ($buildings as &$building) {
                $building['rooms'] = $this->getRoomsByBuildingAndLocations($building['id'], $userLocations);
            }

        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors du chargement du site.";
            header('Location: ' . BASE_URL . 'sites_client');
            exit;
        }

        // Définir la page courante pour le menu
        $currentPage = 'sites_client';
        $pageTitle = 'Détails du Site';

        // Inclure la vue
        require_once __DIR__ . '/../views/client_client/view.php';
    }

    /**
     * Affiche les détails d'un bâtiment spécifique
     */
    public function viewBuilding($buildingId)
    {
        $this->checkClientAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocationsFormatted();

        // Fallback: si aucune localisation explicite n'est définie, donner accès à tout le client de l'utilisateur
        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (empty($userLocations) && $clientId) {
            $userLocations = [
                $clientId => []
            ];
        }

        try {
            // Vérifier que l'utilisateur a accès à ce bâtiment
            if (!$this->hasAccessToBuilding($buildingId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas accès à ce bâtiment.";
                header('Location: ' . BASE_URL . 'sites_client');
                exit;
            }

            // Récupérer les détails du bâtiment
            $building = $this->buildingModel->getBuildingById($buildingId);
            if (!$building) {
                $_SESSION['error'] = "Bâtiment non trouvé.";
                header('Location: ' . BASE_URL . 'sites_client');
                exit;
            }

            // Récupérer le site parent
            $site = $this->siteModel->getSiteById($building['site_id']);

            // Récupérer les salles du bâtiment
            $rooms = $this->getRoomsByBuildingAndLocations($buildingId, $userLocations);

        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors du chargement du bâtiment.";
            header('Location: ' . BASE_URL . 'sites_client');
            exit;
        }

        // Définir la page courante pour le menu
        $currentPage = 'sites_client';
        $pageTitle = 'Détails du Bâtiment';

        // Inclure la vue
        require_once __DIR__ . '/../views/client_client/view_building.php';
    }

    /**
     * Récupère les bâtiments d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Localisations autorisées
     * @return array Liste des bâtiments autorisés
     */
    public function getBuildingsBySiteAndLocations($siteId, $userLocations)
    {
        try {
            // Vérifier que l'utilisateur a accès à ce site
            if (!$this->hasAccessToSite($siteId, $userLocations)) {
                return [];
            }

            // Récupérer tous les bâtiments du site
            $buildings = $this->buildingModel->getBuildingsBySiteId($siteId);

            // Filtrer selon les localisations autorisées
            $filteredBuildings = [];
            foreach ($buildings as $building) {
                if ($this->hasAccessToBuilding($building['id'], $userLocations)) {
                    $filteredBuildings[] = $building;
                }
            }

            return $filteredBuildings;

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des bâtiments : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère les salles d'un bâtiment selon les localisations autorisées
     * @param int $buildingId ID du bâtiment
     * @param array $userLocations Localisations autorisées
     * @return array Liste des salles autorisées
     */
    public function getRoomsByBuildingAndLocations($buildingId, $userLocations)
    {
        try {
            // Vérifier que l'utilisateur a accès à ce bâtiment
            if (!$this->hasAccessToBuilding($buildingId, $userLocations)) {
                return [];
            }

            // Récupérer les salles du bâtiment
            $rooms = $this->roomModel->getRoomsByBuildingId($buildingId);

            // Filtrer selon les localisations autorisées
            $filteredRooms = [];
            foreach ($rooms as $room) {
                if ($this->hasAccessToRoom($room['id'], $userLocations)) {
                    $filteredRooms[] = $room;
                }
            }

            return $filteredRooms;

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des salles : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    private function getSitesByLocations($userLocations)
    {
        try {
            custom_log('getSitesByLocations - START', 'DEBUG');
            custom_log('getSitesByLocations - userLocations: ' . json_encode($userLocations), 'DEBUG');

            $sitesMap = [];

            // Normaliser le format des userLocations
            if (isset($userLocations[0]) && isset($userLocations[0]['client_id'])) {
                $grouped = [];
                foreach ($userLocations as $loc) {
                    $cid = $loc['client_id'];
                    if (!isset($grouped[$cid])) {
                        $grouped[$cid] = [];
                    }
                    $grouped[$cid][] = $loc;
                }
                $userLocations = $grouped;
                custom_log('getSitesByLocations - après normalisation: ' . json_encode($userLocations), 'DEBUG');
            }

            foreach ($userLocations as $clientId => $locations) {
                custom_log("getSitesByLocations - Traitement du clientId: $clientId", 'DEBUG');
                custom_log("getSitesByLocations - locations: " . json_encode($locations), 'DEBUG');

                $clientSites = $this->siteModel->getSitesByClientId($clientId);
                custom_log("getSitesByLocations - clientSites trouvés: " . count($clientSites), 'DEBUG');
                foreach ($clientSites as $s) {
                    custom_log("getSitesByLocations - Site: ID={$s['id']}, name={$s['name']}", 'DEBUG');
                }

                $fullClientAccess = empty($locations);
                custom_log("getSitesByLocations - fullClientAccess: " . ($fullClientAccess ? 'true' : 'false'), 'DEBUG');

                if (!$fullClientAccess && is_array($locations)) {
                    foreach ($locations as $loc) {
                        if (is_array($loc) && ($loc['site_id'] ?? null) === null && ($loc['building_id'] ?? null) === null && ($loc['room_id'] ?? null) === null) {
                            $fullClientAccess = true;
                            custom_log("getSitesByLocations - fullClientAccess devient true (accès client complet)", 'DEBUG');
                            break;
                        }
                    }
                }

                if ($fullClientAccess) {
                    custom_log("getSitesByLocations - Accès complet au client $clientId", 'DEBUG');
                    foreach ($clientSites as $site) {
                        $sitesMap[$site['id']] = $site;
                        custom_log("getSitesByLocations - Ajout du site {$site['id']} dans la map (accès complet)", 'DEBUG');
                    }
                } else {
                    $allowedSiteIds = [];
                    foreach ($locations as $loc) {
                        if (is_array($loc)) {
                            if (!empty($loc['site_id'])) {
                                $allowedSiteIds[(int) $loc['site_id']] = true;
                                custom_log("getSitesByLocations - Site autorisé directement: {$loc['site_id']}", 'DEBUG');
                            } elseif (!empty($loc['building_id'])) {
                                $building = $this->buildingModel->getBuildingById((int) $loc['building_id']);
                                if ($building && !empty($building['site_id'])) {
                                    $allowedSiteIds[(int) $building['site_id']] = true;
                                    custom_log("getSitesByLocations - Site autorisé via bâtiment {$loc['building_id']}: site_id={$building['site_id']}", 'DEBUG');
                                }
                            } elseif (!empty($loc['room_id'])) {
                                $room = $this->roomModel->getRoomById((int) $loc['room_id']);
                                if ($room && !empty($room['building_id'])) {
                                    $building = $this->buildingModel->getBuildingById((int) $room['building_id']);
                                    if ($building && !empty($building['site_id'])) {
                                        $allowedSiteIds[(int) $building['site_id']] = true;
                                        custom_log("getSitesByLocations - Site autorisé via salle {$loc['room_id']}: site_id={$building['site_id']}", 'DEBUG');
                                    }
                                }
                            }
                        }
                    }

                    custom_log("getSitesByLocations - allowedSiteIds: " . json_encode($allowedSiteIds), 'DEBUG');

                    foreach ($clientSites as $site) {
                        if (isset($allowedSiteIds[(int) $site['id']])) {
                            $sitesMap[$site['id']] = $site;
                            custom_log("getSitesByLocations - Ajout du site {$site['id']} dans la map (accès restreint)", 'DEBUG');
                        }
                    }
                }
            }

            $result = array_values($sitesMap);
            custom_log('getSitesByLocations - résultat FINAL: ' . json_encode(array_column($result, 'id')), 'DEBUG');
            return $result;

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des sites : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Vérifie si l'utilisateur a accès à un site spécifique
     * @param int $siteId ID du site
     * @param array $userLocations Localisations autorisées
     * @return bool true si autorisé
     */
    private function hasAccessToSite($siteId, $userLocations)
    {
        try {
            $site = $this->siteModel->getSiteById($siteId);
            if (!$site) {
                return false;
            }

            foreach ($userLocations as $clientId => $locations) {
                if ($site['client_id'] == $clientId) {
                    // Si des localisations spécifiques sont définies, vérifier l'accès
                    if (!empty($locations)) {
                        foreach ($locations as $location) {
                            $locSiteId = $location['site_id'] ?? null;
                            $locBuildingId = $location['building_id'] ?? null;
                            $locRoomId = $location['room_id'] ?? null;

                            // Accès complet au client si site_id, building_id et room_id sont null
                            if ($locSiteId === null && $locBuildingId === null && $locRoomId === null) {
                                return true;
                            }

                            // Accès direct au site
                            if ($locSiteId !== null && (int) $locSiteId === (int) $siteId) {
                                return true;
                            }

                            // Accès via un bâtiment appartenant à ce site
                            if ($locBuildingId !== null) {
                                $building = $this->buildingModel->getBuildingById((int) $locBuildingId);
                                if ($building && (int) $building['site_id'] === (int) $siteId) {
                                    return true;
                                }
                            }

                            // Accès via une salle appartenant à ce site
                            if ($locRoomId !== null) {
                                $room = $this->roomModel->getRoomById((int) $locRoomId);
                                if ($room && !empty($room['building_id'])) {
                                    $building = $this->buildingModel->getBuildingById((int) $room['building_id']);
                                    if ($building && (int) $building['site_id'] === (int) $siteId) {
                                        return true;
                                    }
                                }
                            }
                        }
                    } else {
                        // Accès complet au client
                        return true;
                    }
                }
            }

            return false;

        } catch (Exception $e) {
            custom_log("Erreur lors de la vérification d'accès au site : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur a accès à un bâtiment spécifique
     * @param int $buildingId ID du bâtiment
     * @param array $userLocations Localisations autorisées
     * @return bool true si autorisé
     */
    private function hasAccessToBuilding($buildingId, $userLocations)
    {
        try {
            $building = $this->buildingModel->getBuildingById($buildingId);
            if (!$building) {
                return false;
            }

            // Vérifier d'abord l'accès au site parent
            if (!$this->hasAccessToSite($building['site_id'], $userLocations)) {
                return false;
            }

            // Vérifier si l'utilisateur a un accès spécifique à ce bâtiment
            foreach ($userLocations as $clientId => $locations) {
                if (!empty($locations)) {
                    foreach ($locations as $location) {
                        $locBuildingId = $location['building_id'] ?? null;
                        $locRoomId = $location['room_id'] ?? null;

                        // Accès direct au bâtiment
                        if ($locBuildingId !== null && (int) $locBuildingId === (int) $buildingId) {
                            return true;
                        }

                        // Accès via une salle appartenant à ce bâtiment
                        if ($locRoomId !== null) {
                            $room = $this->roomModel->getRoomById((int) $locRoomId);
                            if ($room && (int) $room['building_id'] === (int) $buildingId) {
                                return true;
                            }
                        }
                    }
                } else {
                    // Accès complet au client
                    return true;
                }
            }

            return false;

        } catch (Exception $e) {
            custom_log("Erreur lors de la vérification d'accès au bâtiment : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur a accès à une salle spécifique
     * @param int $roomId ID de la salle
     * @param array $userLocations Localisations autorisées
     * @return bool true si autorisé
     */
    private function hasAccessToRoom($roomId, $userLocations)
    {
        try {
            $room = $this->roomModel->getRoomById($roomId);
            if (!$room) {
                return false;
            }

            // Vérifier l'accès au bâtiment de la salle
            return $this->hasAccessToBuilding($room['building_id'], $userLocations);

        } catch (Exception $e) {
            custom_log("Erreur lors de la vérification d'accès à la salle : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}