<?php
/**
 * Fonctions de permissions et autorisations
 * Vérifie ce que l'utilisateur peut faire
 */

/**
 * Vérifie si l'utilisateur a une permission spécifique
 * @param string $permission Le nom de la permission
 * @return bool true si l'utilisateur a la permission
 */
function hasPermission($permission) {
    $user = $_SESSION['user'] ?? null;
    
    if (!$user) return false;
    
    // Administrateur a toutes les permissions
    if (isAdmin()) return true;
    
    // Vérifier les permissions spécifiques
    if (isset($user['permissions']) && is_array($user['permissions'])) {
        if (isset($user['permissions']['rights']) && is_array($user['permissions']['rights'])) {
            return isset($user['permissions']['rights'][$permission]) && 
                   $user['permissions']['rights'][$permission] === true;
        } else {
            return isset($user['permissions'][$permission]) && 
                   $user['permissions'][$permission] === true;
        }
    }
    
    return false;
}

/**
 * Vérifie si l'utilisateur peut voir les interventions (accès en lecture)
 * @return bool true si l'utilisateur peut voir les interventions
 */
function canViewInterventions() {
    // Tous les staff peuvent voir les interventions (lecture seule)
    return isStaff();
}

/**
 * Vérifie si l'utilisateur peut créer/modifier des interventions
 * @return bool true si l'utilisateur peut créer/modifier des interventions
 */
function canModifyInterventions() {
    // Staff + permission tech_manage_interventions
    return isStaff() && hasPermission('tech_manage_interventions');
}

/**
 * Vérifie si l'utilisateur peut supprimer des interventions
 * @return bool true si l'utilisateur peut supprimer des interventions
 */
function canDeleteInterventions() {
    // Staff + admin uniquement
    return isStaff() && isAdmin();
}

/**
 * Vérifie si l'utilisateur peut modifier le matériel
 * @return bool true si l'utilisateur peut modifier le matériel
 */
function canModifyMateriel() {
    // Staff + permission tech_manage_documentation
    return isStaff() && hasPermission('tech_manage_documentation');
}

/**
 * Vérifie si un utilisateur client peut modifier le matériel
 * @return bool true si l'utilisateur client peut modifier le matériel
 */
function canModifyMaterielClient() {
    if (!isClient()) {
        return false;
    }
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Vérifier la permission client_modify_materiel
    if (isset($user['permissions']['rights']['client_modify_materiel']) && 
        $user['permissions']['rights']['client_modify_materiel'] === true) {
        return true;
    }
    
    return false;
}

/**
 * Vérifie si l'utilisateur peut modifier les clients
 * @return bool true si l'utilisateur a les droits de modification des clients
 */
function canModifyClients() {
    return hasPermission('tech_manage_clients');
}

/**
 * Vérifie si l'utilisateur peut gérer les contrats
 * @return bool true si l'utilisateur a les droits de gestion des contrats
 */
function canManageContracts() {
    return hasPermission('tech_manage_contrats');
}

/**
 * Vérifie si l'utilisateur peut supprimer des éléments
 * @return bool true si l'utilisateur peut supprimer
 */
function canDelete() {
    return isAdmin();
}

/**
 * Vérifie si l'utilisateur peut gérer la documentation
 * @return bool true si l'utilisateur peut gérer la documentation
 */
function canManageDocumentation() {
    return hasPermission('tech_manage_documentation');
}

/**
 * Vérifie si l'utilisateur peut supprimer la documentation
 * @return bool true si l'utilisateur peut supprimer la documentation
 */
function canDeleteDocumentation() {
    // Administrateur a toutes les permissions
    if (isAdmin()) return true;
    
    // Staff + permission tech_delete_documentation
    return isStaff() && hasPermission('tech_delete_documentation');
}

/**
 * Vérifie si l'utilisateur peut importer du matériel
 * @return bool true si l'utilisateur peut importer du matériel
 */
function canImportMateriel() {
    return isAdmin() || hasPermission('tech_manage_documentation');
}

/**
 * Vérifie si un utilisateur client peut modifier ses propres informations
 * @return bool true si l'utilisateur client peut modifier ses informations
 */
function canModifyOwnInfo() {
    if (!isClient()) {
        return false;
    }
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Vérifier la permission client_modify_info
    if (isset($user['permissions']['rights']['client_modify_info']) && 
        $user['permissions']['rights']['client_modify_info'] === true) {
        return true;
    }
    
    return false;
}

/**
 * Vérifie si un utilisateur client peut gérer les contacts de sa localisation
 * @return bool true si l'utilisateur client peut gérer les contacts
 */
function canManageOwnContacts() {
    if (!isClient()) {
        return false;
    }
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Vérifier la permission client_manage_contacts
    if (isset($user['permissions']['rights']['client_manage_contacts']) && 
        $user['permissions']['rights']['client_manage_contacts'] === true) {
        return true;
    }
    
    return false;
}

/**
 * Vérifie si l'utilisateur a accès à une page spécifique
 * @param string $pageType Le type de page ('admin', 'staff', 'externe', 'all')
 * @return bool true si l'utilisateur a accès
 */
function hasAccess($pageType = 'all') {
    switch ($pageType) {
        case 'admin':
            return isAdmin();
        case 'staff':
            return isStaff();
        case 'externe':
            return isUserGroup('Externe');
        case 'all':
        default:
            return true;
    }
}
