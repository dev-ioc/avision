<?php
require_once __DIR__ . '/../models/ContactModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';

class ContactClientController {
    private $db;
    private $contactModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;

    public function __construct($db) {
        $this->db = $db;
        $this->contactModel = new ContactModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
    }

    /**
     * Endpoint JSON: retourne les contacts du client courant
     */
    public function getContacts() {
        // Accès client requis
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }

        if (!canManageOwnContacts()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Permission manquante"]);
            exit;
        }

        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (!$clientId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Aucun client associé']);
            exit;
        }

        $contacts = $this->contactModel->getContactsByClientId($clientId);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'contacts' => $contacts]);
        exit;
    }

    /**
     * Endpoint JSON: définit le contact principal d'un site ou d'une salle
     */
    public function setPrimaryContact() {
        // Accès client requis
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }

        if (!canManageOwnContacts()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Permission manquante"]);
            exit;
        }

        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (!$clientId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Aucun client associé']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $type = $input['type'] ?? '';
        $entityId = isset($input['id']) ? (int)$input['id'] : 0;
        $contactId = isset($input['contact_id']) && $input['contact_id'] !== '' ? (int)$input['contact_id'] : null;

        if (!in_array($type, ['site', 'room'], true) || $entityId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }

        // Valider que l'entité appartient au client
        if ($type === 'site') {
            $site = $this->siteModel->getSiteById($entityId);
            if (!$site || (int)$site['client_id'] !== (int)$clientId) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "Site non autorisé"]);
                exit;
            }
        } else {
            $room = $this->roomModel->getRoomById($entityId);
            if (!$room || (int)$room['client_id'] !== (int)$clientId) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "Salle non autorisée"]);
                exit;
            }
        }

        // Si un contact est fourni, valider qu'il appartient au même client
        if ($contactId !== null) {
            $contact = $this->contactModel->getContactById($contactId);
            if (!$contact || (int)$contact['client_id'] !== (int)$clientId) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "Contact non autorisé"]);
                exit;
            }
        }

        // Mettre à jour
        $ok = false;
        if ($type === 'site') {
            $ok = $this->siteModel->setSitePrimaryContact($entityId, $contactId);
        } else {
            $ok = $this->roomModel->setRoomPrimaryContact($entityId, $contactId);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }

    /**
     * Affiche la liste des contacts de la localisation du client
     */
    public function index() {
        // Vérifier l'accès client
        checkClientAccess();

        // Vérifier la permission
        if (!canManageOwnContacts()) {
            $_SESSION['error'] = 'Vous n\'avez pas les droits pour gérer les contacts.';
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $user = $_SESSION['user'];
        $userLocations = getUserLocationsFormatted();
        
        if (empty($userLocations)) {
            $_SESSION['error'] = 'Aucune localisation associée à votre compte.';
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer tous les contacts du client auquel l'utilisateur est affecté
        $contacts = $this->getContactsByLocations($userLocations);

        // Inclure la vue
        include_once __DIR__ . '/../views/contact_client/index.php';
    }

    /**
     * Affiche le formulaire d'ajout d'un contact
     */
    public function add() {
        // Vérifier l'accès client
        checkClientAccess();

        // Vérifier la permission
        if (!canManageOwnContacts()) {
            $_SESSION['error'] = 'Vous n\'avez pas les droits pour ajouter des contacts.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        $user = $_SESSION['user'];
        $userLocations = getUserLocationsFormatted();
        
        if (empty($userLocations)) {
            $_SESSION['error'] = 'Aucune localisation associée à votre compte.';
            header('Location: ' . BASE_URL . 'contactClient/add');
            exit;
        }

        // Pas de filtres par site/salle pour les contacts

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $function = trim($_POST['function'] ?? '');
            
            // Récupérer le client de l'utilisateur connecté
            $clientId = null;
            foreach ($userLocations as $clientIdLoc => $locations) {
                $clientId = $clientIdLoc;
                break; // On prend le premier client (normalement il n'y en a qu'un pour un client)
            }

            if (!$clientId) {
                $_SESSION['error'] = 'Aucun client associé à votre compte.';
                header('Location: ' . BASE_URL . 'contactClient/add');
                exit;
            }

            // Validation des champs obligatoires
            if (empty($firstName) || empty($lastName)) {
                $_SESSION['error'] = 'Les champs nom et prénom sont obligatoires.';
                header('Location: ' . BASE_URL . 'contactClient/add');
                exit;
            }

            // Validation de l'email si fourni
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'L\'adresse email n\'est pas valide.';
                header('Location: ' . BASE_URL . 'contactClient/add');
                exit;
            }

            // Créer le contact
            $data = [
                'client_id' => $clientId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone1' => $phone,
                'phone2' => '',
                'fonction' => $function,
                'comment' => ''
            ];

            if ($this->contactModel->createContact($data)) {
                $_SESSION['success'] = 'Contact créé avec succès.';
                header('Location: ' . BASE_URL . 'contactClient');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la création du contact.';
                header('Location: ' . BASE_URL . 'contactClient/add');
                exit;
            }
        }

        // Récupérer les clients disponibles pour le formulaire (simplifié)
        $availableClients = [];
        $availableLocations = [];
        foreach ($userLocations as $clientId => $locations) {
            $client = $this->clientModel->getClientById($clientId);
            $availableClients[] = [
                'client_id' => $clientId,
                'client_name' => $client['name'] ?? ''
            ];
            // Créer la structure attendue par la vue
            $availableLocations[] = [
                'client_id' => $clientId,
                'client_name' => $client['name'] ?? ''
            ];
        }

        // Inclure la vue
        include_once __DIR__ . '/../views/contact_client/add.php';
    }

    /**
     * Affiche le formulaire de modification d'un contact
     */
    public function edit($id) {
        // Vérifier l'accès client
        checkClientAccess();

        // Vérifier la permission
        if (!canManageOwnContacts()) {
            $_SESSION['error'] = 'Vous n\'avez pas les droits pour modifier des contacts.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        $contact = $this->contactModel->getContactById($id);
        if (!$contact) {
            $_SESSION['error'] = 'Contact non trouvé.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        // Vérifier que l'utilisateur peut modifier ce contact
        $userLocations = getUserLocationsFormatted();
        $canModify = false;
        
        foreach ($userLocations as $clientId => $locations) {
            if ($clientId == $contact['client_id']) {
                $canModify = true;
                break;
            }
        }

        if (!$canModify) {
            $_SESSION['error'] = 'Vous n\'êtes pas autorisé à modifier ce contact.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $function = trim($_POST['function'] ?? '');

            // Validation des champs obligatoires
            if (empty($firstName) || empty($lastName)) {
                $_SESSION['error'] = 'Les champs nom et prénom sont obligatoires.';
                header('Location: ' . BASE_URL . 'contactClient/edit/' . $id);
                exit;
            }

            // Validation de l'email si fourni
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'L\'adresse email n\'est pas valide.';
                header('Location: ' . BASE_URL . 'contactClient/edit/' . $id);
                exit;
            }

            // Mettre à jour le contact
            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone1' => $phone,
                'phone2' => '',
                'fonction' => $function,
                'comment' => ''
            ];

            if ($this->contactModel->updateContact($id, $data)) {
                $_SESSION['success'] = 'Contact mis à jour avec succès.';
                header('Location: ' . BASE_URL . 'contactClient');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la mise à jour du contact.';
                header('Location: ' . BASE_URL . 'contactClient/edit/' . $id);
                exit;
            }
        }

        // Inclure la vue
        include_once __DIR__ . '/../views/contact_client/edit.php';
    }

    /**
     * Supprime un contact
     */
    public function delete($id) {
        // Vérifier l'accès client
        checkClientAccess();

        // Vérifier la permission
        if (!canManageOwnContacts()) {
            $_SESSION['error'] = 'Vous n\'avez pas les droits pour supprimer des contacts.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        $contact = $this->contactModel->getContactById($id);
        if (!$contact) {
            $_SESSION['error'] = 'Contact non trouvé.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        // Vérifier que l'utilisateur peut supprimer ce contact
        $userLocations = getUserLocationsFormatted();
        $canDelete = false;
        
        foreach ($userLocations as $clientId => $locations) {
            if ($clientId == $contact['client_id']) {
                $canDelete = true;
                break;
            }
        }

        if (!$canDelete) {
            $_SESSION['error'] = 'Vous n\'êtes pas autorisé à supprimer ce contact.';
            header('Location: ' . BASE_URL . 'contactClient');
            exit;
        }

        if ($this->contactModel->deleteContact($id)) {
            $_SESSION['success'] = 'Contact supprimé avec succès.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression du contact.';
        }

        header('Location: ' . BASE_URL . 'contactClient');
        exit;
    }

    /**
     * Récupère les contacts selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des contacts
     */
    private function getContactsByLocations($userLocations) {
        $contacts = [];
        
        foreach ($userLocations as $clientId => $locations) {
            $clientContacts = $this->contactModel->getContactsByClientId($clientId);
            $contacts = array_merge($contacts, $clientContacts);
        }
        
        return $contacts;
    }
}
?>
