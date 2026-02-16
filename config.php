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
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Chargement du fichier .env
loadEnv(__DIR__ . '/.env');

try {
    // Connexion à la base de données via les variables d'environnement
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // En production, évite d'afficher $e->getMessage() qui peut montrer ton hôte SQL
    die("Erreur de connexion à la base de données.");
}

// Identifiants OAuth
$client_id     = $_ENV['DISCORD_CLIENT_ID'];
$client_secret = $_ENV['DISCORD_CLIENT_SECRET'];
$redirect_uri  = $_ENV['DISCORD_REDIRECT_URI'];

// Liste des administrateurs (on transforme la chaîne du .env en tableau)
$super_admins = explode(',', $_ENV['ADMIN_IDS']);

function insertLog($pdo, $action) {
    $user = $_SESSION['user']['username'] ?? 'Système';
    $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, NOW())");
    $stmt->execute([$user, $action]);
}
?>
