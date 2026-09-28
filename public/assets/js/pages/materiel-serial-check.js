"use strict";

/**
 * Contrôle des doublons de numéro de série (formulaires matériel add/edit).
 * - Avertissement affiché à la sortie du champ #numero_serie
 * - Confirmation demandée à l'envoi si un doublon existe
 * Pour edit.php : ajouter data-exclude-id="<id du matériel>" sur la balise <form>.
 */
(function () {
  document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("numero_serie");
    const box = document.getElementById("serialWarning");
    if (!input || !box) return;

    const form = input.closest("form");
    if (!form) return;

    const excludeId = form.dataset.excludeId || "";

    function baseUrl() {
      return typeof BASE_URL !== "undefined" ? BASE_URL : "";
    }

    async function fetchDuplicates(serial) {
      if (!serial) return [];
      const params = new URLSearchParams({ numero_serie: serial });
      if (excludeId) params.set("exclude_id", excludeId);
      try {
        const res = await fetch(
          `${baseUrl()}materiel/check_serial?${params.toString()}`,
          { credentials: "include" },
        );
        if (!res.ok) {
          console.error("check_serial : HTTP", res.status, res.url);
          return [];
        }
        const data = await res.json();
        return Array.isArray(data.duplicates) ? data.duplicates : [];
      } catch (e) {
        console.error("Erreur contrôle numéro de série:", e);
        return [];
      }
    }

    function locationOf(d) {
      return [d.client_nom, d.site_nom, d.building_nom, d.salle_nom]
        .filter(Boolean)
        .join(" › ");
    }

    function renderWarning(dups) {
      box.replaceChildren();
      if (!dups.length) {
        box.classList.add("d-none");
        return;
      }

      const title = document.createElement("strong");
      title.textContent = "Ce numéro de série existe déjà :";
      box.appendChild(title);

      const ul = document.createElement("ul");
      ul.className = "mb-0 mt-1";
      dups.forEach((d) => {
        const li = document.createElement("li");
        const a = document.createElement("a");
        a.href = `${baseUrl()}materiel/view/${encodeURIComponent(d.id)}`;
        a.target = "_blank";
        a.rel = "noopener";
        a.textContent =
          `${d.marque || ""} ${d.modele || ""}`.trim() || `Matériel #${d.id}`;
        li.appendChild(a);
        const where = locationOf(d);
        if (where) li.append(" — " + where);
        ul.appendChild(li);
      });
      box.appendChild(ul);
      box.classList.remove("d-none");
    }

    async function refreshWarning() {
      const serial = input.value.trim();
      const dups = await fetchDuplicates(serial);
      // Ignorer une réponse devenue obsolète (le champ a changé entre-temps)
      if (input.value.trim() !== serial) return;
      renderWarning(dups);
    }

    input.addEventListener("blur", refreshWarning);
    input.addEventListener("input", () => box.classList.add("d-none"));

    form.addEventListener("submit", async (e) => {
      if (form.dataset.serialChecked === "1") return;

      const serial = input.value.trim();
      if (!serial) return;

      e.preventDefault();
      const dups = await fetchDuplicates(serial);
      renderWarning(dups);

      if (
        dups.length === 0 ||
        confirm(
          "Ce numéro de série existe déjà dans la base.\nVoulez-vous quand même enregistrer ce matériel ?",
        )
      ) {
        form.dataset.serialChecked = "1";
        form.requestSubmit();
      }
    });
  });
})();
