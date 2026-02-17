<?php
ob_start();
require 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================
    FONCTION DE LOGS (SQL Server)
================================== */
function addLog($pdo, $action) {
    $user = 'Anonyme';
    if (isset($_SESSION['user']['username'])) {
        $user = $_SESSION['user']['username'];
    } elseif (isset($_SESSION['username'])) {
        $user = $_SESSION['username'];
    }

    try {
        // GETDATE() au lieu de NOW()
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, GETDATE())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur Log : " . $e->getMessage());
    }
}

// Permission
$can_edit = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== 'user';

/* ==================================
    1. ACTIONS (TRAITEMENT)
================================== */

function redirectWithState($tab, $page, $scroll) {
    header("Location: admin_reussites.php?tab=$tab&page=$page&scroll=$scroll");
    exit();
}

if ($can_edit) {
    // ACTION : AJOUTER UN NOUVEAU MEMBRE
    if (isset($_POST['add_new_member'])) {
        $pseudo = trim($_POST['new_pseudo']);
        $discord_id = trim($_POST['new_discord_id']);
        $role = $_POST['new_role'];
        if (!empty($pseudo) && !empty($discord_id)) {
            // CAST(GETDATE() AS DATE) au lieu de CURDATE()
            $stmt = $pdo->prepare("INSERT INTO membres_formes (discord_id, pseudo, role_obtenu, formation_id, date_reussite) VALUES (?, ?, ?, NULL, CAST(GETDATE() AS DATE))");
            $stmt->execute([$discord_id, $pseudo, $role]);
            
            addLog($pdo, "Réussites : Création du profil staff pour '$pseudo' ($role)");
        }
        redirectWithState($_POST['current_tab'], 1, 0);
    }

    // ACTION : SUPPRIMER UN MEMBRE
    if (isset($_POST['delete_member'])) {
        $pseudo = $_POST['pseudo'];
        $stmt = $pdo->prepare("DELETE FROM membres_formes WHERE pseudo = ?");
        $stmt->execute([$pseudo]);
        
        addLog($pdo, "Réussites : Suppression complète du profil de '$pseudo'");
        redirectWithState($_POST['current_tab'], $_POST['current_page'], 0);
    }

    // ACTION : VALIDER/RETIRER UNE FORMATION (TOGGLE)
    if (isset($_POST['toggle_formation'])) {
        $pseudo = $_POST['pseudo'];
        $formation_id = $_POST['formation_id'];
        $formateur = $_SESSION['user']['username'] ?? 'Admin';
        $current_tab = $_POST['current_tab'] ?? 'tab-mod';
        $current_page = $_POST['current_page'] ?? 1;
        $scroll_pos = $_POST['scroll_pos'] ?? 0;

        $stF = $pdo->prepare("SELECT titre FROM formations WHERE id = ?");
        $stF->execute([$formation_id]);
        $fTitre = $stF->fetchColumn();

        $check = $pdo->prepare("SELECT id FROM membres_formes WHERE pseudo = ? AND formation_id = ?");
        $check->execute([$pseudo, $formation_id]);
        $existing = $check->fetch();

        if ($existing) {
            $pdo->prepare("DELETE FROM membres_formes WHERE id = ?")->execute([$existing['id']]);
            addLog($pdo, "Réussites : Retrait formation '$fTitre' pour $pseudo");
        } else {
            // On récupère les infos de base du membre (Top 1 au lieu de LIMIT 1)
            $stmtInfo = $pdo->prepare("SELECT TOP 1 discord_id, role_obtenu FROM membres_formes WHERE pseudo = ?");
            $stmtInfo->execute([$pseudo]);
            $info = $stmtInfo->fetch();

            if ($info) {
                $pdo->prepare("INSERT INTO membres_formes (discord_id, pseudo, role_obtenu, formation_id, date_reussite, formateur_nom) VALUES (?, ?, ?, ?, CAST(GETDATE() AS DATE), ?)")
                    ->execute([$info['discord_id'], $pseudo, $info['role_obtenu'], $formation_id, $formateur]);
                
                addLog($pdo, "Réussites : Validation formation '$fTitre' pour $pseudo");
            }
        }
        redirectWithState($current_tab, $current_page, $scroll_pos);
    }

    // ACTION : CHANGER LE GRADE
    if (isset($_POST['update_role_trigger'])) {
        $new_role = $_POST['new_role'];
        $pseudo = $_POST['pseudo'];
        $stmt = $pdo->prepare("UPDATE membres_formes SET role_obtenu = ? WHERE pseudo = ?");
        $stmt->execute([$new_role, $pseudo]);
        
        addLog($pdo, "Réussites : Changement de grade pour $pseudo vers $new_role");
        redirectWithState($_POST['current_tab'], $_POST['current_page'], $_POST['scroll_pos']);
    }
}

require 'includes/header.php';

