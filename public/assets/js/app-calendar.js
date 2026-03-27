/**
 * App Calendar
 */

'use strict';

// DOM is ready
document.addEventListener('DOMContentLoaded', function () {
  (function () {
    const calendarEl = document.getElementById('calendar');
    const addEventSidebar = document.getElementById('addEventSidebar');

    // Initialize FullCalendar
    if (calendarEl) {
      if (typeof Calendar === 'undefined') {
        console.error('❌ FullCalendar non disponible !');
        calendarEl.innerHTML = '<div class="alert alert-danger">Erreur : FullCalendar n\'est pas chargé. Vérifiez votre connexion internet.</div>';
        return;
      }
      
      console.log('✅ FullCalendar disponible:', typeof Calendar);
      
      // Vérifier que les plugins sont disponibles
      if (typeof dayGridPlugin === 'undefined' || typeof timegridPlugin === 'undefined' || 
          typeof listPlugin === 'undefined' || typeof interactionPlugin === 'undefined') {
        console.error('❌ Plugins FullCalendar non disponibles !');
        calendarEl.innerHTML = '<div class="alert alert-danger">Erreur : Les plugins FullCalendar ne sont pas chargés.</div>';
        return;
      }
      
      const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timegridPlugin, listPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        locale: 'fr',
        buttonText: {
          today: 'Aujourd\'hui',
          month: 'Mois',
          week: 'Semaine',
          day: 'Jour',
          list: 'Liste'
        },
        eventDisplay: 'block',
        eventContent: function(arg) {
          return {
            html: arg.event.title
          };
        },
        events: function(info, successCallback, failureCallback) {
          // Charger les événements depuis l'API
          console.log('🔍 Chargement des événements pour:', info.startStr, 'à', info.endStr);
          
          // Récupérer les filtres actifs
          const activeFilters = Array.from(document.querySelectorAll('.input-filter:checked'))
            .map(filter => filter.dataset.value);
          
          console.log('🔍 Filtres actifs:', activeFilters);
          
          fetch(BASE_URL + 'agenda/getEvents?' + new URLSearchParams({
            start: info.startStr,
            end: info.endStr,
            filters: JSON.stringify(activeFilters)
          }))
          .then(response => {
            console.log('📡 Réponse API reçue:', response.status);
            return response.json();
          })
          .then(data => {
            console.log('📋 Données reçues:', data);
            
            const events = data.map(event => {
              console.log('🎯 Traitement événement:', event.id, event.extendedProps);
              
              // Créer le titre avec heure, numéro d'intervention et client
              let time = event.extendedProps?.planned_time || '09:00';
              // Enlever les secondes si présentes
              if (time && time.length > 5) {
                time = time.substring(0, 5);
              }
              const interventionNumber = event.extendedProps?.reference || '#' + event.id;
              const clientName = event.extendedProps?.client || 'Client inconnu';
              const displayTitle = time + ' ' + interventionNumber + '<br>' + clientName;
              
              return {
                id: event.id,
                title: displayTitle,
                start: event.start,
                end: event.end,
                backgroundColor: event.extendedProps?.priority_color || '#6c757d',
                borderColor: event.extendedProps?.priority_color || '#6c757d',
                extendedProps: {
                  status: event.extendedProps?.status,
                  client: event.extendedProps?.client,
                  technician: event.extendedProps?.technician,
                  technician_id: event.extendedProps?.technician_id,
                  description: event.extendedProps?.description,
                  original_title: event.extendedProps?.original_title || event.title,
                  reference: event.extendedProps?.reference,
                  site: event.extendedProps?.site,
                  room: event.extendedProps?.room,
                  priority: event.extendedProps?.priority,
                  type: event.extendedProps?.type,
                  planned_date: event.extendedProps?.planned_date,
                  planned_time: event.extendedProps?.planned_time,
                  duration: event.extendedProps?.duration
                }
              };
            });
            
            console.log('✅ Événements traités:', events);
            successCallback(events);
          })
          .catch(error => {
            console.error('❌ Erreur lors du chargement des événements:', error);
            failureCallback(error);
          });
        },
        eventClick: function(info) {
          // Ouvrir le modal d'édition
          openEventModal(info.event);
        },
        selectable: false
      });

      calendar.render();
      window.calendar = calendar;
    }

    // Initialize sidebar
    function initSidebar() {
      // Initialize inline calendar (optionnel)
      const inlineCalendar = document.querySelector('.inline-calendar');
      if (inlineCalendar && typeof flatpickr !== 'undefined') {
        try {
          flatpickr(inlineCalendar, {
            inline: true,
            locale: 'fr',
            dateFormat: 'd/m/Y',
            onChange: function(selectedDates, dateStr, instance) {
              if (window.calendar) {
                window.calendar.gotoDate(selectedDates[0]);
              }
            }
          });
          console.log('✅ Flatpickr initialisé avec succès');
        } catch (error) {
          console.warn('⚠️ Erreur Flatpickr:', error.message);
        }
      } else {
        console.log('ℹ️ Flatpickr non disponible - calendrier inline désactivé');
      }

      // Initialize Select2 (optionnel)
      if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
        try {
          $('.select2').select2({
            dropdownParent: $('#addEventSidebar')
          });
          console.log('✅ Select2 initialisé avec succès');
        } catch (error) {
          console.warn('⚠️ Erreur Select2:', error.message);
        }
      } else {
        console.log('ℹ️ Select2 non disponible - utilisation des dropdowns natifs');
      }
    }

    // Initialize filters
    function initFilters() {
      const selectAll = document.getElementById('selectAll');
      const filters = document.querySelectorAll('.input-filter');

      if (selectAll) {
        selectAll.addEventListener('change', function() {
          const isChecked = this.checked;
          filters.forEach(filter => {
            filter.checked = isChecked;
          });
          filterEvents();
        });
      }

      filters.forEach(filter => {
        filter.addEventListener('change', function() {
          const allChecked = Array.from(filters).every(f => f.checked);
          if (selectAll) {
            selectAll.checked = allChecked;
          }
          filterEvents();
        });
      });
    }

    function filterEvents() {
      const activeFilters = Array.from(document.querySelectorAll('.input-filter:checked'))
        .map(filter => filter.dataset.value);
      
      if (window.calendar) {
        window.calendar.refetchEvents();
      }
    }

    function openEventModal(event) {
      console.log('🔍 Ouverture modal pour événement:', event);
      console.log('📋 ExtendedProps:', event.extendedProps);
      
      // Fill details with event data
      const eventReferenceEl = document.getElementById('eventReference');
      const eventTitleEl = document.getElementById('eventTitle');
      const eventStatusEl = document.getElementById('eventStatus');
      const eventPriorityEl = document.getElementById('eventPriority');
      const eventTypeEl = document.getElementById('eventType');
      const eventClientEl = document.getElementById('eventClient');
      const eventSiteEl = document.getElementById('eventSite');
      const eventRoomEl = document.getElementById('eventRoom');
      const eventTechnicianEl = document.getElementById('eventTechnician');
      const eventPlannedDateEl = document.getElementById('eventPlannedDate');
      const eventPlannedTimeEl = document.getElementById('eventPlannedTime');
      const eventDurationEl = document.getElementById('eventDuration');
      const eventDescriptionEl = document.getElementById('eventDescription');
      const viewInterventionLink = document.getElementById('viewInterventionLink');
      
      // Fill the fields with data from extendedProps
      const reference = event.extendedProps.reference || '#' + event.id;
      const title = event.extendedProps.original_title || event.title.split('\n')[1] || event.title;
      const status = event.extendedProps.status || '-';
      const priority = event.extendedProps.priority || '-';
      const type = event.extendedProps.type || '-';
      const client = event.extendedProps.client || '-';
      const site = event.extendedProps.site || '-';
      const room = event.extendedProps.room || '-';
      const technician = event.extendedProps.technician || 'Non attribué';
      const plannedDate = event.extendedProps.planned_date || '-';
      const plannedTime = event.extendedProps.planned_time || '-';
      const duration = (event.extendedProps.duration || 0) + 'h';
      const description = event.extendedProps.description || '-';
      
      console.log('📝 Valeurs à afficher:', {
        reference, title, status, priority, type, client, site, room, technician, plannedDate, plannedTime, duration, description
      });
      
      if (eventReferenceEl) eventReferenceEl.textContent = reference;
      if (eventTitleEl) eventTitleEl.textContent = title;
      if (eventStatusEl) eventStatusEl.textContent = status;
      if (eventPriorityEl) eventPriorityEl.textContent = priority;
      if (eventTypeEl) eventTypeEl.textContent = type;
      if (eventClientEl) eventClientEl.textContent = client;
      if (eventSiteEl) eventSiteEl.textContent = site;
      if (eventRoomEl) eventRoomEl.textContent = room;
      if (eventTechnicianEl) eventTechnicianEl.textContent = technician;
      if (eventPlannedDateEl) eventPlannedDateEl.textContent = plannedDate;
      if (eventPlannedTimeEl) eventPlannedTimeEl.textContent = plannedTime;
      if (eventDurationEl) eventDurationEl.textContent = duration;
      if (eventDescriptionEl) eventDescriptionEl.textContent = description;
      
      // Set link to intervention view
      if (viewInterventionLink) {
        viewInterventionLink.href = BASE_URL + 'interventions/view/' + event.id;
      }
      
      // Show modal
      const modal = new bootstrap.Offcanvas(addEventSidebar);
      modal.show();
    }

    // Initialize components
    initSidebar();
    initFilters();

  })();
});
