<?php
require_once __DIR__ . '/../models/BuildingModel.php';
require_once __DIR__ . '/../models/ContactModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class BuildingController
{
    use AccessControlTrait;
    private $db;
    private $buildingModel;
    private $contactModel;
    private $siteModel;
    private $clientModel;

    public function __construct()
    {

        global $db;
        $this->db = $db;
        $this->buildingModel = new BuildingModel($this->db);
        $this->contactModel = new ContactModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }


    /**
     * Affiche le formulaire d'ajout d'une salle
     * Peut accepter soit un site_id (comportement classique) soit un client_id via GET
     */
    public function add($id)
    {
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
            $site = $this->buildingModel->getSiteById($siteId);
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
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer un bâtiment.";
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

                    if ($this->buildingModel->createBuilding($data)) {
                        $_SESSION['success'] = "Bâtiment ajouté avec succès.";

                        // Gérer le retour intelligent
                        $returnTo = $_GET['return_to'] ?? 'edit';
                        if ($returnTo === 'view') {
                            header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
                        } else {
                            header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_site_id=' . $formSiteId . '#sites');
                        }
                        exit;
                    } else {
                        $_SESSION['error'] = "Erreur lors de l'ajout du bâtiment.";
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
        $pageTitle = "Ajouter un bâtiment";
        require_once VIEWS_PATH . '/building/add.php';
    }

    /**
     * Affiche le formulaire d'édition d'un bâtiment
     */
    public function edit($id)
    {
        $this->checkAccess();

        // Récupérer le bâtiment d'abord
        $building = $this->buildingModel->getBuildingById($id);
        if (!$building) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer le site associé au bâtiment
        $site = $this->buildingModel->getSiteById($building['site_id']);
        if (!$site) {
            $_SESSION['error'] = "Site associé à ce bâtiment non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier ce bâtiment.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $building['site_id'] . '#sites');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                'status' => isset($_POST['status']) ? 1 : 0
            ];

            if ($this->buildingModel->updateBuilding($id, $data)) {
                $_SESSION['success'] = "Bâtiment modifié avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $building['site_id'] . '#sites');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification du bâtiment.";
            }
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($site['client_id']);

        $pageTitle = "Modifier le bâtiment - " . $building['name'];
        require_once VIEWS_PATH . '/building/edit.php';
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
            $building = $this->buildingModel->getBuildingById($id);
            if ($building && isset($building['client_id'])) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $building['client_id'] . '#sites');
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        // $building is already fetched before the isAdmin check
        $building = $this->buildingModel->getBuildingById($id);
        if (!$building) {
            $_SESSION['error'] = "Bâtiment non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard'); // Or a more relevant general page
            exit;
        }

        // Store client_id and site_id before deletion for the redirect
        $clientId = $building['client_id'];
        $siteId = $building['site_id'];

        if ($this->buildingModel->deleteBuilding($id)) {
            $_SESSION['success'] = "Bâtiment supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du bâtiment.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_site_id=' . $siteId . '#sites');
        exit;
    }

    /**
     * Récupère les salles d'un site via API
     */
    public function getBuildingBySite()
    {
        if (!isset($_GET['site_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID du site manquant']);
            exit;
        }

        $siteId = (int) $_GET['site_id'];
        $buildings = $this->buildingModel->getBuildingsBySiteId($siteId);

        header('Content-Type: application/json');
        echo json_encode($buildings);
        exit;
    }
}