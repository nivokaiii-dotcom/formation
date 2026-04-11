<?php
ob_start();
require_once 'config.php';
require_once 'includes/header.php';

// Sécurité : Seuls les admins accèdent à cette page
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$message = "";
$currentAdminId = (int)$_SESSION['user']['id'];

// --- LOGIQUE DE MISE À JOUR DU RÔLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['role'];
    $allowedRoles = ['user', 'admin'];
    
    if ($userId === $currentAdminId) {
        $message = "<div class='alert alert-warning shadow-sm'>Action impossible : Vous ne pouvez pas modifier votre propre rôle.</div>";
    } elseif (in_array($newRole, $allowedRoles)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $userId]);
            insertLog($pdo, "A changé le rôle de l'utilisateur ID $userId en $newRole");
            $message = "<div class='alert alert-success shadow-sm'>Rôle mis à jour avec succès !</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur SQL : " . $e->getMessage() . "</div>";
        }
    }
}

// --- LOGIQUE DE SUPPRESSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = (int)$_POST['user_id'];
    
    if ($userId === $currentAdminId) {
        $message = "<div class='alert alert-warning shadow-sm'>Action impossible : Vous ne pouvez pas vous supprimer vous-même.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            insertLog($pdo, "A supprimé l'utilisateur ID $userId");
            $message = "<div class='alert alert-success shadow-sm'>Utilisateur supprimé définitivement.</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression : " . $e->getMessage() . "</div>";
        }
    }
}

$users = $pdo->query("SELECT id, username, discord_id, avatar, role FROM users ORDER BY username ASC")->fetchAll();
?>

<style>
    .main-content { min-height: 80vh; }

    .table-container { 
        background: var(--bs-card-bg); 
        border-radius: 12px; 
        border: 1px solid var(--bs-border-color); 
        overflow: hidden; 
    }
    
    /* Style des Badges auto-adaptatifs */
    .role-badge { 
        font-size: 0.7rem; 
        padding: 4px 10px; 
        border-radius: 50px; 
        font-weight: 800; 
        text-transform: uppercase; 
        display: inline-block;
    }
    
    .role-admin { 
        background: rgba(220, 38, 38, 0.15); 
        color: #ef4444; 
        border: 1px solid rgba(220, 38, 38, 0.3); 
    }
    
    .role-user { 
        background: rgba(100, 116, 139, 0.15); 
        color: var(--bs-secondary-color); 
        border: 1px solid rgba(100, 116, 139, 0.3); 
    }

    .avatar-user { 
        width: 38px; 
        height: 38px; 
        border-radius: 50%; 
        object-fit: cover; 
        border: 2px solid var(--bs-border-color); 
    }
    
    .btn-delete { 
        color: #ef4444; 
        background: rgba(239, 68, 68, 0.1); 
        border: 1px solid transparent;
        transition: all 0.2s;
    }
    .btn-delete:hover { 
        background: #ef4444; 
        color: white; 
    }

    /* Correction du tableau en mode sombre */
    .table thead th {
        background: rgba(var(--bs-emphasis-color-rgb), 0.03);
        color: var(--bs-secondary-color);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--bs-border-color);
    }

    .me-row {
        border-left: 4px solid #0dcaf0 !important;
        background: rgba(13, 202, 240, 0.05) !important;
    }
</style>

<div class="main-content">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Gestion des Rôles</h2>
                <p class="text-muted mb-0">Administrez les privilèges et les comptes de l'équipe</p>
            </div>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary px-3 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
        </div>

        <?= $message ?>

        <div class="table-container shadow-sm mt-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4 py-3">Utilisateur</th>
                            <th>Rôle Actuel</th>
                            <th>Attribuer Rôle</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr class="<?= $u['id'] == $currentAdminId ? 'me-row' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="<?= !empty($u['avatar']) ? $u['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($u['username']) ?>" class="avatar-user me-3">
                                    <div>
                                        <div class="fw-bold">
                                            <?= htmlspecialchars($u['username']) ?> 
                                            <?php if($u['id'] == $currentAdminId): ?>
                                                <span class="badge bg-info text-dark ms-1" style="font-size: 0.6rem;">VOUS</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">ID: <?= $u['discord_id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge role-<?= $u['role'] ?>">
                                    <i class="bi <?= $u['role'] === 'admin' ? 'bi-shield-lock-fill' : 'bi-person-fill' ?> me-1"></i>
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm fw-semibold" style="width: 110px; font-size: 0.85rem;" <?= $u['id'] == $currentAdminId ? 'disabled' : '' ?>>
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-primary btn-sm px-2" <?= $u['id'] == $currentAdminId ? 'disabled' : '' ?> title="Enregistrer">
                                        <i class="bi bi-save"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" onsubmit="return confirm('Attention : Suppression définitive ?');">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-delete rounded-pill px-3 fw-bold" style="font-size: 0.8rem;" <?= $u['id'] == $currentAdminId ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash3 me-1"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush(); 
?>