/* ==================================
    2. RÉCUPÉRATION ET PAGINATION (MS SQL)
================================== */
$active_tab = $_GET['tab'] ?? 'tab-mod';
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; 
$offset = ($current_page_num - 1) * $limit;
$scroll_to = $_GET['scroll'] ?? 0;

$all_formations = $pdo->query("SELECT * FROM formations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$role_filter = ($active_tab == 'tab-mod') ? 'Modérateur' : 'Support';

// Compte total
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT pseudo) FROM membres_formes WHERE role_obtenu = ?");
$countStmt->execute([$role_filter]);
$total_members = $countStmt->fetchColumn();
$total_pages = ceil($total_members / $limit);

// Pagination SQL Server (OFFSET ... FETCH NEXT ...)
$pseudoStmt = $pdo->prepare("
    SELECT DISTINCT pseudo, discord_id 
    FROM membres_formes 
    WHERE role_obtenu = ? 
    ORDER BY pseudo ASC 
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
$pseudoStmt->execute([$role_filter, $offset, $limit]);
$pseudos_data = $pseudoStmt->fetchAll(PDO::FETCH_ASSOC);

$matrix = [];
if (!empty($pseudos_data)) {
    $pseudos_only = array_column($pseudos_data, 'pseudo');
    $in = str_repeat('?,', count($pseudos_only) - 1) . '?';
    $dataStmt = $pdo->prepare("SELECT * FROM membres_formes WHERE pseudo IN ($in)");
    $dataStmt->execute($pseudos_only);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pseudos_data as $p_row) {
        $matrix[$p_row['pseudo']] = ['discord_id' => $p_row['discord_id'], 'validations' => []];
    }
    foreach ($rows as $row) {
        if ($row['formation_id']) { 
            $matrix[$row['pseudo']]['validations'][] = $row['formation_id']; 
        }
    }
}

$stats_recap = [];
foreach ($all_formations as $f) {
    $st = $pdo->prepare("SELECT COUNT(DISTINCT pseudo) FROM membres_formes WHERE formation_id = ? AND role_obtenu = ?");
    $st->execute([$f['id'], $role_filter]);
    $stats_recap[$f['id']] = $st->fetchColumn();
}
?>

