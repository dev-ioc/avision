<?php
/**
 * Menu latéral de l'application
 * Différencié selon le type d'utilisateur (admin, technicien, client)
 */

// Inclure les fonctions utilitaires
require_once __DIR__ . '/functions.php';
?>

     <!-- Menu -->

        <aside id="layout-menu" class="layout-menu menu-vertical menu">
        <div class="app-brand mb-4 d-flex justify-content-center">
 
      <img src="<?= BASE_URL ?>assets/img/logo_avision.png" class="w-auto h-px-100" alt="Logo AVision">
 
            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="bi bi-chevron-left"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>

          <ul class="menu-inner py-1">
            <!-- Page -->

            <!-- - Admin et Techniciens -->
             <?php if (isStaff()): ?>

            <li class="menu-item <?php echo isActivePage('dashboard'); ?>">
              <a href="<?php echo BASE_URL; ?>dashboard" class="menu-link">
                <i class="menu-icon bi bi-house"></i>
                <div data-i18n="Page 1">Tableau de bord</div>
              </a>
            </li>

            <?php 
            // Fonction pour détecter si on est sur une page d'intervention
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
            $currentPath = parse_url($currentUrl, PHP_URL_PATH);
            $basePath = parse_url(BASE_URL, PHP_URL_PATH);
            $relativePath = $basePath ? str_replace($basePath, '', $currentPath) : $currentPath;
            $relativePath = trim($relativePath, '/');
            
            $isInterventionPage = isActivePage('interventions') || 
                                 strpos($relativePath, 'interventions/') === 0 ||
                                 $relativePath === 'interventions';
            
            // Détection précise de chaque page
            $isInterventionCurativesPage = $relativePath === 'interventions/curatives' || 
                                         strpos($relativePath, 'interventions/curatives') === 0;
            $isInterventionPreventivesPage = $relativePath === 'interventions/preventives' || 
                                            strpos($relativePath, 'interventions/preventives') === 0;
            ?>
            <li class="menu-item <?php echo $isInterventionPage ? 'active open' : ''; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon bi bi-tools"></i>
                <div data-i18n="interventions">Interventions</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item <?php echo $isInterventionCurativesPage ? 'active' : ''; ?>">
                  <a href="<?php echo BASE_URL; ?>interventions/curatives" class="menu-link">
                    <i class="menu-icon bi bi-tools me-2"></i>
                    <div data-i18n="interventions_curatives">Curatives</div>
                  </a>
                </li>
                <li class="menu-item <?php echo $isInterventionPreventivesPage ? 'active' : ''; ?>">
                  <a href="<?php echo BASE_URL; ?>interventions/preventives" class="menu-link">
                    <i class="menu-icon bi bi-shield-check me-2"></i>
                    <div data-i18n="interventions_preventives">Préventives</div>
                  </a>
                </li>
              </ul>
            </li>

            <li class="menu-item <?php echo isActivePage('agenda'); ?>">
              <a href="<?php echo BASE_URL; ?>agenda" class="menu-link">
                <i class="menu-icon bi bi-calendar-event"></i>
                <div data-i18n="agenda">Agenda</div>
              </a>
             </li>


            <li class="menu-item <?php echo isActivePage('clients'); ?>">
              <a href="<?php echo BASE_URL; ?>clients" class="menu-link">
                <i class="menu-icon bi bi-file-text"></i>
                <div data-i18n="Page 2">Clients</div>
              </a>
            </li>

            <?php 
            // Fonction pour détecter si on est sur une page de contrat
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
            $isContractPage = isActivePage('contracts') || isActivePage('contract_types') || 
                             isActivePage('hors_contrat_facturable') || isActivePage('hors_contrat_non_facturable') ||
                             strpos($currentUrl, '/contracts/') !== false ||
                             strpos($currentUrl, '/hors_contrat_facturable') !== false ||
                             strpos($currentUrl, '/hors_contrat_non_facturable') !== false;
            
            // Détection précise de chaque page
            $isContractListPage = (isActivePage('contracts') && !isActivePage('contract_types')) || 
                                 ($currentUrl === BASE_URL . 'contracts' || $currentUrl === BASE_URL . 'contracts/');
            $isContractAddPage = strpos($currentUrl, '/contracts/add') !== false;
            $isContractViewPage = preg_match('/\/contracts\/view\/\d+/', $currentUrl);
            $isContractEditPage = preg_match('/\/contracts\/edit\/\d+/', $currentUrl);
            $isContractTypesPage = isActivePage('contract_types');
            ?>
            <li class="menu-item <?php echo $isContractPage ? 'active open' : ''; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon bi bi-file-text"></i>
                <div data-i18n="contracts">Contrats</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item <?php echo ($isContractListPage || $isContractViewPage || $isContractEditPage) ? 'active' : ''; ?>">
                  <a href="<?php echo BASE_URL; ?>contracts" class="menu-link">
                    <div data-i18n="contracts_list">Liste des contrats</div>
                  </a>
                </li>
                <li class="menu-item <?php echo (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/hors_contrat_facturable') !== false) ? 'active' : ''; ?>">
                  <a href="<?php echo BASE_URL; ?>hors_contrat_facturable" class="menu-link">
                    <div data-i18n="hors_contrat_facturable">Hors contrat facturable</div>
                  </a>
                </li>
                <li class="menu-item <?php echo (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/hors_contrat_non_facturable') !== false) ? 'active' : ''; ?>">
                  <a href="<?php echo BASE_URL; ?>hors_contrat_non_facturable" class="menu-link">
                    <div data-i18n="hors_contrat_non_facturable">Hors contrat non facturable</div>
                  </a>
                </li>
              </ul>
            </li>





            <li class="menu-item <?php echo isActivePage('materiel'); ?>">
              <a href="<?php echo BASE_URL; ?>materiel" class="menu-link">
                <i class="menu-icon bi bi-file-text"></i>
                <div data-i18n="Page 2">Matériel</div>
              </a>
            </li>

            <li class="menu-item <?php echo isActivePage('documentation'); ?>">
              <a href="<?php echo BASE_URL; ?>documentation" class="menu-link">
                <i class="menu-icon bi bi-book"></i>
                <div data-i18n="documentation">Documentation</div>
              </a>
            </li>

            <?php endif; ?>


            <?php if (isAdmin()): ?>

              <li class="menu-header small">
              <span class="menu-header-text" data-i18n="admin_menu">Administration</span>
            </li>

            <li class="menu-item <?php echo isActivePage('users'); ?>">
              <a href="<?php echo BASE_URL; ?>user" class="menu-link">
                <i class="menu-icon bi bi-file-text"></i>
                <div data-i18n="users">Utilisateurs</div>
              </a>
            </li>

            <li class="menu-item <?php echo isActivePage('settings'); ?>">
              <a href="<?php echo BASE_URL; ?>settings" class="menu-link">
                <i class="menu-icon bi bi-gear"></i>
                <div data-i18n="settings">Paramètres</div>
              </a>
            </li>



            <?php endif; ?>

            <!-- - Clients -->
            <?php if (isClient()): ?>

            <li class="menu-item <?php echo isActivePage('dashboard'); ?>">
              <a href="<?php echo BASE_URL; ?>dashboard" class="menu-link">
                <i class="menu-icon bi bi-house"></i>
                <div data-i18n="Page 1">Tableau de bord</div>
              </a>
            </li>

            <li class="menu-item <?php echo isActivePage('interventions_client'); ?>">
              <a href="<?php echo BASE_URL; ?>interventions_client" class="menu-link">
                <i class="menu-icon bi bi-tools"></i>
                <div data-i18n="interventions">Interventions</div>
              </a>
            </li>

            <li class="menu-item <?php echo isActivePage('contracts_client'); ?>">
              <a href="<?php echo BASE_URL; ?>contracts_client" class="menu-link">
                <i class="menu-icon bi bi-file-earmark-text"></i>
                <div data-i18n="contracts">Contrats</div>
              </a>
            </li>

            <li class="menu-item <?php echo isActivePage('sites_client'); ?>">
              <a href="<?php echo BASE_URL; ?>sites_client" class="menu-link">
                <i class="menu-icon bi bi-building"></i>
                <div data-i18n="sites">Sites et salles</div>
              </a>
            </li>



            <li class="menu-item <?php echo isActivePage('materiel_client'); ?>">
              <a href="<?php echo BASE_URL; ?>materiel_client" class="menu-link">
                <i class="menu-icon bi bi-archive"></i>
                <div data-i18n="materiel">Matériel</div>
              </a>
            </li>

            <li class="menu-item <?php echo isActivePage('documentation_client'); ?>">
              <a href="<?php echo BASE_URL; ?>documentation_client" class="menu-link">
                <i class="menu-icon bi bi-book"></i>
                <div data-i18n="documentation">Documentation</div>
              </a>
            </li>

            <?php if (canModifyOwnInfo()): ?>
            <li class="menu-item <?php echo isActivePage('profileClient'); ?>">
              <a href="<?php echo BASE_URL; ?>profileClient" class="menu-link">
                <i class="menu-icon bi bi-person"></i>
                <div data-i18n="profile">Profil</div>
              </a>
            </li>
            <?php endif; ?>

            <?php if (canManageOwnContacts()): ?>
            <li class="menu-item <?php echo isActivePage('contactClient'); ?>">
              <a href="<?php echo BASE_URL; ?>contactClient" class="menu-link">
                <i class="menu-icon bi bi-people"></i>
                <div data-i18n="contacts">Contacts</div>
              </a>
            </li>
            <?php endif; ?>

            <?php endif; ?>

            <!-- Bouton de déconnexion -->
            <li class="menu-item">
                <a href="<?php echo BASE_URL; ?>auth/logout" class="menu-link">
                    <i class="menu-icon bi bi-power"></i>
                    <div data-i18n="logout">Déconnexion</div>
                </a>
            </li>

            <!-- Version -->
            <li class="menu-item mt-auto">
                <div class="menu-link d-flex align-items-center justify-content-center px-3 py-2">
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-code-slash me-1"></i>Version dev 0.9.5
                    </span>
                </div>
            </li>

          </ul>
        </aside>

        <div class="menu-mobile-toggler d-xl-none rounded-1">
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
            <i class="bi bi-list icon-base"></i>
            <i class="bi bi-chevron-right icon-base"></i>
          </a>
        </div>
        <!-- / Menu --> 