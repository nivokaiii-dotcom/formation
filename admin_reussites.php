<?php
ob_start();
require 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================
    FONCTION DE LOGS
================================== */
function addLog($pdo, $action) {
    $user = 'Anonyme';
    if (isset($_SESSION['user']['username'])) {
        $user = $_SESSION['user']['username'];
    } elseif (isset($_SESSION['username'])) {
        $user = $_SESSION['username'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, NOW())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur Log : " . $e->getMessage());
    }
}

// Définition de la permission
$can_edit = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== 'user';

/* ==================================
    1. ACTIONS (TRAITEMENT)
================================== */

function redirectWithState($tab, $page, $scroll, $search = '') {
    $search_param = !empty($search) ? "&search=" . urlencode($search) : "";
    header("Location: admin_reussites.php?tab=$tab&page=$page&scroll=$scroll" . $search_param);
    exit();
}

if ($can_edit) {
    // AJOUTER UN MEMBRE
    if (isset($_POST['add_new_member'])) {
        $pseudo = trim($_POST['new_pseudo']);
        $discord_id = trim($_POST['new_discord_id']);
        $role = $_POST['new_role'];
        if (!empty($pseudo) && !empty($discord_id)) {
            $stmt = $pdo->prepare("INSERT INTO membres_formes (discord_id, pseudo, role_obtenu, formation_id, date_reussite) VALUES (?, ?, ?, NULL, CURDATE())");
            $stmt->execute([$discord_id, $pseudo, $role]);
            addLog($pdo, "Réussites : Création du profil staff pour '$pseudo' ($role)");
        }
        redirectWithState($_POST['current_tab'], 1, 0);
    }

    // SUPPRIMER UN MEMBRE
    if (isset($_POST['delete_member'])) {
        $pseudo = $_POST['pseudo'];
        $stmt = $pdo->prepare("DELETE FROM membres_formes WHERE pseudo = ?");
        $stmt->execute([$pseudo]);
        addLog($pdo, "Réussites : Suppression complète du profil de '$pseudo'");
        redirectWithState($_POST['current_tab'], $_POST['current_page'], 0, $_POST['search_query'] ?? '');
    }

    // TOGGLE FORMATION
    if (isset($_POST['toggle_formation'])) {
        $pseudo = $_POST['pseudo'];
        $formation_id = $_POST['formation_id'];
        $formateur = $_SESSION['user']['username'] ?? 'Admin';
        
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
            $stmtInfo = $pdo->prepare("SELECT discord_id, role_obtenu FROM membres_formes WHERE pseudo = ? LIMIT 1");
            $stmtInfo->execute([$pseudo]);
            $info = $stmtInfo->fetch();

            if ($info) {
                $pdo->prepare("INSERT INTO membres_formes (discord_id, pseudo, role_obtenu, formation_id, date_reussite, formateur_nom) VALUES (?, ?, ?, ?, CURDATE(), ?)")
                    ->execute([$info['discord_id'], $pseudo, $info['role_obtenu'], $formation_id, $formateur]);
                addLog($pdo, "Réussites : Validation formation '$fTitre' pour $pseudo");
            }
        }
        redirectWithState($_POST['current_tab'], $_POST['current_page'], $_POST['scroll_pos'], $_POST['search_query'] ?? '');
    }

    // CHANGER LE GRADE
    if (isset($_POST['update_role_trigger'])) {
        $new_role = $_POST['new_role'];
        $pseudo = $_POST['pseudo'];
        $stmt = $pdo->prepare("UPDATE membres_formes SET role_obtenu = ? WHERE pseudo = ?");
        $stmt->execute([$new_role, $pseudo]);
        addLog($pdo, "Réussites : Changement de grade pour $pseudo vers $new_role");
        redirectWithState($_POST['current_tab'], $_POST['current_page'], $_POST['scroll_pos'], $_POST['search_query'] ?? '');
    }
}

require 'includes/header.php';

/* ==================================
    2. RÉCUPÉRATION ET PAGINATION
================================== */
$active_tab = $_GET['tab'] ?? 'tab-mod';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; 
$offset = ($current_page_num - 1) * $limit;

$all_formations = $pdo->query("SELECT * FROM formations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$role_filter = ($active_tab == 'tab-mod') ? 'Modérateur' : 'Support';

// Construction de la requête SQL Dynamique
$sql_count = "SELECT COUNT(DISTINCT pseudo) FROM membres_formes WHERE 1=1";
$sql_users = "SELECT DISTINCT pseudo, discord_id, role_obtenu FROM membres_formes WHERE 1=1";
$params = [];

if (!empty($search_query)) {
    // Si recherche : on ignore le filtre de grade pour chercher "partout"
    $search_condition = " AND (pseudo LIKE ? OR discord_id LIKE ?)";
    $sql_count .= $search_condition;
    $sql_users .= $search_condition;
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
} else {
    // Sinon : on filtre par le grade de l'onglet actif
    $sql_count .= " AND role_obtenu = ?";
    $sql_users .= " AND role_obtenu = ?";
    $params[] = $role_filter;
}

// Pagination
$countStmt = $pdo->prepare($sql_count);
$countStmt->execute($params);
$total_members = $countStmt->fetchColumn();
$total_pages = ceil($total_members / $limit);

$sql_users .= " ORDER BY pseudo ASC LIMIT $limit OFFSET $offset";
$pseudoStmt = $pdo->prepare($sql_users);
$pseudoStmt->execute($params);
$pseudos_data = $pseudoStmt->fetchAll(PDO::FETCH_ASSOC);

// Construction de la matrice
$matrix = [];
if (!empty($pseudos_data)) {
    $pseudos_only = array_column($pseudos_data, 'pseudo');
    $in = str_repeat('?,', count($pseudos_only) - 1) . '?';
    $dataStmt = $pdo->prepare("SELECT * FROM membres_formes WHERE pseudo IN ($in)");
    $dataStmt->execute($pseudos_only);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pseudos_data as $p_row) {
        $matrix[$p_row['pseudo']] = [
            'discord_id' => $p_row['discord_id'], 
            'role' => $p_row['role_obtenu'],
            'validations' => []
        ];
    }
    foreach ($rows as $row) {
        if ($row['formation_id']) { $matrix[$row['pseudo']]['validations'][] = $row['formation_id']; }
    }
}

