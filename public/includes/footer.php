            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-fluid">
                <div
                  class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                  <div class="mb-2 mb-md-0">
                    Vidéosonic©<?php echo getCurrentYear(); ?>
                  </div>
                  
                </div>
              </div>
            </footer>
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>

      <!-- Drag Target Area To SlideIn Menu On Small Screens -->
      <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/theme.js  -->

    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/jquery/jquery.js"></script>

    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/popper/popper.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/vendor/js/bootstrap.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/@algolia/autocomplete-js.js"></script>

    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/pickr/pickr.js"></script>

    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/hammer/hammer.js"></script>

    <script src="<?php echo BASE_URL; ?>assets/vendor/js/menu.js"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- DataTables JS -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js" onerror="console.error('ERREUR: datatables-bootstrap5.js n\'a pas pu être chargé');"></script>
    <!-- NOTE:
         Le bundle local datatables-bootstrap5.js inclut déjà DataTables + dépendances (JSZip/pdfmake, etc.).
         Charger Buttons depuis un CDN peut créer des incompatibilités de version et/ou renvoyer du HTML (proxy/403),
         ce qui provoque "Unexpected token '<'" et "e.ext.features.register is not a function".
    -->

    <!-- ApexCharts JS -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    
    <!-- Configuration et variables globales -->
    <!-- Core JavaScript - Phase 1: Fondations -->
    <script>
        // Initialiser AppConfig avec les valeurs PHP AVANT de charger les autres scripts
        window.AppConfig = window.AppConfig || {};
        window.AppConfig.BASE_URL = '<?= BASE_URL ?>';
        window.AppConfig.CSRF_TOKEN = '<?= csrf_token() ?>';
        window.BASE_URL = '<?= BASE_URL ?>';
        window.CSRF_TOKEN = '<?= csrf_token() ?>';
        window.PHP_MAX_FILE_SIZE = '<?php echo ini_get("upload_max_filesize"); ?>';
        
        // Debug: Afficher BASE_URL dans la console
        console.log('DEBUG: BASE_URL =', window.BASE_URL);
        console.log('DEBUG: AppConfig.BASE_URL =', window.AppConfig.BASE_URL);
        
        // Détecter les erreurs de chargement de scripts
        window.addEventListener('error', function(e) {
            if (e.target && e.target.tagName === 'SCRIPT') {
                console.error('ERREUR: Script non chargé:', e.target.src);
                console.error('ERREUR: Message:', e.message);
                console.error('ERREUR: Ligne:', e.lineno, 'Colonne:', e.colno);
            }
        }, true);
    </script>
    <script src="<?php echo BASE_URL; ?>assets/js/core/config.js" onerror="console.error('ERREUR: config.js n\'a pas pu être chargé');"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/core/utils.js" onerror="console.error('ERREUR: utils.js n\'a pas pu être chargé');"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/core/ApiClient.js" onerror="console.error('ERREUR: ApiClient.js n\'a pas pu être chargé');"></script>
    
    <!-- Components JavaScript - Phase 2: Composants réutilisables -->
    <script src="<?php echo BASE_URL; ?>assets/js/components/DragDropUploader.js" onerror="console.error('ERREUR: DragDropUploader.js n\'a pas pu être chargé');"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/components/DataTableManager.js" onerror="console.error('ERREUR: DataTableManager.js n\'a pas pu être chargé');"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/components/ModalManager.js" onerror="console.error('ERREUR: ModalManager.js n\'a pas pu être chargé');"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/components/LocationManager.js" onerror="console.error('ERREUR: LocationManager.js n\'a pas pu être chargé');"></script>

    <script src="<?php echo BASE_URL; ?>assets/js/main.js" onerror="console.error('ERREUR: main.js n\'a pas pu être chargé');"></script>

    <!-- Page JS -->
    <!-- Les scripts spécifiques aux pages sont chargés directement dans les vues -->
    
    <!-- Theme Debug Script -->
    <!-- <script src="<?php echo BASE_URL; ?>test-theme.js"></script> -->
    
    <!-- Manual Theme Test Script -->
    <!-- <script src="<?php echo BASE_URL; ?>test-theme-manual.js"></script> -->
  </body>
</html> 