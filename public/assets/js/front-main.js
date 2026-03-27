/**
 * Main - Front Pages
 */
'use strict';

window.isRtl = window.Helpers.isRtl();
window.isDarkStyle = window.Helpers.isDarkStyle();

(function () {
  const menu = document.getElementById('navbarSupportedContent'),
    nav = document.querySelector('.layout-navbar'),
    navItemLink = document.querySelectorAll('.navbar-nav .nav-link');

  // Initialised custom options if checked
  setTimeout(function () {
    window.Helpers.initCustomOptionCheck();
  }, 1000);

  if (typeof Waves !== 'undefined') {
    Waves.init();
    Waves.attach(".btn[class*='btn-']:not([class*='btn-outline-']):not([class*='btn-label-'])", ['waves-light']);
    Waves.attach("[class*='btn-outline-']");
    Waves.attach("[class*='btn-label-']");
    Waves.attach('.pagination .page-item .page-link');
  }

  // Init BS Tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  if (isRtl) {
    // If layout is RTL add .dropdown-menu-end class to .dropdown-menu
    Helpers._addClass('dropdown-menu-end', document.querySelectorAll('#layout-navbar .dropdown-menu'));
    // If layout is RTL add .dropdown-menu-end class to .dropdown-menu
    Helpers._addClass('dropdown-menu-end', document.querySelectorAll('.dropdown-menu'));
  }

  // Navbar
  window.addEventListener('scroll', e => {
    if (window.scrollY > 10) {
      nav.classList.add('navbar-active');
    } else {
      nav.classList.remove('navbar-active');
    }
  });
  window.addEventListener('load', e => {
    if (window.scrollY > 10) {
      nav.classList.add('navbar-active');
    } else {
      nav.classList.remove('navbar-active');
    }
  });

  // Function to close the mobile menu
  function closeMenu() {
    menu.classList.remove('show');
  }

  document.addEventListener('click', function (event) {
    // Check if the clicked element is inside mobile menu
    if (!menu.contains(event.target)) {
      closeMenu();
    }
  });
  navItemLink.forEach(link => {
    link.addEventListener('click', event => {
      if (!link.classList.contains('dropdown-toggle')) {
        closeMenu();
      } else {
        event.preventDefault();
      }
    });
  });

  // Mega dropdown
  const megaDropdown = document.querySelectorAll('.nav-link.mega-dropdown');
  if (megaDropdown) {
    megaDropdown.forEach(e => {
      new MegaDropdown(e);
    });
  }

  // Thème fixe : sidebar foncée, reste clair (semi-dark)
  // Forcer le thème semi-dark et supprimer les anciens thèmes du localStorage
  document.documentElement.setAttribute('data-bs-theme', 'semi-dark');
  document.documentElement.setAttribute('data-semidark-menu', 'true');
  
  // Nettoyer le localStorage des anciens thèmes
  const themeKeys = Object.keys(localStorage).filter(key => key.startsWith('theme-') || key.startsWith('semi-dark-'));
  themeKeys.forEach(key => localStorage.removeItem(key));
  
  // Run switchImage function pour le thème light (car semi-dark utilise light pour les images)
  window.Helpers.switchImage('light');

  function getScrollbarWidth() {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.setProperty('--bs-scrollbar-width', `${scrollbarWidth}px`);
  }
  getScrollbarWidth();
})();
