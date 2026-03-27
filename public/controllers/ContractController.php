<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../models/ContractModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/AccessLevelModel.php';
require_once __DIR__ . '/../models/MaterielModel.php';
require_once __DIR__ . '/../includes/DateUtils.php';
require_once __DIR__ . '/../classes/Services/AttachmentService.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class ContractController {
    use AccessControlTrait;
    private $db;
    private $contractModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $accessLevelModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->contractModel = new ContractModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->accessLevelModel = new AccessLevelModel($this->db);
    }


    /**
     * Vérifie si l'utilisateur peut gérer les contrats (admin ou permission spécifique)
     */
    private function checkContractManagementAccess() {
        $this->checkAccess();
        
        // Les administrateurs ont toujours accès
        if (isAdmin()) {
            return;
        }
        
        // Vérifier la permission spécifique
        if (!canManageContracts()) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer les contrats.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste des contrats
     */
    public function index($id = null) {
        $this->checkAccess();

        // Si un ID est fourni, rediriger vers la page d'édition du client
        if ($id) {
            header('Location: ' . BASE_URL . 'clients/edit/' . $id);
            exit;
        }

        // Récupérer le filtre d'affichage des statuts
        $show_status = $_GET['show_status'] ?? 'actif'; // Par défaut: 'actif'
        
        // Récupérer le filtre d'affichage des types de tickets
        $ticket_type = $_GET['ticket_type'] ?? 'all'; // Par défaut: 'all'

        // Récupérer les autres filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'contract_type_id' => $_GET['contract_type_id'] ?? null,
            // Le filtre 'status' sera ajouté ci-dessous basé sur $show_status
            // Le filtre 'ticket_type' sera ajouté ci-dessous basé sur $ticket_type
        ];

        // Appliquer le filtre de statut pour la requête SQL
        if ($show_status === 'actif') {
            $filters['status'] = 'actif';
        } elseif ($show_status === 'inactif') {
            $filters['status'] = 'inactif'; // Assurez-vous que 'inactif' est la valeur DB pour les contrats non actifs
        } elseif ($show_status === 'en_attente') {
            $filters['status'] = 'en_attente'; // Filtre pour les contrats en attente
        }
        // Si $show_status est 'all' (ou autre chose), $filters['status'] reste non défini,
        // et le modèle getAllContracts ne filtrera pas par statut si $filters['status'] est vide.

        // Appliquer le filtre de type de tickets pour la requête SQL
        if ($ticket_type === 'with_tickets') {
            $filters['ticket_type'] = 'with_tickets'; // Contrats avec tickets
        } elseif ($ticket_type === 'without_tickets') {
            $filters['ticket_type'] = 'without_tickets'; // Contrats sans tickets
        }
        // Si $ticket_type est 'all' (ou autre chose), $filters['ticket_type'] reste non défini,
        // et le modèle getAllContracts ne filtrera pas par type de tickets si $filters['ticket_type'] est vide.

        // Récupérer les contrats filtrés
        $contracts = $this->contractModel->getAllContracts($filters);

        // Récupérer les statistiques par statut (pour les badges des filtres)
        $statsByStatus = $this->contractModel->getContractStatsByStatus();

        // Récupérer les clients pour le filtre
        $clients = $this->clientModel->getAllClientsWithStats();

        // Récupérer les sites pour le filtre (si un client est sélectionné)
        $sites = [];
        if ($filters['client_id']) {
            $sites = $this->siteModel->getSitesByClientId($filters['client_id']);
        }

        // Récupérer les salles pour le filtre (si un site est sélectionné)
        $rooms = [];
        if ($filters['site_id']) {
            $rooms = $this->roomModel->getRoomsBySiteId($filters['site_id']);
        }

        // Récupérer les types de contrats
        $contractTypes = $this->contractModel->getContractTypes();
        
        // Passer la vue de filtre actuelle au template pour l'affichage des boutons
        $current_filter_view = $show_status;
        
        // Passer le filtre de type de tickets au template
        $current_ticket_filter = $ticket_type;

        // Définir isAdmin pour la vue
        $isAdmin = isAdmin();

        // Charger la vue
        require_once VIEWS_PATH . '/contract/index.php';
    }

    /**
     * Affiche le formulaire d'ajout d'un contrat
     */
    public function add($clientId = null) {
        checkContractManagementAccess();

        // echo "Debug: Entered add method.<br>"; // Temporary debug

        $client = null; 
        $sites = [];
        $rooms = [];
        $allClientsForDropdown = null;

        try {
            // echo "Debug: Inside try block.<br>"; // Temporary debug

            if ($clientId !== null) {
                // Un ID client est fourni, valider et charger les données spécifiques au client
                if (!is_numeric($clientId) || $clientId <= 0) {
                    $_SESSION['error'] = "ID de client invalide fourni.";
                    header('Location: ' . BASE_URL . 'contracts');
                    exit;
                }
                // echo "Debug: Client ID is $clientId.<br>"; // Temporary debug
                $client = $this->clientModel->getClientById($clientId);
                if (!$client) {
                    $_SESSION['error'] = "Client non trouvé pour l'ID fourni.";
                    header('Location: ' . BASE_URL . 'contracts');
                    exit;
                }
                // echo "Debug: Client data fetched.<br>"; // Temporary debug
                $sites = $this->siteModel->getSitesByClientId($clientId);
                foreach ($sites as $site) {
                    $siteRooms = $this->roomModel->getRoomsBySiteId($site['id']);
                    foreach ($siteRooms as $room) {
                        $rooms[] = $room;
                    }
                }
                // echo "Debug: Sites and rooms fetched for client.<br>"; // Temporary debug
            } else {
                // Aucun ID client fourni
                // echo "Debug: clientId is null, fetching all clients.<br>"; // Temporary debug
                $allClientsForDropdown = $this->clientModel->getAllClients(); 
                // if ($allClientsForDropdown === null) { echo "Debug: getAllClients returned null.<br>"; } // Temporary debug
                // else { echo "Debug: getAllClients returned " . count($allClientsForDropdown) . " clients.<br>"; } // Temporary debug
            }

            // echo "Debug: Fetching contract types.<br>"; // Temporary debug
            $contractTypes = $this->contractModel->getContractTypes();
            // if ($contractTypes === null) { echo "Debug: getContractTypes returned null.<br>"; } // Temporary debug
            // else { echo "Debug: getContractTypes returned " . count($contractTypes) . " types.<br>"; } // Temporary debug
            
            // Récupérer les niveaux d'accès disponibles
            $accessLevels = $this->accessLevelModel->getAllAccessLevels();
            
            // echo "Debug: About to load view /contract/add.php.<br>"; // Temporary debug
            // exit; // <<< ADD THIS EXIT TEMPORARILY TO SEE IF WE REACH HERE

            require_once VIEWS_PATH . '/contract/add.php';
            // echo "Debug: View /contract/add.php loaded.<br>"; // Temporary debug (won't see if view exits)
            // exit;

        } catch (Throwable $t) { // Catch Throwable to get more error types
            custom_log("Erreur Throwable dans ContractController::add : " . $t->getMessage() . "\nStack Trace:\n" . $t->getTraceAsString(), 'ERROR');
            $_SESSION['error'] = "Une erreur critique est survenue (add): " . $t->getMessage();
            // Temporarily echo error for immediate visibility during debug
            // echo "<pre>Caught Throwable: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "</pre>";
            // exit; // Halt execution after printing error
            header('Location: ' . BASE_URL . 'contracts'); // Redirect to contracts index
            exit;
        }
    }

    /**
     * Traite l'ajout d'un contrat
     */
    public function create() {
        checkContractManagementAccess();

        try {
            // Vérifier si le formulaire a été soumis
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }

            // Récupérer les données du formulaire
            $clientId = $_POST['client_id'] ?? null;
            $rooms = $_POST['rooms'] ?? []; // Nouveau : tableau de salles
            $contractTypeId = $_POST['contract_type_id'] ?? null;
            $accessLevelId = $_POST['access_level_id'] ?? null;
            $name = $_POST['name'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $ticketsNumber = $_POST['tickets_number'] ?? 0;
            $comment = $_POST['comment'] ?? '';
            $reminderEnabled = isset($_POST['reminder_enabled']) ? 1 : 0;
            $reminderDays = $_POST['reminder_days'] ?? 30;
            $renouvellementTacite = isset($_POST['renouvellement_tacite']) ? 1 : 0;
            $numFacture = $_POST['num_facture'] ?? null;
            $tarif = $_POST['tarif'] ?? null;
            $indice = $_POST['indice'] ?? null;
            $isticketcontract = isset($_POST['isticketcontract']) ? 1 : 0;
            $status = $_POST['status'] ?? 'actif';

            // Valider les données
            if (empty($clientId) || empty($contractTypeId) || empty($accessLevelId) || empty($name) || empty($startDate) || empty($endDate)) {
                $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
                $_SESSION['form_data'] = $_POST;
                
                // Gérer le retour en cas d'erreur de validation
                $returnTo = $_GET['return_to'] ?? 'index';
                if ($returnTo === 'view') {
                    header('Location: ' . BASE_URL . 'contracts/add/' . $clientId . '?return_to=view');
                } else {
                    header('Location: ' . BASE_URL . 'contracts/add/' . $clientId);
                }
                exit;
            }

            // Créer le contrat
            $result = $this->contractModel->createContract([
                'client_id' => $clientId,
                'rooms' => $rooms,
                'contract_type_id' => $contractTypeId,
                'access_level_id' => $accessLevelId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tickets_number' => $ticketsNumber,
                'tickets_remaining' => $ticketsNumber,
                'comment' => $comment,
                'reminder_enabled' => $reminderEnabled,
                'reminder_days' => $reminderDays,
                'renouvellement_tacite' => $renouvellementTacite,
                'num_facture' => $numFacture,
                'tarif' => $tarif,
                'indice' => $indice,
                'isticketcontract' => $isticketcontract,
                'status' => $status
            ]);

            if ($result) {
                // Récupérer les informations du type de contrat pour les interventions préventives
                $contractType = $this->contractModel->getContractTypeById($contractTypeId);
                $nbInterPrev = $contractType['nb_inter_prev'] ?? 0;
                
                // Vérifier que c'est un contrat non-ticket (tickets initiaux = 0)
                if ($nbInterPrev > 0 && $ticketsNumber == 0) {
                    // Récupérer les salles du contrat
                    $contractRooms = $this->contractModel->getContractRooms($result);
                    
                    if (!empty($contractRooms)) {
                        // Programmer les interventions préventives pour chaque salle
                        $scheduledInterventions = [];
                        foreach ($contractRooms as $room) {
                            $roomInterventions = DateUtils::schedulePreventiveInterventions(
                                $nbInterPrev,
                                $startDate,
                                $endDate,
                                '09:00', // Heure par défaut
                                $room // Passer les informations de la salle
                            );
                            
                            // Ajouter les interventions de cette salle
                            foreach ($roomInterventions as $intervention) {
                                $intervention['room_id'] = $room['room_id'];
                                $intervention['site_name'] = $room['site_name'];
                                $intervention['room_name'] = $room['room_name'];
                                $scheduledInterventions[] = $intervention;
                            }
                        }
                        
                        // Stocker les interventions programmées en session pour confirmation
                        $_SESSION['scheduled_interventions'] = $scheduledInterventions;
                        $_SESSION['contract_id'] = $result;
                        $_SESSION['client_id'] = $clientId;
                        $_SESSION['contract_name'] = $name;
                        $_SESSION['nb_interventions'] = count($scheduledInterventions);
                        $_SESSION['nb_rooms'] = count($contractRooms);
                        
                        $_SESSION['success'] = "Le contrat a été créé avec succès. " . count($scheduledInterventions) . " intervention(s) préventive(s) ont été programmées pour " . count($contractRooms) . " salle(s).";
                        header('Location: ' . BASE_URL . 'contracts/confirmPreventiveInterventions/' . $result);
                        exit;
                    } else {
                        $_SESSION['success'] = "Le contrat a été créé avec succès. Aucune salle associée, donc pas d'interventions préventives.";
                        
                        // Gérer le retour intelligent
                        $returnTo = $_GET['return_to'] ?? 'index';
                        if ($returnTo === 'view') {
                            header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=contracts-tab');
                        } else {
                            header('Location: ' . BASE_URL . 'contracts');
                        }
                        exit;
                    }
                } else {
                    if ($ticketsNumber > 0) {
                        $_SESSION['success'] = "Le contrat a été créé avec succès. Les interventions préventives ne sont pas disponibles pour les contrats à tickets.";
                    } else {
                        $_SESSION['success'] = "Le contrat a été créé avec succès.";
                    }
                    
                    // Gérer le retour intelligent
                    $returnTo = $_GET['return_to'] ?? 'index';
                    if ($returnTo === 'view') {
                        header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=contracts-tab');
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    exit;
                }
            } else {
                $_SESSION['error'] = "Une erreur est survenue lors de la création du contrat.";
                $_SESSION['form_data'] = $_POST;
                
                // Gérer le retour en cas d'erreur
                $returnTo = $_GET['return_to'] ?? 'index';
                if ($returnTo === 'view') {
                    header('Location: ' . BASE_URL . 'contracts/add/' . $clientId . '?return_to=view');
                } else {
                    header('Location: ' . BASE_URL . 'contracts/add/' . $clientId);
                }
                exit;
            }
        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::create : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la création du contrat.";
            $_SESSION['form_data'] = $_POST;
            
            // Gérer le retour en cas d'erreur
            $returnTo = $_GET['return_to'] ?? 'index';
            if ($returnTo === 'view') {
                header('Location: ' . BASE_URL . 'contracts/add/' . $clientId . '?return_to=view');
            } else {
                header('Location: ' . BASE_URL . 'contracts/add/' . $clientId);
            }
            exit;
        }
    }

    /**
     * Affiche la page de confirmation des interventions préventives
     */
    public function confirmPreventiveInterventions($contractId) {
        checkContractManagementAccess();

        try {
            // Vérifier si l'ID est valide
            if (!is_numeric($contractId) || $contractId <= 0) {
                $_SESSION['error'] = "ID de contrat invalide";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Vérifier que les données de session existent
            if (!isset($_SESSION['scheduled_interventions']) || !isset($_SESSION['contract_id'])) {
                $_SESSION['error'] = "Données de session manquantes pour la confirmation des interventions.";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Récupérer les données du contrat
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Récupérer les interventions programmées
            $scheduledInterventions = $_SESSION['scheduled_interventions'];
            $clientId = $_SESSION['client_id'];
            $contractName = $_SESSION['contract_name'];
            $nbInterventions = $_SESSION['nb_interventions'];

            // Récupérer les informations du client
            $client = $this->clientModel->getClientById($clientId);

            // Récupérer les techniciens disponibles
            require_once __DIR__ . '/../models/UserModel.php';
            $userModel = new UserModel($this->db);
            $technicians = $userModel->getTechnicians();

            // Récupérer les types d'intervention
            require_once __DIR__ . '/../models/InterventionModel.php';
            $interventionModel = new InterventionModel($this->db);
            $interventionTypes = $interventionModel->getAllTypes();

            // Charger la vue
            require_once VIEWS_PATH . '/contract/confirm_preventive_interventions.php';

        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::confirmPreventiveInterventions : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la confirmation des interventions préventives.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Crée les interventions préventives confirmées
     */
    public function createPreventiveInterventions() {
        checkContractManagementAccess();

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Vérifier que les données de session existent
            if (!isset($_SESSION['scheduled_interventions']) || !isset($_SESSION['contract_id'])) {
                $_SESSION['error'] = "Données de session manquantes pour la création des interventions.";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            $contractId = $_SESSION['contract_id'];
            $clientId = $_SESSION['client_id'];
            $scheduledInterventions = $_SESSION['scheduled_interventions'];

            // Récupérer les données du contrat
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Créer les interventions
            require_once __DIR__ . '/../models/InterventionModel.php';
            $interventionModel = new InterventionModel($this->db);

            custom_log("Début création des interventions préventives pour le contrat ID: $contractId", 'DEBUG');
            custom_log("Nombre d'interventions à créer: " . count($scheduledInterventions), 'DEBUG');

            $createdCount = 0;
            foreach ($scheduledInterventions as $index => $intervention) {
                // Récupérer les données du formulaire pour cette intervention
                $title = $_POST['title'][$index] ?? $intervention['title'];
                $date = $_POST['date'][$index] ?? $intervention['date'];
                $heure = $_POST['heure'][$index] ?? $intervention['heure'];
                $technicianId = $_POST['technician_id'][$index] ?? null;
                $typeId = $_POST['type_id'][$index] ?? 2; // Maintenance par défaut
                $description = $_POST['description'][$index] ?? $intervention['description'];
                
                // Ajouter le commentaire additionnel s'il existe
                if (isset($intervention['comment']) && !empty($intervention['comment'])) {
                    $description .= "\n\n" . $intervention['comment'];
                }

                // Récupérer les informations de salle si disponibles
                $roomId = $intervention['room_id'] ?? null;
                $siteId = null;
                
                // Si on a une room_id, récupérer le site_id correspondant
                if ($roomId) {
                    $roomQuery = "SELECT site_id FROM rooms WHERE id = :room_id";
                    $roomStmt = $this->db->prepare($roomQuery);
                    $roomStmt->execute([':room_id' => $roomId]);
                    $roomData = $roomStmt->fetch(PDO::FETCH_ASSOC);
                    $siteId = $roomData['site_id'] ?? null;
                }
                
                // Créer l'intervention
                $interventionData = [
                    'title' => $title,
                    'client_id' => $clientId,
                    'contract_id' => $contractId,
                    'site_id' => $siteId,
                    'room_id' => $roomId,
                    'technician_id' => !empty($technicianId) ? $technicianId : null,
                    'type_id' => $typeId,
                    'status_id' => 1, // Nouveau
                    'priority_id' => 5, // Préventif
                    'duration' => 2.0, // Durée par défaut
                    'description' => $description,
                    'date_planif' => $date,
                    'heure_planif' => $heure
                ];

                custom_log("Tentative de création d'intervention: " . json_encode($interventionData), 'DEBUG');

                if ($interventionModel->create($interventionData)) {
                    $createdCount++;
                    custom_log("Intervention créée avec succès (index: $index)", 'DEBUG');
                } else {
                    custom_log("Échec de création de l'intervention (index: $index)", 'ERROR');
                }
            }

            // Sauvegarder l'ID du contrat avant de nettoyer la session
            $contractId = $_SESSION['contract_id'];
            $isExistingContract = $_SESSION['is_existing_contract'] ?? false;
            
            // Nettoyer les données de session
            unset($_SESSION['scheduled_interventions']);
            unset($_SESSION['contract_id']);
            unset($_SESSION['client_id']);
            unset($_SESSION['contract_name']);
            unset($_SESSION['nb_interventions']);
            unset($_SESSION['nb_rooms']);
            unset($_SESSION['is_existing_contract']);

            $_SESSION['success'] = "{$createdCount} intervention(s) préventive(s) ont été créées avec succès.";
            
            // Rediriger vers le contrat si c'était un contrat existant
            if ($isExistingContract) {
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
            } else {
                header('Location: ' . BASE_URL . 'contracts');
            }
            exit;

        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::createPreventiveInterventions : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la création des interventions préventives.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Ignore la création des interventions préventives
     */
    public function ignorePreventiveInterventions() {
        checkContractManagementAccess();

        // Sauvegarder l'ID du contrat avant de nettoyer la session
        $contractId = $_SESSION['contract_id'] ?? null;
        $isExistingContract = $_SESSION['is_existing_contract'] ?? false;
        
        // Nettoyer les données de session
        unset($_SESSION['scheduled_interventions']);
        unset($_SESSION['contract_id']);
        unset($_SESSION['client_id']);
        unset($_SESSION['contract_name']);
        unset($_SESSION['nb_interventions']);
        unset($_SESSION['nb_rooms']);
        unset($_SESSION['is_existing_contract']);

        // Message adapté selon le contexte
        if ($isExistingContract) {
            $_SESSION['success'] = "Génération d'interventions préventives annulée.";
            header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
        } else {
            $_SESSION['success'] = "Le contrat a été créé avec succès. Les interventions préventives ont été ignorées.";
            header('Location: ' . BASE_URL . 'contracts');
        }
        exit;
    }

    /**
     * Affiche le formulaire d'édition d'un contrat
     */
    public function edit($id) {
        checkContractManagementAccess();

        try {
            // Vérifier si l'ID est valide
            if (!is_numeric($id) || $id <= 0) {
                $_SESSION['error'] = "ID de contrat invalide";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }

            // Récupérer les informations du contrat
            $contract = $this->contractModel->getContractById($id);
            
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }

            // Récupérer les informations du client
            $client = $this->clientModel->getClientById($contract['client_id']);

            // Récupérer toutes les salles du client pour le formulaire
            $allRooms = $this->contractModel->getRoomsForClient($contract['client_id']);

            // Récupérer les types de contrats disponibles
            $contractTypes = $this->contractModel->getContractTypes();

            // Récupérer les niveaux d'accès disponibles
            $accessLevels = $this->accessLevelModel->getAllAccessLevels();

            // Charger la vue avec les données
            require_once VIEWS_PATH . '/contract/edit.php';
        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::edit : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la préparation du formulaire d'édition de contrat.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Met à jour un contrat
     */
    public function update($id) {
        checkContractManagementAccess();

        try {
            // Vérifier si le formulaire a été soumis
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }

            // Vérifier si l'ID est valide
            if (!is_numeric($id) || $id <= 0) {
                $_SESSION['error'] = "ID de contrat invalide";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }

            // Récupérer les données du formulaire
            $clientId = $_POST['client_id'] ?? null;
            $rooms = $_POST['rooms'] ?? []; // Nouveau : tableau de salles
            $contractTypeId = $_POST['contract_type_id'] ?? null;
            $accessLevelId = $_POST['access_level_id'] ?? null;
            $name = $_POST['name'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $ticketsNumber = $_POST['tickets_number'] ?? 0;
            $ticketsRemaining = $_POST['tickets_remaining'] ?? 0;
            $comment = $_POST['comment'] ?? '';
            $reminderEnabled = isset($_POST['reminder_enabled']) ? 1 : 0;
            $reminderDays = $_POST['reminder_days'] ?? 30;
            $renouvellementTacite = isset($_POST['renouvellement_tacite']) ? 1 : 0;
            $numFacture = $_POST['num_facture'] ?? null;
            $tarif = $_POST['tarif'] ?? null;
            $indice = $_POST['indice'] ?? null;
            $isticketcontract = isset($_POST['isticketcontract']) ? 1 : 0;
            $status = $_POST['status'] ?? 'actif';

            // Valider les données
            if (empty($clientId) || empty($contractTypeId) || empty($accessLevelId) || empty($name) || empty($startDate) || empty($endDate)) {
                $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
                $_SESSION['form_data'] = $_POST;
                header('Location: ' . BASE_URL . 'contracts/edit/' . $id);
                exit;
            }

            // Récupérer l'ancien contrat pour comparer le niveau d'accès et l'historique
            $oldContract = $this->contractModel->getContractById($id);
            $oldAccessLevelId = $oldContract['access_level_id'] ?? null;
            $accessLevelChanged = ($oldAccessLevelId != $accessLevelId);
            
            // Vérification de sécurité : si on désactive les tickets, vérifier qu'il n'y a pas d'interventions qui ont décompté des tickets
            $oldIsticketcontract = $oldContract['isticketcontract'] ?? 0;
            if ($oldIsticketcontract == 1 && $isticketcontract == 0) {
                // Le contrat passait de "à tickets" à "sans tickets"
                // Vérifier s'il y a des interventions qui ont utilisé des tickets
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count, SUM(tickets_used) as total_tickets_used 
                    FROM interventions 
                    WHERE contract_id = ? AND tickets_used > 0
                ");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    // Il y a des interventions qui ont décompté des tickets
                    $totalTicketsUsed = $result['total_tickets_used'] ?? 0;
                    $interventionsCount = $result['count'];
                    
                    // Vérifier si l'utilisateur a confirmé la suppression des tickets
                    if (!isset($_POST['confirm_ticket_removal'])) {
                        // Afficher un message d'erreur avec demande de confirmation
                        $_SESSION['error'] = "⚠️ ATTENTION : Ce contrat a {$interventionsCount} intervention(s) qui ont décompté {$totalTicketsUsed} ticket(s). 
                        Désactiver le système de tickets remettra les champs 'tickets_number' et 'tickets_remaining' à 0. 
                        Cochez la case de confirmation si vous souhaitez continuer.";
                        $_SESSION['form_data'] = $_POST;
                        $_SESSION['show_ticket_removal_confirmation'] = true;
                        header('Location: ' . BASE_URL . 'contracts/edit/' . $id);
                        exit;
                    }
                }
            }

            // Si on désactive les tickets, remettre les tickets à 0
            if ($oldIsticketcontract == 1 && $isticketcontract == 0) {
                $ticketsNumber = 0;
                $ticketsRemaining = 0;
            }

            // Préparer les nouvelles données pour l'historique
            $newData = [
                'client_id' => $clientId,
                'contract_type_id' => $contractTypeId,
                'access_level_id' => $accessLevelId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tickets_number' => $ticketsNumber,
                'tickets_remaining' => $ticketsRemaining,
                'comment' => $comment,
                'reminder_enabled' => $reminderEnabled,
                'reminder_days' => $reminderDays,
                'renouvellement_tacite' => $renouvellementTacite,
                'num_facture' => $numFacture,
                'tarif' => $tarif,
                'indice' => $indice,
                'isticketcontract' => $isticketcontract,
                'status' => $status
            ];

            // Log des données reçues
            custom_log('[DEBUG] Données reçues pour updateContract : ' . json_encode([
                'id' => $id,
                'client_id' => $clientId,
                'rooms' => $rooms,
                'contract_type_id' => $contractTypeId,
                'access_level_id' => $accessLevelId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tickets_number' => $ticketsNumber,
                'tickets_remaining' => $ticketsRemaining,
                'comment' => $comment,
                'reminder_enabled' => $reminderEnabled,
                'reminder_days' => $reminderDays,
                'status' => $status
            ]), 'DEBUG');

            // Mettre à jour le contrat
            $result = $this->contractModel->updateContract($id, [
                'client_id' => $clientId,
                'rooms' => $rooms,
                'contract_type_id' => $contractTypeId,
                'access_level_id' => $accessLevelId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tickets_number' => $ticketsNumber,
                'tickets_remaining' => $ticketsRemaining,
                'comment' => $comment,
                'reminder_enabled' => $reminderEnabled,
                'reminder_days' => $reminderDays,
                'renouvellement_tacite' => $renouvellementTacite,
                'num_facture' => $numFacture,
                'tarif' => $tarif,
                'indice' => $indice,
                'isticketcontract' => $isticketcontract,
                'status' => $status
            ]);

            if ($result) {
                // Enregistrer les modifications dans l'historique
                $this->contractModel->recordChanges($id, $oldContract, $newData);
                
                $_SESSION['success'] = "Le contrat a été mis à jour avec succès.";
                
                // Si le niveau d'accès a changé, proposer de mettre à jour les matériels
                if ($accessLevelChanged) {
                    // Récupérer les informations sur les niveaux d'accès
                    $oldAccessLevel = $this->accessLevelModel->getAccessLevelById($oldAccessLevelId);
                    $newAccessLevel = $this->accessLevelModel->getAccessLevelById($accessLevelId);
                    
                    // Compter les matériels affectés
                    $affectedMaterials = $this->countMaterialsForContract($id);
                    
                    if ($affectedMaterials > 0) {
                        $_SESSION['access_level_change'] = [
                            'contract_id' => $id,
                            'old_level' => $oldAccessLevel,
                            'new_level' => $newAccessLevel,
                            'affected_materials' => $affectedMaterials
                        ];
                        
                        // Rediriger vers une page de confirmation
                        header('Location: ' . BASE_URL . 'contracts/confirm_access_level_change/' . $id);
                        exit;
                    }
                }
                
                header('Location: ' . BASE_URL . 'contracts/view/' . $id);
                exit;
            } else {
                $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour du contrat.";
                $_SESSION['form_data'] = $_POST;
                header('Location: ' . BASE_URL . 'contracts/edit/' . $id);
                exit;
            }
        } catch (Exception $e) {
            custom_log("[ERROR] Exception dans ContractController::update : " . $e->getMessage() . "\nPOST : " . json_encode($_POST), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour du contrat.";
            $_SESSION['form_data'] = $_POST;
            header('Location: ' . BASE_URL . 'contracts/edit/' . $id);
            exit;
        }
    }

    /**
     * Supprime un contrat
     */
    public function delete($id) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les contrats.");

        // Déterminer l'URL de redirection
        $redirect_url = BASE_URL . 'contracts'; // URL de redirection par défaut
        if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
            // Idéalement, valider que HTTP_REFERER est une URL interne et sûre
            // Pour l'instant, on l'utilise directement si elle est définie.
            $redirect_url = $_SERVER['HTTP_REFERER'];
        }

        try {
            // Vérifier si l'ID est valide
            if (!is_numeric($id) || $id <= 0) {
                $_SESSION['error'] = "ID de contrat invalide";
                header('Location: ' . $redirect_url);
                exit;
            }

            // Tenter de supprimer le contrat directement
            $result = $this->contractModel->deleteContract($id);

            if ($result) {
                $_SESSION['success'] = "Le contrat a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Impossible de supprimer le contrat. Il se peut qu'il n\'existe pas ou qu\'il soit lié à des interventions.";
            }
            header('Location: ' . $redirect_url);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::delete : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur technique est survenue lors de la tentative de suppression du contrat.";
            header('Location: ' . $redirect_url);
            exit;
        }
    }

    public function view($id) {
        // Vérifier les permissions
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Récupérer le contrat avec les informations associées
            $contract = $this->contractModel->getContractById($id);
            
            if (!$contract) {
                $_SESSION['error'] = "Contrat introuvable.";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Récupérer les interventions associées
            $sql_interventions = "SELECT i.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as technician_name,
                    ist.name as status_name,
                    ist.color as status_color
                    FROM interventions i
                    LEFT JOIN users u ON i.technician_id = u.id
                    LEFT JOIN intervention_statuses ist ON i.status_id = ist.id
                    WHERE i.contract_id = ?
                    ORDER BY COALESCE(i.date_planif, i.created_at) DESC";
            
            $stmt_interventions = $this->db->prepare($sql_interventions);
            $stmt_interventions->execute([$id]);
            $interventions = $stmt_interventions->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les pièces jointes
            $attachments = $this->contractModel->getPiecesJointes($id);

            // Récupérer l'historique du contrat
            $history = $this->contractModel->getContractHistory($id);

            // Charger la vue
            require_once __DIR__ . '/../views/contract/view.php';
        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::view : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de l'affichage du contrat.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Endpoint AJAX pour récupérer les salles d'un client
     */
    public function getRoomsForClient($clientId) {
        $this->checkAccess();
        
        try {
            if (!$clientId || !is_numeric($clientId)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID client invalide']);
                return;
            }

            $rooms = $this->contractModel->getRoomsForClient($clientId);
            
            header('Content-Type: application/json');
            echo json_encode(['rooms' => $rooms]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des salles']);
        }
    }

    /**
     * Compte les matériels associés à un contrat
     */
    private function countMaterialsForContract($contractId) {
        $query = "SELECT COUNT(*) as count 
                  FROM materiel m 
                  INNER JOIN contract_rooms cr ON m.salle_id = cr.room_id 
                  WHERE cr.contract_id = :contract_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':contract_id' => $contractId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Affiche la page de confirmation pour la mise à jour des matériels
     */
    public function confirmAccessLevelChange($contractId) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les contrats.");
        
        if (!isset($_SESSION['access_level_change']) || $_SESSION['access_level_change']['contract_id'] != $contractId) {
            $_SESSION['error'] = "Session de confirmation invalide.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
        
        $changeData = $_SESSION['access_level_change'];
        $contract = $this->contractModel->getContractById($contractId);
        
        if (!$contract) {
            $_SESSION['error'] = "Contrat introuvable.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
        
        // Charger la vue de confirmation
        require_once VIEWS_PATH . '/contract/confirm_access_level_change.php';
    }

    /**
     * Traite la mise à jour des matériels suite au changement de niveau d'accès
     */
    public function applyAccessLevelChange($contractId) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les contrats.");
        
        if (!isset($_SESSION['access_level_change']) || $_SESSION['access_level_change']['contract_id'] != $contractId) {
            $_SESSION['error'] = "Session de confirmation invalide.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
        
        try {
            $changeData = $_SESSION['access_level_change'];
            $newAccessLevelId = $changeData['new_level']['id'];
            
            // Récupérer les règles de visibilité du nouveau niveau d'accès
            $visibilityRules = $this->accessLevelModel->getVisibilityRulesForLevel($newAccessLevelId);
            
            // Log des règles de visibilité
            custom_log("[DEBUG] Règles de visibilité pour le niveau d'accès $newAccessLevelId : " . json_encode($visibilityRules), 'DEBUG');
            
            // Récupérer tous les matériels des salles associées au contrat
            $query = "SELECT m.id FROM materiel m 
                     INNER JOIN contract_rooms cr ON m.salle_id = cr.room_id
                     WHERE cr.contract_id = :contract_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':contract_id' => $contractId]);
            $materiels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            custom_log("[DEBUG] Matériels trouvés pour le contrat $contractId : " . count($materiels), 'DEBUG');
            
            // Mettre à jour la visibilité pour chaque matériel
            $materielModel = new MaterielModel($this->db);
            $affectedRows = 0;
            
            foreach ($materiels as $materiel) {
                $materielId = $materiel['id'];
                
                // Préparer les visibilités basées sur les règles du niveau d'accès
                $visibilites = [];
                foreach ($visibilityRules as $champ => $visible) {
                    $visibilites[$champ] = $visible;
                }
                
                custom_log("[DEBUG] Mise à jour matériel $materielId avec visibilités : " . json_encode($visibilites), 'DEBUG');
                
                // Sauvegarder les visibilités pour ce matériel
                if ($materielModel->saveVisibiliteChamps($materielId, $visibilites)) {
                    $affectedRows++;
                    custom_log("[DEBUG] Matériel $materielId mis à jour avec succès", 'DEBUG');
                } else {
                    custom_log("[ERROR] Échec de la mise à jour du matériel $materielId", 'ERROR');
                }
            }
            
            // $affectedRows est déjà calculé dans la boucle
            
            // Nettoyer la session
            unset($_SESSION['access_level_change']);
            
            $_SESSION['success'] = "Le niveau d'accès a été mis à jour et $affectedRows matériel(s) ont été modifié(s).";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
            
        } catch (Exception $e) {
            custom_log("[ERROR] Exception dans ContractController::applyAccessLevelChange : " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour des matériels.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Ignore la mise à jour des matériels
     */
    public function ignoreAccessLevelChange($contractId) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les contrats.");
        
        if (!isset($_SESSION['access_level_change']) || $_SESSION['access_level_change']['contract_id'] != $contractId) {
            $_SESSION['error'] = "Session de confirmation invalide.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
        
        // Nettoyer la session
        unset($_SESSION['access_level_change']);
        
        $_SESSION['success'] = "Le contrat a été mis à jour. Les matériels n'ont pas été modifiés.";
        header('Location: ' . BASE_URL . 'contracts');
        exit;
    }

    /**
     * Endpoint AJAX pour charger les salles d'un client avec HTML formaté
     */
    public function load_client_rooms() {
        $this->checkAccess();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                return;
            }

            $clientId = $_POST['client_id'] ?? null;
            $contractId = $_POST['contract_id'] ?? null;

            if (!$clientId || !is_numeric($clientId)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID client invalide']);
                return;
            }

            // Récupérer les salles du client
            $rooms = $this->contractModel->getRoomsForClient($clientId);
            
            // Récupérer les salles déjà associées au contrat (si en mode édition)
            $selectedRooms = [];
            if ($contractId && is_numeric($contractId)) {
                $contract = $this->contractModel->getContractById($contractId);
                if ($contract) {
                    $selectedRooms = $this->contractModel->getContractRooms($contractId);
                }
            }

            // Grouper les salles par site
            $sites = [];
            foreach ($rooms as $room) {
                $siteName = $room['site_name'] ?? 'Site inconnu';
                if (!isset($sites[$siteName])) {
                    $sites[$siteName] = [];
                }
                $sites[$siteName][] = $room;
            }

            // Générer le HTML
            ob_start();
            if (!empty($sites)) {
                foreach ($sites as $siteName => $siteRooms) {
                    echo '<div class="mb-3">';
                    echo '<div class="d-flex align-items-center mb-2">';
                    echo '<i class="fas fa-building text-primary me-2"></i>';
                    echo '<strong class="text-primary">' . h($siteName) . '</strong>';
                    echo '</div>';
                    echo '<div class="ms-4">';
                    
                    foreach ($siteRooms as $room) {
                        $roomId = $room['id'];
                        $isChecked = in_array($roomId, array_column($selectedRooms, 'room_id'));
                        
                        echo '<div class="form-check mb-1">';
                        echo '<input class="form-check-input" type="checkbox" ';
                        echo 'id="room_' . $roomId . '" name="rooms[]" ';
                        echo 'value="' . $roomId . '"';
                        if ($isChecked) {
                            echo ' checked';
                        }
                        echo '>';
                        echo '<label class="form-check-label" for="room_' . $roomId . '">';
                        echo '<i class="fas fa-door-open text-muted me-1"></i>';
                        echo h($room['name']);
                        echo '</label>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="text-center text-muted">';
                echo '<i class="fas fa-info-circle"></i>';
                echo ' Aucune salle disponible pour ce client';
                echo '</div>';
            }
            
            $html = ob_get_clean();
            
            header('Content-Type: application/json');
            echo json_encode(['html' => $html]);
            
        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::load_client_rooms : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors du chargement des salles']);
        }
    }

    /**
     * Ajoute une pièce jointe à un contrat
     */
    public function addAttachment($contractId) {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
            exit;
        }

        try {
            // Vérifier que le contrat existe
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                throw new Exception("Contrat non trouvé");
            }

            // Vérifier qu'un fichier a été uploadé
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erreur lors de l'upload du fichier");
            }

            $file = $_FILES['attachment'];
            $originalFileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmpPath = $file['tmp_name'];

            // Vérifier la taille du fichier (limite du serveur)
            $maxFileSize = getServerMaxUploadSize();
            if ($fileSize > $maxFileSize) {
                throw new Exception("Le fichier est trop volumineux (max " . formatFileSize($maxFileSize) . ")");
            }

            // Vérifier l'extension
            require_once INCLUDES_PATH . '/FileUploadValidator.php';
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                throw new Exception("Ce format n'est pas accepté, rapprochez-vous de l'administrateur du site, ou utilisez un format compressé.");
            }

            // Créer le répertoire de destination (à la racine du site, pas dans public/)
            $uploadDir = __DIR__ . '/../../uploads/contracts/' . $contractId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Générer un nom de fichier unique
            $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFileName);
            $finalFileName = time() . '_' . $fileName;
            $filePath = $uploadDir . '/' . $finalFileName;

            // Déplacer le fichier
            if (move_uploaded_file($fileTmpPath, $filePath)) {
                // Préparer les données pour la base
                $data = [
                    'nom_fichier' => $originalFileName,
                    'chemin_fichier' => 'uploads/contracts/' . $contractId . '/' . $finalFileName,
                    'type_fichier' => $fileExtension,
                    'taille_fichier' => $fileSize,
                    'commentaire' => $_POST['description'] ?? null,
                    'masque_client' => isset($_POST['masque_client']) ? 1 : 0,
                    'created_by' => $_SESSION['user']['id']
                ];

                // Ajouter la pièce jointe
                $pieceJointeId = $this->contractModel->addPieceJointe($contractId, $data);
                
                $_SESSION['success'] = "Pièce jointe ajoutée avec succès";
            } else {
                throw new Exception("Erreur lors du déplacement du fichier");
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
        exit;
    }

    /**
     * Ajoute plusieurs pièces jointes à un contrat (Drag & Drop)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function addMultipleAttachments($contractId) {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }

        try {
            // Vérifier que le contrat existe
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                throw new Exception("Contrat non trouvé");
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            // Utiliser AttachmentService pour gérer l'upload
            $attachmentService = new AttachmentService($this->db);
            
            // Préparer les options
            $options = [
                'descriptions' => $_POST['file_description'] ?? [],
                'masque_client' => $_POST['file_masque_client'] ?? []
            ];

            // Upload des fichiers
            $result = $attachmentService->upload(
                AttachmentService::TYPE_CONTRACT,
                $contractId,
                $_FILES['attachments'],
                $options,
                $_SESSION['user']['id']
            );

            // Retourner le résultat
            header('Content-Type: application/json');
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => count($result['uploaded_files']) . ' fichier(s) uploadé(s) avec succès',
                    'uploaded_files' => $result['uploaded_files']
                ]);
            } else {
                $errorMessage = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Aucun fichier uploadé';
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'uploaded_files' => $result['uploaded_files']
                ]);
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout des pièces jointes : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Supprime une pièce jointe d'un contrat
     * Utilise AttachmentService pour centraliser la logique
     */
    public function deleteAttachment($contractId, $pieceJointeId) {
        $this->checkAccess();

        try {
            // Utiliser AttachmentService pour gérer la suppression
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->delete($pieceJointeId, AttachmentService::TYPE_CONTRACT, $contractId);
            
            $_SESSION['success'] = "Pièce jointe supprimée avec succès";

        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la suppression de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
        exit;
    }

    /**
     * Télécharge une pièce jointe
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($pieceJointeId) {
        $this->checkAccess();

        try {
            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->download($pieceJointeId, true);

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Aperçu d'une pièce jointe
     */
    public function preview($attachmentId) {
        $this->checkAccess();

        try {
            // Log pour débogage
            custom_log("Tentative d'aperçu contrat pour l'ID: " . $attachmentId, 'DEBUG');
            
            // Récupérer les informations de la pièce jointe
            $pieceJointe = $this->contractModel->getPieceJointeById($attachmentId);
            
            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée");
            }

            custom_log("Pièce jointe trouvée: " . json_encode($pieceJointe), 'DEBUG');

            $filePath = __DIR__ . '/../../' . $pieceJointe['chemin_fichier'];
            custom_log("Chemin du fichier: " . $filePath, 'DEBUG');
            
            if (!file_exists($filePath)) {
                custom_log("Le fichier n'existe pas: " . $filePath, 'ERROR');
                throw new Exception("Fichier non trouvé");
            }

            custom_log("Fichier trouvé, taille: " . filesize($filePath), 'DEBUG');

            // Définir les en-têtes pour l'aperçu
            $mimeType = mime_content_type($filePath);
            custom_log("Type MIME détecté: " . ($mimeType ?: 'null'), 'DEBUG');
            
            if (!$mimeType) {
                // Fallback basé sur l'extension si mime_content_type échoue
                $extension = strtolower(pathinfo($pieceJointe['nom_fichier'], PATHINFO_EXTENSION));
                switch ($extension) {
                    case 'pdf':
                        $mimeType = 'application/pdf';
                        break;
                    case 'jpg':
                    case 'jpeg':
                        $mimeType = 'image/jpeg';
                        break;
                    case 'png':
                        $mimeType = 'image/png';
                        break;
                    case 'gif':
                        $mimeType = 'image/gif';
                        break;
                    case 'webp':
                        $mimeType = 'image/webp';
                        break;
                    case 'svg':
                        $mimeType = 'image/svg+xml';
                        break;
                    case 'bmp':
                        $mimeType = 'image/bmp';
                        break;
                    default:
                        $mimeType = 'application/octet-stream';
                        break;
                }
            }
            
            header('Content-Type: ' . $mimeType);
            
            // Pour les images et PDFs, utiliser inline pour la prévisualisation
            if (strpos($mimeType, 'image/') === 0 || $mimeType === 'application/pdf') {
                header('Content-Disposition: inline; filename="' . $pieceJointe['nom_fichier'] . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $pieceJointe['nom_fichier'] . '"');
            }

            header('Content-Length: ' . filesize($filePath));
            // Headers plus permissifs pour la prévisualisation
            header('Cache-Control: public, max-age=3600');
            header('X-Content-Type-Options: nosniff');

            // Nettoyer les buffers de sortie avant d'envoyer le fichier
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Lire et envoyer le fichier
            readfile($filePath);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Change la visibilité d'une pièce jointe
     */
    public function toggleAttachmentVisibility($contractId, $pieceJointeId) {
        $this->checkAccess();

        try {
            // Récupérer les informations de la pièce jointe
            $attachments = $this->contractModel->getPiecesJointes($contractId);
            $pieceJointe = null;
            
            foreach ($attachments as $piece) {
                if ($piece['id'] == $pieceJointeId) {
                    $pieceJointe = $piece;
                    break;
                }
            }

            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Inverser la visibilité
            $newVisibility = $pieceJointe['masque_client'] == 1 ? 0 : 1;
            $this->contractModel->updatePieceJointeVisibility($pieceJointeId, $newVisibility);
            
            $_SESSION['success'] = $newVisibility == 1 ? 
                "Pièce jointe masquée aux clients" : 
                "Pièce jointe rendue visible aux clients";

        } catch (Exception $e) {
            custom_log("Erreur lors du changement de visibilité : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du changement de visibilité : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
        exit;
    }

    /**
     * Ajoute des tickets à un contrat
     */
    public function addTickets($contractId) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les contrats.");

        try {
            // Debug: Log des données reçues
            custom_log("DEBUG - addTickets appelé avec contractId: $contractId", 'DEBUG');
            custom_log("DEBUG - POST data: " . json_encode($_POST), 'DEBUG');

            // Vérifier si le formulaire a été soumis
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                custom_log("DEBUG - Méthode non POST, redirection", 'DEBUG');
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            // Récupérer les données du formulaire
            $ticketsToAdd = (int)($_POST['tickets_to_add'] ?? 0);
            $addTicketsDate = $_POST['add_tickets_date'] ?? date('Y-m-d');
            $addTicketsComment = trim($_POST['add_tickets_comment'] ?? '');
            $newNumFacture = trim($_POST['new_num_facture'] ?? '');
            $extendContract = isset($_POST['extend_contract']) && $_POST['extend_contract'] == '1';

            custom_log("DEBUG - Tickets à ajouter: $ticketsToAdd", 'DEBUG');
            custom_log("DEBUG - Date: $addTicketsDate", 'DEBUG');
            custom_log("DEBUG - Commentaire: $addTicketsComment", 'DEBUG');
            custom_log("DEBUG - Nouveau numéro de facture: $newNumFacture", 'DEBUG');
            custom_log("DEBUG - Prolonger contrat: " . ($extendContract ? 'OUI' : 'NON'), 'DEBUG');

            // Validation
            if ($ticketsToAdd <= 0) {
                $_SESSION['error'] = "Le nombre de tickets à ajouter doit être supérieur à 0.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            if (empty($addTicketsDate)) {
                $_SESSION['error'] = "La date d'ajout est obligatoire.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            // Récupérer le contrat
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé.";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            custom_log("DEBUG - Contrat trouvé: " . json_encode($contract), 'DEBUG');

            // Vérifier que c'est un contrat de type ticket
            if (!isContractTicketById($contract['id'])) {
                $_SESSION['error'] = "Ce contrat n'est pas de type ticket.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            // Mettre à jour le nombre de tickets
            $oldTicketsNumber = $contract['tickets_number'];
            $oldTicketsRemaining = $contract['tickets_remaining'];
            $newTicketsNumber = $oldTicketsNumber + $ticketsToAdd;
            $newTicketsRemaining = $oldTicketsRemaining + $ticketsToAdd;

            custom_log("DEBUG - Anciens tickets: $oldTicketsNumber, Nouveaux: $newTicketsNumber", 'DEBUG');
            custom_log("DEBUG - Anciens restants: $oldTicketsRemaining, Nouveaux: $newTicketsRemaining", 'DEBUG');

            // Gérer l'upload de l'avenant si fourni
            $avenantUploaded = false;
            if (isset($_FILES['avenant_file']) && $_FILES['avenant_file']['error'] === UPLOAD_ERR_OK) {
                try {
                    $file = $_FILES['avenant_file'];
                    $originalFileName = $file['name'];
                    $fileSize = $file['size'];
                    $fileTmpPath = $file['tmp_name'];

                    // Vérifier la taille du fichier (limite du serveur)
                    $maxFileSize = getServerMaxUploadSize();
                    if ($fileSize > $maxFileSize) {
                        throw new Exception("Le fichier avenant est trop volumineux (max " . formatFileSize($maxFileSize) . ")");
                    }

                    // Vérifier l'extension
                    require_once INCLUDES_PATH . '/FileUploadValidator.php';
                    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                    if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                        throw new Exception("Le format du fichier avenant n'est pas accepté");
                    }

                    // Créer le répertoire de destination (à la racine du site, pas dans public/)
                    $uploadDir = __DIR__ . '/../../uploads/contracts/' . $contractId;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Générer un nom de fichier unique
                    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFileName);
                    $finalFileName = time() . '_avenant_' . $fileName;
                    $filePath = $uploadDir . '/' . $finalFileName;

                    // Déplacer le fichier
                    if (move_uploaded_file($fileTmpPath, $filePath)) {
                        // Ajouter la pièce jointe
                        $data = [
                            'nom_fichier' => $originalFileName,
                            'chemin_fichier' => 'uploads/contracts/' . $contractId . '/' . $finalFileName,
                            'type_fichier' => $fileExtension,
                            'taille_fichier' => $fileSize,
                            'commentaire' => 'Avenant pour ajout de ' . $ticketsToAdd . ' tickets - ' . $addTicketsComment,
                            'masque_client' => 0,
                            'created_by' => $_SESSION['user']['id']
                        ];

                        $this->contractModel->addPieceJointe($contractId, $data);
                        $avenantUploaded = true;
                        custom_log("DEBUG - Avenant uploadé avec succès: $originalFileName", 'DEBUG');
                    } else {
                        throw new Exception("Erreur lors du déplacement du fichier avenant");
                    }
                } catch (Exception $e) {
                    custom_log("Erreur lors de l'upload de l'avenant : " . $e->getMessage(), 'ERROR');
                    $_SESSION['error'] = "Erreur lors de l'upload de l'avenant : " . $e->getMessage();
                    header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                    exit;
                }
            }

            // Mettre à jour le contrat (tickets + numéro de facture si fourni)
            $updateFields = [
                'tickets_number' => $newTicketsNumber,
                'tickets_remaining' => $newTicketsRemaining,
                'updated_at' => 'NOW()'
            ];
            
            $oldNumFacture = $contract['num_facture'];
            if (!empty($newNumFacture)) {
                $updateFields['num_facture'] = $newNumFacture;
                custom_log("DEBUG - Mise à jour numéro de facture: '$oldNumFacture' → '$newNumFacture'", 'DEBUG');
            }

            // Gérer la prolongation du contrat si demandée
            $oldEndDate = $contract['end_date'];
            if ($extendContract) {
                // Calculer la nouvelle date de fin : 31 décembre de l'année suivante (règle 1 an + fin d'année)
                $currentYear = date('Y');
                $nextYear = $currentYear + 1;
                $newEndDate = $nextYear . '-12-31';
                $updateFields['end_date'] = $newEndDate;
                custom_log("DEBUG - Prolongation contrat: '$oldEndDate' → '$newEndDate'", 'DEBUG');
            }

            // Construire la requête SQL dynamiquement
            $setClause = [];
            $params = [];
            foreach ($updateFields as $field => $value) {
                if ($value === 'NOW()') {
                    $setClause[] = "$field = NOW()";
                } else {
                    $setClause[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            $params[':contract_id'] = $contractId;

            $sql = "UPDATE contracts SET " . implode(', ', $setClause) . " WHERE id = :contract_id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            custom_log("DEBUG - Update SQL result: " . ($result ? 'success' : 'failed'), 'DEBUG');

            if ($result) {
                // Enregistrer dans l'historique
                $this->contractModel->recordTicketAddition(
                    $contractId, 
                    $ticketsToAdd, 
                    $addTicketsDate,
                    $addTicketsComment,
                    $oldNumFacture,
                    $newNumFacture,
                    $extendContract ? $oldEndDate : null,
                    $extendContract ? $newEndDate : null
                );

                custom_log("DEBUG - Historique enregistré", 'DEBUG');
                
                $successMessage = "$ticketsToAdd tickets ajoutés avec succès au contrat.";
                if (!empty($newNumFacture)) {
                    $successMessage .= " Numéro de facture mis à jour.";
                }
                if ($extendContract) {
                    $nextYear = date('Y') + 1;
                    $successMessage .= " Contrat prolongé jusqu'au 31/12/" . $nextYear . ".";
                }
                if ($avenantUploaded) {
                    $successMessage .= " Avenant uploadé.";
                }
                
                $_SESSION['success'] = $successMessage;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout des tickets.";
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout de tickets : " . $e->getMessage(), 'ERROR');
            custom_log("DEBUG - Stack trace: " . $e->getTraceAsString(), 'DEBUG');
            $_SESSION['error'] = "Une erreur est survenue lors de l'ajout des tickets.";
        }

        custom_log("DEBUG - Redirection vers: " . BASE_URL . 'contracts/view/' . $contractId, 'DEBUG');
        header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
        exit;
    }

    /**
     * Vérifie les droits de renouvellement de contrat
     */
    private function checkRenewalRights() {
        // Vérifier que l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier que l'utilisateur est admin ou staff avec droits
        if (!isStaff()) {
            $_SESSION['error'] = "Vous n'avez pas les droits pour renouveler un contrat.";
            header('Location: ' . BASE_URL . 'contracts');
            exit;
        }
    }

    /**
     * Renouvelle un contrat avec renouvellement tacite
     */
    public function renew($contractId) {
        $this->checkRenewalRights();

        try {
            // Vérifier si le formulaire a été soumis
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            // Récupérer les données du formulaire
            $newContractName = trim($_POST['new_contract_name'] ?? '');
            $renewalComment = trim($_POST['renewal_comment'] ?? '');
            $resetTickets = isset($_POST['reset_tickets']) ? true : false;

            // Validation
            if (empty($newContractName)) {
                $_SESSION['error'] = "Le nom du nouveau contrat est obligatoire.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            // Récupérer le contrat actuel
            $currentContract = $this->contractModel->getContractById($contractId);
            if (!$currentContract) {
                $_SESSION['error'] = "Contrat non trouvé.";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Vérification du renouvellement tacite supprimée - autorisation sans contrainte

            // Vérifier que le contrat peut être renouvelé (30 jours avant la fin OU après la fin)
            $endDate = new DateTime($currentContract['end_date']);
            $today = new DateTime();
            $daysUntilEnd = $today->diff($endDate)->days;
            $isExpired = $today > $endDate;
            $canRenew = ($daysUntilEnd <= 30 && $daysUntilEnd >= 0) || $isExpired;
            
            if (!$canRenew) {
                $_SESSION['error'] = "Le contrat ne peut être renouvelé que dans les 30 jours précédant sa date de fin ou après son expiration.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            // Calculer les dates du nouveau contrat
            if ($isExpired) {
                // Si le contrat est expiré, commencer le nouveau contrat aujourd'hui
                $newStartDate = date('Y-m-d');
                $newEndDate = date('Y-m-d', strtotime($newStartDate . ' +364 days'));
            } else {
                // Si le contrat n'est pas encore expiré, commencer le jour après la fin
                $newStartDate = date('Y-m-d', strtotime($currentContract['end_date'] . ' +1 day'));
                $newEndDate = date('Y-m-d', strtotime($newStartDate . ' +364 days'));
            }

            // Récupérer les salles du contrat actuel
            $currentRooms = $this->contractModel->getContractRooms($contractId);
            $roomIds = array_column($currentRooms, 'room_id');

            // Déterminer les tickets pour le nouveau contrat
            $newTicketsNumber = $currentContract['tickets_number'];
            $newTicketsRemaining = isContractTicketById($currentContract['id']) ? 
                ($resetTickets ? $currentContract['tickets_number'] : $currentContract['tickets_remaining']) : 
                0;

            // Créer le nouveau contrat
            $newContractData = [
                'client_id' => $currentContract['client_id'],
                'rooms' => $roomIds,
                'contract_type_id' => $currentContract['contract_type_id'],
                'access_level_id' => $currentContract['access_level_id'],
                'name' => $newContractName,
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'tickets_number' => $newTicketsNumber,
                'tickets_remaining' => $newTicketsRemaining,
                'comment' => $currentContract['comment'] . "\n\nRenouvellement automatique du contrat #" . $currentContract['id'] . "\n" . $renewalComment,
                'reminder_enabled' => $currentContract['reminder_enabled'],
                'reminder_days' => $currentContract['reminder_days'],
                'renouvellement_tacite' => $currentContract['renouvellement_tacite'],
                'status' => 'actif'
            ];

            $newContractId = $this->contractModel->createContract($newContractData);

            if ($newContractId) {
                // Désactiver le contrat actuel
                $this->contractModel->updateContract($contractId, [
                    'client_id' => $currentContract['client_id'],
                    'rooms' => $roomIds,
                    'contract_type_id' => $currentContract['contract_type_id'],
                    'access_level_id' => $currentContract['access_level_id'],
                    'name' => $currentContract['name'],
                    'start_date' => $currentContract['start_date'],
                    'end_date' => $currentContract['end_date'],
                    'tickets_number' => $currentContract['tickets_number'],
                    'tickets_remaining' => $currentContract['tickets_remaining'],
                    'comment' => $currentContract['comment'],
                    'reminder_enabled' => $currentContract['reminder_enabled'],
                    'reminder_days' => $currentContract['reminder_days'],
                    'renouvellement_tacite' => $currentContract['renouvellement_tacite'],
                    'status' => 'inactif'
                ]);

                // Enregistrer le renouvellement dans l'historique du contrat actuel
                $resetTicketsForHistory = isContractTicketById($currentContract['id']) ? $resetTickets : false;
                $this->contractModel->recordRenewal($contractId, $newContractId, $newContractName, $renewalComment, $resetTicketsForHistory);

                $_SESSION['success'] = "Le contrat a été renouvelé avec succès. Nouveau contrat créé : #$newContractId";
                header('Location: ' . BASE_URL . 'contracts/view/' . $newContractId);
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la création du nouveau contrat.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

        } catch (Exception $e) {
            custom_log("Erreur lors du renouvellement du contrat : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors du renouvellement du contrat.";
            header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
            exit;
        }
    }

    /**
     * Affiche la page de confirmation des interventions préventives pour un contrat existant
     */
    public function generatePreventiveInterventions($contractId) {
        checkContractManagementAccess();

        try {
            // Vérifier que l'ID est valide
            if (!is_numeric($contractId) || $contractId <= 0) {
                $_SESSION['error'] = "ID de contrat invalide";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Récupérer les données du contrat
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Vérifier que c'est un contrat sans tickets
            if (isContractTicketById($contract['id'])) {
                $_SESSION['error'] = "Les interventions préventives ne sont disponibles que pour les contrats sans tickets.";
                header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Traitement du formulaire de génération
                $nbInterventions = (int)($_POST['nb_interventions'] ?? 4);
                $defaultHour = $_POST['default_hour'] ?? '09:00';
                $interventionComment = $_POST['intervention_comment'] ?? '';

                // Valider les données
                if ($nbInterventions < 1 || $nbInterventions > 12) {
                    $_SESSION['error'] = "Le nombre d'interventions doit être entre 1 et 12.";
                    header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                    exit;
                }

                // Récupérer les salles du contrat
                $contractRooms = $this->contractModel->getContractRooms($contractId);
                
                if (empty($contractRooms)) {
                    $_SESSION['error'] = "Ce contrat n'a pas de salles associées. Impossible de créer des interventions préventives.";
                    header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
                    exit;
                }

                // Programmer les interventions préventives pour chaque salle
                $scheduledInterventions = [];
                foreach ($contractRooms as $room) {
                    $roomInterventions = DateUtils::schedulePreventiveInterventions(
                        $nbInterventions,
                        $contract['start_date'],
                        $contract['end_date'],
                        $defaultHour,
                        $room
                    );
                    
                    // Ajouter les interventions de cette salle
                    foreach ($roomInterventions as $intervention) {
                        $intervention['room_id'] = $room['room_id'];
                        $intervention['site_name'] = $room['site_name'];
                        $intervention['room_name'] = $room['room_name'];
                        $intervention['comment'] = $interventionComment;
                        $scheduledInterventions[] = $intervention;
                    }
                }

                // Stocker les interventions programmées en session pour confirmation
                $_SESSION['scheduled_interventions'] = $scheduledInterventions;
                $_SESSION['contract_id'] = $contractId;
                $_SESSION['client_id'] = $contract['client_id'];
                $_SESSION['contract_name'] = $contract['name'];
                $_SESSION['nb_interventions'] = count($scheduledInterventions);
                $_SESSION['nb_rooms'] = count($contractRooms);
                $_SESSION['is_existing_contract'] = true; // Marquer que c'est un contrat existant

                header('Location: ' . BASE_URL . 'contracts/confirmPreventiveInterventions/' . $contractId);
                exit;
            } else {
                // Affichage du formulaire de génération
                $this->showGeneratePreventiveForm($contractId);
            }

        } catch (Exception $e) {
            custom_log("Erreur dans ContractController::generatePreventiveInterventions : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la génération des interventions préventives.";
            header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
            exit;
        }
    }

    /**
     * Affiche le formulaire de génération d'interventions préventives
     */
    private function showGeneratePreventiveForm($contractId) {
        checkContractManagementAccess();

        try {
            // Récupérer les données du contrat
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé";
                header('Location: ' . BASE_URL . 'contracts');
                exit;
            }

            // Récupérer les salles du contrat
            $contractRooms = $this->contractModel->getContractRooms($contractId);

            // Définir les variables de page
            setPageVariables('Générer des interventions préventives', 'contracts');
            $currentPage = 'contracts';

            // Inclure les vues
            include_once __DIR__ . '/../includes/header.php';
            include_once __DIR__ . '/../includes/sidebar.php';
            include_once __DIR__ . '/../includes/navbar.php';
            include_once VIEWS_PATH . '/contract/generate_preventive_form.php';
            include_once __DIR__ . '/../includes/footer.php';

        } catch (Exception $e) {
            custom_log("Erreur lors de l'affichage du formulaire de génération : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de l'affichage du formulaire.";
            header('Location: ' . BASE_URL . 'contracts/view/' . $contractId);
            exit;
        }
    }
} 