<style>
    body { background-color: var(--bs-body-bg); color: var(--bs-body-color); }
    .table-responsive { border-radius: 12px; background: var(--bs-card-bg); border: 1px solid var(--bs-border-color); }
    .badge-toggle { display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px; border-radius: 50px; font-size: 0.65rem; font-weight: 800; border: none; transition: 0.2s; width: 85px; text-decoration: none; cursor: pointer; }
    .btn-valid { background-color: #10b981; color: white; }
    <?php if($can_edit): ?>
    .btn-valid:hover { background-color: #ef4444; color: white; }
    .btn-valid:hover span { display: none; }
    .btn-valid:hover::after { content: "RETIRER"; }
    <?php endif; ?>
    .btn-empty { background-color: var(--bs-tertiary-bg); color: #94a3b8; border: 1px dashed var(--bs-border-color); }
    .sticky-col { position: sticky; left: 0; background: var(--bs-card-bg); z-index: 2; border-right: 2px solid var(--bs-border-color) !important; }
    .stat-card { background: var(--bs-card-bg); border-left: 4px solid #3b82f6; border-radius: 8px; transition: 0.2s; }
</style>

<div class="container-fluid mt-4 px-4">
    
    <div class="row g-3 mb-4">
        <div class="col-12"><h5 class="fw-bold mb-0">📊 Progression <?= $role_filter ?>s</h5></div>
        <?php foreach ($all_formations as $f): 
            $count = $stats_recap[$f['id']] ?? 0;
            $pct = ($total_members > 0) ? ($count / $total_members) * 100 : 0;
        ?>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card shadow-sm p-3">
                <div class="text-muted small text-truncate fw-bold"><?= mb_strtoupper($f['titre']) ?></div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="fw-bold h5 mb-0"><?= $count ?>/<?= $total_members ?></span>
                    <small class="text-success fw-bold"><?= round($pct) ?>%</small>
                </div>
                <div class="progress mt-2" style="height: 4px;"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h3 class="fw-bold m-0">🛡️ Gestion Staff</h3>
        <div class="d-flex gap-3">
            <input type="text" id="searchInput" class="form-control border-0 shadow-sm" style="width: 300px;" placeholder="Rechercher Pseudo ou ID Discord...">
            <?php if ($can_edit): ?>
                <button class="btn btn-success shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="bi bi-person-plus-fill me-2"></i>Nouveau Staff
                </button>
            <?php endif; ?>
        </div>
    </div>

    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item"><a href="?tab=tab-mod&page=1" class="nav-link <?= $active_tab == 'tab-mod' ? 'active' : '' ?>">Modérateurs</a></li>
        <li class="nav-item"><a href="?tab=tab-sup&page=1" class="nav-link <?= $active_tab == 'tab-sup' ? 'active' : '' ?>">Supports</a></li>
    </ul>

    <div class="table-responsive shadow-sm mb-4">
        <table class="table align-middle text-center mb-0">
            <thead class="table-light">
                <tr class="text-muted" style="font-size: 0.7rem;">
                    <th class="text-start ps-4 sticky-col">MEMBRE STAFF</th>
                    <?php foreach ($all_formations as $f): ?>
                        <th><?= mb_strtoupper(htmlspecialchars($f['titre'])) ?></th>
                    <?php endforeach; ?>
                    <?php if ($can_edit): ?><th>ACTIONS</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matrix as $pseudo => $data): ?>
                    <tr class="member-row" data-search-pseudo="<?= strtolower($pseudo) ?>" data-search-discord="<?= $data['discord_id'] ?>">
                        <td class="text-start ps-4 sticky-col fw-bold">
                            <div><?= htmlspecialchars($pseudo) ?></div>
                            <small class="text-muted fw-normal" style="font-size: 0.65rem;"><?= $data['discord_id'] ?></small>
                        </td>
                        <?php foreach ($all_formations as $f): 
                            $isValid = in_array($f['id'], $data['validations']);
                        ?>
                            <td>
                                <?php if ($can_edit): ?>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="pseudo" value="<?= htmlspecialchars($pseudo) ?>">
                                        <input type="hidden" name="formation_id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="current_tab" value="<?= $active_tab ?>">
                                        <input type="hidden" name="current_page" value="<?= $current_page_num ?>">
                                        <input type="hidden" name="scroll_pos" class="scroll_input">
                                        <input type="hidden" name="toggle_formation" value="1">
                                        <button type="submit" class="badge-toggle <?= $isValid ? 'btn-valid' : 'btn-empty' ?>">
                                            <span><?= $isValid ? 'Valide' : 'Néant' ?></span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge-toggle <?= $isValid ? 'btn-valid' : 'btn-empty' ?>">
                                        <span><?= $isValid ? 'Valide' : 'Néant' ?></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        
                        <?php if ($can_edit): ?>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="pseudo" value="<?= htmlspecialchars($pseudo) ?>">
                                    <input type="hidden" name="current_tab" value="<?= $active_tab ?>">
                                    <input type="hidden" name="current_page" value="<?= $current_page_num ?>">
                                    <input type="hidden" name="scroll_pos" class="scroll_input">
                                    <input type="hidden" name="update_role_trigger" value="1">
                                    <input type="hidden" name="new_role" value="<?= ($active_tab === 'tab-sup') ? 'Modérateur' : 'Support' ?>">
                                    <button type="submit" class="btn btn-sm btn-light border" title="Changer de Grade"><i class="bi bi-arrow-left-right text-primary"></i></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Supprimer ce membre du staff ?');">
                                    <input type="hidden" name="pseudo" value="<?= htmlspecialchars($pseudo) ?>">
                                    <input type="hidden" name="current_tab" value="<?= $active_tab ?>">
                                    <input type="hidden" name="current_page" value="<?= $current_page_num ?>">
                                    <button type="submit" name="delete_member" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav><ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $current_page_num ? 'active' : '' ?>">
                    <a class="page-link" href="?tab=<?= $active_tab ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
</div>

<?php if ($can_edit): ?>
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Ajouter au Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="current_tab" value="<?= $active_tab ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">Pseudo</label>
                    <input type="text" name="new_pseudo" class="form-control" placeholder="ex: JohnDoe" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ID Discord</label>
                    <input type="text" name="new_discord_id" class="form-control" placeholder="ex: 1234567890" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Grade Initial</label>
                    <select name="new_role" class="form-select">
                        <option value="Support" <?= $active_tab == 'tab-sup' ? 'selected' : '' ?>>Support</option>
                        <option value="Modérateur" <?= $active_tab == 'tab-mod' ? 'selected' : '' ?>>Modérateur</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_new_member" class="btn btn-primary w-100">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    // Gestion du scroll après rechargement
    window.addEventListener('load', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const scrollTarget = parseInt(urlParams.get('scroll')) || 0;
        if (scrollTarget > 0) window.scrollTo(0, scrollTarget);
    });

    // Capture de la position du scroll
    document.querySelectorAll('.action-form').forEach(form => {
        form.addEventListener('submit', function() {
            let input = this.querySelector('.scroll_input');
            if(input) input.value = Math.floor(window.scrollY);
        });
    });

    // Recherche en temps réel
    document.getElementById('searchInput').addEventListener('input', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.member-row').forEach(row => {
            let pseudo = row.getAttribute('data-search-pseudo');
            let discord = row.getAttribute('data-search-discord');
            row.style.display = (pseudo.includes(val) || discord.includes(val)) ? '' : 'none';
        });
    });
</script>

<?php require 'includes/footer.php'; ob_end_flush(); ?>
