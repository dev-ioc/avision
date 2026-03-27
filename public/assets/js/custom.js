// Gestion du menu mobile
document.addEventListener('DOMContentLoaded', function() {
    // Toggle du menu mobile
    const toggleBtn = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Fermer le menu mobile lors du clic en dehors
    document.addEventListener('click', function(event) {
        if (sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
}); 