<?php
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/ContractModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/ContactModel.php';
require_once __DIR__ . '/../models/InterventionModel.php';

require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class ClientController {
    use AccessControlTrait;
    private $db;
    private $clientModel;
    private $siteModel;
    private $contractModel;
    private $roomModel;
    private $contactModel;
    private $interventionModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->contractModel = new ContractModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->contactModel = new ContactModel($this->db);
        $this->interventionModel = new InterventionModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder aux clients
     */

    /**
     * Affiche la liste des clients avec leurs statistiques
     */
    public function index() {
        $this->checkAccess();

        try {
            // Récupération des filtres
            $filters = [
                'search' => $_GET['search'] ?? '',
                'city' => $_GET['city'] ?? '',
                'status' => $_GET['status'] ?? ''
            ];

            // Récupérer tous les clients avec leurs statistiques
            $clients = $this->clientModel->getAllClientsWithStats($filters);

            // Inclure la vue
            require_once VIEWS_PATH . '/client/index.php';
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des clients : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la récupération des clients.";
            require_once VIEWS_PATH . '/client/index.php';
        }
    }

    public function view($id) {
        $this->checkAccess();

        // Vérifier si l'ID est valide
        if (!is_numeric($id) || $id <= 0) {
            $_SESSION['error'] = "ID de client invalide";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer les informations du client
        $client = $this->clientModel->getClientById($id);
        
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer les statistiques du client
        $stats = [
            'site_count' => $this->siteModel->getSiteCountByClientId($id),
            'room_count' => $this->siteModel->getRoomCountByClientId($id),
            'contract_count' => $this->contractModel->getContractCountByClientId($id)
        ];

        // Récupérer les sites du client
        $sites = $this->siteModel->getSitesByClientId($id);

        // Pour chaque site, récupérer ses salles
        foreach ($sites as $key => $site) {
            $sites[$key]['rooms'] = $this->roomModel->getRoomsBySiteId($site['id'], true); // true = salles actives uniquement
        }

        // Récupérer les contrats du client (actifs et inactifs)
        $contracts = $this->contractModel->getContractsByClientId($id, null, null, true);

        // Récupérer les contacts du client
        $contacts = $this->contactModel->getContactsByClientId($id);

        // Récupérer les interventions groupées par contrat et par type
        $interventionsGrouped = $this->interventionModel->getInterventionsByClientGrouped($id);

        // Charger la vue avec les données structurées
        require_once VIEWS_PATH . '/client/view.php';
    }

    /**
     * Affiche le formulaire d'édition d'un client
     */
    public function edit($id) {
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier ce client.";
            header('Location: ' . BASE_URL . 'clients/view/' . $id);
            exit;
        }

        // Vérifier si l'ID est valide
        if (!is_numeric($id) || $id <= 0) {
            $_SESSION['error'] = "ID de client invalide";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer les informations du client
        $client = $this->clientModel->getClientById($id);
        
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer les sites du client
        $sites = $this->siteModel->getSitesByClientId($id);

        // Pour chaque site, récupérer ses salles
        foreach ($sites as $key => $site) {
            $sites[$key]['rooms'] = $this->roomModel->getRoomsBySiteId($site['id'], true);
        }

        // Récupérer les contrats du client (actifs et inactifs)
        $contracts = $this->contractModel->getContractsByClientId($id, null, null, true);

        // Récupérer les contacts du client
        $contacts = $this->contactModel->getContactsByClientId($id);

        // Récupérer les types de contrats disponibles
        $contractTypes = $this->contractModel->getContractTypes();

        // Charger la vue avec les données structurées
        require_once VIEWS_PATH . '/client/edit.php';
    }

    /**
     * Met à jour les informations d'un client
     */
    public function update($id) {
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier ce client.";
            header('Location: ' . BASE_URL . 'clients/view/' . $id);
            exit;
        }

        // Vérifier si l'ID est valide
        if (!is_numeric($id) || $id <= 0) {
            $_SESSION['error'] = "ID de client invalide";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si le formulaire a été soumis
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'clients/edit/' . $id);
            exit;
        }

        // Récupérer les données du formulaire
        $clientData = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? '',
            'city' => $_POST['city'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'website' => $_POST['website'] ?? '',
            'comment' => $_POST['comment'] ?? ''
        ];
        
        // Seul un administrateur peut modifier le statut
        if (isAdmin()) {
            $clientData['status'] = isset($_POST['status']) ? (int)$_POST['status'] : 0;
        }

        // Valider les données
        if (empty($clientData['name'])) {
            $_SESSION['error'] = "Le nom du client est obligatoire";
            header('Location: ' . BASE_URL . 'clients/edit/' . $id);
            exit;
        }

        try {
            // Mettre à jour le client
            $this->clientModel->updateClient($id, $clientData);
            
            $_SESSION['success'] = "Le client a été mis à jour avec succès";
            header('Location: ' . BASE_URL . 'clients/view/' . $id);
            exit;
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du client : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour du client.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $id);
            exit;
        }
    }

    /**
     * Affiche le formulaire d'ajout d'un client
     */
    public function add() {
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour ajouter un client.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Charger la vue
        require_once VIEWS_PATH . '/client/add.php';
    }

    /**
     * Traite l'ajout d'un nouveau client
     */
    public function store() {
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour ajouter un client.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si le formulaire a été soumis
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'clients/add');
            exit;
        }

        // Récupérer les données du formulaire
        $clientData = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? '',
            'city' => $_POST['city'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'website' => $_POST['website'] ?? '',
            'comment' => $_POST['comment'] ?? ''
        ];
        
        // Seul un administrateur peut définir le statut
        if (isAdmin()) {
            $clientData['status'] = isset($_POST['status']) ? (int)$_POST['status'] : 1;
        } else {
            $clientData['status'] = 1; // Par défaut actif pour les non-admins
        }

        // Valider les données
        if (empty($clientData['name'])) {
            $_SESSION['error'] = "Le nom du client est obligatoire";
            $_SESSION['form_data'] = $clientData;
            header('Location: ' . BASE_URL . 'clients/add');
            exit;
        }

        try {
            // Ajouter le client
            $clientId = $this->clientModel->createClient($clientData);
            
            // Créer automatiquement les contrats "hors contrat" avec des types appropriés
            $this->createDefaultContracts($clientId);
            
            $_SESSION['success'] = "Le client a été ajouté avec succès";
            header('Location: ' . BASE_URL . 'clients/view/' . $clientId);
            exit;
        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout du client : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de l'ajout du client.";
            $_SESSION['form_data'] = $clientData;
            header('Location: ' . BASE_URL . 'clients/add');
            exit;
        }
    }

    /**
     * Crée automatiquement les contrats "hors contrat" pour un nouveau client
     * 
     * @param int $clientId ID du client
     */
    private function createDefaultContracts($clientId) {
        try {
            // Récupérer le niveau d'accès par défaut
            $defaultAccessLevelId = $this->getDefaultAccessLevel();
            
            // Créer le contrat "Hors contrat facturable" SANS type de contrat
            $this->contractModel->createContract([
                'client_id' => $clientId,
                'contract_type_id' => null,
                'access_level_id' => $defaultAccessLevelId,
                'name' => 'Hors contrat facturable',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+10 years')),
                'tickets_number' => 0,
                'tickets_remaining' => 0,
                'isticketcontract' => 0,
                'comment' => 'Contrat créé automatiquement lors de la création du client',
                'status' => 'actif',
                'reminder_enabled' => 0,
                'reminder_days' => 30
            ]);
            
            // Créer le contrat "Hors contrat non facturable" SANS type de contrat
            $this->contractModel->createContract([
                'client_id' => $clientId,
                'contract_type_id' => null,
                'access_level_id' => $defaultAccessLevelId,
                'name' => 'Hors contrat non facturable',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+10 years')),
                'tickets_number' => 0,
                'tickets_remaining' => 0,
                'isticketcontract' => 0,
                'comment' => 'Contrat créé automatiquement lors de la création du client',
                'status' => 'actif',
                'reminder_enabled' => 0,
                'reminder_days' => 30
            ]);
            
            custom_log("Contrats par défaut créés pour le client ID: $clientId", 'INFO');
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la création des contrats par défaut pour le client $clientId : " . $e->getMessage(), 'ERROR');
            // Ne pas faire échouer la création du client si les contrats échouent
        }
    }

    /**
     * Récupère ou crée un type de contrat
     * 
     * @param string $name Nom du type de contrat
     * @param string $description Description du type de contrat
     * @return int ID du type de contrat
     */
    private function getOrCreateContractType($name, $description) {
        try {
            // Vérifier si le type existe déjà
            $query = "SELECT id FROM contract_types WHERE name = :name LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':name' => $name]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // Créer le type s'il n'existe pas
            $type = $this->createContractType($name, $description, 0, 0);
            return $type['id'];
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération/création du type de contrat '$name' : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Crée un type de contrat s'il n'existe pas
     * 
     * @param string $name Nom du type de contrat
     * @param string $description Description du type de contrat
     * @param int $defaultTickets Nombre de tickets par défaut
     * @param int $nbInterPrev Nombre d'interventions préventives
     * @return array Le type de contrat créé
     */
    private function createContractType($name, $description, $defaultTickets = 0, $nbInterPrev = 0) {
        try {
            $query = "INSERT INTO contract_types (
                        name, 
                        description, 
                        default_tickets, 
                        nb_inter_prev, 
                        ordre_affichage,
                        created_at, 
                        updated_at
                    ) VALUES (
                        :name, 
                        :description, 
                        :default_tickets, 
                        :nb_inter_prev,
                        (SELECT COALESCE(MAX(ordre_affichage), 0) + 1 FROM contract_types),
                        NOW(), 
                        NOW()
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':default_tickets', $defaultTickets, PDO::PARAM_INT);
            $stmt->bindParam(':nb_inter_prev', $nbInterPrev, PDO::PARAM_INT);
            
            $stmt->execute();
            $typeId = $this->db->lastInsertId();
            
            return [
                'id' => $typeId,
                'name' => $name,
                'description' => $description,
                'default_tickets' => $defaultTickets,
                'nb_inter_prev' => $nbInterPrev
            ];
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la création du type de contrat '$name' : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Récupère le niveau d'accès par défaut (gratuit)
     * 
     * @return int ID du niveau d'accès par défaut
     */
    private function getDefaultAccessLevel() {
        try {
            $query = "SELECT id FROM contract_access_levels WHERE name = 'gratuit' LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // Si le niveau gratuit n'existe pas, créer les niveaux d'accès par défaut
            $this->createDefaultAccessLevels();
            
            // Récupérer à nouveau l'ID du niveau gratuit
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['id'];
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du niveau d'accès par défaut : " . $e->getMessage(), 'ERROR');
            return 1; // Valeur par défaut
        }
    }

    /**
     * Crée les niveaux d'accès par défaut s'ils n'existent pas
     */
    private function createDefaultAccessLevels() {
        try {
            // Créer les niveaux d'accès de base
            $levels = [
                ['name' => 'gratuit', 'description' => 'Niveau d\'accès gratuit - visibilité limitée des champs matériel'],
                ['name' => 'gold', 'description' => 'Niveau d\'accès Gold - visibilité intermédiaire des champs matériel'],
                ['name' => 'premium', 'description' => 'Niveau d\'accès Premium - visibilité complète des champs matériel']
            ];
            
            foreach ($levels as $index => $level) {
                $query = "INSERT IGNORE INTO contract_access_levels (name, description, ordre_affichage) VALUES (:name, :description, :ordre_affichage)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':name' => $level['name'],
                    ':description' => $level['description'],
                    ':ordre_affichage' => $index + 1
                ]);
            }
            
            custom_log("Niveaux d'accès par défaut créés", 'INFO');
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la création des niveaux d'accès par défaut : " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Supprime un client
     */
    public function delete($id) {
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits de suppression
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des clients.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'ID est valide
        if (!is_numeric($id) || $id <= 0) {
            $_SESSION['error'] = "ID de client invalide";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si le client existe
        $client = $this->clientModel->getClientById($id);
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        try {
            // Supprimer le client et toutes ses données associées
            $this->clientModel->deleteClient($id);
            
            $_SESSION['success'] = "Le client '" . h($client['name']) . "' a été supprimé avec succès";
            header('Location: ' . BASE_URL . 'clients');
            exit;
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression du client ID $id : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la suppression du client : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }
    }
} 