<?php

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ===============================
   1. Vérification code Discord
================================ */

if (!isset($_GET['code'])) {

    header("Location: login.php?error=missing_code");
    exit();

}

$code = $_GET['code'];


/* ===============================
   2. Token OAuth Discord
================================ */

$ch = curl_init("https://discord.com/api/oauth2/token");

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => http_build_query([

        'client_id' => $client_id,

        'client_secret' => $client_secret,

        'grant_type' => 'authorization_code',

        'code' => $code,

        'redirect_uri' => $redirect_uri

    ]),

    CURLOPT_HTTPHEADER => [

        'Content-Type: application/x-www-form-urlencoded'

    ]

]);

$response = curl_exec($ch);

curl_close($ch);

$token = json_decode($response, true);


if (!isset($token['access_token'])) {

    header("Location: login.php?error=token_error");
    exit();

}



/* ===============================
   3. Profil Discord
================================ */

$ch = curl_init("https://discord.com/api/users/@me");

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_HTTPHEADER => [

        'Authorization: Bearer ' . $token['access_token']

    ]

]);

$response = curl_exec($ch);

curl_close($ch);

$discordUser = json_decode($response, true);


if (!isset($discordUser['id'])) {

    header("Location: login.php?error=user_fetch_error");
    exit();

}



/* ===============================
   4. Préparation données
================================ */

$discord_id = $discordUser['id'];

$username = $discordUser['username'];

$avatar = !empty($discordUser['avatar'])

    ? "https://cdn.discordapp.com/avatars/$discord_id/{$discordUser['avatar']}.png"

    : "https://ui-avatars.com/api/?name=" . urlencode($username);



/* ===============================
   5. Liste admins
================================ */

$super_admins = [

    "882717172575653928"

];



/* ===============================
   6. Vérifier si existe
================================ */

$stmt = $pdo->prepare("

    SELECT id, role

    FROM users

    WHERE discord_id = ?

");

$stmt->execute([$discord_id]);

$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);



/* ===============================
   7. SI EXISTE
================================ */

if ($dbUser) {

    $user_internal_id = $dbUser['id'];

    $role = $dbUser['role'];


    // UPDATE INFOS

    $update = $pdo->prepare("

        UPDATE users

        SET username = ?, avatar = ?

        WHERE discord_id = ?

    ");

    $update->execute([

        $username,

        $avatar,

        $discord_id

    ]);

}



/* ===============================
   8. SINON CREATION SQL SERVER
================================ */

else {


    $role = in_array($discord_id, $super_admins)

        ? 'admin'
        : NULL;



    // IMPORTANT SQL SERVER

    $insert = $pdo->prepare("

        INSERT INTO users
        (discord_id, username, avatar, role)

        OUTPUT INSERTED.id

        VALUES (?, ?, ?, ?)

    ");

    $insert->execute([

        $discord_id,

        $username,

        $avatar,

        $role

    ]);


    // récupérer ID SQL Server

    $user_internal_id = $insert->fetchColumn();

}



/* ===============================
   9. REFUSER SI PAS ROLE
================================ */

if (empty($role)) {

    header("Location: login.php?error=pending");

    exit();

}



/* ===============================
   10. SESSION
================================ */

session_regenerate_id(true);


$_SESSION['user'] = [

    'id' => $user_internal_id,

    'discord_id' => $discord_id,

    'username' => $username,

    'avatar' => $avatar,

    'role' => $role

];



/* ===============================
   11. REDIRECTION
================================ */

header("Location: dashboard.php");

exit();

?>
