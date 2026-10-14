<?php
/**
 * Modales + JS de création rapide (client, site, bâtiment, salle, contact).
 * À inclure APRÈS le conteneur principal, jamais à l'intérieur de #interventionForm.
 */
$quickCreateModals = [
    'client' => [
        'title' => 'Créer un nouveau client',
        'icon' => 'bi-person-plus',
        'button' => 'Créer le client',
        'fields' => [
            // [name, label, type, placeholder, required, colonnes md]
            ['name', 'Nom du client *', 'text', "Nom de l'entreprise", true, 12],
            ['email', 'Email', 'email', 'contact@entreprise.com', false, 6],
            ['phone', 'Téléphone', 'tel', '01 23 45 67 89', false, 6],
            ['website', 'Site web', 'url', 'https://www.entreprise.com', false, 12],
            ['address', 'Adresse', 'text', '123 Rue de la Paix', false, 12],
            ['postal_code', 'Code postal', 'text', '75001', false, 4],
            ['city', 'Ville', 'text', 'Paris', false, 8],
            ['comment', 'Commentaire', 'textarea', '', false, 12],
        ],
    ],
    'site' => [
        'title' => 'Créer un nouveau site',
        'icon' => 'bi-geo-alt',
        'button' => 'Créer le site',
        'fields' => [
            ['name', 'Nom du site *', 'text', 'Nom du site', true, 12],
            ['address', 'Adresse', 'text', '123 Rue de la Paix', false, 12],
            ['postal_code', 'Code postal', 'text', '75001', false, 4],
            ['city', 'Ville', 'text', 'Paris', false, 8],
            ['phone', 'Téléphone', 'tel', '01 23 45 67 89', false, 6],
            ['email', 'Email', 'email', 'contact@site.com', false, 6],
            ['comment', 'Commentaire', 'textarea', '', false, 12],
        ],
    ],
    'building' => [
        'title' => 'Créer un nouveau bâtiment',
        'icon' => 'bi-building',
        'button' => 'Créer le bâtiment',
        'fields' => [
            ['name', 'Nom du bâtiment *', 'text', 'Nom du bâtiment', true, 12],
            ['comment', 'Commentaire', 'textarea', '', false, 12],
        ],
    ],
    'room' => [
        'title' => 'Créer une nouvelle salle',
        'icon' => 'bi-door-open',
        'button' => 'Créer la salle',
        'fields' => [
            ['name', 'Nom de la salle *', 'text', 'Nom de la salle', true, 12],
            ['comment', 'Commentaire', 'textarea', '', false, 12],
        ],
    ],
    'contact' => [
        'title' => 'Créer un nouveau contact',
        'icon' => 'bi-person-plus',
        'button' => 'Créer le contact',
        'fields' => [
            ['first_name', 'Prénom *', 'text', 'Prénom', true, 6],
            ['last_name', 'Nom *', 'text', 'Nom', true, 6],
            ['email', 'Email', 'email', 'contact@exemple.com', false, 6],
            ['phone1', 'Téléphone 1', 'tel', '01 23 45 67 89', false, 6],
            ['phone2', 'Téléphone 2', 'tel', '01 23 45 67 89', false, 6],
            ['fonction', 'Fonction', 'text', 'Directeur, Responsable IT...', false, 6],
            ['comment', 'Commentaire', 'textarea', '', false, 12],
        ],
    ],
];
?>

