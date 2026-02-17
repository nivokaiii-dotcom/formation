<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fonction pour charger les variables d'environnement
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($name, $value) = explode('=', $line, 2);
        // On retire d'éventuels guillemets autour de la valeur
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[trim($name)] = $value;
    }
}

// Chargement du fichier .env
loadEnv(__DIR__ . '/.env');

try {
    /**
     * Connexion à SQL Server (MS SQL)
     * Requis : extension=php_pdo_sqlsrv et extension=php_sqlsrv dans php.ini
     */
    $serverName = $_ENV['DB_HOST'] ?? '';
    $database   = $_ENV['DB_NAME'] ?? '';
    $user       = $_ENV['DB_USER'] ?? '';
    $pass       = $_ENV['DB_PASS'] ?? '';

    // Construction du DSN
    // TrustServerCertificate=true est souvent nécessaire pour les serveurs locaux ou sans certificat SSL valide
    $dsn = "sqlsrv:Server=$serverName;Database=$database;TrustServerCertificate=true";

    $pdo = new PDO($dsn, $user, $pass);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur de connexion SQL Server : " . $e->getMessage());
    die("Erreur de connexion à la base de données. Vérifiez vos pilotes sqlsrv.");
}

// Identifiants OAuth Discord
$client_id     = $_ENV['DISCORD_CLIENT_ID'] ?? '';
$client_secret = $_ENV['DISCORD_CLIENT_SECRET'] ?? '';
$redirect_uri  = $_ENV['DISCORD_REDIRECT_URI'] ?? '';

// Liste des administrateurs (ex: 123456,7891011)
$admin_ids_str = $_ENV['ADMIN_IDS'] ?? '';
$super_admins  = !empty($admin_ids_str) ? explode(',', $admin_ids_str) : [];

/**
 * Fonction de log (Version SQL Server)
 */
if (!function_exists('insertLog')) {
    function insertLog($pdo, $action) {
        $user = $_SESSION['user']['username'] ?? 'Système';
        try {
            // GETDATE() est l'équivalent de NOW() sous SQL Server
            $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, GETDATE())");
            $stmt->execute([$user, $action]);
        } catch (PDOException $e) {
            error_log("Erreur insertLog : " . $e->getMessage());
        }
    }
}
?>
