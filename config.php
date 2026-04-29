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

/**
 * Autoloader pour charger les classes automatiquement
 */
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/helpers/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Fonction pour insérer un log dans la base de données
 */
function insertLog($pdo, $action) {
    $user = $_SESSION['user']['username'] ?? 'Système';
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, NOW())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur insertion log : " . $e->getMessage());
    }
}

/**
 * Fonction pour vérifier si l'utilisateur est admin
 */
function isAdmin($pdo) {
    if (!isset($_SESSION['user'])) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE discord_id = ?");
    $stmt->execute([$_SESSION['user']['discord_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['role'] === 'admin';
}

/**
 * Fonction pour vérifier si l'utilisateur est connecté
 */
function isConnected() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

/**
 * Fonction pour récupérer l'utilisateur actuel
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Fonction pour rediriger si non authentifié
 */
function requireLogin() {
    if (!isConnected()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Fonction pour rediriger si non admin
 */
function requireAdmin($pdo) {
    if (!isAdmin($pdo)) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Fonction pour nettoyer et valider les entrées
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Fonction pour valider une URL
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Fonction pour générer un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Fonction pour vérifier un token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>