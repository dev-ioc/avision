<?php
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/ContactModel.php';
require_once __DIR__ . '/../models/BuildingModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class RoomController
{
    use AccessControlTrait;
    private $db;
    private $roomModel;
    private $contactModel;
    private $buildingModel;
    private $clientModel;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->roomModel = new RoomModel($this->db);
        $this->contactModel = new ContactModel($this->db);
        $this->buildingModel = new BuildingModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }


    /**
     * Affiche le formulaire d'ajout d'une salle
     * Peut accepter soit un building_id (comportement classique) soit un client_id via GET
     */
    public function add($id)
    {
        $this->checkAccess();

        // Vérifier si on a un client_id dans les paramètres GET (mode sélection de bâtiment)
        $clientId = $_GET['client_id'] ?? null;
        $buildingId = null;
        $building = null;
        $buildings = [];
        $selectedBuildingId = $_GET['building_id'] ?? null; // Bâtiment pré-sélectionné

        if ($clientId) {
            // Mode sélection de bâtiment : on vient de la vue client
            // Récupérer tous les bâtiments du client via ses sites
            $buildings = $this->buildingModel->getBuildingsByClientId($clientId);

            if (empty($buildings)) {
                $_SESSION['error'] = "Aucun bâtiment trouvé pour ce client. Veuillez d'abord créer un bâtiment.";
                header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
                exit;
            }

            // Si un bâtiment est pré-sélectionné, l'utiliser
            if ($selectedBuildingId) {
                $building = $this->buildingModel->getBuildingById($selectedBuildingId);
                if ($building && $building['client_id'] == $clientId) {
                    $buildingId = $selectedBuildingId;
                }
            } else {
                // Si un seul bâtiment, le pré-sélectionner automatiquement
                if (count($buildings) === 1) {
                    $building = $buildings[0];
                    $buildingId = $building['id'];
                }
            }
        } else {
            // Mode classique : l'ID est un building_id
            if (empty($id) || $id == 0) {
                $_SESSION['error'] = "Bâtiment non spécifié.";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }
            $buildingId = $id;
            $building = $this->buildingModel->getBuildingById($buildingId);
            if (!$building) {
                $_SESSION['error'] = "Bâtiment non trouvé.";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }
            $clientId = $building['client_id'];
        }

        // Vérifier si l'utilisateur a les droits de création
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une salle.";
            $returnTo = $_GET['return_to'] ?? 'edit';
            if ($returnTo === 'view') {
                header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
            } else {
                header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . ($buildingId ? '?open_building_id=' . $buildingId . '#sites' : '#sites'));
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Utiliser le building_id du formulaire
            $formBuildingId = $_POST['building_id'] ?? $buildingId;

            if (empty($formBuildingId)) {
                $_SESSION['error'] = "Veuillez sélectionner un bâtiment.";
            } else {
                // Vérifier que le bâtiment existe et appartient au client
                $formBuilding = $this->buildingModel->getBuildingById($formBuildingId);
                if (!$formBuilding || $formBuilding['client_id'] != $clientId) {
                    $_SESSION['error'] = "Bâtiment invalide.";
                } else {
                    $data = [
                        'building_id' => $formBuildingId,
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
                            header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_building_id=' . $formBuildingId . '#sites');
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

        // Récupérer les bâtiments du client pour la liste déroulante
        $buildings = $this->buildingModel->getBuildingsByClientId($clientId);

        // Générer les breadcrumbs personnalisés
        if (isset($client) && !empty($client)) {
            $GLOBALS['customBreadcrumbs'] = generateRoomAddBreadcrumbs($client, $building);
        }

        // Passer les variables à la vue
        $pageTitle = "Ajouter une salle";
        require_once VIEWS_PATH . '/room/add.php';
    }
    /**
     * Affiche le formulaire d'édition d'une salle
     */
    public function edit($id)
    {
        $this->checkAccess();

        // Récupérer la salle d'abord
        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer le site associé à la salle
        $building = $this->roomModel->getBuildingById($room['building_id']);
        if (!$building) {
            $_SESSION['error'] = "Site associé à cette salle non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier cette salle.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $building['client_id'] . '?open_site_id=' . $room['site_id'] . '#sites');
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
                header('Location: ' . BASE_URL . 'clients/edit/' . $building['client_id'] . '?open_site_id=' . $room['site_id'] . '#sites');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification de la salle.";
            }
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($building['client_id']);

        $pageTitle = "Modifier la salle - " . $room['name'];
        require_once VIEWS_PATH . '/room/edit.php';
    }

    /**
     * Supprime une salle
     */
    public function delete($id)
    {
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
        $buildingId = $room['site_id'];

        if ($this->roomModel->deleteRoom($id)) {
            $_SESSION['success'] = "Salle supprimée avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression de la salle.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_site_id=' . $buildingId . '#sites');
        exit;
    }

    /**
     * Récupère les salles d'un site via API
     */
    public function getRoomsBySite()
    {
        if (!isset($_GET['site_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID du site manquant']);
            exit;
        }

        $buildingId = (int) $_GET['site_id'];
        $rooms = $this->roomModel->getRoomsByBuildingId($buildingId);

        header('Content-Type: application/json');
        echo json_encode($rooms);
        exit;
    }
}