<?php
/**
 * Trait pour centraliser les vérifications d'accès dans les contrôleurs
 * 
 * Ce trait fournit des méthodes communes pour vérifier les permissions
 * et rediriger les utilisateurs non autorisés.
 */
trait AccessControlTrait {
    
    /**
     * Vérifie que l'utilisateur est connecté et est staff
     * Méthode de base utilisée par la plupart des contrôleurs staff
     */
    protected function checkAccess() {
        checkStaffAccess();
    }
    
    /**
     * Vérifie simplement que l'utilisateur est connecté
     * Utilisé par les contrôleurs client qui ont leurs propres vérifications de permission
     */
    protected function checkLoginOnly() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
    }
    
    /**
     * Vérifie que l'utilisateur est connecté et est client
     */
    protected function checkClientAccess() {
        checkClientAccess();
    }
    
    /**
     * Vérifie que l'utilisateur est administrateur
     * Appelle d'abord checkAccess() puis vérifie si admin
     */
    protected function checkAdminAccess($customMessage = null) {
        $this->checkAccess();
        
        if (!isAdmin()) {
            $message = $customMessage ?: "Seuls les administrateurs peuvent accéder à cette page.";
            $_SESSION['error'] = $message;
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }
    
    /**
     * Vérifie une permission spécifique
     * @param string $permission Le nom de la permission à vérifier
     * @param string|null $errorMessage Message d'erreur personnalisé
     */
    protected function checkPermission($permission, $errorMessage = null) {
        $this->checkAccess();
        
        if (!hasPermission($permission)) {
            $message = $errorMessage ?: "Vous n'avez pas les permissions nécessaires pour accéder à cette page.";
            $_SESSION['error'] = $message;
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }
    
    /**
     * Vérifie l'accès à la gestion des contrats
     */
    protected function checkContractManagementAccess() {
        checkContractManagementAccess();
    }
    
    /**
     * Vérifie l'accès à la gestion des clients
     */
    protected function checkClientManagementAccess() {
        checkClientManagementAccess();
    }
    
    /**
     * Vérifie l'accès à la gestion des interventions
     */
    protected function checkInterventionManagementAccess() {
        checkInterventionManagementAccess();
    }
    
    /**
     * Vérifie l'accès à la gestion de la documentation
     */
    protected function checkDocumentationManagementAccess() {
        checkDocumentationManagementAccess();
    }
    
    /**
     * Vérifie l'accès à la suppression de la documentation
     */
    protected function checkDocumentationDeleteAccess() {
        checkDocumentationDeleteAccess();
    }
    
    /**
     * Vérifie l'accès avec gestion spéciale pour les requêtes AJAX
     * @param string $pageType Type de page (par défaut 'all')
     */
    protected function checkAccessWithAjax($pageType = 'all') {
        if (!isset($_SESSION['user'])) {
            // Vérifier si c'est une requête AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                http_response_code(401);
                echo json_encode(['error' => 'Non autorisé']);
                exit;
            }
            
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
        
        if (!hasAccess($pageType)) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }
    
    /**
     * Vérifie l'accès avec vérification de permission client spécifique
     * @param string $permission Permission à vérifier
     */
    protected function checkClientPermission($permission) {
        $this->checkLoginOnly();
        
        if (!hasPermission($permission)) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour accéder à cette page.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }
    
    /**
     * Vérifie l'accès avec vérification optionnelle d'un client spécifique
     * @param int|null $clientId ID du client à vérifier (optionnel)
     */
    protected function checkAccessWithClient($clientId = null) {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
        
        // Si un clientId est fourni, vérifier l'accès à ce client
        if ($clientId !== null) {
            if (!canViewClientData($clientId)) {
                $_SESSION['error'] = "Vous n'avez pas accès à ce client.";
                header('Location: ' . BASE_URL . 'dashboard');
                exit;
            }
        }
    }
}
