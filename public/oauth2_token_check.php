<?php
// Script de vérification du token OAuth2
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔐 Vérification Token OAuth2</h2>";
echo "<p><strong>Date :</strong> " . date('Y-m-d H:i:s') . "</p>";

try {
    // Charger la configuration
    require_once '../config/database.php';
    require_once '../config/config.php';
    
    $config = Config::getInstance();
    
    echo "<h3>📋 Configuration OAuth2</h3>";
    echo "<ul>";
    echo "<li><strong>OAuth2 activé :</strong> " . ($config->get('oauth2_enabled') ? '✅ Oui' : '❌ Non') . "</li>";
    echo "<li><strong>Client ID :</strong> " . ($config->has('oauth2_client_id') ? '✅ Configuré' : '❌ Manquant') . "</li>";
    echo "<li><strong>Client Secret :</strong> " . ($config->has('oauth2_client_secret') ? '✅ Configuré' : '❌ Manquant') . "</li>";
    echo "<li><strong>Tenant ID :</strong> " . ($config->has('oauth2_tenant_id') ? '✅ Configuré' : '❌ Manquant') . "</li>";
    echo "<li><strong>Access Token :</strong> " . ($config->has('oauth2_access_token') ? '✅ Configuré' : '❌ Manquant') . "</li>";
    echo "<li><strong>Refresh Token :</strong> " . ($config->has('oauth2_refresh_token') ? '✅ Configuré' : '❌ Manquant') . "</li>";
    echo "</ul>";
    
    if ($config->has('oauth2_access_token')) {
        echo "<h3>🔍 Analyse du Token</h3>";
        
        // Décoder le token JWT (partie payload)
        $accessToken = $config->get('oauth2_access_token');
        $tokenParts = explode('.', $accessToken);
        if (count($tokenParts) >= 2) {
            $payload = json_decode(base64_decode($tokenParts[1]), true);
            
            if ($payload) {
                echo "<ul>";
                echo "<li><strong>Issuer :</strong> " . ($payload['iss'] ?? 'N/A') . "</li>";
                echo "<li><strong>Audience :</strong> " . ($payload['aud'] ?? 'N/A') . "</li>";
                echo "<li><strong>Scopes :</strong> " . ($payload['scp'] ?? 'N/A') . "</li>";
                echo "<li><strong>Expire :</strong> " . (isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'N/A') . "</li>";
                echo "<li><strong>Expire dans :</strong> " . (isset($payload['exp']) ? ($payload['exp'] - time()) . ' secondes' : 'N/A') . "</li>";
                echo "</ul>";
                
                // Vérifier si le token est expiré
                if (isset($payload['exp']) && $payload['exp'] < time()) {
                    echo "<div style='color: red; font-weight: bold;'>❌ Token expiré !</div>";
                } else {
                    echo "<div style='color: green; font-weight: bold;'>✅ Token valide</div>";
                }
                
                // Vérifier les scopes
                $scopes = $payload['scp'] ?? '';
                if (strpos($scopes, 'SMTP.Send') !== false) {
                    echo "<div style='color: green;'>✅ Scope SMTP.Send présent</div>";
                } else {
                    echo "<div style='color: red;'>❌ Scope SMTP.Send manquant</div>";
                }
            } else {
                echo "<div style='color: red;'>❌ Impossible de décoder le payload du token</div>";
            }
        } else {
            echo "<div style='color: red;'>❌ Format de token invalide</div>";
        }
    }
    
    echo "<h3>💡 Recommandations</h3>";
    echo "<ul>";
    echo "<li>Si le token est expiré : <strong>Re-autorisez l'application</strong></li>";
    echo "<li>Si le scope est incorrect : <strong>Re-autorisez l'application</strong></li>";
    echo "<li>Si le token est valide mais l'envoi échoue : <strong>Vérifiez la connectivité SMTP</strong></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Erreur : " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><em>⚠️ Supprimez ce fichier après diagnostic</em></p>";
?>
