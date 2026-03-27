<?php
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class SiteClientController {
    use AccessControlTrait;
    private $db;
    private $siteModel;
    private $roomModel;
    private $clientModel;

    public function __construct($db = null) {
        global $db;
        $this->db = $db ?? $db;
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }


    /**
     * Affiche la liste des sites et salles du client
     */
    public function index() {
        $this->checkClientAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocationsFormatted();
        // Fallback: si aucune localisation explicite n'est définie, donner accès à tout le client de l'utilisateur
        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (empty($userLocations) && $clientId) {
            // Interprétation attendue par le contrôleur: tableau vide = accès complet au client
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
            
            // Pour chaque site, récupérer les salles
            foreach ($sites as &$site) {
                $site['rooms'] = $this->getRoomsBySiteAndLocations($site['id'], $userLocations);
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
        $pageTitle = 'Mes Sites et Salles';

        // Inclure la vue
        require_once __DIR__ . '/../views/client_client/index.php';
    }

    /**
     * Affiche les détails d'un site spécifique
     */
    public function view($siteId) {
        $this->checkClientAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocationsFormatted();
        // Fallback: si aucune localisation explicite n'est définie, donner accès à tout le client de l'utilisateur
        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (empty($userLocations) && $clientId) {
            // Interprétation attendue par le contrôleur: tableau vide = accès complet au client
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

            // Récupérer les salles du site
            $rooms = $this->getRoomsBySiteAndLocations($siteId, $userLocations);

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
     * Récupère les salles d'un site selon les localisations autorisées
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations) {
        try {
            // Vérifier que l'utilisateur a accès à ce site
            if (!$this->hasAccessToSite($siteId, $userLocations)) {
                return [];
            }

            // Récupérer les salles du site
            $rooms = $this->roomModel->getRoomsBySiteId($siteId);
            
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

    /**
     * Récupère les sites selon les localisations autorisées
     */
    private function getSitesByLocations($userLocations) {
        try {
            $sites = [];
            
            foreach ($userLocations as $clientId => $locations) {
                // Récupérer tous les sites du client
                $clientSites = $this->siteModel->getSitesByClientId($clientId);

                // Déterminer si l'utilisateur a un accès complet au client
                $fullClientAccess = empty($locations);

                if (!$fullClientAccess) {
                    foreach ($locations as $location) {
                        $siteIdVal = $location['site_id'] ?? null;
                        $roomIdVal = $location['room_id'] ?? null;
                        if ($siteIdVal === null && $roomIdVal === null) {
                            $fullClientAccess = true;
                            break;
                        }
                    }
                }

                // Construire un ensemble d'ID de sites autorisés à partir des localisations
                $allowedSiteIds = [];
                if (!$fullClientAccess) {
                    foreach ($locations as $location) {
                        if (!empty($location['site_id'])) {
                            $allowedSiteIds[(int)$location['site_id']] = true;
                        } elseif (!empty($location['room_id'])) {
                            // Récupérer le site de la salle
                            $room = $this->roomModel->getRoomById((int)$location['room_id']);
                            if ($room && !empty($room['site_id'])) {
                                $allowedSiteIds[(int)$room['site_id']] = true;
                            }
                        }
                    }
                }

                // Filtrer la liste des sites selon l'accès
                foreach ($clientSites as $site) {
                    if ($fullClientAccess) {
                        $sites[] = $site;
                        continue;
                    }

                    if (isset($allowedSiteIds[(int)$site['id']])) {
                        $sites[] = $site;
                    }
                }
            }

            return $sites;

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des sites : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Vérifie si l'utilisateur a accès à un site spécifique
     */
    private function hasAccessToSite($siteId, $userLocations) {
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
                            $locRoomId = $location['room_id'] ?? null;

                            // Accès complet au client si site_id et room_id sont null
                            if ($locSiteId === null && $locRoomId === null) {
                                return true;
                            }

                            // Accès direct au site
                            if ($locSiteId !== null && (int)$locSiteId === (int)$siteId) {
                                return true;
                            }

                            // Accès via une salle appartenant à ce site
                            if ($locRoomId !== null) {
                                $room = $this->roomModel->getRoomById((int)$locRoomId);
                                if ($room && (int)$room['site_id'] === (int)$siteId) {
                                    return true;
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
     * Vérifie si l'utilisateur a accès à une salle spécifique
     */
    private function hasAccessToRoom($roomId, $userLocations) {
        try {
            $room = $this->roomModel->getRoomById($roomId);
            if (!$room) {
                return false;
            }

            // Vérifier l'accès au site de la salle
            return $this->hasAccessToSite($room['site_id'], $userLocations);

        } catch (Exception $e) {
            custom_log("Erreur lors de la vérification d'accès à la salle : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
?>
