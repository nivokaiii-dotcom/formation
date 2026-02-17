<?php
session_start();

/**
 * Charger les variables .env
 */
function loadEnv($path)
{
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line)
    {
        if (strpos(trim($line), '#') === 0) continue;

        list($name, $value) = explode('=', $line, 2);

        $_ENV[trim($name)] = trim($value);
    }
}

// Chargement
loadEnv(__DIR__ . '/.env');


/* =========================================
   CONNEXION SQL SERVER
========================================= */

try {

    $server   = $_ENV['DB_HOST'];
    $database = $_ENV['DB_NAME'];
    $user     = $_ENV['DB_USER'];
    $pass     = $_ENV['DB_PASS'];

    $pdo = new PDO(

        "sqlsrv:Server=$server;Database=$database",

        $user,

        $pass

    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


} catch (PDOException $e) {

    die("Erreur de connexion SQL Server.");

}


/* =========================================
   DISCORD
========================================= */

$client_id     = $_ENV['DISCORD_CLIENT_ID'];

$client_secret = $_ENV['DISCORD_CLIENT_SECRET'];

$redirect_uri  = $_ENV['DISCORD_REDIRECT_URI'];


/* =========================================
   ADMINS
========================================= */

$super_admins = explode(',', $_ENV['ADMIN_IDS']);


/* =========================================
   LOGS SQL SERVER
========================================= */

function insertLog($pdo, $action)
{

    $user = $_SESSION['user']['username'] ?? 'Systeme';

    $stmt = $pdo->prepare("

        INSERT INTO logs
        (utilisateur, action, date_action)

        VALUES (?, ?, GETDATE())

    ");

    $stmt->execute([

        $user,

        $action

    ]);

}
?>
