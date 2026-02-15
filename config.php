<?php
session_start();

try {
    // Connexion à la base de données MySQL sur Infomaniak
    $pdo = new PDO(
        "mysql:host=3f2b5f.myd.infomaniak.com;dbname=3f2b5f_formation;charset=utf8",
        "3f2b5f_formation",
        "Trizomique@1234"
    );

    // Pour afficher les erreurs SQL si problème
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Arrêt du script et affichage de l'erreur si connexion impossible
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Identifiants pour OAuth ou API
$client_id = "1471140202268328120";
$client_secret = "h-z0R0Avpodv0QyQc-N6gboGvlWMzEc-";
$redirect_uri = "https://formation.tastytom.ch/callback.php";

// Liste des administrateurs
$admins = [
    "882717172575653928"
];

function insertLog($pdo, $action) {
    $user = $_SESSION['user']['username'] ?? 'Système';
    $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action) VALUES (?, ?)");
    $stmt->execute([$user, $action]);
}
?>
