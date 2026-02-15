<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']]
]);

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

// Liste des IDs Discord qui deviennent admin automatiquement à l'inscription
$super_admins = ["882717172575653928"];

/* ===============================
   5. Gestion Base de Données
================================ */
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE discord_id = ?");
$stmt->execute([$discord_id]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dbUser) {
    // UTILISATEUR EXISTANT
    $role = $dbUser['role'];
    $user_internal_id = $dbUser['id'];

    // Mise à jour des infos (Pseudo et Avatar peuvent changer sur Discord)
    $update = $pdo->prepare("UPDATE users SET username = ?, avatar = ? WHERE discord_id = ?");
    $update->execute([$username, $avatar, $discord_id]);
} else {
    // NOUVEL UTILISATEUR
    $role = in_array($discord_id, $super_admins) ? 'admin' : null;

    $insert = $pdo->prepare("INSERT INTO users (discord_id, username, avatar, role) VALUES (?, ?, ?, ?)");
    $insert->execute([$discord_id, $username, $avatar, $role]);
    $user_internal_id = $pdo->lastInsertId();
}

/* ===============================
   6. Blocage si rôle NULL
================================ */
// Important : Ton header.php déconnecte si le rôle est vide.
// On fait la même chose ici pour éviter d'entrer sur le dashboard.
if (empty($role)) {
    header("Location: login.php?error=pending"); 
    exit();
}

/* ===============================
   7. Création de la Session
================================ */
// On régénère l'ID de session par sécurité
session_regenerate_id(true);

$_SESSION['user'] = [
    'id'       => $user_internal_id, // ID de la base de données
    'discord_id' => $discord_id,
    'username' => $username,
    'avatar'   => $avatar,
    'role'     => $role
];

// Redirection finale
header("Location: dashboard.php");
exit();