<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Configuration email', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les settings actuels
$config = Config::getInstance();
    $mailSettings = [
        'mail_host' => $config->get('mail_host', ''),
        'mail_port' => $config->get('mail_port', '587'),
        'mail_username' => $config->get('mail_username', ''),
        'mail_password' => $config->get('mail_password', ''),
        'mail_encryption' => $config->get('mail_encryption', 'tls'),
        'mail_from_address' => $config->get('mail_from_address', ''),
        'mail_from_name' => $config->get('mail_from_name', ''),
        'mail_cc_address' => $config->get('mail_cc_address', ''),
        'email_auto_send_creation' => $config->get('email_auto_send_creation', '0'),
        'email_auto_send_closing' => $config->get('email_auto_send_closing', '0'),
        'email_auto_send_bon' => $config->get('email_auto_send_bon', '0'),
        'test_email' => $config->get('test_email', ''),
        // Paramètres OAuth2
        'oauth2_enabled' => $config->get('oauth2_enabled', '0'),
        'oauth2_client_id' => $config->get('oauth2_client_id', ''),
        'oauth2_client_secret' => $config->get('oauth2_client_secret', ''),
        'oauth2_tenant_id' => $config->get('oauth2_tenant_id', ''),
        'oauth2_redirect_uri' => $config->get('oauth2_redirect_uri', ''),
        'oauth2_access_token' => $config->get('oauth2_access_token', ''),
        'oauth2_refresh_token' => $config->get('oauth2_refresh_token', ''),
        'oauth2_token_expires' => $config->get('oauth2_token_expires', ''),
    ];

