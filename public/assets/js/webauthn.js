// --- Encodage : le navigateur travaille en ArrayBuffer, le serveur en base64url ---

function base64urlToBuffer(base64url) {
  const padding = "=".repeat((4 - (base64url.length % 4)) % 4);
  const base64 = (base64url + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = atob(base64);
  const buffer = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) buffer[i] = raw.charCodeAt(i);
  return buffer.buffer;
}

function bufferToBase64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = "";
  for (const b of bytes) str += String.fromCharCode(b);
  return btoa(str).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

function publicKeyCredentialToJSON(credential) {
  const json = {
    id: credential.id,
    rawId: bufferToBase64url(credential.rawId),
    type: credential.type,
  };

  if (credential.response.attestationObject) {
    // Enregistrement
    json.response = {
      clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
      attestationObject: bufferToBase64url(
        credential.response.attestationObject,
      ),
    };
  } else {
    // Authentification
    json.response = {
      clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
      authenticatorData: bufferToBase64url(
        credential.response.authenticatorData,
      ),
      signature: bufferToBase64url(credential.response.signature),
      userHandle: credential.response.userHandle
        ? bufferToBase64url(credential.response.userHandle)
        : null,
    };
  }

  return json;
}

function isWebauthnSupported() {
  return window.PublicKeyCredential !== undefined;
}

// --- Helper CSRF : lit le token déjà présent dans un formulaire de la page ---
function getCsrfToken() {
  const input = document.querySelector('input[name="csrf_token"]');
  return input ? input.value : null;
}

// --- Connexion via passkey (page login) ---
async function loginWithPasskey(buttonEl) {
  const errorBox = document.getElementById("webauthn-error");
  if (errorBox) errorBox.classList.add("d-none");

  if (!isWebauthnSupported()) {
    showWebauthnError("Votre navigateur ne prend pas en charge les passkeys.");
    return;
  }

  const originalText = buttonEl.innerHTML;
  buttonEl.disabled = true;
  buttonEl.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Connexion en cours...';

  try {
    const optionsResp = await fetch(BASE_URL + "auth/webauthn-login-options", {
      method: "GET",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    const optionsData = await optionsResp.json();

    if (!optionsData.success) {
      throw new Error(
        optionsData.error || "Impossible de démarrer la connexion.",
      );
    }

    const publicKey = optionsData.options;
    publicKey.challenge = base64urlToBuffer(publicKey.challenge);
    if (publicKey.allowCredentials) {
      publicKey.allowCredentials.forEach(
        (c) => (c.id = base64urlToBuffer(c.id)),
      );
    }

    const credential = await navigator.credentials.get({
      publicKey,
      mediation: "optional",
    });

    const verifyResp = await fetch(BASE_URL + "auth/webauthn-login-verify", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-Token": getCsrfToken() || "",
      },
      body: JSON.stringify({
        challengeId: optionsData.challengeId,
        credential: publicKeyCredentialToJSON(credential),
      }),
    });
    const verifyData = await verifyResp.json();

    if (verifyData.success) {
      window.location.href = verifyData.redirect || BASE_URL + "dashboard";
    } else {
      throw new Error(verifyData.error || "Authentification échouée.");
    }
  } catch (err) {
    console.error("Erreur WebAuthn login:", err);
    if (err.name === "NotAllowedError") {
      showWebauthnError("Connexion annulée ou délai dépassé.");
    } else {
      showWebauthnError(err.message || "Une erreur est survenue.");
    }
    buttonEl.disabled = false;
    buttonEl.innerHTML = originalText;
  }
}

function showWebauthnError(message) {
  const errorBox = document.getElementById("webauthn-error");
  if (errorBox) {
    errorBox.textContent = message;
    errorBox.classList.remove("d-none");
  } else {
    alert(message);
  }
}

// --- Enregistrement d'une passkey (page profil) ---
async function registerPasskey(buttonEl) {
  if (!isWebauthnSupported()) {
    alert("Votre navigateur ne prend pas en charge les passkeys.");
    return;
  }

  const name = prompt("Nom de cet appareil (ex : iPhone de Jean)", "");
  if (name === null) return; // annulé

  const originalText = buttonEl.innerHTML;
  buttonEl.disabled = true;
  buttonEl.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

  try {
    const optionsResp = await fetch(
      BASE_URL + "auth/webauthn-register-options",
      {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      },
    );
    const optionsData = await optionsResp.json();

    if (!optionsData.success)
      throw new Error(optionsData.error || "Erreur serveur.");

    const publicKey = optionsData.options;
    publicKey.challenge = base64urlToBuffer(publicKey.challenge);
    publicKey.user.id = base64urlToBuffer(publicKey.user.id);
    if (publicKey.excludeCredentials) {
      publicKey.excludeCredentials.forEach(
        (c) => (c.id = base64urlToBuffer(c.id)),
      );
    }

    const credential = await navigator.credentials.create({ publicKey });

    const verifyResp = await fetch(BASE_URL + "auth/webauthn-register-verify", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-Token": getCsrfToken() || "",
      },
      body: JSON.stringify({
        challengeId: optionsData.challengeId,
        credential: publicKeyCredentialToJSON(credential),
        name: name || "Appareil sans nom",
      }),
    });
    const verifyData = await verifyResp.json();

    if (verifyData.success) {
      location.reload();
    } else {
      throw new Error(
        verifyData.error || "Impossible d'enregistrer la passkey.",
      );
    }
  } catch (err) {
    console.error("Erreur WebAuthn register:", err);
    alert(
      err.name === "NotAllowedError"
        ? "Enregistrement annulé."
        : err.message || "Une erreur est survenue.",
    );
    buttonEl.disabled = false;
    buttonEl.innerHTML = originalText;
  }
}

async function deletePasskey(credentialId, rowEl) {
  if (!confirm("Supprimer cette passkey ?")) return;

  try {
    const resp = await fetch(
      BASE_URL +
        "auth/webauthn-delete-credential/" +
        encodeURIComponent(credentialId),
      {
        method: "DELETE",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-Token": getCsrfToken() || "",
        },
      },
    );
    const data = await resp.json();
    if (data.success) {
      rowEl.remove();
    } else {
      alert(data.error || "Suppression impossible.");
    }
  } catch (err) {
    console.error("Erreur suppression passkey:", err);
    alert("Une erreur est survenue.");
  }
}
async function loadPasskeys() {
  try {
    const resp = await fetch(BASE_URL + "auth/webauthn-credentials", {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    const data = await resp.json();
    const list = document.getElementById("passkey-list");
    const empty = document.getElementById("passkey-empty");
    list.innerHTML = "";

    if (!data.success || data.credentials.length === 0) {
      empty.classList.remove("d-none");
      return;
    }
    empty.classList.add("d-none");

    data.credentials.forEach((cred) => {
      const li = document.createElement("li");
      li.className =
        "list-group-item d-flex justify-content-between align-items-center";
      li.innerHTML = `
                <span>
                    <strong>${cred.name || "Appareil sans nom"}</strong>
                    <br><small class="text-muted">Ajoutée le ${new Date(cred.created_at).toLocaleDateString("fr-FR")}</small>
                </span>
                <button class="btn btn-sm btn-outline-danger">Supprimer</button>
            `;
      li.querySelector("button").onclick = () => deletePasskey(cred.id, li);
      list.appendChild(li);
    });
  } catch (err) {
    console.error("Erreur chargement passkeys:", err);
    document.getElementById("passkey-empty").textContent =
      "Impossible de charger vos passkeys pour le moment.";
    document.getElementById("passkey-empty").classList.remove("d-none");
  }
}
