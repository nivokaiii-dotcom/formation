<?php
ob_start();
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sécurité Admin : Vérification stricte du rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit("Accès refusé.");
}

// Action : Vider les logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    // On vide la table
    $pdo->query("TRUNCATE TABLE logs");
    // On log l'action de nettoyage
    insertLog($pdo, "⚠️ NETTOYAGE : L'historique des logs a été intégralement vidé.");
    
    header("Location: admin_logs.php?success=cleared");
    exit();
}

// Récupération des logs avec limite
$logs = $pdo->query("SELECT * FROM logs ORDER BY date_action DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
    :root {
        --log-bg: var(--bs-body-bg);
        --log-card-bg: var(--bs-tertiary-bg);
        --log-border: var(--bs-border-color);
    }

    .main-content { background-color: var(--log-bg); min-height: 100vh; }
    
    .log-card { 
        background: var(--log-card-bg); 
        border: 1px solid var(--log-border); 
        border-radius: 12px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .search-box {
        max-width: 300px;
    }

    .table thead th { 
        font-size: 0.7rem; 
        text-transform: uppercase; 
        letter-spacing: 0.05rem; 
        color: var(--bs-secondary);
        background: rgba(0,0,0,0.02);
        border-top: none;
    }

    .badge-user { 
        background: rgba(13, 110, 253, 0.1); 
        color: #0d6efd; 
        border: 1px solid rgba(13, 110, 253, 0.2);
        font-weight: 600;
    }

    .action-text { font-size: 0.95rem; }
    
    .timestamp { font-family: 'Monaco', 'Consolas', monospace; opacity: 0.7; }

    /* Animation au survol */
    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.03) !important;
        transition: 0.2s;
    }
</style>

<div class="main-content py-4">
    <div class="container">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item small"><a href="dashboard.php">Admin</a></li>
                        <li class="breadcrumb-item small active">Sécurité</li>
                    </ol>
                </nav>
                <h2 class="fw-bold m-0">
                    <i class="bi bi-shield-lock-fill text-primary me-2"></i>Logs Système
                </h2>
                <p class="text-muted small mb-0">Surveillance des 500 dernières activités du panel.</p>
            </div>

            <div class="d-flex gap-2">
                <div class="input-group input-group-sm search-box">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="logSearch" class="form-control border-start-0" placeholder="Filtrer par nom ou action...">
                </div>
                
                <form method="POST" onsubmit="return confirm('⚠️ Action irréversible : Effacer tout l\'historique ?');">
                    <button type="submit" name="clear_logs" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash3 me-1"></i> Vider
                    </button>
                </form>
            </div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> L'historique a été réinitialisé avec succès.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="log-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="logsTable">
                    <thead>
                        <tr>
                            <th class="ps-4 py-3">Horodatage</th>
                            <th class="py-3">Utilisateur</th>
                            <th class="py-3">Action effectuée</th>
                            <th class="text-end pe-4 py-3">Réussite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" style="width: 64px; opacity: 0.3;" alt="empty">
                                    <p class="mt-3 text-muted">Aucune activité enregistrée dans la base de données.</p>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($logs as $l): 
                            // Analyse de l'action pour icône dynamique
                            $icon = "bi-info-circle";
                            if(stripos($l['action'], 'Suppression') !== false) $icon = "bi-exclamation-triangle text-danger";
                            if(stripos($l['action'], 'Connexion') !== false) $icon = "bi-door-open text-success";
                            if(stripos($l['action'], 'Mise à jour') !== false) $icon = "bi-pencil text-warning";
                        ?>
                            <tr class="log-row">
                                <td class="ps-4 timestamp small">
                                    <span class="text-nowrap"><?= date('d/m/Y', strtotime($l['date_action'])) ?></span><br>
                                    <span class="fw-bold"><?= date('H:i:s', strtotime($l['date_action'])) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-user rounded-pill px-3 py-2">
                                        <i class="bi bi-person me-1"></i> 
                                        <?= htmlspecialchars($l['utilisateur']) ?>
                                    </span>
                                </td>
                                <td class="action-text">
                                    <i class="bi <?= $icon ?> me-2"></i>
                                    <?= htmlspecialchars($l['action']) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-success-subtle text-success small border border-success-subtle">
                                        <i class="bi bi-check-lg"></i> SUCCESS
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-3 text-center">
            <p class="text-muted small">Fin de l'historique récent.</p>
        </div>
    </div>
</div>

<script>
    // Script de filtrage dynamique
    document.getElementById('logSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.log-row');

        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>

<?php 
require_once 'includes/footer.php';
ob_end_flush(); 
?>
