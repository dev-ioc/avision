<?php
// Servir directement les fichiers statiques (assets) sans passer par le routage
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$script_dir = dirname($script_name);

// Extraire le chemin relatif depuis la racine du script
$path = $request_uri;
if ($script_dir !== '/') {
    // Si l'application est dans un sous-dossier, retirer le chemin du sous-dossier
    $path = substr($request_uri, strlen($script_dir));
}
$path = parse_url($path, PHP_URL_PATH); // Retirer les paramètres de requête

// Normaliser le chemin
$path = '/' . ltrim($path, '/');

// Si c'est un fichier statique (assets, uploads, etc.), le servir directement
if (preg_match('#^/(assets|uploads)/#', $path)) {
    $file_path = __DIR__ . $path;
    if (file_exists($file_path) && is_file($file_path)) {
        // Déterminer le type MIME
        $mime_types = [
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'json' => 'application/json; charset=utf-8',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];

        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: public, max-age=3600');
        readfile($file_path);
        exit;
    } else {
        // Fichier non trouvé - retourner 404
        http_response_code(404);
        exit;
    }
}

// Chargement de l'initialisation
require_once __DIR__ . '/includes/init.php';

// Chargement du middleware CSRF (doit être après init.php pour avoir accès à la session)
require_once __DIR__ . '/includes/middleware/csrf.php';

// Application du middleware CSRF pour les requêtes modifiantes
try {
    csrfMiddleware();
} catch (Exception $e) {
    // Le middleware gère déjà les erreurs et les redirections
    exit;
}

// Chargement des modèles
require_once MODELS_PATH . '/UserModel.php';
require_once MODELS_PATH . '/ClientModel.php';
require_once MODELS_PATH . '/SiteModel.php';
require_once MODELS_PATH . '/ContactModel.php';
require_once MODELS_PATH . '/ContractModel.php';
require_once MODELS_PATH . '/ContractsClientModel.php';
require_once MODELS_PATH . '/RoomModel.php';
require_once MODELS_PATH . '/InterventionModel.php';
require_once MODELS_PATH . '/DocumentationModel.php';
require_once MODELS_PATH . '/MaterielModel.php';
require_once MODELS_PATH . '/InterventionTypeModel.php';

// Chargement des contrôleurs
require_once CONTROLLERS_PATH . '/AuthController.php';
require_once CONTROLLERS_PATH . '/DashboardController.php';
require_once CONTROLLERS_PATH . '/UserController.php';
require_once CONTROLLERS_PATH . '/ClientController.php';
require_once CONTROLLERS_PATH . '/ContactController.php';
require_once CONTROLLERS_PATH . '/SiteController.php';
require_once CONTROLLERS_PATH . '/RoomController.php';
require_once CONTROLLERS_PATH . '/ContractController.php';
require_once CONTROLLERS_PATH . '/ContractsClientController.php';
require_once CONTROLLERS_PATH . '/HorsContratFacturableController.php';
require_once CONTROLLERS_PATH . '/HorsContratNonFacturableController.php';
require_once CONTROLLERS_PATH . '/InterventionController.php';
require_once CONTROLLERS_PATH . '/InterventionsClientController.php';
require_once CONTROLLERS_PATH . '/AgendaController.php';
require_once CONTROLLERS_PATH . '/DocumentationController.php';
require_once CONTROLLERS_PATH . '/MaterielController.php';
require_once CONTROLLERS_PATH . '/MaterielClientController.php';
require_once CONTROLLERS_PATH . '/MaterielTestController.php';
require_once CONTROLLERS_PATH . '/DocumentationClientController.php';
require_once CONTROLLERS_PATH . '/ProfileClientController.php';
require_once CONTROLLERS_PATH . '/ContactClientController.php';
require_once CONTROLLERS_PATH . '/SiteClientController.php'; // SiteClientController pour les clients
require_once CONTROLLERS_PATH . '/SettingsController.php';
require_once CONTROLLERS_PATH . '/InterventionTypeController.php';
require_once CONTROLLERS_PATH . '/QRCodeController.php';
require_once CONTROLLERS_PATH . '/ExcelController.php';

// Récupération de l'URL demandée
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = parse_url(BASE_URL, PHP_URL_PATH);

// Gestion du sous-dossier si l'application n'est pas à la racine
$script_name = $_SERVER['SCRIPT_NAME'];
$script_dir = dirname($script_name);
if ($script_dir !== '/') {
    $base_path = $script_dir;
}

// Nettoyer le base_path pour éviter les problèmes de double slash
$base_path = rtrim($base_path, '/');
$path = substr($request_uri, strlen($base_path));

// Si le path commence par un slash, le supprimer
if (strpos($path, '/') === 0) {
    $path = substr($path, 1);
}

