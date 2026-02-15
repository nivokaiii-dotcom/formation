<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Redirection automatique si déjà connecté
if (isset($_SESSION['user']['role']) && !empty($_SESSION['user']['role'])) {
    header("Location: dashboard.php");
    exit();
}

// 2. Préparation du lien Discord
$params = [
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'identify'
];
$auth_url = "https://discord.com/api/oauth2/authorize?" . http_build_query($params);

// 3. Gestion des messages d'alerte
$error_message = "";
$alert_type = "danger";

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'pending':
            $error_message = "<strong>Accès restreint :</strong> Votre compte est en attente de validation par un administrateur.";
            $alert_type = "warning";
            break;
        case 'access_denied':
            $error_message = "Votre session a expiré ou vous n'avez plus les permissions nécessaires.";
            break;
        case 'token_error':
            $error_message = "Erreur de communication avec Discord. Veuillez réessayer.";
            break;
        default:
            $error_message = "Une erreur inconnue est survenue.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | Staff Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #0f172a;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1.5rem;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .logo-container {
            width: 80px;
            height: 80px;
            background: #3b82f6;
            border-radius: 1rem;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
        }
        .btn-discord {
            background-color: #5865F2;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            text-decoration: none;
        }
        .btn-discord:hover {
            background-color: #4752C4;
            transform: translateY(-2px);
            color: white;
        }
        .text-muted { color: #94a3b8 !important; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        
        <h2 class="text-white fw-bold mb-2">Staff Panel</h2>
        <p class="text-muted mb-4">Connectez-vous pour accéder à l'administration</p>

        <?php if ($error_message): ?>
            <div class="alert alert-<?= $alert_type ?> border-0 small mb-4" role="alert">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <a href="<?= $auth_url ?>" class="btn-discord">
            <i class="bi bi-discord fs-5"></i>
            Se connecter avec Discord
        </a>

        <div class="mt-4 pt-3 border-top border-secondary">
            <p class="text-muted mb-0 small">
                En vous connectant, vous acceptez les conditions d'utilisation du panel.
            </p>
        </div>
    </div>

</body>
</html>