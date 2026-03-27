<?php
/**
 * Fonctions utilitaires pour l'interface utilisateur
 * Variables de page, états actifs, icônes paramétrées
 */

/**
 * Définit les variables de page pour le menu
 * @param string $title Le titre de la page
 * @param string $currentPage Le nom de la page courante
 */
function setPageVariables($title, $currentPage = 'dashboard') {
   global $pageTitle, $currentPageName;
   $pageTitle = $title;
   $currentPageName = $currentPage;
}

/**
 * Vérifie si une page est active dans le menu
 * @param string $pageName Le nom de la page à vérifier
 * @return string 'active' si la page est active, sinon chaîne vide
 */
function isActivePage($pageName) {
   global $currentPageName;
   return ($currentPageName === $pageName) ? 'active' : '';
}

/**
 * Récupère la classe CSS de l'icône paramétrée pour une action donnée
 * @param string $iconKey La clé de l'icône (ex: 'view', 'edit', 'delete', ...)
 * @param string $defaultIcon Classe CSS par défaut si non trouvée
 * @return string
 */
function getIcon($iconKey, $defaultIcon = 'bi bi-eye') {
    global $db;
    try {
        $sql = "SELECT icon_class FROM settings_icons WHERE icon_key = ? AND is_active = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$iconKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['icon_class'] : $defaultIcon;
    } catch (Exception $e) {
        return $defaultIcon;
    }
}