// Nettoyage de l'URL et séparation des paramètres de requête
$path_parts = explode('?', $path);
$path = $path_parts[0];

// Nettoyage de l'URL
$path = trim($path, '/');
if (empty($path)) {
    $path = 'dashboard';
}

// Séparation du contrôleur et de l'action
$parts = explode('/', $path);
$controller = $parts[0] ?? 'dashboard';
$action = $parts[1] ?? 'index';
$id = $parts[2] ?? null;

// Vérification de l'authentification
$public_routes = ['auth/login', 'auth/logout', 'settings/getAllowedExtensions'];
$current_route = $controller . '/' . $action;

if (!in_array($current_route, $public_routes) && !isset($_SESSION['user'])) {
    // Vérifier si c'est une requête AJAX
    $isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Si c'est une requête AJAX, retourner du JSON au lieu de rediriger
    if ($isAjaxRequest) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'error' => 'Session expirée. Veuillez vous reconnecter.',
            'redirect' => BASE_URL . 'auth/login'
        ]);
        exit;
    }

    // Ignorer les requêtes pour les fichiers statiques (favicon, robots.txt, etc.)
    $staticFiles = ['favicon.ico', 'robots.txt', '.well-known'];
    $isStaticFile = false;
    foreach ($staticFiles as $staticFile) {
        if (strpos($path, $staticFile) !== false || strpos($controller, $staticFile) !== false) {
            $isStaticFile = true;
            break;
        }
    }

    // Ne sauvegarder l'URL de redirection que si ce n'est pas un fichier statique
    // et si on n'a pas déjà une URL de redirection valide en session
    if (!$isStaticFile && (!isset($_SESSION['redirect_after_login']) || empty($_SESSION['redirect_after_login']) || $_SESSION['redirect_after_login'] === 'favicon.ico')) {
        // Stocker l'URL de redirection dans la session pour rediriger après connexion
        $redirectUrl = $controller;
        if ($action && $action !== 'index') {
            $redirectUrl .= '/' . $action;
        }
        if ($id) {
            $redirectUrl .= '/' . $id;
        }
        // Ajouter les paramètres de requête s'il y en a
        if (!empty($_SERVER['QUERY_STRING'])) {
            $redirectUrl .= '?' . $_SERVER['QUERY_STRING'];
        }
        $_SESSION['redirect_after_login'] = $redirectUrl;

        // Debug: logger l'URL sauvegardée
        custom_log("Redirection vers login - URL sauvegardée: " . $redirectUrl . " (Session ID: " . session_id() . ")", 'DEBUG');
    } else if ($isStaticFile) {
        // Pour les fichiers statiques, rediriger directement sans sauvegarder
        custom_log("Fichier statique ignoré: " . $path, 'DEBUG');
    }

    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Routage
