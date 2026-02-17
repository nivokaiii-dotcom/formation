<?php
session_start();

/**
 * Fonction simple pour charger les variables d'environnement
 * du fichier .env vers $_ENV
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue; // Sécurité si ligne mal formatée
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Chargement du fichier .env
loadEnv(__DIR__ . '/.env');

try {
    /**
     * Connexion à SQL Server (MS SQL)
     * Note : Assure-toi que l'extension 'php_pdo_sqlsrv' est activée dans ton PHP
     */
    $serverName = $_ENV['DB_HOST'];
    $database   = $_ENV['DB_NAME'];
    $user       = $_ENV['DB_USER'];
    $pass       = $_ENV['DB_PASS'];

    // Construction du DSN pour SQL Server
    $dsn = "sqlsrv:Server=$serverName;Database=$database;TrustServerCertificate=true";

    $pdo = new PDO($dsn, $user, $pass);
    
    // Configuration des erreurs et du mode de récupération par défaut
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En cas d'erreur, on logue en silence et on affiche un message générique
    error_log("Erreur de connexion SQL Server : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// Identifiants OAuth
$client_id     = $_ENV['DISCORD_CLIENT_ID'] ?? '';
$client_secret = $_ENV['DISCORD_CLIENT_SECRET'] ?? '';
$redirect_uri  = $_ENV['DISCORD_REDIRECT_URI'] ?? '';

// Liste des administrateurs
$admin_ids_str = $_ENV['ADMIN_IDS'] ?? '';
$super_admins  = !empty($admin_ids_str) ? explode(',', $admin_ids_str) : [];

/**
 * Fonction de log (Version SQL Server)
 */
function insertLog($pdo, $action) {
    $user = $_SESSION['user']['username'] ?? 'Système';
    try {
        // Remplacement de NOW() par GETDATE()
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, GETDATE())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur insertLog : " . $e->getMessage());
    }
}
?>
