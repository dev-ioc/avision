<?php
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/ContactModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class SiteController {
    use AccessControlTrait;
    private $db;
    private $siteModel;
    private $contactModel;
    private $clientModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->siteModel = new SiteModel($this->db);
        $this->contactModel = new ContactModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }


    /**
     * Affiche le formulaire d'ajout d'un site
     */
    public function add($clientId) {
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits de création
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer un site.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '#sites');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'client_id' => $clientId,
                'name' => $_POST['name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'city' => $_POST['city'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                'status' => 1
            ];

            if ($this->siteModel->createSite($data)) {
                $_SESSION['success'] = "Site ajouté avec succès.";
                
                // Gérer le retour intelligent
                $returnTo = $_GET['return_to'] ?? 'edit';
                if ($returnTo === 'view') {
                    header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab');
                } else {
                    header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '#sites');
                }
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du site.";
                
                // Gérer le retour en cas d'erreur
                $returnTo = $_GET['return_to'] ?? 'edit';
                if ($returnTo === 'view') {
                    header('Location: ' . BASE_URL . 'site/add/' . $clientId . '?return_to=view');
                } else {
                    header('Location: ' . BASE_URL . 'site/add/' . $clientId);
                }
                exit;
            }
        }

        // Récupérer les informations du client
        $client = $this->clientModel->getClientById($clientId);
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($clientId);

        // Définir les variables pour la vue
        $pageTitle = "Ajouter un site";
        require_once VIEWS_PATH . '/site/add.php';
    }

    /**
     * Affiche le formulaire d'édition d'un site
     */
    public function edit($id) {
        $this->checkAccess();

        $site = $this->siteModel->getSiteById($id);
        if (!$site) {
            $_SESSION['error'] = "Site non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier ce site.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $site['id'] . '#sites');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'city' => $_POST['city'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                'status' => isset($_POST['status']) ? 1 : 0
            ];

            if ($this->siteModel->updateSite($id, $data)) {
                $_SESSION['success'] = "Site modifié avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $site['id'] . '#sites');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification du site.";
            }
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($site['client_id']);

        $isAdmin = isAdmin(); // Définir isAdmin pour la vue

        $pageTitle = "Modifier le site - " . $site['name'];
        require_once VIEWS_PATH . '/site/edit.php';
    }

    /**
     * Supprime un site
     */
    public function delete($id) {
        $this->checkAccess();

        // Récupérer le site d'abord
        $site = $this->siteModel->getSiteById($id);
        if (!$site) {
            $_SESSION['error'] = "Site non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'utilisateur est un administrateur
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des sites.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $site['id'] . '#sites');
            exit;
        }

        if ($this->siteModel->deleteSite($id)) {
            $_SESSION['success'] = "Site supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du site.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '#sites');
        exit;
    }
} 