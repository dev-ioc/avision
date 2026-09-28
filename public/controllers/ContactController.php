<?php
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';
require_once __DIR__ . '/../models/UserModel.php';
class ContactController
{
    use AccessControlTrait;
    private $db;
    private $contactModel;
    private $clientModel;
    private $userModel;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->contactModel = new ContactModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Vérifie l'accès avec vérification optionnelle d'un client spécifique
     * Utilise AccessControlTrait::checkAccessWithClient() et checkClientManagementAccess()
     */
    private function checkAccess($clientId = null)
    {
        $this->checkAccessWithClient($clientId);

        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les permissions nécessaires pour accéder à cette page.";

            // Rediriger vers la page d'édition du client si l'ID du client est fourni
            if ($clientId) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $clientId);
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }
    }

    public function add($clientId = null)
    {
        $this->checkAccess($clientId);

        if (!$clientId) {
            $_SESSION['error'] = "ID du client non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $client = $this->clientModel->getClientById($clientId);
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validation des champs obligatoires
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
                $_SESSION['error'] = "Le prénom, le nom et l'email sont obligatoires.";

                $returnTo = $_GET['return_to'] ?? 'edit';
                if ($returnTo === 'view') {
                    header('Location: ' . BASE_URL . 'contacts/add/' . $clientId . '?return_to=view');
                } else {
                    header('Location: ' . BASE_URL . 'contacts/add/' . $clientId);
                }
                exit;
            }

            $data = [
                'client_id' => $clientId,
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'fonction' => $_POST['fonction'] ?? '',
                'phone1' => $_POST['phone1'] ?? '',
                'phone2' => $_POST['phone2'] ?? '',
                'email' => $_POST['email'],
                'comment' => $_POST['comment'] ?? '',
                'has_user_account' => isset($_POST['has_user_account']) ? 1 : 0,
                'is_vip' => isset($_POST['is_vip']) ? 1 : 0,
                'status' => 1
            ];

            // Vérifier si on doit créer un compte utilisateur
            if (isset($_POST['has_user_account']) && isAdmin()) {

                $password = $_POST['password'] ?? '';

                if (!empty($password)) {
                    // Validation du mot de passe uniquement s'il est renseigné
                    $errors = [];

                    if (strlen($password) < 8) {
                        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
                    }
                    if (!preg_match('/[A-Z]/', $password)) {
                        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
                    }
                    if (!preg_match('/[a-z]/', $password)) {
                        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
                    }
                    if (!preg_match('/[0-9]/', $password)) {
                        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
                    }
                    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                        $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
                    }

                    if (!empty($errors)) {
                        $_SESSION['error'] = "Erreurs de validation du mot de passe :<br>" . implode("<br>", $errors);
                        header('Location: ' . BASE_URL . 'contacts/add/' . $clientId);
                        exit;
                    }
                } else {
                    // Mot de passe non renseigné : génération automatique
                    $password = bin2hex(random_bytes(6));
                    custom_log("Mot de passe généré automatiquement pour le nouveau contact (client #$clientId)", 'INFO');
                }

                // Le login est toujours l'email du contact
                $userData = [
                    'username' => $_POST['email'],
                    'password' => $password, // Le UserModel s'occupe du hash
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'],
                    'type' => 'client',
                    'is_admin' => 0,
                    'status' => 1,
                    'client_id' => $clientId
                ];

                custom_log("CONTACT_USER_CREATION: Tentative de création d'utilisateur pour contact", 'INFO', [
                    'client_id' => $clientId,
                    'email' => $userData['email'],
                    'type' => $userData['type']
                ]);

                $userId = $this->userModel->createUser($userData);

                custom_log("CONTACT_USER_CREATION: Résultat création utilisateur", 'INFO', [
                    'success' => $userId ? true : false,
                    'user_id' => $userId,
                    'email' => $userData['email']
                ]);

                if ($userId) {
                    $data['user_id'] = $userId;
                } else {
                    $_SESSION['error'] = "Erreur lors de la création du compte utilisateur. Veuillez vérifier que cet email n'est pas déjà utilisé.";
                    header('Location: ' . BASE_URL . 'contacts/add/' . $clientId);
                    exit;
                }
            }

            if ($this->contactModel->createContact($data)) {
                $_SESSION['success'] = "Contact ajouté avec succès.";

                $returnTo = $_GET['return_to'] ?? 'edit';
                if ($returnTo === 'view') {
                    header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=contacts-tab');
                } else {
                    header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '#contacts');
                }
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du contact.";
                header('Location: ' . BASE_URL . 'contacts/add/' . $clientId);
                exit;
            }
        }

        $pageTitle = "Ajouter un contact - " . $client['name'];
        require_once VIEWS_PATH . '/contact/add.php';
    }

    public function edit($id = null)
    {
        // Récupérer d'abord le contact pour obtenir l'ID du client
        $contact = null;
        if ($id) {
            $contact = $this->contactModel->getContactById($id);
        }

        // Vérifier les permissions avec l'ID du client
        $this->checkAccess($contact ? $contact['client_id'] : null);

        if (!$id) {
            $_SESSION['error'] = "ID du contact non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if (!$contact) {
            $_SESSION['error'] = "Contact non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'fonction' => $_POST['fonction'] ?? '',
                'phone1' => $_POST['phone1'] ?? '',
                'phone2' => $_POST['phone2'] ?? '',
                'email' => $_POST['email'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'is_vip' => isset($_POST['is_vip']) ? 1 : 0,
                'has_user_account' => isset($_POST['has_user_account']) ? 1 : 0,
            ];

            custom_log("Données POST reçues pour modification du contact #$id: " . json_encode($_POST), 'INFO');

            if ($this->contactModel->updateContact($id, $data)) {

                // Gestion de la création/liaison du compte utilisateur si la case est cochée
                // et que le contact n'a pas déjà de compte lié
              if ($data['has_user_account'] && empty($contact['user_id'])) {

            // Email du compte : celui saisi dans le sous-formulaire, sinon fallback sur l'email du contact
            $accountEmail = trim($_POST['username'] ?? '') ?: $data['email'];

            if (!filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Contact modifié, mais l'email fourni pour le compte utilisateur est invalide.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
            exit;
            }

            $existingUser = $this->userModel->getUserByEmail($accountEmail);

            if ($existingUser) {
            custom_log("Utilisateur existant trouvé pour l'email {$accountEmail}, liaison au contact #$id", 'INFO');

            if (!$this->contactModel->linkUserAccount($id, $existingUser['id'])) {
                custom_log("Échec de la liaison du compte existant #{$existingUser['id']} au contact #$id", 'ERROR');
                $_SESSION['error'] = "Contact modifié, mais la liaison au compte utilisateur existant a échoué.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
                exit;
            }
            } else {
            $password = $_POST['password'] ?? '';
            if (!empty($password)) {
                $passwordErrors = [];
                if (strlen($password) < 8) $passwordErrors[] = "au moins 8 caractères";
                if (!preg_match('/[A-Z]/', $password)) $passwordErrors[] = "une majuscule";
                if (!preg_match('/[a-z]/', $password)) $passwordErrors[] = "une minuscule";
                if (!preg_match('/[0-9]/', $password)) $passwordErrors[] = "un chiffre";
                if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $passwordErrors[] = "un caractère spécial";

                if (!empty($passwordErrors)) {
                    $_SESSION['error'] = "Contact modifié, mais le mot de passe ne respecte pas les règles requises (" . implode(', ', $passwordErrors) . ").";
                    header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
                    exit;
                }
            } else {
                $password = bin2hex(random_bytes(6)); // génération automatique si vide
                custom_log("Mot de passe généré automatiquement pour le contact #$id", 'INFO');
            }
            // Mot de passe non obligatoire : génération automatique si absent
            if (empty($password)) {
                $password = bin2hex(random_bytes(6)); // 12 caractères aléatoires
                custom_log("Mot de passe généré automatiquement pour le contact #$id", 'INFO');
            }

            $userData = [
                'email' => $accountEmail,
                'password' => $password,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'type' => 'client',
                'is_admin' => 0,
                'status' => 1,
                'coef_utilisateur' => null,
                'client_id' => $contact['client_id'],
            ];

            custom_log("Création du compte utilisateur pour le contact #$id: " . json_encode($userData), 'INFO');

            $userId = $this->userModel->createUser($userData);

            if ($userId) {
                custom_log("Compte utilisateur #$userId créé, liaison au contact #$id", 'INFO');
                $this->contactModel->linkUserAccount($id, $userId);
            } else {
                custom_log("Échec de la création du compte utilisateur pour le contact #$id", 'ERROR');
                $_SESSION['error'] = "Contact modifié, mais la création du compte utilisateur a échoué.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
                exit;
            }
            }
            }

                $_SESSION['success'] = "Contact modifié avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification du contact.";
            }
        }

        $contact = $this->contactModel->getContactById($id);

        // Générer le QR VIP si le contact est marqué comme tel
        require_once __DIR__ . '/../controllers/QRCodeController.php';
        $qrcodeController = new QRCodeController();
        $contactQR = (!empty($contact['is_vip'])) ? $qrcodeController->generateQRCodeBase64(
            $qrcodeController->generateContactQRUrl($contact['id']),
            130
        ) : null;

        $pageTitle = "Modifier le contact - " . $contact['first_name'] . " " . $contact['last_name'];
        require_once VIEWS_PATH . '/contact/edit.php';
    }
    public function delete($id = null)
    {
        // Récupérer d'abord le contact pour obtenir l'ID du client
        $contact = null;
        if ($id) {
            $contact = $this->contactModel->getContactById($id);
        }

        // Vérifier si l'utilisateur est un administrateur
        if (!isset($_SESSION['user']) || !isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des contacts.";
            // Rediriger vers la page d'édition du client avec l'onglet contacts actif
            if ($contact && isset($contact['client_id'])) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        if (!$id) {
            $_SESSION['error'] = "ID du contact non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if (!$contact) {
            $_SESSION['error'] = "Contact non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($this->contactModel->deleteContact($id)) {
            $_SESSION['success'] = "Contact supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du contact.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
        exit;
    }

    public function index()
    {
        // Récupérer l'ID du client depuis les paramètres GET si disponible
        $clientId = isset($_GET['client_id']) ? $_GET['client_id'] : null;

        // Vérifier les permissions avec l'ID du client
        $this->checkAccess($clientId);

        // Récupérer les contacts avec filtres
        $filters = [
            'search' => $_GET['search'] ?? '',
            'client_id' => $clientId,
            'status' => isset($_GET['status']) ? $_GET['status'] : null
        ];

        // Rediriger vers le tableau de bord car les contacts sont gérés dans la vue client
        header('Location: ' . BASE_URL . 'dashboard');
        exit;
    }
    public function exportCsv()
    {
        if (!isset($_SESSION['user']) || !canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Empêcher toute notice/warning PHP de polluer le flux CSV
        $previousDisplayErrors = ini_set('display_errors', '0');
        error_reporting(0);

        $clientId = $_GET['client_id'] ?? null;
        $vipOnly = isset($_GET['vip_only']) && $_GET['vip_only'] == '1';

        $contacts = $this->contactModel->getContactsForExport($clientId ?: null, $vipOnly);

        require_once __DIR__ . '/../controllers/QRCodeController.php';
        $qrcodeController = new QRCodeController();

        // Vider tout buffer de sortie déjà rempli par d'éventuelles notices précédentes
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contacts_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Société', 'Nom', 'Prénom', 'Email', 'URL'], ';');

        foreach ($contacts as $contact) {
            $url = !empty($contact['is_vip'])
                ? $qrcodeController->generateContactQRUrl($contact['id'])
                : '';
            fputcsv($output, [
                $contact['client_name'] ?? '',
                $contact['last_name'] ?? '',
                $contact['first_name'] ?? '',
                $contact['email'] ?? '',
                $url
            ], ';');
        }

        fclose($output);
        exit;
    }
    public function exportForm()
    {
        if (!isset($_SESSION['user']) || !canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $clients = $this->clientModel->getAllClients();
        $pageTitle = "Export des contacts";
        require_once VIEWS_PATH . '/contact/export.php';
    }

}