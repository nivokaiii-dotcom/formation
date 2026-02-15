<?php
ob_start();
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

/* ============================================================
    SYNCHRONISATION ET VÉRIFICATION DU RÔLE
   ============================================================ */
// On récupère le rôle actuel directement en BDD
$stmtCheck = $pdo->prepare("SELECT role FROM users WHERE username = ?");
$stmtCheck->execute([$_SESSION['user']['username']]);
$userFreshData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

// Sécurité : Si l'utilisateur n'existe plus ou si son rôle est NULL/vide
if (!$userFreshData || empty($userFreshData['role'])) {
    session_destroy(); // On détruit la session
    header("Location: login.php?error=access_denied");
    exit();
}

// Mise à jour de la session si le rôle a changé en BDD
if ($_SESSION['user']['role'] !== $userFreshData['role']) {
    $_SESSION['user']['role'] = $userFreshData['role'];
}

$role = $_SESSION['user']['role'];
$username = htmlspecialchars($_SESSION['user']['username'] ?? 'Staff');
$avatar_user = $_SESSION['user']['avatar'] ?? 'https://ui-avatars.com/api/?name='.$username.'&background=random';
$current_page = basename($_SERVER['PHP_SELF']);

// --- CONFIGURATION LOGO LOCAL ---
$logo_path = "includes/favicon.ico"; 
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Panel | <?= ucfirst(str_replace(['admin_', '.php'], ['', ''], $current_page)) ?></title>
    
    <link rel="icon" type="image/png" href="<?= $logo_path ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root[data-bs-theme="light"] {
            --body-bg: #f4f7fe; --card-bg: #ffffff; --text-main: #1e293b;
            --nav-bg: #0f172a; --border-color: #e2e8f0; --table-header: #f8fafc;
        }
        :root[data-bs-theme="dark"] {
            --body-bg: #0f172a; --card-bg: #1e293b; --text-main: #f1f5f9;
            --nav-bg: #020617; --border-color: #334155; --table-header: #334155;
        }
        :root { --admin-gold: #fbbf24; --danger-soft: #f87171; --primary-accent: #3b82f6; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-main);
            margin: 0;
            transition: background 0.3s ease;
        }

        .navbar-main { background: var(--nav-bg); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 0.6rem 0; }
        .nav-logo { width: 32px; height: 32px; object-fit: contain; margin-right: 10px; }
        .nav-link { font-size: 0.9rem; font-weight: 500; color: #94a3b8 !important; transition: all 0.2s; }
        .nav-link:hover, .nav-link.active { color: #fff !important; }
        .nav-admin-link { color: var(--admin-gold) !important; }

        .user-section { border-left: 1px solid rgba(255,255,255,0.1); padding-left: 1rem; margin-left: 1rem; gap: 12px; }
        .user-badge {
            background: rgba(255,255,255,0.05); padding: 0.3rem 0.6rem; border-radius: 10px;
            font-size: 0.85rem; color: #f1f5f9; display: flex; align-items: center; gap: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .theme-toggle {
            background: rgba(255,255,255,0.1); border: none; color: var(--admin-gold);
            width: 35px; height: 35px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s;
        }
        .nav-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
        .logout-icon { color: var(--danger-soft); font-size: 1.2rem; transition: transform 0.2s; }
        .logout-icon:hover { transform: scale(1.1); color: #ff8a8a; }

        @media (max-width: 991px) {
            .user-section { border-left: none; padding-left: 0; margin-left: 0; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-main sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="dashboard.php">
            <img src="<?= $logo_path ?>" class="nav-logo" alt="Logo">
            <span>STAFF PANEL</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-grid-1x2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'planning.php') ? 'active' : '' ?>" href="planning.php">
                        <i class="bi bi-calendar3 me-1"></i> Planning
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'admin_add_planning.php') ? 'active' : '' ?>" href="admin_add_planning.php">
                        <i class="bi bi-calendar-plus me-1"></i> Sessions
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'admin_reussites.php') ? 'active' : '' ?>" href="admin_reussites.php">
                        <i class="bi bi-person-check me-1"></i> Réussites
                    </a>
                </li>

                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link nav-admin-link <?= ($current_page == 'admin_formations.php') ? 'active' : '' ?>" href="admin_formations.php"><i class="bi bi-book me-1"></i> Modules</a></li>
                    <li class="nav-item"><a class="nav-link nav-admin-link <?= ($current_page == 'admin_formateurs.php') ? 'active' : '' ?>" href="admin_formateurs.php"><i class="bi bi-people me-1"></i> Formateurs</a></li>
                    <li class="nav-item"><a class="nav-link nav-admin-link <?= ($current_page == 'admin_roles.php') ? 'active' : '' ?>" href="admin_roles.php"><i class="bi bi-shield-lock me-1"></i> Rôles</a></li>
                    <li class="nav-item"><a class="nav-link nav-admin-link <?= ($current_page == 'admin_logs.php') ? 'active' : '' ?>" href="admin_logs.php"><i class="bi bi-list-check me-1"></i> Logs</a></li>
                <?php endif; ?>
            </ul>

            <div class="user-section d-flex align-items-center">
                <button class="theme-toggle" id="themeBtn" title="Changer le mode">
                    <i class="bi bi-sun-fill" id="themeIcon"></i>
                </button>

                <div class="user-badge">
                    <img src="<?= $avatar_user ?>" class="nav-avatar" alt="Avatar">
                    <span class="fw-semibold d-none d-sm-inline"><?= $username ?></span>
                    <span class="mx-1 text-muted">|</span>
                    <small class="text-info"><?= strtoupper($role) ?></small>
                </div>

                <a href="logout.php" class="logout-icon" title="Déconnexion">
                    <i class="bi bi-power"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    const themeBtn = document.getElementById('themeBtn');
    const themeIcon = document.getElementById('themeIcon');
    const htmlTag = document.documentElement;

    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);

    themeBtn.addEventListener('click', () => {
        const currentTheme = htmlTag.getAttribute('data-bs-theme');
        setTheme(currentTheme === 'light' ? 'dark' : 'light');
    });

    function setTheme(theme) {
        htmlTag.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        themeIcon.className = theme === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
    }
</script>