// Les templates sont récupérés par le contrôleur
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-envelope me-2 me-1"></i>Configuration email
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Indicateur mode test -->
    <?php if (!empty($mailSettings['test_email'])): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Mode test activé :</strong> Tous les emails seront envoyés à 
            <strong><?= h($mailSettings['test_email']) ?></strong> au lieu des destinataires réels.
        </div>
    <?php endif; ?>


    <div class="row">
        <!-- Configuration SMTP -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-server text-primary me-2 me-1"></i>
                        Configuration SMTP
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>settings/saveEmailConfig">
                        <?= csrf_field() ?>
                        <!-- Paramètres SMTP -->
                        <div class="mb-3">
                            <label for="mail_host" class="form-label">Serveur SMTP</label>
                            <input type="text" class="form-control" id="mail_host" name="mail_host" 
                                   value="<?= h($mailSettings['mail_host']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="mail_port" name="mail_port" 
                                   value="<?= h($mailSettings['mail_port']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="mail_username" name="mail_username" 
                                   value="<?= h($mailSettings['mail_username']) ?>"
                                   autocomplete="off" placeholder="Laisser vide pour Mailpit / serveur sans auth">
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="mail_password" name="mail_password" 
                                   value="<?= h($mailSettings['mail_password']) ?>"
                                   autocomplete="new-password" placeholder="Laisser vide si pas d'authentification">
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_encryption" class="form-label">Chiffrement</label>
                            <select class="form-select" id="mail_encryption" name="mail_encryption">
                                <option value="none" <?= $mailSettings['mail_encryption'] == 'none' ? 'selected' : '' ?>>Aucun</option>
                                <option value="tls" <?= $mailSettings['mail_encryption'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= $mailSettings['mail_encryption'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_from_address" class="form-label">Adresse d'expédition</label>
                            <input type="email" class="form-control" id="mail_from_address" name="mail_from_address" 
                                   value="<?= h($mailSettings['mail_from_address']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_from_name" class="form-label">Nom d'expédition</label>
                            <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                   value="<?= h($mailSettings['mail_from_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="mail_cc_address" class="form-label">Copie (CC) systématique</label>
                            <input type="email" class="form-control" id="mail_cc_address" name="mail_cc_address"
                                   value="<?= h($mailSettings['mail_cc_address']) ?>"
                                   placeholder="copie@example.com">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Si renseigné, cette adresse sera mise en copie sur tous les emails sortants.
                            </div>
                        </div>
                        
                        <!-- Aide SMTP avec OAuth2 -->
                        <div id="smtpOAuth2Help" style="display: none;">
                            <div class="alert alert-info border-primary">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Configuration SMTP avec OAuth2 :</strong><br>
                                <small class="text-muted">
                                    • Serveur : <code>smtp.office365.com</code><br>
                                    • Port : <code>587</code> avec <code>TLS</code><br>
                                    • L'authentification basique sera ignorée si OAuth2 est activé
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Sauvegarder la configuration SMTP
                            </button>
                            <button type="button" class="btn btn-outline-success" id="testSmtpBtn">
                                <i class="bi bi-gear me-1"></i> Tester SMTP simple
                            </button>
                        </div>
                        
                        <!-- Séparateur OAuth2 -->
                        <hr class="my-4">
                        
                        <!-- Configuration OAuth2 -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="oauth2_enabled" 
                                       name="oauth2_enabled" value="1" 
                                       <?= $mailSettings['oauth2_enabled'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="oauth2_enabled">
                                    <strong>Activer OAuth2 (Exchange 365)</strong>
                                </label>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                OAuth2 est recommandé pour Exchange 365. L'authentification basique sera désactivée.
                            </div>
                        </div>
                        
                        <div id="oauth2Config" style="<?= $mailSettings['oauth2_enabled'] == '1' ? '' : 'display: none;' ?>">
                            <!-- Aide rapide OAuth2 -->
                            <div class="alert alert-light border-primary mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-question-circle text-primary me-2 mt-1"></i>
                                    <div class="small">
                                        <strong>Besoin d'aide ?</strong><br>
                                        <span class="text-muted">1. Créez une app Azure AD → 2. Ajoutez les permissions Mail.Send → 3. Générez un secret → 4. Récupérez les IDs</span>
                                        <a href="#azure-guide" class="text-decoration-none ms-1">↑ Voir le guide détaillé</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="oauth2_client_id" class="form-label">Client ID Azure AD</label>
                                <input type="text" class="form-control" id="oauth2_client_id" name="oauth2_client_id" 
                                       value="<?= h($mailSettings['oauth2_client_id']) ?>" 
                                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            </div>
                            
                            <div class="mb-3">
                                <label for="oauth2_client_secret" class="form-label">Client Secret</label>
                                <input type="password" class="form-control" id="oauth2_client_secret" name="oauth2_client_secret" 
                                       value="<?= h($mailSettings['oauth2_client_secret']) ?>" 
                                       placeholder="Votre client secret">
                            </div>
                            
                            <div class="mb-3">
                                <label for="oauth2_tenant_id" class="form-label">Tenant ID</label>
                                <input type="text" class="form-control" id="oauth2_tenant_id" name="oauth2_tenant_id" 
                                       value="<?= h($mailSettings['oauth2_tenant_id']) ?>" 
                                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            </div>
                            
                            <div class="mb-3">
                                <label for="oauth2_redirect_uri" class="form-label">Redirect URI</label>
                                <input type="url" class="form-control" id="oauth2_redirect_uri" name="oauth2_redirect_uri" 
                                       value="<?= h($mailSettings['oauth2_redirect_uri']) ?>" 
                                       placeholder="<?= BASE_URL ?>settings/oauth2/callback">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    URL de redirection après autorisation OAuth2
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" id="testOAuth2Btn">
                                    <i class="bi bi-shield-check me-1"></i> Tester OAuth2
                                </button>
                                <button type="button" class="btn btn-outline-info" id="authorizeOAuth2Btn">
                                    <i class="bi bi-person-check me-1"></i> Autoriser l'application
                                </button>
                                <button type="button" class="btn btn-outline-success" id="testEmailSendBtn">
                                    <i class="bi bi-envelope-check me-1"></i> Tester envoi email
                                </button>
                            </div>
                            
                            <!-- Aide pour les tests -->
                            <div class="mt-3">
                                <div class="alert alert-light border-success">
                                    <i class="bi bi-lightbulb me-2"></i>
                                    <strong>Étapes de test :</strong>
                                    <ol class="mb-0 mt-2 small">
                                        <li><strong>Tester OAuth2</strong> : Vérifie la connectivité et la configuration</li>
                                        <li><strong>Autoriser l'application</strong> : Lance le processus d'autorisation avec votre compte Exchange 365</li>
                                        <li><strong>Tester envoi email</strong> : Envoie un email de test pour vérifier le fonctionnement</li>
                                        <li><strong>Sauvegarder</strong> : Enregistre tous les paramètres (SMTP + OAuth2)</li>
                                    </ol>
                                </div>
                            </div>
                            
                            <?php if (!empty($mailSettings['oauth2_access_token'])): ?>
                                <div class="mt-3">
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle me-2"></i>
                                        <strong>OAuth2 configuré</strong>
                                        <?php if (!empty($mailSettings['oauth2_token_expires'])): ?>
                                            <br><small>Token expire le : <?= date('d/m/Y H:i', strtotime($mailSettings['oauth2_token_expires'])) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Paramètres d'envoi automatique -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear text-primary me-2 me-1"></i>
                        Paramètres d'envoi automatique
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>settings/saveEmailSettings">
                        <?= csrf_field() ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Envoi automatique verrouillé :</strong> ces options sont désactivées pour le moment et ne peuvent pas être activées.
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_auto_send_creation" 
                                       name="email_auto_send_creation" value="1" 
                                       disabled>
                                <label class="form-check-label" for="email_auto_send_creation">
                                    Envoi automatique à la création d'intervention
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_auto_send_closing" 
                                       name="email_auto_send_closing" value="1" 
                                       disabled>
                                <label class="form-check-label" for="email_auto_send_closing">
                                    Envoi automatique à la fermeture d'intervention
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_auto_send_bon" 
                                       name="email_auto_send_bon" value="1" 
                                       disabled>
                                <label class="form-check-label" for="email_auto_send_bon">
                                    Envoi automatique du bon d'intervention (actuellement désactivé)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Email de test (développement)</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" 
                                   value="<?= h($mailSettings['test_email']) ?>" 
                                   placeholder="dev@example.com">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Si renseigné, tous les emails seront envoyés à cette adresse au lieu des destinataires réels.
                                <strong>Utile pour le développement.</strong>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Sauvegarder les paramètres
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Guide de configuration Azure AD -->
        <div class="col-md-6 mb-4" id="azure-guide">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Guide de configuration Azure AD pour OAuth2
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">
                                <i class="bi bi-1-circle me-1"></i> Créer une application Azure AD
                            </h6>
                            <ol class="small">
                                <li>Allez sur <a href="https://portal.azure.com" target="_blank" class="text-decoration-none">portal.azure.com</a></li>
                                <li>Azure Active Directory → Inscriptions d'applications</li>
                                <li>Nouvelle inscription → Nom : "Avision Email System"</li>
                                <li>URI de redirection : <code><?= BASE_URL ?>settings/oauth2/callback</code></li>
                            </ol>
                            
                            <h6 class="text-primary mt-3">
                                <i class="bi bi-2-circle me-1"></i> Configurer les permissions
                            </h6>
                            <ul class="small">
                                <li>Autorisations d'API → Microsoft Graph</li>
                                <li>Ajouter : <code>Mail.Send</code> et <code>offline_access</code></li>
                                <li>Accorder le consentement administrateur</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">
                                <i class="bi bi-3-circle me-1"></i> Générer un secret client
                            </h6>
                            <ol class="small">
                                <li>Certificats et secrets → Nouveau secret client</li>
                                <li>Description : "Avision Email Secret"</li>
                                <li>Durée : 24 mois</li>
                                <li><strong>⚠️ Copiez immédiatement la valeur !</strong></li>
                            </ol>
                            
                            <h6 class="text-primary mt-3">
                                <i class="bi bi-4-circle me-1"></i> Récupérer les identifiants
                            </h6>
                            <ul class="small">
                                <li><strong>Client ID</strong> : Vue d'ensemble de l'application</li>
                                <li><strong>Tenant ID</strong> : Vue d'ensemble d'Azure AD</li>
                                <li><strong>Client Secret</strong> : Valeur copiée précédemment</li>
                                <li><strong>Redirect URI</strong> : <code><?= BASE_URL ?>settings/oauth2/callback</code></li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="alert alert-light border-info">
                            <i class="bi bi-lightbulb me-2"></i>
                            <strong>Conseil :</strong> Une fois OAuth2 configuré, vous pourrez désactiver l'authentification basique dans Exchange 365 pour une sécurité renforcée.
                            <a href="<?= BASE_URL ?>OAUTH2_SETUP_GUIDE.md" target="_blank" class="btn btn-sm btn-outline-info ms-2">
                                <i class="bi bi-book me-1"></i> Guide complet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gestion des templates -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-text text-primary me-2 me-1"></i>
                        Templates d'emails
                    </h5>
                    <a href="<?= BASE_URL ?>settings/emailTemplate" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus me-1"></i> Nouveau template
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($templates)): ?>
                        <p class="text-muted">Aucun template configuré.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Type</th>
                                        <th>Sujet</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td><?= h($template['name']) ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= h($template['template_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= h($template['subject']) ?></td>
                                            <td>
                                                <?php if ($template['is_active']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>settings/emailTemplate/<?= $template['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil me-1"></i> Modifier
                                                </a>
                                                <a href="<?= BASE_URL ?>settings/deleteEmailTemplate/<?= $template['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce template ?')">
                                                    <i class="bi bi-trash me-1"></i> Supprimer
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Gestion du switch OAuth2
document.getElementById('oauth2_enabled').addEventListener('change', function() {
    const oauth2Config = document.getElementById('oauth2Config');
    const smtpOAuth2Help = document.getElementById('smtpOAuth2Help');
    
    if (this.checked) {
        oauth2Config.style.display = 'block';
        smtpOAuth2Help.style.display = 'block';
    } else {
        oauth2Config.style.display = 'none';
        smtpOAuth2Help.style.display = 'none';
    }
});

// Afficher l'aide SMTP si OAuth2 est déjà activé au chargement
document.addEventListener('DOMContentLoaded', function() {
    const oauth2Enabled = document.getElementById('oauth2_enabled');
    const smtpOAuth2Help = document.getElementById('smtpOAuth2Help');
    
    if (oauth2Enabled.checked) {
        smtpOAuth2Help.style.display = 'block';
    }
});

// Test OAuth2
document.getElementById('testOAuth2Btn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Test en cours...';
    
    const formData = new FormData();
    formData.append('oauth2_client_id', document.getElementById('oauth2_client_id').value);
    formData.append('oauth2_client_secret', document.getElementById('oauth2_client_secret').value);
    formData.append('oauth2_tenant_id', document.getElementById('oauth2_tenant_id').value);
    formData.append('oauth2_redirect_uri', document.getElementById('oauth2_redirect_uri').value);
    
    fetch('<?= BASE_URL ?>settings/testOAuth2', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showModal('success', 'Test OAuth2 réussi !', data.message);
        } else {
            showModal('danger', 'Test OAuth2 échoué', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showModal('danger', 'Erreur lors du test OAuth2', error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Autorisation OAuth2
document.getElementById('authorizeOAuth2Btn').addEventListener('click', function() {
    const clientId = document.getElementById('oauth2_client_id').value;
    const tenantId = document.getElementById('oauth2_tenant_id').value;
    const redirectUri = document.getElementById('oauth2_redirect_uri').value;
    
    if (!clientId || !tenantId || !redirectUri) {
        showModal('warning', 'Configuration incomplète', 'Veuillez remplir tous les champs OAuth2 avant de lancer l\'autorisation.');
        return;
    }
    
    // Construire l'URL d'autorisation OAuth2
    const authUrl = `https://login.microsoftonline.com/${tenantId}/oauth2/v2.0/authorize?` +
        `client_id=${encodeURIComponent(clientId)}&` +
        `response_type=code&` +
        `redirect_uri=${encodeURIComponent(redirectUri)}&` +
        `scope=${encodeURIComponent('https://outlook.office.com/SMTP.Send offline_access')}&` +
        `response_mode=query&` +
        `state=oauth2_auth`;
    
    // Ouvrir la fenêtre d'autorisation
    window.open(authUrl, 'oauth2_auth', 'width=600,height=700,scrollbars=yes,resizable=yes');
});

// Test d'envoi d'email
document.getElementById('testEmailSendBtn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Envoi en cours...';
    
    const formData = new FormData();
    formData.append('test_email', document.getElementById('test_email').value);
    
    fetch('<?= BASE_URL ?>settings/testEmailSend', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showModal('success', 'Email envoyé avec succès !', data.message);
        } else {
            showModal('danger', 'Erreur lors de l\'envoi', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showModal('danger', 'Erreur lors de l\'envoi', error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Test SMTP simple
document.getElementById('testSmtpBtn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    // Désactiver le bouton et afficher un indicateur de chargement
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Test en cours...';
    
    // Récupérer les valeurs du formulaire
    const mailHost = document.getElementById('mail_host').value;
    const mailPort = document.getElementById('mail_port').value;
    const mailUsername = document.getElementById('mail_username').value;
    const mailPassword = document.getElementById('mail_password').value;
    const mailEncryption = document.getElementById('mail_encryption').value;
    
    // Créer les données à envoyer
    const formData = new FormData();
    formData.append('test_smtp', '1');
    formData.append('mail_host', mailHost);
    formData.append('mail_port', mailPort);
    formData.append('mail_username', mailUsername);
    formData.append('mail_password', mailPassword);
    formData.append('mail_encryption', mailEncryption);
    
    // Envoyer la requête
    fetch('<?= BASE_URL ?>settings/testSmtp', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showModal('success', 'Test SMTP réussi', data.message);
        } else {
            showModal('danger', 'Test SMTP échoué', data.message);
        }
    })
    .catch(error => {
        showModal('danger', 'Erreur', 'Erreur lors du test SMTP: ' + error.message);
    })
    .finally(() => {
        // Réactiver le bouton
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});


function showModal(type, title, message) {
    // Supprimer les anciennes modales de test SMTP
    const existingModals = document.querySelectorAll('#smtpTestModal');
    existingModals.forEach(modal => modal.remove());
    
    // Définir les icônes selon le type
    const icons = {
        'success': 'bi-check-circle-fill text-success',
        'danger': 'bi-exclamation-triangle-fill text-danger',
        'warning': 'bi-exclamation-triangle-fill text-warning',
        'info': 'bi-info-circle-fill text-info'
    };
    
    // Créer la modale
    const modalHtml = `
        <div class="modal fade" id="smtpTestModal" tabindex="-1" aria-labelledby="smtpTestModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="smtpTestModalLabel">
                            <i class="bi ${icons[type] || icons['info']} me-2"></i>
                            ${title}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-${type} mb-0">
                            <i class="bi ${icons[type] || icons['info']} me-2"></i>
                            ${message}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> Fermer
                        </button>
                        ${type === 'success' ? '' : `
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('testSmtpBtn').click()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Tester à nouveau
                        </button>
                        `}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insérer la modale dans le DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Afficher la modale
    const modal = new bootstrap.Modal(document.getElementById('smtpTestModal'));
    modal.show();
    
    // Supprimer la modale du DOM quand elle est fermée
    document.getElementById('smtpTestModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}
</script>