// Recap Stats (basé sur le rôle de l'onglet, sauf si recherche)
$stats_recap = [];
$stat_role = !empty($search_query) ? null : $role_filter;
foreach ($all_formations as $f) {
    if ($stat_role) {
        $st = $pdo->prepare("SELECT COUNT(DISTINCT pseudo) FROM membres_formes WHERE formation_id = ? AND role_obtenu = ?");
        $st->execute([$f['id'], $stat_role]);
    } else {
        $st = $pdo->prepare("SELECT COUNT(DISTINCT pseudo) FROM membres_formes WHERE formation_id = ?");
        $st->execute([$f['id']]);
    }
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
        <div class="col-12"><h5 class="fw-bold mb-0">📊 Statistiques <?= !empty($search_query) ? '(Globales)' : $role_filter.'s' ?></h5></div>
        <?php foreach ($all_formations as $f): 
            $count = $stats_recap[$f['id']] ?? 0;
            $divisor = !empty($search_query) ? $total_members : $total_members; // Simplifié ici
            $pct = ($total_members > 0) ? ($count / $total_members) * 100 : 0;
        ?>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card shadow-sm p-3">
                <div class="text-muted small text-truncate fw-bold"><?= mb_strtoupper($f['titre']) ?></div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="fw-bold h5 mb-0"><?= $count ?></span>
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
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="<?= $active_tab ?>">
                <input type="text" name="search" class="form-control border-0 shadow-sm" style="width: 300px;" placeholder="Rechercher partout..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <?php if(!empty($search_query)): ?>
                    <a href="?tab=<?= $active_tab ?>" class="btn btn-light border"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
            <?php if ($can_edit): ?>
                <button class="btn btn-success shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="bi bi-person-plus-fill me-2"></i>Nouveau
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if(empty($search_query)): ?>
    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item"><a href="?tab=tab-mod&page=1" class="nav-link <?= $active_tab == 'tab-mod' ? 'active' : '' ?>">Modérateurs</a></li>
        <li class="nav-item"><a href="?tab=tab-sup&page=1" class="nav-link <?= $active_tab == 'tab-sup' ? 'active' : '' ?>">Supports</a></li>
    </ul>
    <?php else: ?>
        <div class="alert alert-info py-2 shadow-sm"><i class="bi bi-info-circle me-2"></i> Résultats pour "<?= htmlspecialchars($search_query) ?>" dans tout le staff.</div>
    <?php endif; ?>

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
                <?php if(empty($matrix)): ?>
                    <tr><td colspan="<?= count($all_formations) + 2 ?>" class="py-5 text-muted">Aucun résultat trouvé.</td></tr>
                <?php endif; ?>
                <?php foreach ($matrix as $pseudo => $data): ?>
                    <tr class="member-row">
                        <td class="text-start ps-4 sticky-col fw-bold">
                            <div><?= htmlspecialchars($pseudo) ?></div>
                            <small class="badge bg-secondary-soft text-muted fw-normal" style="font-size: 0.6rem;"><?= $data['role'] ?> | <?= $data['discord_id'] ?></small>
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
                                        <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_query) ?>">
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
                                    <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_query) ?>">
                                    <input type="hidden" name="scroll_pos" class="scroll_input">
                                    <input type="hidden" name="update_role_trigger" value="1">
                                    <input type="hidden" name="new_role" value="<?= ($data['role'] === 'Support') ? 'Modérateur' : 'Support' ?>">
                                    <button type="submit" class="btn btn-sm btn-light border" title="Changer de Grade"><i class="bi bi-arrow-left-right text-primary"></i></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Supprimer ce membre ?');">
                                    <input type="hidden" name="pseudo" value="<?= htmlspecialchars($pseudo) ?>">
                                    <input type="hidden" name="current_tab" value="<?= $active_tab ?>">
                                    <input type="hidden" name="current_page" value="<?= $current_page_num ?>">
                                    <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_query) ?>">
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
                    <a class="page-link" href="?tab=<?= $active_tab ?>&page=<?= $i ?>&search=<?= urlencode($search_query) ?>"><?= $i ?></a>
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
    // Scroll restoration
    window.addEventListener('load', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const scrollTarget = parseInt(urlParams.get('scroll')) || 0;
        if (scrollTarget > 0) window.scrollTo(0, scrollTarget);
    });

    document.querySelectorAll('.action-form').forEach(form => {
        form.addEventListener('submit', function() {
            let input = this.querySelector('.scroll_input');
            if(input) input.value = Math.floor(window.scrollY);
        });
    });
</script>

<?php require 'includes/footer.php'; ob_end_flush(); ?>