<?php foreach ($quickCreateModals as $key => $m):
    $Key = ucfirst($key); ?>
    <div class="modal fade" id="quickCreate<?= $Key ?>Modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi <?= $m['icon'] ?> me-2"></i>
                        <?= h($m['title']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="quickCreate<?= $Key ?>Form">
                        <div class="row g-3">
                            <?php foreach ($m['fields'] as [$name, $label, $type, $placeholder, $required, $col]): ?>
                                <div class="col-12 col-md-<?= $col ?>">
                                    <label class="form-label fw-bold">
                                        <?= h($label) ?>
                                    </label>
                                    <?php if ($type === 'textarea'): ?>
                                        <textarea class="form-control" name="<?= $name ?>" rows="2"
                                            placeholder="<?= h($placeholder) ?>"></textarea>
                                    <?php else: ?>
                                        <input type="<?= $type ?>" class="form-control" name="<?= $name ?>"
                                            placeholder="<?= h($placeholder) ?>" <?= $required ? 'required' : '' ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="saveQuick<?= $Key ?>Btn">
                        <i class="bi bi-check-lg me-1"></i>
                        <?= h($m['button']) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
    window.addEventListener('load', function () {
        console.log('[quick-create] script chargé, bootstrap =', typeof bootstrap);
        if (typeof bootstrap === 'undefined') {
            console.error('quick-create : Bootstrap JS introuvable');
            return;
        }
        const canModifyClients = <?= canModifyClients() ? 'true' : 'false' ?>;

        const configs = [
            {
                key: 'client', endpoint: 'quickCreateClient', select: 'client_id', result: 'client',
                requires: [], extra: {},
                value: i => i.id, label: i => i.name
            },
            {
                key: 'site', endpoint: 'quickCreateSite', select: 'site_id', result: 'site',
                requires: [['client_id', "Veuillez d'abord sélectionner un client avant de créer un site."]],
                extra: { client_id: 'client_id' },
                value: i => i.id, label: i => i.name
            },
            {
                key: 'building', endpoint: 'quickCreateBuilding', select: 'building_id', result: 'building',
                requires: [['site_id', "Veuillez d'abord sélectionner un site avant de créer un bâtiment."]],
                extra: { client_id: 'client_id', site_id: 'site_id' },
                value: i => i.id, label: i => i.name
            },
            {
                key: 'room', endpoint: 'quickCreateRoom', select: 'room_id', result: 'room',
                requires: [['building_id', "Veuillez d'abord sélectionner un bâtiment avant de créer une salle."]],
                extra: { client_id: 'client_id', site_id: 'site_id', building_id: 'building_id' },
                value: i => i.id, label: i => i.name
            },
            {
                key: 'contact', endpoint: 'quickCreateContact', select: 'contact_client_select', result: 'contact',
                requires: [['client_id', "Veuillez d'abord sélectionner un client avant de créer un contact."]],
                extra: { client_id: 'client_id' },
                value: i => i.email || '',
                label: i => `${i.first_name} ${i.last_name} (${i.email || 'sans email'})`,
                after: i => { document.getElementById('contact_client').value = i.email || ''; }
            }
        ];

        function toast(message) {
            const div = document.createElement('div');
            div.className = 'alert alert-success alert-dismissible fade show position-fixed';
            div.style.cssText = 'top:20px;right:20px;z-index:9999;min-width:300px;';
            div.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            document.body.appendChild(div);
            setTimeout(() => div.remove(), 3000);
        }

        configs.forEach(cfg => {
            const Key = cfg.key.charAt(0).toUpperCase() + cfg.key.slice(1);
            const openBtn = document.getElementById('quickCreate' + Key + 'Btn');
            const modalEl = document.getElementById('quickCreate' + Key + 'Modal');
            const form = document.getElementById('quickCreate' + Key + 'Form');
            const saveBtn = document.getElementById('saveQuick' + Key + 'Btn');
            if (!openBtn || !modalEl || !form || !saveBtn) return;

            const modal = new bootstrap.Modal(modalEl);

            openBtn.addEventListener('click', function () {
                if (!canModifyClients) {
                    alert("Vous n'avez pas les permissions nécessaires.");
                    return;
                }
                for (const [selectId, message] of cfg.requires) {
                    const sel = document.getElementById(selectId);
                    if (!sel || !sel.value) {
                        alert(message);
                        if (sel) sel.focus();
                        return;
                    }
                }
                form.reset();
                modal.show();
            });

            // Entrée dans un champ = valider la modale (et non soumettre la page)
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                saveBtn.click();
            });

            saveBtn.addEventListener('click', async function () {
                if (!form.reportValidity()) return;

                const fd = new FormData(form);
                Object.entries(cfg.extra).forEach(([param, selectId]) => {
                    fd.append(param, document.getElementById(selectId).value);
                });
                fd.append('csrf_token', window.csrfToken);

                const original = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Création...';

                try {
                    const response = await fetch(`${window.BASE_URL}interventions/${cfg.endpoint}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-Token': window.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: fd,
                        credentials: 'same-origin'
                    });

                    const raw = await response.text();
                    let data;
                    try { data = JSON.parse(raw); }
                    catch (e) { throw new Error('Réponse invalide du serveur (voir les logs PHP).'); }

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Une erreur est survenue');
                    }

                    const item = data[cfg.result];
                    const select = document.getElementById(cfg.select);
                    select.appendChild(new Option(cfg.label(item), cfg.value(item), true, true));
                    select.value = cfg.value(item);
                    if (cfg.after) cfg.after(item);

                    modal.hide();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    toast(data.message || 'Créé avec succès');
                } catch (error) {
                    alert('Erreur : ' + error.message);
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = original;
                }
            });
        });
    });
</script>