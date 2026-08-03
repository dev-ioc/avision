/**
 * App Calendar
 */

"use strict";
let lastAutoJumpFilters = null;

function showCalendarMessage(text) {
  const el = document.getElementById("calendarMessage");
  if (!el) return;
  el.textContent = text;
  el.classList.remove("d-none");
}

function hideCalendarMessage() {
  const el = document.getElementById("calendarMessage");
  if (!el) return;
  el.classList.add("d-none");
}

function formatDateFr(isoDate) {
  const [y, m, d] = isoDate.split("-");
  return d + "/" + m + "/" + y;
}
document.addEventListener("DOMContentLoaded", function () {
  (function () {
    const calendarEl = document.getElementById("calendar");
    const addEventSidebar = document.getElementById("addEventSidebar");

    if (calendarEl) {
      if (typeof Calendar === "undefined") {
        calendarEl.innerHTML =
          '<div class="alert alert-danger">Erreur : FullCalendar n\'est pas chargé. Vérifiez votre connexion internet.</div>';
        return;
      }
      if (
        typeof dayGridPlugin === "undefined" ||
        typeof timegridPlugin === "undefined" ||
        typeof listPlugin === "undefined" ||
        typeof interactionPlugin === "undefined"
      ) {
        calendarEl.innerHTML =
          '<div class="alert alert-danger">Erreur : Les plugins FullCalendar ne sont pas chargés.</div>';
        return;
      }
      const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timegridPlugin, listPlugin, interactionPlugin],
        initialView: "dayGridMonth",
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
        },
        locale: "fr",
        buttonText: {
          today: "Aujourd'hui",
          month: "Mois",
          week: "Semaine",
          day: "Jour",
          list: "Liste",
        },
        eventDisplay: "block",
        eventContent: function (arg) {
          return {
            html: arg.event.title,
          };
        },
        events: function (info, successCallback, failureCallback) {
          const activeFilters = Array.from(
            document.querySelectorAll(".input-filter:checked"),
          ).map((filter) => filter.dataset.value);

          fetch(
            BASE_URL +
              "agenda/getEvents?" +
              new URLSearchParams({
                start: info.startStr,
                end: info.endStr,
                filters: JSON.stringify(activeFilters),
              }),
          )
            .then((response) => {
              return response.json();
            })
            .then((data) => {
              const events = data.map((event) => {
                let time = event.extendedProps?.planned_time || "09:00";
                if (time && time.length > 5) {
                  time = time.substring(0, 5);
                }
                const interventionNumber =
                  event.extendedProps?.reference || "#" + event.id;
                const clientName =
                  event.extendedProps?.client || "Client inconnu";
                const displayTitle =
                  time + " " + interventionNumber + "<br>" + clientName;

                let formattedDate = "-";
                const rawDate = event.extendedProps?.planned_date;

                if (rawDate) {
                  if (typeof rawDate === "string") {
                    if (rawDate.includes("-")) {
                      const parts = rawDate.split("-");
                      if (parts.length === 3) {
                        formattedDate =
                          parts[2] + "/" + parts[1] + "/" + parts[0];
                      }
                    } else {
                      formattedDate = rawDate;
                    }
                  } else {
                    formattedDate = String(rawDate);
                  }
                }

                return {
                  id: event.id,
                  title: displayTitle,
                  start: event.start,
                  end: event.end,
                  backgroundColor:
                    event.extendedProps?.priority_color || "#0b88f7",
                  borderColor: event.extendedProps?.priority_color || "#0b88f7",
                  extendedProps: {
                    status: event.extendedProps?.status,
                    client: event.extendedProps?.client,
                    technician: event.extendedProps?.technician,
                    technician_id: event.extendedProps?.technician_id,
                    description: event.extendedProps?.description,
                    original_title:
                      event.extendedProps?.original_title || event.title,
                    reference: event.extendedProps?.reference,
                    site: event.extendedProps?.site,
                    room: event.extendedProps?.room,
                    priority: event.extendedProps?.priority,
                    type: event.extendedProps?.type,
                    planned_date: formattedDate,
                    planned_time: event.extendedProps?.planned_time,
                    duration: event.extendedProps?.duration,
                  },
                };
              });
              successCallback(events);
              hideCalendarMessage();

              const totalFilters =
                document.querySelectorAll(".input-filter").length;
              const filtersKey = JSON.stringify(activeFilters);
              const isRestrictedSelection =
                activeFilters.length > 0 && activeFilters.length < totalFilters;

              if (
                events.length === 0 &&
                isRestrictedSelection &&
                lastAutoJumpFilters !== filtersKey
              ) {
                fetch(
                  BASE_URL +
                    "agenda/getNearestEventDate?" +
                    new URLSearchParams({
                      reference: info.startStr,
                      filters: filtersKey,
                    }),
                )
                  .then((r) => r.json())
                  .then((result) => {
                    lastAutoJumpFilters = filtersKey;
                    if (result && result.date) {
                      const label =
                        result.direction === "past"
                          ? "la dernière intervention connue"
                          : "la prochaine intervention";
                      showCalendarMessage(
                        "Aucune intervention dans cette période pour la sélection. Affichage de " +
                          label +
                          " : " +
                          formatDateFr(result.date),
                      );
                      window.calendar.gotoDate(result.date);
                    } else {
                      showCalendarMessage(
                        "Aucune intervention trouvée pour cette sélection.",
                      );
                    }
                  })
                  .catch((err) =>
                    console.warn(
                      "Erreur lors de la recherche de date proche:",
                      err,
                    ),
                  );
              }
            })
            .catch((error) => {
              console.error("Erreur lors du chargement des événements:", error);
              failureCallback(error);
            });
        },
        eventClick: function (info) {
          openEventModal(info.event);
        },
        selectable: false,
      });

      calendar.render();
      window.calendar = calendar;
    }

    function initSidebar() {
      const inlineCalendar = document.querySelector(".inline-calendar");
      if (inlineCalendar && typeof flatpickr !== "undefined") {
        try {
          flatpickr(inlineCalendar, {
            inline: true,
            locale: "fr",
            dateFormat: "d/m/Y",
            onChange: function (selectedDates, dateStr, instance) {
              if (window.calendar) {
                window.calendar.gotoDate(selectedDates[0]);
              }
            },
          });
          console.log("Flatpickr initialisé avec succès");
        } catch (error) {
          console.warn("Erreur Flatpickr:", error.message);
        }
      } else {
        console.log("Flatpickr non disponible - calendrier inline désactivé");
      }

      if (typeof $ !== "undefined" && typeof $.fn.select2 !== "undefined") {
        try {
          $(".select2").select2({
            dropdownParent: $("#addEventSidebar"),
          });
        } catch (error) {
          console.warn("Erreur Select2:", error.message);
        }
      } else {
        console.log(
          "Select2 non disponible - utilisation des dropdowns natifs",
        );
      }
    }

    function initFilters() {
      const selectAll = document.getElementById("selectAll");
      const filters = document.querySelectorAll(".input-filter");

      if (selectAll) {
        selectAll.addEventListener("change", function () {
          const isChecked = this.checked;
          filters.forEach((filter) => {
            filter.checked = isChecked;
          });
          filterEvents();
        });
      }

      filters.forEach((filter) => {
        filter.addEventListener("change", function () {
          const allChecked = Array.from(filters).every((f) => f.checked);
          if (selectAll) {
            selectAll.checked = allChecked;
          }
          filterEvents();
        });
      });
    }

    function filterEvents() {
      const activeFilters = Array.from(
        document.querySelectorAll(".input-filter:checked"),
      ).map((filter) => filter.dataset.value);

      lastAutoJumpFilters = null;
      hideCalendarMessage();

      if (window.calendar) {
        window.calendar.refetchEvents();
      }
    }

    function openEventModal(event) {
      const eventReferenceEl = document.getElementById("eventReference");
      const eventTitleEl = document.getElementById("eventTitle");
      const eventStatusEl = document.getElementById("eventStatus");
      const eventPriorityEl = document.getElementById("eventPriority");
      const eventTypeEl = document.getElementById("eventType");
      const eventClientEl = document.getElementById("eventClient");
      const eventSiteEl = document.getElementById("eventSite");
      const eventRoomEl = document.getElementById("eventRoom");
      const eventTechnicianEl = document.getElementById("eventTechnician");
      const eventPlannedDateEl = document.getElementById("eventPlannedDate");
      const eventPlannedTimeEl = document.getElementById("eventPlannedTime");
      const eventDurationEl = document.getElementById("eventDuration");
      const eventDescriptionEl = document.getElementById("eventDescription");
      const viewInterventionLink = document.getElementById(
        "viewInterventionLink",
      );
      const reference = event.extendedProps.reference || "#" + event.id;
      const title =
        event.extendedProps.original_title ||
        event.title.split("\n")[1] ||
        event.title;
      const status = event.extendedProps.status || "-";
      const priority = event.extendedProps.priority || "-";
      const type = event.extendedProps.type || "-";
      const client = event.extendedProps.client || "-";
      const site = event.extendedProps.site || "-";
      const room = event.extendedProps.room || "-";
      const technician = event.extendedProps.technician || "Non attribué";
      const plannedDate = event.extendedProps.planned_date || "-";
      const plannedTime = event.extendedProps.planned_time || "-";
      const duration = (event.extendedProps.duration || 0) + "h";
      const description = event.extendedProps.description || "-";
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

      if (viewInterventionLink) {
        viewInterventionLink.href = BASE_URL + "interventions/view/" + event.id;
      }

      const modal = new bootstrap.Offcanvas(addEventSidebar);
      modal.show();
    }
    initSidebar();
    initFilters();
  })();
});
