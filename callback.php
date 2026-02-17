<?php
ob_start();
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================
    FONCTION DE LOGS (Adaptée SQL Server)
================================== */
function addLog($pdo, $user, $action) {
    try {
        // Remplacement de NOW() par GETDATE() pour SQL Server
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, GETDATE())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur Log : " . $e->getMessage());
    }
}

// 1. Vérification du code Discord
if (!isset($_GET['code'])) {
    header("Location: login.php?error=missing_code");
    exit("Code Discord manquant.");
}

$code = $_GET['code'];

/* ===============================
   2. Récupération du token OAuth
================================ */
$ch = curl_init("https://discord.com/api/oauth2/token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $redirect_uri
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);

$response = curl_exec($ch);
curl_close($ch);
$token = json_decode($response, true);

if (!isset($token['access_token'])) {
    header("Location: login.php?error=token_error");
    exit();
}

/* ===============================
   3. Récupération profil Discord
================================ */
$ch = curl_init("https://discord.com/api/users/@me");
curl_array = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']]
];
curl_setopt_array($ch, $curl_array);

$response = curl_exec($ch);
curl_close($ch);
$discordUser = json_decode($response, true);

if (!isset($discordUser['id'])) {
    header("Location: login.php?error=user_fetch_error");
    exit();
}

/* ===============================
   4. Préparation des données
================================ */
$discord_id = $discordUser['id'];
$username   = $discordUser['username'];
$avatar     = !empty($discordUser['avatar']) 
              ? "https://cdn.discordapp.com/avatars/$discord_id/{$discordUser['avatar']}.png" 
              : "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random";

$super_admins = ["882717172575653928"];

/* ===============================
   5. Gestion Base de Données (SQL Server)
================================ */
// On cherche l'utilisateur
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE discord_id = ?");
$stmt->execute([$discord_id]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dbUser) {
    // UTILISATEUR EXISTANT
    $role = $dbUser['role'];
    $user_internal_id = $dbUser['id'];

    $update = $pdo->prepare("UPDATE users SET username = ?, avatar = ? WHERE discord_id = ?");
    $update->execute([$username, $avatar, $discord_id]);
    
    if (!empty($role)) {
        addLog($pdo, $username, "Connexion : L'utilisateur s'est connecté au panel.");
    }
} else {
    // NOUVEL UTILISATEUR
    $role = in_array($discord_id, $super_admins) ? 'admin' : null;

    $insert = $pdo->prepare("INSERT INTO users (discord_id, username, avatar, role) VALUES (?, ?, ?, ?)");
    $insert->execute([$discord_id, $username, $avatar, $role]);
    
    // Récupération de l'ID sous SQL Server
    $user_internal_id = $pdo->lastInsertId();
    
    addLog($pdo, $username, "Inscription : Nouvel utilisateur détecté (ID Discord: $discord_id).");
}

/* ===============================
   6. Blocage si rôle NULL
================================ */
if (empty($role)) {
    header("Location: login.php?error=pending"); 
    exit();
}

/* ===============================
   7. Création de la Session
================================ */
session_regenerate_id(true);

$_SESSION['user'] = [
    'id'         => $user_internal_id,
    'discord_id' => $discord_id,
    'username'   => $username,
    'avatar'     => $avatar,
    'role'       => $role
];

header("Location: dashboard.php");
exit();
ob_end_flush();
