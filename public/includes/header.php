<?php
/**
 * Header de l'application
 * Inclut les dépendances et le menu latéral
 */

// Vérification de la session
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}


// Inclure les fonctions utilitaires (seulement si pas déjà inclus)
if (!function_exists('setPageVariables')) {
    require_once 'includes/functions.php';
}

// Variables par défaut si non définies
if (!isset($pageTitle)) $pageTitle = 'Videosonic';
if (!isset($pageDescription)) $pageDescription = '';
if (!isset($currentPageName)) $currentPageName = 'index';
?>
<!doctype html>

<html
  lang="en"
  class="layout-menu-fixed"
  dir="ltr"
  data-skin="default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-starter"
  data-bs-theme="semi-dark">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title><?php echo h($pageTitle); ?></title>

    <meta name="description" content="<?php echo h($pageDescription); ?>" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/pickr/pickr-themes.css" />

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/css/core.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/avision.css" />
    
    <!-- CSS critique pour le thème semi-dark (doit être chargé après core.css) -->
    <style>
        /* Variables pour le thème semi-dark - chargement critique */
        [data-bs-theme=semi-dark] {
          /* Contenu principal reste clair */
          --bs-body-color: #646e78;
          --bs-body-color-rgb: 100, 110, 120;
          --bs-body-bg: #f5f5f9;
          --bs-body-bg-rgb: 245, 245, 249;
          --bs-emphasis-color: #22303e;
          --bs-emphasis-color-rgb: 34, 48, 62;
          --bs-secondary-color: #a7acb2;
          --bs-secondary-color-rgb: 167, 172, 178;
          --bs-secondary-bg: #e4e6e8;
          --bs-secondary-bg-rgb: 228, 230, 232;
          --bs-tertiary-color: rgba(100, 110, 120, 0.5);
          --bs-tertiary-color-rgb: 100, 110, 120;
          --bs-tertiary-bg: #e9eaec;
          --bs-tertiary-bg-rgb: 233, 234, 236;
          
          /* Paper background reste clair */
          --bs-paper-bg: #fff;
          --bs-paper-bg-rgb: 255, 255, 255;
          
          /* Menu devient sombre */
          --bs-menu-bg: #2b2c40;
          --bs-menu-bg-rgb: 43, 44, 64;
          --bs-menu-color: #b2b2c4;
          --bs-menu-color-rgb: 178, 178, 196;
          --bs-menu-hover-bg: rgba(230, 230, 241, 0.06);
          --bs-menu-hover-color: #b2b2c4;
          --bs-menu-active-bg: var(--bs-primary);
          --bs-menu-active-color: #fff;
          --bs-menu-active-toggle-bg: rgba(230, 230, 241, 0.08);
          --bs-menu-active-toggle-color: #b2b2c4;
          --bs-menu-sub-bg: rgba(0, 0, 0, 0.2);
          --bs-menu-sub-color: #b2b2c4;
          --bs-menu-sub-hover-bg: rgba(230, 230, 241, 0.06);
          --bs-menu-sub-hover-color: #b2b2c4;
          --bs-menu-sub-active-bg: rgba(230, 230, 241, 0.08);
          --bs-menu-sub-active-color: #b2b2c4;
          --bs-menu-header-color: #b2b2c4;
          --bs-menu-border-color: rgba(230, 230, 241, 0.08);
          --bs-menu-box-shadow: 0 0 0 1px rgba(230, 230, 241, 0.08);
          
          /* Navbar reste clair */
          --bs-navbar-bg: var(--bs-paper-bg);
          --bs-navbar-color: var(--bs-body-color);
          --bs-navbar-hover-color: var(--bs-emphasis-color);
          --bs-navbar-disabled-color: var(--bs-secondary-color);
          --bs-navbar-active-color: var(--bs-emphasis-color);
          --bs-navbar-brand-color: var(--bs-emphasis-color);
          --bs-navbar-brand-hover-color: var(--bs-emphasis-color);
          --bs-navbar-toggler-border-color: var(--bs-border-color);
          --bs-navbar-toggler-icon-bg: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23646e78' d='M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z'/%3E%3C/svg%3E");
          
          /* Border et autres éléments restent clairs */
          --bs-border-color: #e4e6e8;
          --bs-border-color-translucent: rgba(0, 0, 0, 0.175);
          --bs-heading-color: var(--bs-emphasis-color);
          --bs-link-color: var(--bs-primary);
          --bs-link-hover-color: var(--bs-primary);
          --bs-link-color-rgb: var(--bs-primary-rgb);
          --bs-link-hover-color-rgb: var(--bs-primary-rgb);
          --bs-code-color: #e83e8c;
          --bs-highlight-bg: #fff3cd;
        }
    </style>

    <!-- Vendors CSS -->

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />

    <!-- Custom DataTables Dark Mode Fixes -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/datatables-dark-mode.css" />

    <!-- ApexCharts CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/apex-charts/apex-charts.css" />

                    <!-- FullCalendar CSS -->
                <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/fullcalendar/fullcalendar.css" />

                <!-- Select2 CSS -->
                <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/select2/select2.css" />

    <!-- endbuild -->

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/js/helpers.js"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->

    <script src="<?php echo BASE_URL; ?>assets/js/config.js"></script>

    <!-- Fonctions JavaScript partagées -->
    <script src="<?php echo BASE_URL; ?>assets/js/shared-functions.js"></script>
    
    <!-- Initialisation des limites d'upload PHP pour JavaScript -->
    <script>
    // Fonction pour obtenir la limite effective d'upload du serveur
    window.getServerMaxUploadSize = function() {
        const phpMaxFileSize = '<?php echo ini_get("upload_max_filesize"); ?>';
        const phpPostMaxSize = '<?php echo ini_get("post_max_size"); ?>';
        return Math.min(parsePhpSize(phpMaxFileSize), parsePhpSize(phpPostMaxSize));
    };
    </script>

                    <!-- jQuery -->
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

                <!-- Select2 -->
                <script src="<?php echo BASE_URL; ?>assets/vendor/libs/select2/select2.js"></script>

                <!-- FullCalendar JS -->
                <script src="<?php echo BASE_URL; ?>assets/vendor/libs/fullcalendar/fullcalendar.js"></script>

    <script>
    (function() {
      // Thème fixe : sidebar foncée, reste clair (semi-dark)
      try {
        // Forcer le thème semi-dark
        document.documentElement.setAttribute('data-bs-theme', 'semi-dark');
        document.documentElement.setAttribute('data-semidark-menu', 'true');
        
        // Nettoyer le localStorage des anciens thèmes
        var templateName = document.documentElement.getAttribute('data-template') || 'vertical-menu-template-starter';
        localStorage.removeItem('theme-' + templateName);
        localStorage.removeItem('semi-dark-' + templateName);
      } catch(e) {}
    })();
    </script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container"> 