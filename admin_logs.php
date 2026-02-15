<?php
ob_start();
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sécurité Admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Accès refusé.");
}

require_once 'includes/header.php';

// Option : Vider les logs
if (isset($_POST['clear_logs'])) {
    $pdo->query("DELETE FROM logs");
    insertLog($pdo, "A vidé l'historique des logs");
    header("Location: admin_logs.php");
    exit();
}

// Récupération des logs (du plus récent au plus ancien)
$logs = $pdo->query("SELECT * FROM logs ORDER BY date_action DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Intégration du thème dynamique */
    .log-card { 
        background: var(--card-bg); 
        border: 1px solid var(--border-color); 
        border-radius: 15px; 
    }
    
    .table thead { 
        background: var(--table-header); 
    }
    
    .table thead th { 
        font-size: 0.75rem; 
        text-transform: uppercase; 
        letter-spacing: 0.05rem; 
        color: var(--text-main);
        opacity: 0.7;
        border-bottom: 1px solid var(--border-color);
    }

    .table td { 
        color: var(--text-main); 
        border-bottom: 1px solid var(--border-color);
    }

    /* Badge utilisateur adaptable */
    .badge-user { 
        background: var(--table-header); 
        color: var(--text-main); 
        border: 1px solid var(--border-color);
        padding: 5px 10px;
    }

    .text-dimmed { color: var(--text-main); opacity: 0.6; }
</style>

<div class="container mt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1" style="color: var(--text-main);">
                <i class="bi bi-list-check text-primary"></i> Logs Système
            </h2>
            <p class="text-dimmed small mb-0">Historique des 500 dernières actions effectuées sur le panel.</p>
        </div>
        
        <form method="POST" onsubmit="return confirm('Voulez-vous vraiment effacer tous les logs ?');">
            <button type="submit" name="clear_logs" class="btn btn-outline-danger btn-sm px-3">
                <i class="bi bi-trash"></i> Vider l'historique
            </button>
        </form>
    </div>

    <div class="log-card shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Date & Heure</th>
                        <th>Utilisateur</th>
                        <th>Action effectuée</th>
                        <th class="text-end pe-4">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-dimmed">
                                <i class="bi bi-info-circle d-block fs-2 mb-2"></i>
                                Aucun log enregistré pour le moment.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="ps-4 text-dimmed small">
                                <?= date('d/m/Y H:i', strtotime($l['date_action'])) ?>
                            </td>
                            <td>
                                <span class="badge badge-user">
                                    <i class="bi bi-person-circle me-1 text-primary"></i> 
                                    <?= htmlspecialchars($l['utilisateur']) ?>
                                </span>
                            </td>
                            <td class="fw-medium">
                                <?= htmlspecialchars($l['action']) ?>
                            </td>
                            <td class="text-end pe-4">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3">
                                    OK
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush(); 
?>