try {
    switch ($controller) {
        case 'auth':
            $authController = new AuthController();
            switch ($action) {
                case 'login':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $authController->login();
                    } else {
                        $authController->showLoginForm();
                    }
                    break;
                case 'logout':
                    $authController->logout();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'auth/login');
                    break;
            }
            break;

        case 'user':
            $userController = new UserController($db);
            switch ($action) {
                case 'index':
                    $userController->index();
                    break;
                case 'add':
                    $userController->add();
                    break;
                case 'edit':
                    if ($id) {
                        $userController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'user');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $userController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'user');
                    }
                    break;
                case 'get_permissions':
                    $userController->get_permissions();
                    break;
                case 'load_permissions':
                    $userController->load_permissions();
                    break;
                case 'load_client_locations':
                    $userController->load_client_locations();
                    break;
                case 'get_client_locations':
                    $userController->get_client_locations();
                    break;
                case 'get_user_locations':
                    $userController->get_user_locations();
                    break;
                case 'view':
                    if ($id) {
                        $userController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'user');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'user');
                    break;
            }
            break;
        case 'excel':
            $excel = new ExcelController($db);
            $subAction = $_GET['subaction'] ?? 'index';
            if ($subAction === 'index') {
                $excel->index();
            } elseif ($subAction === 'save') {
                $excel->save();
            } else {
                header('Location: ' . BASE_URL . 'excel');
            }
            break;
        case 'dashboard':
            $dashboardController = new DashboardController();
            switch ($action) {
                case 'index':
                    $dashboardController->index();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'dashboard');
                    break;
            }
            break;

        case 'clients':
            $clientController = new ClientController();
            switch ($action) {
                case 'index':
                    $clientController->index();
                    break;
                case 'add':
                    $clientController->add();
                    break;
                case 'store':
                    $clientController->store();
                    break;
                case 'view':
                    if ($id) {
                        $clientController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'clients');
                    }
                    break;
                case 'edit':
                    if ($id) {
                        $clientController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'clients');
                    }
                    break;
                case 'update':
                    if ($id) {
                        $clientController->update($id);
                    } else {
                        header('Location: ' . BASE_URL . 'clients');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $clientController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'clients');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'clients');
                    break;
            }
            break;

        case 'contacts':
            $contactController = new ContactController();
            switch ($action) {
                case 'add':
                    if ($id) {
                        $contactController->add($id);
                    } else {
                        $_SESSION['error'] = "Client ID manquant pour ajouter un contact.";
                        header('Location: ' . BASE_URL . 'dashboard');
                        exit;
                    }
                    break;
                case 'edit':
                    if ($id) {
                        $contactController->edit($id);
                    } else {
                        $_SESSION['error'] = "Contact ID manquant pour la modification.";
                        header('Location: ' . BASE_URL . 'dashboard');
                        exit;
                    }
                    break;
                case 'store':
                    $contactController->store();
                    break;
                case 'update':
                    if ($id) {
                        $contactController->update($id);
                    } else {
                        $_SESSION['error'] = "Contact ID manquant pour la mise à jour.";
                        header('Location: ' . BASE_URL . 'dashboard');
                        exit;
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $contactController->delete($id);
                    } else {
                        $_SESSION['error'] = "Contact ID manquant pour la suppression.";
                        header('Location: ' . BASE_URL . 'dashboard');
                        exit;
                    }
                    break;
                default:
                    $_SESSION['error'] = "Action non valide pour les contacts.";
                    header('Location: ' . BASE_URL . 'dashboard');
                    break;
            }
            break;

        case 'documentation':
            $documentationController = new DocumentationController();
            switch ($action) {
                case 'index':
                    $documentationController->index();
                    break;
                case 'add':
                    $documentationController->add();
                    break;
                case 'create':
                    $documentationController->create();
                    break;
                case 'store':
                    $documentationController->store();
                    break;
                case 'edit':
                    if ($id) {
                        $documentationController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'update':
                    if ($id) {
                        $documentationController->update($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $documentationController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'view':
                    if ($id) {
                        $documentationController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'get_sites':
                    $documentationController->get_sites();
                    break;
                case 'get_rooms':
                    $documentationController->get_rooms();
                    break;
                case 'get_attachments':
                    $documentationController->getAttachments();
                    break;
                case 'upload_attachment':
                    $documentationController->uploadAttachment();
                    break;
                case 'delete_attachment':
                    if ($id) {
                        $documentationController->deleteAttachment($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID manquant']);
                    }
                    break;
                case 'addMultipleAttachments':
                    $documentationController->addMultipleAttachments();
                    break;
                case 'download':
                    if (isset($_GET['attachment_id'])) {
                        $documentationController->download($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'preview':
                    if (isset($_GET['attachment_id'])) {
                        $documentationController->preview($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'toggleAttachmentVisibility':
                    if (isset($_GET['attachment_id'])) {
                        $documentationController->toggleAttachmentVisibility($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    break;
                case 'updateName':
                    $documentationController->updateName();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'documentation');
                    break;
            }
            break;

        case 'site':
            $siteController = new SiteController();
            switch ($action) {
                case 'add':
                    if ($id) {
                        $siteController->add($id);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                case 'edit':
                    if ($id) {
                        $siteController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $siteController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'dashboard');
                    break;
            }
            break;

        case 'room':
            $roomController = new RoomController();
            switch ($action) {
                case 'add':
                    // Permettre room/add/0 si client_id est présent dans les paramètres GET
                    if ($id || isset($_GET['client_id'])) {
                        $roomController->add($id ?? 0);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                case 'edit':
                    if ($id) {
                        $roomController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $roomController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                case 'getRoomsBySite':
                    $roomController->getRoomsBySite();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'dashboard');
                    break;
            }
            break;

        case 'contracts':
            $contractController = new ContractController();
            switch ($action) {
                case 'index':
                    $contractController->index();
                    break;
                case 'add':
                    $contractController->add($id);
                    break;
                case 'create':
                    $contractController->create();
                    break;
                case 'edit':
                    if ($id) {
                        $contractController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'update':
                    if ($id) {
                        $contractController->update($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $contractController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'view':
                    if ($id) {
                        $contractController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'getRoomsForClient':
                    if ($id) {
                        $contractController->getRoomsForClient($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID client manquant']);
                        exit;
                    }
                    break;
                case 'load_client_rooms':
                    $contractController->load_client_rooms();
                    break;
                case 'confirm_access_level_change':
                    if ($id) {
                        $contractController->confirmAccessLevelChange($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'apply_access_level_change':
                    if ($id) {
                        $contractController->applyAccessLevelChange($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'ignore_access_level_change':
                    if ($id) {
                        $contractController->ignoreAccessLevelChange($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'confirmPreventiveInterventions':
                    if ($id) {
                        $contractController->confirmPreventiveInterventions($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'createPreventiveInterventions':
                    $contractController->createPreventiveInterventions();
                    break;
                case 'ignorePreventiveInterventions':
                    $contractController->ignorePreventiveInterventions();
                    break;
                case 'generatePreventiveInterventions':
                    if ($id) {
                        $contractController->generatePreventiveInterventions($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'addAttachment':
                    if ($id) {
                        $contractController->addAttachment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'addMultipleAttachments':
                    if ($id) {
                        $contractController->addMultipleAttachments($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'deleteAttachment':
                    if ($id && isset($_GET['attachment_id'])) {
                        $contractController->deleteAttachment($id, $_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'download':
                    if (isset($_GET['attachment_id'])) {
                        $contractController->download($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'preview':
                    if (isset($_GET['attachment_id'])) {
                        $contractController->preview($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'toggleAttachmentVisibility':
                    if ($id && isset($_GET['attachment_id'])) {
                        $contractController->toggleAttachmentVisibility($id, $_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'addTickets':
                    if ($id) {
                        $contractController->addTickets($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                case 'renew':
                    if ($id) {
                        $contractController->renew($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'contracts');
                    break;
            }
            break;

        case 'hors_contrat_facturable':
            $horsContratFacturableController = new HorsContratFacturableController();
            switch ($action) {
                case 'index':
                    $horsContratFacturableController->index();
                    break;
                case 'view':
                    if ($id) {
                        $horsContratFacturableController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'hors_contrat_facturable');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'hors_contrat_facturable');
                    break;
            }
            break;

        case 'hors_contrat_non_facturable':
            $horsContratNonFacturableController = new HorsContratNonFacturableController();
            switch ($action) {
                case 'index':
                    $horsContratNonFacturableController->index();
                    break;
                case 'view':
                    if ($id) {
                        $horsContratNonFacturableController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'hors_contrat_non_facturable');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'hors_contrat_non_facturable');
                    break;
            }
            break;

        case 'interventions':
            $interventionController = new InterventionController($db);
            switch ($action) {
                case 'index':
                    // Rediriger vers curatives par défaut
                    header('Location: ' . BASE_URL . 'interventions/curatives');
                    exit;
                case 'curatives':
                    $interventionController->curatives();
                    break;
                case 'preventives':
                    $interventionController->preventives();
                    break;
                case 'add':
                    $interventionController->create();
                    break;
                case 'store':
                    $interventionController->store();
                    break;
                case 'view':
                    if ($id) {
                        $interventionController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'addComment':
                    if ($id) {
                        $interventionController->addComment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'editComment':
                    if ($id) {
                        $interventionController->editComment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'addAttachment':
                    if ($id) {
                        $interventionController->addAttachment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'addMultipleAttachments':
                    if ($id) {
                        $interventionController->addMultipleAttachments($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'getAttachmentInfo':
                    if ($id) {
                        $interventionController->getAttachmentInfo($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'updateAttachmentName':
                    if ($id) {
                        $interventionController->updateAttachmentName($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'generateBon':
                    if ($id) {
                        $interventionController->generateBon($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'saveBonSelection':
                    if ($id) {
                        $interventionController->saveBonSelection($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'generateBonPdf':
                    if ($id) {
                        $interventionController->generateBonPdf($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'download':
                    if ($id) {
                        $interventionController->download($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'preview':
                    if ($id) {
                        $interventionController->preview($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'generateReport':
                    if ($id) {
                        $interventionController->generateReport($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'assignToMe':
                    if ($id) {
                        $interventionController->assignToMe($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'getCloseDetails':
                    if ($id) {
                        $interventionController->getCloseDetails($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'close':
                    if ($id) {
                        $interventionController->close($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'forceTickets':
                    if ($id) {
                        $interventionController->forceTickets($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'edit':
                    if ($id) {
                        $interventionController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'update':
                    if ($id) {
                        $interventionController->update($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'deleteComment':
                    if ($id) {
                        $interventionController->deleteComment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'deleteAttachment':
                    if ($id) {
                        $interventionController->deleteAttachment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $interventionController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions');
                    }
                    break;
                case 'getTypeInfo':
                    if ($id) {
                        $interventionController->getTypeInfo($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID manquant']);
                        exit;
                    }
                    break;
                case 'getEmailData':
                    if ($id) {
                        $interventionController->getEmailData($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'ID manquant']);
                        exit;
                    }
                    break;
                case 'getMailHistory':
                    if ($id) {
                        $interventionController->getMailHistory($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'ID manquant']);
                        exit;
                    }
                    break;
                case 'sendEmail':
                    if ($id) {
                        $interventionController->sendEmail($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'ID manquant']);
                        exit;
                    }
                    break;
                case 'previewEmailTemplate':
                    if ($id) {
                        $interventionController->previewEmailTemplate($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'ID manquant']);
                        exit;
                    }
                    break;
                case 'getSites':
                    if ($id) {
                        $interventionController->getSites($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID client manquant']);
                        exit;
                    }
                    break;
                case 'getRooms':
                    if ($id) {
                        $interventionController->getRooms($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID site manquant']);
                        exit;
                    }
                    break;
                case 'getContracts':
                    if ($id) {
                        $siteId = $_GET['site_id'] ?? null;
                        $roomId = $_GET['room_id'] ?? null;
                        $interventionController->getContracts($id, $siteId, $roomId);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID client manquant']);
                        exit;
                    }
                    break;
                case 'getContractByRoom':
                    if ($id) {
                        $interventionController->getContractByRoom($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID salle manquant']);
                        exit;
                    }
                    break;
                case 'getContractInfo':
                    if ($id) {
                        $interventionController->getContractInfo($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID contrat manquant']);
                        exit;
                    }
                    break;
                case 'getContacts':
                    if ($id) {
                        $interventionController->getContacts($id);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'ID client manquant']);
                        exit;
                    }
                    break;
                case 'quickCreateClient':
                    $interventionController->quickCreateClient();
                    break;
                case 'quickCreateSite':
                    $interventionController->quickCreateSite();
                    break;
                case 'quickCreateRoom':
                    $interventionController->quickCreateRoom();
                    break;
                case 'quickCreateContact':
                    $interventionController->quickCreateContact();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'interventions');
                    break;
            }
            break;

        case 'agenda':
            $agendaController = new AgendaController($db);
            switch ($action) {
                case 'index':
                    $agendaController->index();
                    break;
                case 'getEvents':
                    $agendaController->getEvents();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'agenda');
                    break;
            }
            break;

        case 'interventions_client':
            $interventionsClientController = new InterventionsClientController($db);
            switch ($action) {
                case 'index':
                    $interventionsClientController->index();
                    break;
                case 'view':
                    if ($id) {
                        $interventionsClientController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'addComment':
                    if ($id) {
                        $interventionsClientController->addComment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'editComment':
                    if ($id) {
                        $interventionsClientController->editComment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'deleteComment':
                    if ($id) {
                        $interventionsClientController->deleteComment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'addAttachment':
                    if ($id) {
                        $interventionsClientController->addAttachment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'addMultipleAttachments':
                    if ($id) {
                        $interventionsClientController->addMultipleAttachments($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'deleteAttachment':
                    if ($id) {
                        $interventionsClientController->deleteAttachment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'download':
                    if ($id) {
                        $interventionsClientController->download($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'preview':
                    if ($id) {
                        $interventionsClientController->preview($id);
                    } else {
                        header('Location: ' . BASE_URL . 'interventions_client');
                    }
                    break;
                case 'get_rooms':
                    // Vérifier que l'utilisateur est connecté et est un client
                    if (!isset($_SESSION['user']) || !isClient()) {
                        header('Content-Type: application/json');
                        http_response_code(401);
                        echo json_encode(['error' => 'Non autorisé']);
                        exit;
                    }

                    $siteId = $_GET['site_id'] ?? null;
                    if ($siteId) {
                        try {
                            // Récupérer les salles du site selon les localisations autorisées
                            $userLocations = getUserLocations();
                            if (empty($userLocations)) {
                                header('Content-Type: application/json');
                                echo json_encode(['error' => 'Aucune localisation autorisée']);
                                exit;
                            }
                            $rooms = $interventionsClientController->getRoomsBySiteAndLocations($siteId, $userLocations);
                            header('Content-Type: application/json');
                            echo json_encode($rooms);
                            exit;
                        } catch (Exception $e) {
                            custom_log("Erreur lors de la récupération des salles (client): " . $e->getMessage(), 'ERROR');
                            header('Content-Type: application/json');
                            http_response_code(500);
                            echo json_encode(['error' => 'Erreur serveur lors de la récupération des salles']);
                            exit;
                        }
                    } else {
                        header('Content-Type: application/json');
                        http_response_code(400);
                        echo json_encode(['error' => 'ID site manquant']);
                        exit;
                    }
                    break;
                case 'add':
                    $interventionsClientController->add();
                    break;
                case 'store':
                    $interventionsClientController->store();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'interventions_client');
                    break;
            }
            break;

        case 'contracts_client':
            $contractsClientController = new ContractsClientController();
            switch ($action) {
                case 'index':
                    $contractsClientController->index();
                    break;
                case 'view':
                    if ($id) {
                        $contractsClientController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts_client');
                    }
                    break;
                case 'getRoomsBySiteAndLocations':
                    $contractsClientController->getRoomsBySiteAndLocations();
                    break;
                case 'download':
                    if (isset($_GET['attachment_id'])) {
                        $contractsClientController->download($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts_client');
                    }
                    break;
                case 'preview':
                    if (isset($_GET['attachment_id'])) {
                        $contractsClientController->preview($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'contracts_client');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'contracts_client');
                    break;
            }
            break;

        case 'materiel':
            $materielController = new MaterielController();
            switch ($action) {
                case 'index':
                    $materielController->index();
                    break;
                case 'view':
                    if ($id) {
                        $materielController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'salle':
                    if ($id) {
                        $materielController->salle($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'add':
                    $materielController->add();
                    break;
                case 'store':
                    $materielController->store();
                    break;
                case 'edit':
                    if ($id) {
                        $materielController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'update':
                    if ($id) {
                        $materielController->update($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $materielController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'addAttachment':
                    if ($id) {
                        $materielController->addAttachment($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'addMultipleAttachments':
                    if ($id) {
                        $materielController->addMultipleAttachments($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'deleteAttachment':
                    if ($id) {
                        $pieceJointeId = $parts[3] ?? null;
                        if ($pieceJointeId) {
                            $materielController->deleteAttachment($id, $pieceJointeId);
                        } else {
                            header('Location: ' . BASE_URL . 'materiel/view/' . $id);
                        }
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'toggleAttachmentVisibility':
                    if ($id) {
                        $pieceJointeId = $parts[3] ?? null;
                        if ($pieceJointeId) {
                            $materielController->toggleAttachmentVisibilityDirect($id, $pieceJointeId);
                        } else {
                            header('Location: ' . BASE_URL . 'materiel/view/' . $id);
                        }
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'download':
                    if ($id) {
                        $materielController->download($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'preview':
                    if ($id) {
                        $materielController->preview($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'get_sites':
                    $materielController->get_sites();
                    break;
                case 'get_rooms':
                    $materielController->get_rooms();
                    break;
                case 'get_room_access_level':
                    $materielController->get_room_access_level();
                    break;
                case 'getAttachments':
                    if ($id) {
                        $materielController->getAttachments($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'downloadAll':
                    if ($id) {
                        $materielController->downloadAll($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel');
                    }
                    break;
                case 'import':
                    $materielController->import();
                    break;
                case 'process_import':
                    $materielController->process_import();
                    break;
                case 'download_template':
                    $materielController->download_template();
                    break;
                case 'uploadAttachment':
                    $materielController->uploadAttachment();
                    break;
                case 'toggleAttachmentVisibility':
                    $materielController->toggleAttachmentVisibility();
                    break;
                case 'deleteBulk':
                    $materielController->deleteBulk();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'materiel');
                    break;
            }
            break;

        case 'materiel_v2':
            require_once __DIR__ . '/controllers/MaterielV2Controller.php';
            $materielV2Controller = new MaterielV2Controller();
            switch ($action) {
                case 'index':
                case '':
                    $materielV2Controller->index();
                    break;
                case 'updateField':
                    $materielV2Controller->updateField();
                    break;
                case 'getAttachments':
                    $materielV2Controller->getAttachments();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'materiel_v2');
                    break;
            }
            break;

        case 'materiel_bulk':
            require_once __DIR__ . '/controllers/MaterielBulkController.php';
            $materielBulkController = new MaterielBulkController();
            switch ($action) {
                case 'index':
                case '':
                    $materielBulkController->index();
                    break;
                case 'validate_import':
                    $materielBulkController->validate_import();
                    break;
                case 'confirm_import':
                    $materielBulkController->confirm_import();
                    break;
                case 'process_bulk_import':
                    $materielBulkController->process_bulk_import();
                    break;
                case 'export':
                    $materielBulkController->export();
                    break;
                case 'download_template':
                    $materielBulkController->download_template();
                    break;
                case 'bulk_delete':
                    $materielBulkController->bulk_delete();
                    break;
                case 'bulk_delete_execute':
                    $materielBulkController->bulk_delete_execute();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'materiel_bulk');
                    break;
            }
            break;

        case 'materiel_client':
            $materielClientController = new MaterielClientController();
            switch ($action) {
                case 'index':
                    $materielClientController->index();
                    break;
                case 'view':
                    if ($id) {
                        $materielClientController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel_client');
                    }
                    break;
                case 'salle':
                    if ($id) {
                        $materielClientController->salle($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel_client');
                    }
                    break;
                case 'get_sites':
                    $materielClientController->get_sites();
                    break;
                case 'get_rooms':
                    $materielClientController->get_rooms();
                    break;
                case 'download':
                    if ($id) {
                        $materielClientController->download($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel_client');
                    }
                    break;
                case 'preview':
                    if ($id) {
                        $materielClientController->preview($id);
                    } else {
                        header('Location: ' . BASE_URL . 'materiel_client');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'materiel_client');
                    break;
            }
            break;

        case 'materiel_test':
            $materielTestController = new MaterielTestController();
            switch ($action) {
                case 'index':
                case '':
                    $materielTestController->index();
                    break;
                case 'get_sites':
                    $materielTestController->get_sites();
                    break;
                case 'get_rooms':
                    $materielTestController->get_rooms();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'materiel_test');
                    break;
            }
            break;

        case 'documentation_client':
            $documentationClientController = new DocumentationClientController();
            switch ($action) {
                case 'index':
                    $documentationClientController->index();
                    break;
                case 'add':
                    $documentationClientController->add();
                    break;
                case 'create':
                    $documentationClientController->create();
                    break;
                case 'store':
                    $documentationClientController->store();
                    break;
                case 'edit':
                    if ($id) {
                        $documentationClientController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation_client');
                    }
                    break;
                case 'update':
                    if ($id) {
                        $documentationClientController->update($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation_client');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $documentationClientController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation_client');
                    }
                    break;
                case 'get_rooms':
                    $documentationClientController->get_rooms();
                    break;
                case 'download':
                    if (isset($_GET['attachment_id'])) {
                        $documentationClientController->download($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation_client');
                    }
                    break;
                case 'preview':
                    if (isset($_GET['attachment_id'])) {
                        $documentationClientController->preview($_GET['attachment_id']);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation_client');
                    }
                    break;
                case 'updateName':
                    $documentationClientController->updateName();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'documentation_client');
                    break;
            }
            break;

        case 'profileClient':
            $profileClientController = new ProfileClientController($db);
            switch ($action) {
                case 'index':
                    $profileClientController->index();
                    break;
                case 'edit':
                    $profileClientController->edit();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'profileClient');
                    break;
            }
            break;

        case 'contactClient':
            $contactClientController = new ContactClientController($db);
            switch ($action) {
                case 'index':
                    $contactClientController->index();
                    break;
                case 'add':
                    $contactClientController->add();
                    break;
                case 'edit':
                    if ($id) {
                        $contactClientController->edit($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contactClient');
                    }
                    break;
                case 'delete':
                    if ($id) {
                        $contactClientController->delete($id);
                    } else {
                        header('Location: ' . BASE_URL . 'contactClient');
                    }
                    break;
                case 'getContacts':
                    $contactClientController->getContacts();
                    break;
                case 'setPrimaryContact':
                    $contactClientController->setPrimaryContact();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'contactClient');
                    break;
            }
            break;

        case 'sites_client':
            $siteClientController = new SiteClientController();
            switch ($action) {
                case 'index':
                    $siteClientController->index();
                    break;
                case 'view':
                    if ($id) {
                        $siteClientController->view($id);
                    } else {
                        header('Location: ' . BASE_URL . 'sites_client');
                    }
                    break;
                default:
                    header('Location: ' . BASE_URL . 'sites_client');
                    break;
            }
            break;

        case 'settings':
            $settingsController = new SettingsController();
            switch ($action) {
                case 'index':
                    require_once VIEWS_PATH . '/settings/index.php';
                    break;
                case 'accessLevels':
                    $settingsController->accessLevels();
                    break;
                case 'saveAccessLevelVisibility':
                    $settingsController->saveAccessLevelVisibility();
                    break;
                case 'createAccessLevel':
                    $settingsController->createAccessLevel();
                    break;
                case 'getContractsByAccessLevel':
                    if ($id) {
                        $settingsController->getContractsByAccessLevel($id);
                    } else {
                        header('Location: ' . BASE_URL . 'settings');
                    }
                    break;
                case 'applyVisibilityToAllMaterials':
                    $settingsController->applyVisibilityToAllMaterials();
                    break;
                case 'getVisibilityPreview':
                    if ($id) {
                        $settingsController->getVisibilityPreview($id);
                    } else {
                        header('Location: ' . BASE_URL . 'settings');
                    }
                    break;
                case 'updateAccessLevel':
                    $settingsController->updateAccessLevel();
                    break;
                case 'deleteAccessLevel':
                    $settingsController->deleteAccessLevel();
                    break;
                case 'checkAccessLevelDeletion':
                    $settingsController->checkAccessLevelDeletion();
                    break;
                case 'updateAccessLevelOrder':
                    $settingsController->updateAccessLevelOrder();
                    break;
                case 'icons':
                    $settingsController->icons();
                    break;
                case 'updateIcons':
                    $settingsController->updateIcons();
                    break;
                case 'fileExtensions':
                    $settingsController->fileExtensions();
                    break;
                case 'getAllowedExtensions':
                    $settingsController->getAllowedExtensions();
                    break;
                case 'configuration':
                    $settingsController->configuration();
                    break;
                case 'saveConfiguration':
                    $settingsController->saveConfiguration();
                    break;
                case 'contractTypes':
                    require_once __DIR__ . '/controllers/ContractTypeController.php';
                    $contractTypeController = new ContractTypeController($db);

                    // Récupérer l'action spécifique pour les types de contrats
                    $contractTypeAction = $parts[2] ?? 'index';
                    $contractTypeId = $parts[3] ?? null;

                    switch ($contractTypeAction) {
                        case 'index':
                            $contractTypeController->index();
                            break;
                        case 'add':
                            $contractTypeController->add();
                            break;
                        case 'create':
                            $contractTypeController->create();
                            break;
                        case 'edit':
                            if ($contractTypeId) {
                                $contractTypeController->edit($contractTypeId);
                            } else {
                                header('Location: ' . BASE_URL . 'settings/contractTypes');
                            }
                            break;
                        case 'update':
                            if ($contractTypeId) {
                                $contractTypeController->update($contractTypeId);
                            } else {
                                header('Location: ' . BASE_URL . 'settings/contractTypes');
                            }
                            break;
                        case 'delete':
                            if ($contractTypeId) {
                                $contractTypeController->delete($contractTypeId);
                            } else {
                                header('Location: ' . BASE_URL . 'settings/contractTypes');
                            }
                            break;
                        case 'updateOrder':
                            $contractTypeController->updateOrder();
                            break;
                        default:
                            header('Location: ' . BASE_URL . 'settings/contractTypes');
                            break;
                    }
                    break;
                case 'interventionTypes':
                    require_once __DIR__ . '/views/settings/intervention_types.php';
                    break;
                case 'userTypes':
                    require_once __DIR__ . '/views/settings/user_types.php';
                    break;
                case 'email':
                    $settingsController->email();
                    break;
                case 'saveEmailConfig':
                    $settingsController->saveEmailConfig();
                    break;
                case 'saveEmailSettings':
                    $settingsController->saveEmailSettings();
                    break;
                case 'emailTemplate':
                    $templateId = $parts[2] ?? null;
                    $settingsController->emailTemplate($templateId);
                    break;
                case 'saveEmailTemplate':
                    $settingsController->saveEmailTemplate();
                    break;
                case 'deleteEmailTemplate':
                    $settingsController->deleteEmailTemplate();
                    break;
                case 'testSmtp':
                    $settingsController->testSmtp();
                    break;
                case 'testOAuth2':
                    $settingsController->testOAuth2();
                    break;
                case 'oauth2':
                    if ($action === 'callback') {
                        $settingsController->oauth2Callback();
                    } else {
                        header('Location: ' . BASE_URL . 'settings');
                    }
                    break;
                case 'oauth2Callback':
                    $settingsController->oauth2Callback();
                    break;
                case 'testEmailSend':
                    $settingsController->testEmailSend();
                    break;
                case 'testSmtp':
                    $settingsController->testSmtp();
                    break;
                case 'exportRoomsUrls':
                    $settingsController->exportRoomsUrls();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'settings');
                    break;
            }
            break;

        case 'qrcode':
            $qrcodeController = new QRCodeController();
            switch ($action) {
                case 'generate':
                    if (isset($parts[2]) && $parts[2] === 'site' && isset($parts[3])) {
                        $qrcodeController->generateSite($parts[3]);
                    } elseif (isset($parts[2]) && $parts[2] === 'salle' && isset($parts[3])) {
                        $qrcodeController->generateSalle($parts[3]);
                    } else {
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    break;
                case 'redirect':
                    $qrcodeController->redirect();
                    break;
                default:
                    header('Location: ' . BASE_URL . 'dashboard');
                    break;
            }
            break;

        case 'test':
            switch ($action) {
                case 'dragDrop':
                    require_once 'views/test/drag_drop_test.php';
                    break;
                default:
                    header('Location: ' . BASE_URL);
                    break;
            }
            break;
        default:
            // Redirection vers le tableau de bord par défaut
            header('Location: ' . BASE_URL . 'dashboard');
            break;
    }
} catch (Exception $e) {
    custom_log("Erreur : " . $e->getMessage(), 'ERROR', [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // En production, afficher une page d'erreur générique
    header("HTTP/1.0 500 Internal Server Error");
    echo "Une erreur est survenue";
}