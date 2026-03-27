<?php
/**
 * Fonctions de vérification d'accès avec redirection
 * Contrôle l'accès aux pages et redirige si nécessaire
 */

/**
 * Vérifie l'accès et redirige si nécessaire
 * @param string $pageType Le type de page requis
 * @param string $redirectUrl URL de redirection si pas d'accès
 */
function checkAccess($pageType = 'all', $redirectUrl = null) {
    if (!checkLogin()) {
        return; // checkLogin() gère déjà la redirection
    }
    
    if (!hasAccess($pageType)) {
        $redirectUrl = $redirectUrl ?: BASE_URL . 'auth/login';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Vérifie que l'utilisateur est staff (groupe Staff = group_id = 1)
 * Redirige vers le dashboard si ce n'est pas le cas
 * @param array $exceptions Liste des routes autorisées pour les non-staff
 */
function checkStaffAccess($exceptions = []) {
    if (!checkLogin()) {
        return; // checkLogin() gère déjà la redirection
    }
    
    // Vérifier si l'utilisateur est staff
    if (!isStaff()) {
        $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur est client (groupe Externe = group_id = 2)
 * Redirige vers le dashboard si ce n'est pas le cas
 * @param array $exceptions Liste des routes autorisées pour les non-clients
 */
function checkClientAccess($exceptions = []) {
    if (!checkLogin()) {
        return; // checkLogin() gère déjà la redirection
    }
    
    // Vérifier si l'utilisateur est client
    if (!isClient()) {
        $_SESSION['error'] = "Cette page est réservée aux clients.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de gérer les contrats
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkContractManagementAccess() {
    checkStaffAccess();
    
    if (!canManageContracts()) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer les contrats.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de gérer les clients
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkClientManagementAccess() {
    checkStaffAccess();
    
    if (!canModifyClients()) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer les clients.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de gérer les interventions
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkInterventionManagementAccess() {
    // Vérifier d'abord l'accès en lecture
    if (!canViewInterventions()) {
        $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
    
    // Si l'utilisateur n'a pas les droits de modification, on le laisse en lecture seule
    // (pas de redirection, juste pas de boutons de modification)
}

/**
 * Vérifie que l'utilisateur a la permission de gérer la documentation
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkDocumentationManagementAccess() {
    checkStaffAccess();
    
    if (!hasPermission('tech_manage_documentation')) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer la documentation.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de supprimer la documentation
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkDocumentationDeleteAccess() {
    checkStaffAccess();
    
    if (!canDeleteDocumentation()) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour supprimer la documentation.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}
