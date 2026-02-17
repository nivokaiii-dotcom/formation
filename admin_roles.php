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
    
    // Empêcher de changer son propre rôle
    if ($userId === $currentAdminId) {
        $message = "<div class='alert alert-warning shadow-sm'>Action impossible : Vous ne pouvez pas modifier votre propre rôle.</div>";
    } elseif (in_array($newRole, $allowedRoles)) {
        try {
            // Syntaxe standard compatible SQL Server
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $userId]);
            
            // Note : Assure-toi que insertLog utilise GETDATE() pour SQL Server
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
    
    // Empêcher de se supprimer soi-même
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

// Récupération de la liste des utilisateurs (Syntaxe SQL Server compatible)
$users = $pdo->query("SELECT id, username, discord_id, avatar, role FROM users ORDER BY username ASC")->fetchAll();
?>

<style>
    html, body { height: 100%; margin: 0; }
    body { display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1 0 auto; }
    .table-container { 
        background: var(--bs-card-bg); 
        border-radius: 12px; 
        border: 1px solid var(--bs-border-color); 
        overflow: hidden; 
    }
    .role-badge { font-size: 0.75rem; padding: 5px 12px; border-radius: 50px; font-weight: 700; text-transform: uppercase; }
    .role-admin { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .role-user { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
    [data-bs-theme="dark"] .role-user { background: #334155; color: #f1f5f9; border-color: #475569; }
    .avatar-user { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--bs-border-color); }
    .btn-delete { 
        color: #ef4444; 
        background: rgba(239, 68, 68, 0.1); 
        transition: all 0.2s;
    }
    .btn-delete:hover { background: #ef4444; color: white; }
    .btn-delete:disabled { opacity: 0.5; cursor: not-allowed; background: #ccc; color: #666; }
    .text-title { color: var(--bs-heading-color); }
</style>

<div class="main-content">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-title mb-1">Gestion des Rôles</h2>
                <p class="text-muted">Administrez les privilèges et les comptes</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm fw-bold">
                <i class="bi bi-house-door me-1"></i> Dashboard
            </a>
        </div>

        <?= $message ?>

        <div class="table-container shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Utilisateur</th>
                            <th>Rôle</th>
                            <th>Modifier</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr class="<?= $u['id'] == $currentAdminId ? 'table-info' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <?php 
                                        $avatarUrl = !empty($u['avatar']) ? $u['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($u['username']);
                                    ?>
                                    <img src="<?= $avatarUrl ?>" class="avatar-user me-3" alt="Avatar">
                                    <div>
                                        <div class="fw-bold text-title">
                                            <?= htmlspecialchars($u['username'] ?? '') ?> 
                                            <?= $u['id'] == $currentAdminId ? '<span class="badge bg-info text-dark ms-1">Moi</span>' : '' ?>
                                        </div>
                                        <div class="text-muted small">ID: <?= htmlspecialchars($u['discord_id'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge role-<?= htmlspecialchars($u['role']) ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm fw-bold" style="width: 110px;" <?= $u['id'] == $currentAdminId ? 'disabled' : '' ?>>
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-primary btn-sm rounded-pill" <?= $u['id'] == $currentAdminId ? 'disabled' : '' ?>>
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" onsubmit="return confirm('Confirmer la suppression définitive de cet utilisateur ?');">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-delete rounded-pill px-3 fw-bold" <?= $u['id'] == $currentAdminId ? 'disabled' : '' ?>>
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
