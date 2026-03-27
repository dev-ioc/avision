<?php
/**
 * Contrôleur UserController
 * Gère toutes les actions liées aux utilisateurs
 */

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../includes/functions.php';

class UserController {
    private $userModel;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new UserModel($db);
    }

    /**
     * Affiche la liste des utilisateurs
     */
    public function index() {
        // Vérifier l'accès admin
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent gérer les utilisateurs.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupération des filtres
        $filters = [
            'type' => $_GET['type'] ?? '',
            'status' => isset($_GET['status']) ? $_GET['status'] : '',
            'search' => $_GET['search'] ?? ''
        ];

        // Récupération de tous les utilisateurs (pagination gérée par DataTables côté client)
        $result = $this->userModel->getUsers($filters, 1, 1000); // Limite élevée pour récupérer tous les utilisateurs

        // Extraction des variables pour la vue
        $users = $result['users'];

        // Chargement de la vue
        require_once __DIR__ . '/../views/user/index.php';
    }

    /**
     * Affiche le formulaire de création d'utilisateur
     */
    public function add() {
        // Vérifier l'accès admin
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent créer des utilisateurs.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer les types d'utilisateurs depuis la base de données
        $userTypes = [];
        try {
            $stmt = $this->db->prepare("
                SELECT ut.name, ut.description, ug.name as group_name
                FROM user_types ut
                JOIN user_groups ug ON ut.group_id = ug.id
                ORDER BY ug.name, ut.name
            ");
            $stmt->execute();
            $userTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // En cas d'erreur, utiliser les types par défaut
            $userTypes = [
                ['name' => 'technicien', 'description' => 'Technicien', 'group_name' => 'Staff'],
                ['name' => 'adv', 'description' => 'Commercial (ADV)', 'group_name' => 'Staff'],
                ['name' => 'client', 'description' => 'Client', 'group_name' => 'Externe']
            ];
        }

        // Récupérer les permissions disponibles selon le type d'utilisateur sélectionné
        $availablePermissions = [];
        $selectedType = $_POST['type'] ?? '';
        if (!empty($selectedType) && in_array($selectedType, ['technicien', 'adv', 'client'])) {
            $availablePermissions = $this->userModel->getAvailablePermissions($selectedType);
        }

        // Récupérer la liste des clients actifs
        $clients = $this->userModel->getActiveClients();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Debug: Afficher les données reçues
            custom_log("Données POST reçues pour création d'utilisateur: " . json_encode($_POST), 'INFO');
            
            $data = [
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'password' => !empty($_POST['password']) ? $_POST['password'] : null,
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
                'status' => isset($_POST['status']) ? 1 : 0,
                'coef_utilisateur' => $_POST['coef_utilisateur'] ?? null,
                'client_id' => $_POST['client_id'] ?? null
            ];

            // Debug: Afficher les données traitées
            custom_log("Données traitées pour création d'utilisateur: " . json_encode($data), 'INFO');

            // Validation
            $errors = $this->validateUserData($data);
            
            // Debug: Afficher les erreurs de validation
            if (!empty($errors)) {
                custom_log("Erreurs de validation: " . json_encode($errors), 'ERROR');
            }
            
            if (empty($errors)) {
                // Créer l'utilisateur
                $userId = $this->userModel->createUser($data);
                
                // Debug: Afficher le résultat de la création
                custom_log("Résultat de la création d'utilisateur: " . ($userId ? "Succès, ID: $userId" : "Échec"), $userId ? 'INFO' : 'ERROR');
                
                if ($userId) {
                    // Gérer les permissions si c'est un technicien ou un client
                    if (in_array($data['type'], ['technicien', 'client'])) {
                        $permissions = $_POST['permissions'] ?? [];
                        custom_log("Permissions à ajouter: " . json_encode($permissions), 'INFO');
                        foreach ($permissions as $permission) {
                            $this->userModel->addUserPermission($userId, $permission);
                        }
                    }

                    // Traiter les localisations
                    if (isset($_POST['locations']) && $data['type'] === 'client') {
                        custom_log("Localisations à sauvegarder: " . json_encode($_POST['locations']), 'INFO');
                        $this->userModel->saveUserLocations($userId, $_POST['locations']);
                    }

                    header('Location: ' . BASE_URL . 'user');
                    exit;
                } else {
                    $errors[] = "Erreur lors de la création de l'utilisateur";
                }
            }
        }

        // Chargement de la vue
        require_once __DIR__ . '/../views/user/add.php';
    }

    /**
     * Affiche le formulaire de modification d'utilisateur
     */
    public function edit($id) {
        // Vérifier les droits d'accès
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $user = $this->userModel->getUserById($id);
        if (!$user) {
            header('Location: ' . BASE_URL . 'user');
            exit;
        }

        // Récupérer les permissions existantes
        $existingPermissions = $this->userModel->getUserPermissions($id);
        $existingPermissionIds = array_column($existingPermissions, 'right_name');

        // Récupérer les IDs des permissions existantes pour JavaScript
        $existingPermissionIdsForJS = $this->userModel->getUserPermissionIds($id);

        // Récupérer les types d'utilisateurs depuis la base de données
        $userTypes = [];
        try {
            $stmt = $this->db->prepare("
                SELECT ut.name, ut.description, ug.name as group_name
                FROM user_types ut
                JOIN user_groups ug ON ut.group_id = ug.id
                ORDER BY ug.name, ut.name
            ");
            $stmt->execute();
            $userTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // En cas d'erreur, utiliser les types par défaut
            $userTypes = [
                ['name' => 'technicien', 'description' => 'Technicien', 'group_name' => 'Staff'],
                ['name' => 'adv', 'description' => 'Commercial (ADV)', 'group_name' => 'Staff'],
                ['name' => 'client', 'description' => 'Client', 'group_name' => 'Externe']
            ];
        }

        // Récupérer les permissions disponibles selon le type d'utilisateur
        $availablePermissions = [];
        $selectedType = $_POST['type'] ?? $user['user_type'] ?? '';
        if (!empty($selectedType) && in_array($selectedType, ['technicien', 'adv', 'client'])) {
            $availablePermissions = $this->userModel->getAvailablePermissions($selectedType);
        }

        // Récupérer les localisations existantes pour JavaScript
        $userLocations = $this->userModel->getUserLocations($id);
        $formattedLocations = [];
        
        if (!empty($userLocations)) {
            $formattedLocations = [
                'client_full' => null,
                'sites' => [],
                'rooms' => []
            ];
            
            foreach ($userLocations as $location) {
                if ($location['site_id'] === null && $location['room_id'] === null) {
                    // Accès complet au client
                    $formattedLocations['client_full'] = $location['client_id'];
                } elseif ($location['room_id'] === null) {
                    // Accès au site
                    $formattedLocations['sites'][] = $location['site_id'];
                } else {
                    // Accès à la salle
                    $formattedLocations['rooms'][] = $location['room_id'];
                }
            }
        }

        // Récupérer la liste des clients actifs
        $clients = $this->userModel->getActiveClients();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'password' => !empty($_POST['password']) ? $_POST['password'] : null,
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
                'status' => isset($_POST['status']) ? 1 : 0,
                'coef_utilisateur' => $_POST['coef_utilisateur'] ?? null,
                'client_id' => $_POST['client_id'] ?? null
            ];

            // Validation
            $errors = $this->validateUserData($data, $id);
            if (empty($errors)) {
                if ($this->userModel->updateUser($id, $data)) {
                    // Gérer les permissions si c'est un membre du staff ou un client
                    if (in_array($data['type'], ['admin', 'technicien', 'adv', 'client'])) {
                        // Supprimer toutes les permissions existantes
                        $this->userModel->deleteUserPermissions($id);
                        
                        // Ajouter les nouvelles permissions
                        $permissions = $_POST['permissions'] ?? [];
                        foreach ($permissions as $permission) {
                            $this->userModel->addUserPermission($id, $permission);
                        }

                        // Gérer les localisations - utiliser directement la valeur du formulaire
                        // Si c'est un client, toujours traiter les localisations (même si vides pour supprimer les anciennes)
                        if ($data['type'] === 'client') {
                            $locations = $_POST['locations'] ?? [];
                            custom_log("Sauvegarde des localisations pour l'utilisateur {$id}, type: {$data['type']}", 'INFO');
                            custom_log("Localisations reçues: " . json_encode($locations), 'DEBUG');
                            $result = $this->userModel->saveUserLocations($id, $locations);
                            custom_log("Résultat de saveUserLocations: " . ($result ? 'SUCCÈS' : 'ÉCHEC'), $result ? 'INFO' : 'ERROR');
                        } else {
                            // Si ce n'est plus un client, supprimer toutes les localisations
                            custom_log("Utilisateur {$id} n'est plus un client, suppression des localisations", 'INFO');
                            $this->userModel->saveUserLocations($id, []);
                        }
                    }
                    header('Location: ' . BASE_URL . 'user');
                    exit;
                } else {
                    $errors[] = "Erreur lors de la modification de l'utilisateur";
                }
            }
        }

        // Chargement de la vue
        require_once __DIR__ . '/../views/user/edit.php';
    }

    /**
     * Supprime un utilisateur
     */
    public function delete($id) {
        // Vérifier les droits d'accès
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if ($this->userModel->deleteUser($id)) {
            header('Location: ' . BASE_URL . 'user');
        } else {
            // Gérer l'erreur
            header('Location: ' . BASE_URL . 'user?error=delete_failed');
        }
        exit;
    }

    /**
     * Valide les données d'un utilisateur
     */
    private function validateUserData($data, $excludeId = null) {
        $errors = [];

        // Validation du nom d'utilisateur
        if (empty($data['username'])) {
            $errors[] = "Le nom d'utilisateur est requis";
        } elseif ($this->userModel->usernameExists($data['username'], $excludeId)) {
            $errors[] = "Ce nom d'utilisateur existe déjà";
        }

        // Validation de l'email
        if (empty($data['email'])) {
            $errors[] = "L'email est requis";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'email n'est pas valide";
        } elseif ($this->userModel->emailExists($data['email'], $excludeId)) {
            $errors[] = "Cet email existe déjà";
        }

        // Validation du mot de passe (uniquement pour la création ou si fourni)
        if (!isset($data['password']) && !$excludeId) {
            $errors[] = "Le mot de passe est requis";
        } elseif (isset($data['password']) && strlen($data['password']) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
        }

        // Validation du type
        if (empty($data['type'])) {
            $errors[] = "Le type d'utilisateur est requis";
        } elseif (!in_array($data['type'], ['technicien', 'adv', 'client'])) {
            $errors[] = "Le type d'utilisateur n'est pas valide";
        }

        return $errors;
    }

    /**
     * Récupère les permissions disponibles pour un type d'utilisateur (AJAX)
     */
    public function get_permissions() {
        // Vérifier les droits d'accès
        if (!isset($_SESSION['user']) || !isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $userType = $_POST['type'] ?? $_GET['type'] ?? '';
        
        if (empty($userType) || !in_array($userType, ['technicien', 'adv', 'client', 'admin'])) {
            echo json_encode([]);
            return;
        }

        $permissions = $this->userModel->getAvailablePermissions($userType);
        echo json_encode($permissions);
    }

    /**
     * Affiche les détails d'un utilisateur
     */
    public function view($id) {
        // Vérifier les droits d'accès
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer les informations de l'utilisateur
        $user = $this->userModel->getUserById($id);
        if (!$user) {
            header('Location: ' . BASE_URL . 'user');
            exit;
        }

        // Récupérer les permissions si c'est un membre du staff ou un client
        $userPermissions = [];
        if (isStaff() || isClient()) {
            $userPermissions = $this->userModel->getUserPermissions($id);
        }

        // Chargement de la vue
        require_once __DIR__ . '/../views/user/view.php';
    }

    /**
     * Récupère les localisations d'un client (AJAX)
     */
    public function get_client_locations() {
        try {
            // Vérifier si l'utilisateur est connecté
            if (!isset($_SESSION['user'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Session expirée']);
                exit;
            }

            // Vérifier si l'utilisateur est admin
            if (!isAdmin()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Accès non autorisé']);
                exit;
            }

            // Vérifier si l'ID du client est fourni
            if (!isset($_GET['client_id'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'ID du client manquant']);
                exit;
            }

            $clientId = (int)$_GET['client_id'];

            // Vérifier si le client existe
            $client = $this->userModel->getClientById($clientId);
            if (!$client) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Client non trouvé']);
                exit;
            }

            // Récupérer les localisations
            $locations = $this->userModel->getClientLocations($clientId);
            if ($locations === false) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Erreur lors de la récupération des localisations']);
                exit;
            }

            // Définir l'en-tête Content-Type avant d'envoyer la réponse
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['locations' => $locations], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur dans get_client_locations: " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Une erreur est survenue']);
            exit;
        }
    }

    /**
     * Récupère les localisations d'un utilisateur (AJAX)
     */
    public function get_user_locations() {
        try {
            // Vérifier si l'utilisateur est connecté
            if (!isset($_SESSION['user'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Session expirée']);
                exit;
            }

            // Vérifier si l'utilisateur est admin
            if (!isAdmin()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Accès non autorisé']);
                exit;
            }

            // Vérifier si l'ID de l'utilisateur est fourni
            if (!isset($_GET['user_id'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'ID de l\'utilisateur manquant']);
                exit;
            }

            $userId = (int)$_GET['user_id'];

            // Vérifier si l'utilisateur existe
            $user = $this->userModel->getUserById($userId);
            if (!$user) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Utilisateur non trouvé']);
                exit;
            }

            // Récupérer les localisations
            $locations = $this->userModel->getUserLocations($userId);
            if ($locations === false) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Erreur lors de la récupération des localisations']);
                exit;
            }

            // Définir l'en-tête Content-Type avant d'envoyer la réponse
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['locations' => $locations], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur dans get_user_locations: " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Une erreur est survenue']);
            exit;
        }
    }

    /**
     * Charge les localisations d'un client (AJAX simple)
     */
    public function load_client_locations() {
        // Définir le Content-Type JSON dès le début pour éviter toute confusion
        header('Content-Type: application/json; charset=UTF-8');
        
        // Log pour déboguer en production
        $hasUser = isset($_SESSION['user']);
        $isAdminUser = $hasUser && isAdmin();
        $sessionId = session_id();
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        custom_log("load_client_locations - Session ID: $sessionId, Has User: " . ($hasUser ? 'yes' : 'no') . ", Is Admin: " . ($isAdminUser ? 'yes' : 'no') . ", Is AJAX: " . ($isAjax ? 'yes' : 'no'), 'DEBUG');
        
        // Vérifier les droits d'accès
        if (!$hasUser || !$isAdminUser) {
            http_response_code(403);
            $errorMsg = !$hasUser ? 'Session expirée ou non accessible' : 'Accès refusé - droits insuffisants';
            custom_log("load_client_locations - Accès refusé: $errorMsg (Session ID: $sessionId)", 'WARNING');
            echo json_encode(['error' => $errorMsg]);
            return;
        }

        $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? '';
        $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
        
        if (empty($clientId)) {
            echo json_encode(['html' => '<p class="text-muted">Veuillez sélectionner un client.</p>']);
            return;
        }

        // Récupérer les localisations du client
        $clientLocations = $this->userModel->getClientLocations($clientId);
        
        if (!$clientLocations) {
            echo json_encode(['html' => '<p class="text-muted">Aucune localisation disponible pour ce client.</p>']);
            return;
        }

        // Récupérer les localisations existantes si on édite un utilisateur
        $existingUserLocations = [];
        if ($userId) {
            $existingUserLocations = $this->userModel->getUserLocations($userId);
        }

        // Charger la vue
        ob_start();
        include __DIR__ . '/../views/user/locations_client.php';
        $html = ob_get_clean();
        
        echo json_encode(['html' => $html]);
    }

    /**
     * Charge les permissions pour un type d'utilisateur (AJAX simple)
     */
    public function load_permissions() {
        // Définir le Content-Type JSON dès le début pour éviter toute confusion
        header('Content-Type: application/json; charset=UTF-8');
        
        // Log pour déboguer en production
        $hasUser = isset($_SESSION['user']);
        $isAdminUser = $hasUser && isAdmin();
        $sessionId = session_id();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        custom_log("load_permissions - Session ID: $sessionId, Has User: " . ($hasUser ? 'yes' : 'no') . ", Is Admin: " . ($isAdminUser ? 'yes' : 'no') . ", Is AJAX: " . ($isAjax ? 'yes' : 'no'), 'DEBUG');
        
        // Vérifier les droits d'accès
        if (!$hasUser || !$isAdminUser) {
            http_response_code(403);
            $errorMsg = !$hasUser ? 'Session expirée ou non accessible' : 'Accès refusé - droits insuffisants';
            custom_log("load_permissions - Accès refusé: $errorMsg (Session ID: $sessionId)", 'WARNING');
            echo json_encode(['error' => $errorMsg]);
            return;
        }

        $userType = $_POST['type'] ?? $_GET['type'] ?? '';
        $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
        
        if (empty($userType) || !in_array($userType, ['technicien', 'adv', 'client'])) {
            echo json_encode(['html' => '<p class="text-muted">Type d\'utilisateur invalide.</p>']);
            return;
        }

        // Récupérer les permissions disponibles pour le type d'utilisateur
        $availablePermissions = $this->userModel->getAvailablePermissions($userType);
        
        // Récupérer les permissions existantes si on édite un utilisateur
        $existingPermissionIds = [];
        if ($userId) {
            $existingPermissions = $this->userModel->getUserPermissions($userId);
            $existingPermissionIds = $this->userModel->getUserPermissionIds($userId);
        }

        // Charger la vue appropriée
        ob_start();
        
        // Passer les variables nécessaires aux vues
        if ($userType === 'client') {
            include __DIR__ . '/../views/user/permissions_client.php';
        } else {
            // Pour technicien et adv, utiliser la même vue
            include __DIR__ . '/../views/user/permissions_staff.php';
        }
        
        $html = ob_get_clean();
        
        echo json_encode(['html' => $html]);
    }
} 