<?php
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/ContactModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class RoomController {
    use AccessControlTrait;
    private $db;
    private $roomModel;
    private $contactModel;
    private $siteModel;
    private $clientModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->roomModel = new RoomModel($this->db);
        $this->contactModel = new ContactModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }


    /**
     * Affiche le formulaire d'ajout d'une salle
     * Peut accepter soit un site_id (comportement classique) soit un client_id via GET
     */
    public function add($id) {
        $this->checkAccess();

        // Vérifier si on a un client_id dans les paramètres GET (mode sélection de site)
        $clientId = $_GET['client_id'] ?? null;
        $siteId = null;
        $site = null;
        $sites = [];
        $selectedSiteId = $_GET['site_id'] ?? null; // Site pré-sélectionné si on vient de la vue edit

        if ($clientId) {
            // Mode sélection de site : on vient de la vue client
            // Récupérer tous les sites du client
            $sites = $this->siteModel->getSitesByClientId($clientId);
            if (empty($sites)) {
                $_SESSION['error'] = "Aucun site trouvé pour ce client. Veuillez d'abord créer un site.";
                header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
                exit;
            }

            // Si un site est pré-sélectionné, l'utiliser
            if ($selectedSiteId) {
                $site = $this->siteModel->getSiteById($selectedSiteId);
                if ($site && $site['client_id'] == $clientId) {
                    $siteId = $selectedSiteId;
                }
            } else {
                // Si un seul site, le pré-sélectionner automatiquement mais garder $sites rempli
                // pour afficher la liste déroulante dans la vue
                if (count($sites) === 1) {
                    $site = $sites[0];
                    $siteId = $site['id'];
                }
            }
        } else {
            // Mode classique : l'ID est un site_id
            if (empty($id) || $id == 0) {
                $_SESSION['error'] = "Site non spécifié.";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }
            $siteId = $id;
            $site = $this->roomModel->getSiteById($siteId);
            if (!$site) {
                $_SESSION['error'] = "Site non trouvé.";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }
            $clientId = $site['client_id'];
            // Dans le mode classique, on n'a pas besoin de la liste des sites
            // mais on initialise $sites comme tableau vide pour éviter des erreurs dans la vue
            $sites = [];
        }

        // Vérifier si l'utilisateur a les droits de création
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une salle.";
            $returnTo = $_GET['return_to'] ?? 'edit';
            if ($returnTo === 'view') {
                header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
            } else {
                header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . ($siteId ? '?open_site_id=' . $siteId . '#sites' : '#sites'));
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Utiliser le site_id du formulaire
            $formSiteId = $_POST['site_id'] ?? $siteId;
            
            if (empty($formSiteId)) {
                $_SESSION['error'] = "Veuillez sélectionner un site.";
            } else {
                // Vérifier que le site existe et appartient au client
                $formSite = $this->siteModel->getSiteById($formSiteId);
                if (!$formSite || $formSite['client_id'] != $clientId) {
                    $_SESSION['error'] = "Site invalide.";
                } else {
                    $data = [
                        'site_id' => $formSiteId,
                        'name' => $_POST['name'] ?? '',
                        'comment' => $_POST['comment'] ?? '',
                        'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                        'status' => 1
                    ];

                    if ($this->roomModel->createRoom($data)) {
                        $_SESSION['success'] = "Salle ajoutée avec succès.";
                        
                        // Gérer le retour intelligent
                        $returnTo = $_GET['return_to'] ?? 'edit';
                        if ($returnTo === 'view') {
                            header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
                        } else {
                            header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_site_id=' . $formSiteId . '#sites');
                        }
                        exit;
                    } else {
                        $_SESSION['error'] = "Erreur lors de l'ajout de la salle.";
                    }
                }
            }
        }

        // S'assurer que clientId est défini
        if (empty($clientId)) {
            $_SESSION['error'] = "Client non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer les informations du client pour les breadcrumbs
        $client = $this->clientModel->getClientById($clientId);
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($clientId);

        // Générer les breadcrumbs personnalisés
        if (isset($client) && !empty($client)) {
            $GLOBALS['customBreadcrumbs'] = generateRoomAddBreadcrumbs($client, $site);
        }

        // Passer les variables à la vue
        $pageTitle = "Ajouter une salle";
        require_once VIEWS_PATH . '/room/add.php';
    }

    /**
     * Affiche le formulaire d'édition d'une salle
     */
    public function edit($id) {
        $this->checkAccess();

        // Récupérer la salle d'abord
        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer le site associé à la salle
        $site = $this->roomModel->getSiteById($room['site_id']);
        if (!$site) {
            $_SESSION['error'] = "Site associé à cette salle non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier cette salle.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $room['site_id'] . '#sites');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                'status' => isset($_POST['status']) ? 1 : 0
            ];

            if ($this->roomModel->updateRoom($id, $data)) {
                $_SESSION['success'] = "Salle modifiée avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $room['site_id'] . '#sites');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification de la salle.";
            }
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($site['client_id']);

        $pageTitle = "Modifier la salle - " . $room['name'];
        require_once VIEWS_PATH . '/room/edit.php';
    }

    /**
     * Supprime une salle
     */
    public function delete($id) {
        $this->checkAccess();

        // Vérifier si l'utilisateur est un administrateur
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des salles.";
            // Redirect to client edit page if room context is available
            $room = $this->roomModel->getRoomById($id);
            if ($room && isset($room['client_id'])) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $room['client_id'] . '#sites');
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        // $room is already fetched before the isAdmin check
        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'dashboard'); // Or a more relevant general page
            exit;
        }

        // Store client_id and site_id before deletion for the redirect
        $clientId = $room['client_id'];
        $siteId = $room['site_id'];

        if ($this->roomModel->deleteRoom($id)) {
            $_SESSION['success'] = "Salle supprimée avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression de la salle.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_site_id=' . $siteId . '#sites');
        exit;
    }

    /**
     * Récupère les salles d'un site via API
     */
    public function getRoomsBySite() {
        if (!isset($_GET['site_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID du site manquant']);
            exit;
        }

        $siteId = (int)$_GET['site_id'];
        $rooms = $this->roomModel->getRoomsBySiteId($siteId);

        header('Content-Type: application/json');
        echo json_encode($rooms);
        exit;
    }
} 