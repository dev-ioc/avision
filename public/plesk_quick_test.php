<?php
// Test rapide SMTP pour Plesk
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 30);

echo "<h2>⚡ Test Rapide SMTP Plesk</h2>";
echo "<p><strong>Date :</strong> " . date('Y-m-d H:i:s') . "</p>";

try {
    // Charger la configuration
    require_once '../config/database.php';
    require_once '../config/config.php';
    
    $config = Config::getInstance();
    
    echo "<h3>📋 Configuration</h3>";
    echo "<ul>";
    echo "<li><strong>OAuth2 activé :</strong> " . ($config->get('oauth2_enabled') ? '✅ Oui' : '❌ Non') . "</li>";
    echo "<li><strong>Mail Host :</strong> " . $config->get('mail_host', 'Non configuré') . "</li>";
    echo "<li><strong>Mail Port :</strong> " . $config->get('mail_port', 'Non configuré') . "</li>";
    echo "</ul>";
    
    echo "<h3>🌐 Test DNS Rapide</h3>";
    $hosts = ['smtp.office365.com', 'outlook.office365.com'];
    foreach ($hosts as $host) {
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            echo "✅ $host → $ip<br>";
        } else {
            echo "❌ $host → DNS échoué<br>";
        }
    }
    
    echo "<h3>🔌 Test Ports (5s timeout)</h3>";
    $ports = [
        ['smtp.office365.com', 587],
        ['smtp.office365.com', 465]
    ];
    
    foreach ($ports as $test) {
        list($host, $port) = $test;
        echo "Test $host:$port : ";
        
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($connection) {
            echo "✅ Port ouvert<br>";
            fclose($connection);
        } else {
            echo "❌ Port fermé (Code: $errno)<br>";
        }
    }
    
    echo "<h3>💡 Solutions Plesk</h3>";
    echo "<ul>";
    echo "<li><strong>1. SMTP Relais Plesk :</strong> Utilisez le SMTP relais de Plesk</li>";
    echo "<li><strong>2. Firewall Plesk :</strong> Vérifiez les règles de pare-feu</li>";
    echo "<li><strong>3. Ports sortants :</strong> Demandez l'ouverture des ports 587/465</li>";
    echo "<li><strong>4. Service SMTP :</strong> Utilisez SendGrid, Mailgun, etc.</li>";
    echo "</ul>";
    
    echo "<h3>🔧 Configuration SMTP Relais Plesk</h3>";
    echo "<pre>";
    echo "Host: localhost (ou IP du serveur Plesk)\n";
    echo "Port: 587 ou 25\n";
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
