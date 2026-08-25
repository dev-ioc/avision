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
        // Appliquer le middleware CSRF pour les requêtes POST
        // Dans RoomController::add(), avant la validation CSRF
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Test manuel
            $postToken = $_POST['csrf_token'] ?? '';
            $sessionToken = $_SESSION['csrf_token']['token'] ?? '';

            error_log("=== TEST MANUEL CSRF ===");
            error_log("POST token: '" . $postToken . "'");
            error_log("Session token: '" . $sessionToken . "'");
            error_log("POST token length: " . strlen($postToken));
            error_log("Session token length: " . strlen($sessionToken));
            error_log("POST token type: " . gettype($postToken));
            error_log("Session token type: " . gettype($sessionToken));
            error_log("Are identical: " . ($postToken === $sessionToken ? 'YES' : 'NO'));
            error_log("hash_equals result: " . (hash_equals($sessionToken, $postToken) ? 'TRUE' : 'FALSE'));

            // Test caractère par caractère
            for ($i = 0; $i < strlen($postToken); $i++) {
                if ($postToken[$i] !== $sessionToken[$i]) {
                    error_log("Différence à la position $i: '" . $postToken[$i] . "' vs '" . $sessionToken[$i] . "'");
                    break;
                }
            }
        }
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

        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        $building = $this->roomModel->getBuildingById($room['building_id']);
        if (!$building) {
            $_SESSION['error'] = "Site associé à cette salle non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        $defaultReturn = BASE_URL . 'clients/view/' . (int) $building['client_id'];

        $returnUrl = $_POST['return_url'] ?? $_GET['return_url'] ?? $defaultReturn;

        // Sécurité : uniquement des URL internes à l'application
        if (strpos($returnUrl, BASE_URL) !== 0) {
            $returnUrl = $defaultReturn;
        }

        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier cette salle.";
            header('Location: ' . $returnUrl);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id'])
                    ? $_POST['main_contact_id']
                    : null,
                'status' => isset($_POST['status']) ? 1 : 0
            ];

            if ($this->roomModel->updateRoom($id, $data)) {
                $_SESSION['success'] = "Salle modifiée avec succès.";
                header('Location: ' . $returnUrl);
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification de la salle.";
            }
        }

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
        $buildingId = $room['building_id'];

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
    public function toggleQrCode($id = null)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Non authentifié.']);
            exit;
        }

        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];

        // Accept token from header first, fall back to JSON body
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);

        if (!$csrfToken || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
            http_response_code(419); // or 403
            echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']);
            exit;
        }

        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Salle introuvable.']);
            exit;
        }

        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "Vous n'avez pas les droits nécessaires."]);
            exit;
        }

        $edited = isset($input['edited']) ? (bool) $input['edited'] : false;

        if ($this->roomModel->updateQrCodeStatus($id, $edited)) {
            echo json_encode(['success' => true, 'edited' => $edited]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour.']);
        }
        exit;
    }
}