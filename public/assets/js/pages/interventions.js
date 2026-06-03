/**
 * JavaScript spécifique à la page de visualisation des interventions
 * Utilise les composants réutilisables (DragDropUploader, Utils, ApiClient, etc.)
 */

"use strict";

(function () {
  "use strict";

  // Attendre que le DOM soit chargé
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  function init() {
    console.log("interventions.js: Initialisation...");
    console.log(
      "interventions.js: DragDropUploader disponible?",
      typeof DragDropUploader !== "undefined",
    );
    console.log(
      "interventions.js: Utils disponible?",
      typeof Utils !== "undefined",
    );
    console.log(
      "interventions.js: ApiClient disponible?",
      typeof ApiClient !== "undefined",
    );

    // IMPORTANT:
    // Cette page charge `interventions.js` AVANT le footer qui charge les dépendances (DragDropUploader/Utils/ApiClient).
    // Donc on ne doit PAS `return` ici: on attend le moment où la modale s'ouvre pour vérifier/initialiser.
    initAttachmentUploader();
    initContractDetailsModal();
  }

  /**
   * Initialise l'uploader de pièces jointes
   */
  function initAttachmentUploader() {
    const modal = document.getElementById("addAttachmentModal");
    if (!modal) return;

    let uploader = null;

    // Initialiser l'uploader quand la modale s'ouvre
    modal.addEventListener("shown.bs.modal", async function () {
      console.log(
        "interventions.js: Modal ouverte, initialisation de l'uploader...",
      );

      // Vérifier les dépendances au moment utile (après chargement du footer)
      if (
        typeof DragDropUploader === "undefined" ||
        typeof Utils === "undefined" ||
        typeof ApiClient === "undefined"
      ) {
        console.error(
          "interventions.js: Dépendances manquantes au moment d'ouvrir la modale",
          {
            DragDropUploader: typeof DragDropUploader,
            Utils: typeof Utils,
            ApiClient: typeof ApiClient,
          },
        );
        return;
      }

      if (!uploader || !uploader.initialized) {
        // Récupérer l'URL d'upload depuis le formulaire
        const form = document.getElementById("dragDropForm");
        const baseUrl = window.BASE_URL || window.AppConfig?.BASE_URL || "";
        const uploadUrl = form ? form.action.replace(baseUrl, "") : null;

        console.log("interventions.js: Form trouvé?", !!form);
        console.log("interventions.js: Upload URL:", uploadUrl);

        if (!uploadUrl) {
          console.error("URL d'upload non trouvée");
          return;
        }

        // Vérifier que les éléments DOM existent
        const dropZone = document.getElementById("dropZone");
        const fileInput = document.getElementById("fileInput");
        const fileList = document.getElementById("fileList");

        console.log("interventions.js: dropZone trouvé?", !!dropZone);
        console.log("interventions.js: fileInput trouvé?", !!fileInput);
        console.log("interventions.js: fileList trouvé?", !!fileList);

        if (!dropZone || !fileInput || !fileList) {
          console.error(
            "interventions.js: Éléments DOM manquants pour l'uploader",
          );
          return;
        }

        // Créer l'instance de l'uploader
        uploader = new DragDropUploader({
          dropZoneId: "dropZone",
          fileInputId: "fileInput",
          fileListId: "fileList",
          uploadUrl: uploadUrl,
          statsId: "stats",
          validCountId: "validCount",
          invalidCountId: "invalidCount",
          progressFillId: "progressFill",
          uploadBtnId: "uploadValidBtn",
          clearBtnId: "clearAllBtn",
          filesOptionsId: "filesOptions",
          filesOptionsListId: "filesOptionsList",
          formId: "dragDropForm",
          onSuccess: (result) => {
            console.log("interventions.js: Upload réussi", result);
            // Fermer la modale et recharger la page
            const modalInstance = bootstrap?.Modal?.getInstance?.(modal);
            if (modalInstance) {
              modalInstance.hide();
            }
            setTimeout(() => {
              window.location.reload();
            }, 500);
          },
          onError: (error) => {
            console.error("interventions.js: Erreur lors de l'upload:", error);
            if (error && error.data) {
              console.error(
                "interventions.js: Détails erreur upload:",
                error.data,
              );
            }
          },
        });

        // Initialiser l'uploader - attendre que le DOM soit prêt
        try {
          console.log("interventions.js: Appel de uploader.init()...");
          await uploader.init();
          console.log(
            "interventions.js: DragDropUploader initialisé avec succès",
          );
        } catch (error) {
          console.error(
            "interventions.js: Erreur lors de l'initialisation de DragDropUploader:",
            error,
          );
          console.error("interventions.js: Stack trace:", error.stack);
        }

        // Stocker l'instance globalement pour les fonctions onclick
        window.dragDropUploaderInstance = uploader;
      } else {
        console.log("interventions.js: Uploader déjà initialisé");
      }
    });

    // Réinitialiser l'uploader quand la modale se ferme
    modal.addEventListener("hidden.bs.modal", function () {
      console.log("interventions.js: Modal fermée");
      if (uploader) {
        // Reset silencieux: ne pas demander confirmation lors d'une fermeture automatique (après upload)
        uploader.clearAllFiles(false);
        uploader.initialized = false;
        uploader = null;
      }
    });
  }

  /**
   * Initialise la modale de détails du contrat
   */
  function initContractDetailsModal() {
    // Gérer les clics sur les liens de contrat
    document.addEventListener("click", function (e) {
      if (
        e.target.classList.contains("contract-info-link") ||
        e.target.closest(".contract-info-link")
      ) {
        e.preventDefault();
        const link = e.target.classList.contains("contract-info-link")
          ? e.target
          : e.target.closest(".contract-info-link");
        const contractId = link.getAttribute("data-contract-id");

        if (contractId) {
          // Ouvrir la modal
          const modalElement = document.getElementById("contractDetailsModal");
          if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            // Charger les détails du contrat
            loadContractDetails(contractId);
          }
        }
      }
    });
  }

  /* ── Contrat info (VERSION CORRIGÉE) ──────────────────────────────────────── */
  function loadContractDetails(contractId) {
    var modalContent = document.getElementById("contractDetailsContent");
    if (!modalContent) {
      console.error("Element contractDetailsContent non trouvé");
      return;
    }

    modalContent.innerHTML =
      '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Chargement des détails du contrat...</p></div>';

    var url =
      window.BASE_URL +
      "interventions/getContractInfo/" +
      contractId +
      "?_t=" +
      Date.now();

    fetch(url, {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      credentials: "include",
    })
      .then(function (response) {
        return response.text().then(function (text) {
          if (text.trim().startsWith("<")) {
            throw new Error("Le serveur a retourné du HTML au lieu de JSON");
          }

          // Parser le JSON
          try {
            return JSON.parse(text);
          } catch (e) {
            throw new Error("Erreur de parsing JSON: " + e.message);
          }
        });
      })
      .then(function (data) {
        if (!data || typeof data !== "object") {
          throw new Error("Données invalides reçues du serveur");
        }

        if (data.error) {
          modalContent.innerHTML =
            '<div class="alert alert-danger m-3">' +
            escapeHtml2(data.error) +
            "</div>";
          return;
        }

        var startDate = data.start_date
          ? new Date(data.start_date).toLocaleDateString("fr-FR")
          : "Non définie";
        var endDate = data.end_date
          ? new Date(data.end_date).toLocaleDateString("fr-FR")
          : "Non définie";
        var isTicketContract = data.isticketcontract == 1;
        var ticketColor = "secondary";
        var ticketsDisplay = "";

        if (isTicketContract) {
          if (data.tickets_remaining > 3) ticketColor = "success";
          else if (data.tickets_remaining > 0) ticketColor = "warning";
          else ticketColor = "danger";
          ticketsDisplay =
            '<tr><th class="text-muted">Tickets restants:</th><td><span class="badge bg-' +
            ticketColor +
            '">' +
            (data.tickets_remaining || 0) +
            "</span></td></tr>";
        } else {
          ticketsDisplay =
            '<tr><th class="text-muted">Tickets:</th><td><span class="badge bg-secondary">Sans tickets</span></td></tr>';
        }

        var html = '<div class="p-3">';
        html +=
          '<h6 class="fw-bold mb-3 border-bottom pb-2">' +
          escapeHtml2(data.name || "Contrat") +
          "</h6>";
        html += '<table class="table table-sm">';
        html +=
          '<tr><th class="text-muted" style="width:40%">Type:</th><td>' +
          (data.type_name ? escapeHtml2(data.type_name) : "Non défini") +
          "</td></tr>";
        html += ticketsDisplay;
        html +=
          '<tr><th class="text-muted">Date de début:</th><td>' +
          escapeHtml2(startDate) +
          "</td></tr>";
        html +=
          '<tr><th class="text-muted">Date de fin:</th><td>' +
          escapeHtml2(endDate) +
          "</td></tr>";
        html += "</table>";

        if (data.comment) {
          html +=
            '<div class="alert alert-info mt-2 mb-0">' +
            escapeHtml2(data.comment) +
            "</div>";
        }

        html += "</div>";
        modalContent.innerHTML = html;
      })
      .catch(function (error) {
        console.error("Erreur loadContractDetails:", error);
        modalContent.innerHTML =
          '<div class="alert alert-danger m-3">' +
          '<i class="bi bi-exclamation-triangle-fill me-2"></i>' +
          "Erreur lors du chargement des détails du contrat.<br>" +
          '<small class="text-muted">' +
          escapeHtml2(error.message) +
          "</small>" +
          '<hr><button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="closeContractModalAndRefresh()">' +
          '<i class="bi bi-arrow-repeat me-1"></i>Fermer et réessayer</button>' +
          "</div>";
      });
  }

  function escapeHtml2(text) {
    if (!text) return "";
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function closeContractModalAndRefresh() {
    var modalElement = document.getElementById("contractDetailsModal");
    if (modalElement) {
      var modal = bootstrap.Modal.getInstance(modalElement);
      if (modal) {
        modal.hide();
      } else {
        modalElement.style.display = "none";
        document.body.classList.remove("modal-open");
        var backdrops = document.querySelectorAll(".modal-backdrop");
        backdrops.forEach(function (backdrop) {
          backdrop.remove();
        });
      }
    }
    var modalContent = document.getElementById("contractDetailsContent");
    if (modalContent) {
      modalContent.innerHTML =
        '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Chargement...</p></div>';
    }
  }

  function escapeHtml2(text) {
    if (!text) return "";
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Fonction pour éditer le nom d'une pièce jointe
   * @param {string|number} attachmentId - ID de la pièce jointe
   * @param {string} currentName - Nom actuel (non utilisé, conservé pour compatibilité)
   */
  window.editAttachmentName = async function (attachmentId, currentName) {
    try {
      const data = await ApiClient.get(
        `interventions/getAttachmentInfo/${attachmentId}`,
      );

      if (data.success) {
        // Remplir la modale
        const nameInput = document.getElementById("editAttachmentName");
        const originalNameDisplay = document.getElementById("editOriginalName");
        const form = document.getElementById("editAttachmentNameForm");

        if (nameInput) {
          nameInput.value =
            data.attachment.nom_personnalise || data.attachment.nom_fichier;
        }
        if (originalNameDisplay) {
          originalNameDisplay.textContent = data.attachment.nom_fichier;
        }
        if (form) {
          form.setAttribute("data-attachment-id", attachmentId);
        }

        // Ouvrir la modale
        const modalElement = document.getElementById("editAttachmentNameModal");
        if (modalElement) {
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
        }
      } else {
        Utils.showError(
          "Erreur lors du chargement des informations du fichier : " +
            (data.error || "Erreur inconnue"),
        );
      }
    } catch (error) {
      console.error("Erreur:", error);
      Utils.showError("Erreur lors du chargement des informations du fichier");
    }
  };

  /**
   * Initialise le formulaire d'édition du nom de pièce jointe
   */
  function initEditAttachmentNameForm() {
    const form = document.getElementById("editAttachmentNameForm");
    if (!form) return;

    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      const attachmentId = this.getAttribute("data-attachment-id");
      const nameInput = document.getElementById("editAttachmentName");
      const newName = nameInput ? nameInput.value.trim() : "";

      if (!newName) {
        Utils.showError("Le nom du fichier ne peut pas être vide");
        return;
      }

      // Désactiver le bouton de soumission
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="bi bi-arrow-clockwise spin me-1"></i>Sauvegarde...';

      try {
        const data = await ApiClient.post(
          `interventions/updateAttachmentName/${attachmentId}`,
          {
            nom_fichier: newName,
          },
        );

        if (data.success) {
          // Fermer la modale
          const modalElement = document.getElementById(
            "editAttachmentNameModal",
          );
          if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
              modal.hide();
            }
          }

          // Recharger la page pour afficher les changements
          window.location.reload();
        } else {
          Utils.showError(data.error || "Erreur lors de la sauvegarde");
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      } catch (error) {
        console.error("Erreur:", error);
        Utils.showError("Erreur lors de la sauvegarde");
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }

  // Initialiser le formulaire d'édition
  initEditAttachmentNameForm();

  // Exposer la fonction globalement si nécessaire
  window.loadContractDetails = loadContractDetails;

  /**
   * Initialise le calcul d'impact des tickets (pour la modale de force tickets)
   */
  function initForceTicketsCalculation() {
    const newTicketsInput = document.getElementById("new_tickets");
    if (!newTicketsInput) return;

    // Récupérer les valeurs depuis les data-attributes
    const currentTickets =
      parseInt(newTicketsInput.getAttribute("data-current-tickets")) || 0;
    const contractTickets =
      parseInt(newTicketsInput.getAttribute("data-contract-tickets")) || 0;
    const contractRemaining =
      parseInt(newTicketsInput.getAttribute("data-contract-remaining")) || 0;

    function updateImpact() {
      const newTickets = parseInt(newTicketsInput.value) || 0;
      const difference = newTickets - currentTickets;
      const currentRemaining = contractRemaining;
      const newRemaining = currentRemaining - difference;

      const currentRemainingEl = document.getElementById("current_remaining");
      const newRemainingEl = document.getElementById("new_remaining");

      if (currentRemainingEl) currentRemainingEl.textContent = currentRemaining;
      if (newRemainingEl) {
        newRemainingEl.textContent = newRemaining;

        // Changer la couleur selon l'impact
        if (newRemaining < 0) {
          newRemainingEl.className = "text-danger fw-bold";
        } else if (newRemaining < 5) {
          newRemainingEl.className = "text-warning fw-bold";
        } else {
          newRemainingEl.className = "text-success fw-bold";
        }
      }
    }

    newTicketsInput.addEventListener("input", updateImpact);
    updateImpact(); // Calcul initial

    // Debug: Vérifier que le formulaire envoie bien les données
    const forceTicketsForm = document.querySelector("#forceTicketsModal form");
    if (forceTicketsForm) {
      forceTicketsForm.addEventListener("submit", function (e) {
        console.log("DEBUG: Formulaire soumis");
        console.log(
          "DEBUG: tickets_used:",
          document.getElementById("new_tickets").value,
        );
        console.log("DEBUG: reason:", document.getElementById("reason").value);
      });
    }
  }

  // Initialiser le calcul des tickets si la modale existe
  // Utiliser un délai pour s'assurer que la modale est chargée
  setTimeout(() => {
    if (document.getElementById("new_tickets")) {
      initForceTicketsCalculation();
    }
  }, 100);
})();
