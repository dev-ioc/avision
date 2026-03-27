<?php
// Test ultra-rapide Plesk (sans tests réseau)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 10);

echo "<h2>⚡ Test Ultra-Rapide Plesk</h2>";
echo "<p><strong>Date :</strong> " . date('Y-m-d H:i:s') . "</p>";

try {
    // Charger la configuration
    require_once '../config/database.php';
    require_once '../config/config.php';
    
    $config = Config::getInstance();
    
    echo "<h3>📋 Configuration actuelle</h3>";
    echo "<ul>";
    echo "<li><strong>OAuth2 activé :</strong> " . ($config->get('oauth2_enabled') ? '✅ Oui' : '❌ Non') . "</li>";
    echo "<li><strong>Mail Host :</strong> " . $config->get('mail_host', 'Non configuré') . "</li>";
    echo "<li><strong>Mail Port :</strong> " . $config->get('mail_port', 'Non configuré') . "</li>";
    echo "<li><strong>Mail Encryption :</strong> " . $config->get('mail_encryption', 'Non configuré') . "</li>";
    echo "</ul>";
    
    echo "<h3>🚨 Problème identifié</h3>";
    echo "<p><strong>Ports SMTP bloqués :</strong> Votre serveur Plesk bloque les ports 587 et 465 vers Office 365</p>";
    
    echo "<h3>🔧 Solution immédiate</h3>";
    echo "<p>Utilisez le bouton <strong>\"Désactivation d'urgence\"</strong> dans les paramètres email pour :</p>";
    echo "<ul>";
    echo "<li>Désactiver OAuth2</li>";
    echo "<li>Configurer SMTP classique</li>";
    echo "<li>Éviter les ports bloqués</li>";
    echo "</ul>";
    
    echo "<h3>💡 Solutions Plesk</h3>";
    echo "<ol>";
    echo "<li><strong>SMTP Relais Plesk :</strong> localhost:587</li>";
    echo "<li><strong>Service externe :</strong> SendGrid, Mailgun, Amazon SES</li>";
    echo "<li><strong>Contacter hébergeur :</strong> Ouvrir ports 587/465</li>";
    echo "</ol>";
    
    echo "<h3>⚙️ Configuration recommandée</h3>";
    echo "<pre>";
    echo "Host: localhost\n";
    echo "Port: 587\n";
    echo "Encryption: TLS\n";
    echo "Username: Votre email\n";
    echo "Password: Votre mot de passe\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Erreur : " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><em>⚠️ Supprimez ce fichier après diagnostic</em></p>";
?>
