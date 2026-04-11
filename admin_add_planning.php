<?php
require 'config.php';
require 'includes/header.php';

/* =========================		
    CONFIGURATION & LOGS
========================= */
$webhook = $_ENV['DISCORD_WEBHOOK_URL_PLANNING'] ?? getenv('DISCORD_WEBHOOK_URL_PLANNING');
define('DISCORD_WEBHOOK_URL', $webhook);

function addLog($pdo, $action) {
    $user = $_SESSION['user']['username'] ?? $_SESSION['username'] ?? 'Anonyme';
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, NOW())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur Log : " . $e->getMessage());
    }
}

function syncDiscordPlanning($pdo) {
    $dateObj = new DateTime();
    $dateObj->modify('monday this week');
    $monday = $dateObj->format('Y-m-d');
    $sunday = (clone $dateObj)->modify('+6 days')->format('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT p.date, p.heure, p.formateur, f.titre 
        FROM planning p 
        JOIN formations f ON p.formation_id = f.id 
        WHERE p.date BETWEEN ? AND ? 
        ORDER BY p.date ASC, p.heure ASC
    ");
    $stmt->execute([$monday, $sunday]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $calendar = [];
    foreach ($sessions as $s) { $calendar[$s['date']][] = $s; }

    $joursFr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];

    $fields = [];
    for ($i = 0; $i < 7; $i++) {
        $currentLoopDate = (clone $dateObj)->modify("+$i days");
        $dateStr = $currentLoopDate->format('Y-m-d');
        $dayLabel = $joursFr[$currentLoopDate->format('l')] . " " . $currentLoopDate->format('d/m');

        $text = "";
        if (isset($calendar[$dateStr])) {
            foreach ($calendar[$dateStr] as $sess) {
                $text .= "🕒 `".substr($sess['heure'], 0, 5)."` **{$sess['titre']}**\n└ 👤 _{$sess['formateur']}_\n";
            }
        } else {
            $text = "*Aucune session prévue*";
        }
        $fields[] = ["name" => "─── {$dayLabel} ───", "value" => $text, "inline" => false];
    }

    $payload = [
        "username" => "Planning Live",
        "embeds" => [[
            "title" => "📅 Emploi du Temps Hebdomadaire",
            "description" => "Semaine du **" . date('d/m', strtotime($monday)) . "** au **" . date('d/m', strtotime($sunday)) . "**\n━━━━━━━━━━━━━━━━━━━━━━━━",
            "color" => hexdec("5865F2"),
            "fields" => $fields,
            "footer" => ["text" => "Dernière mise à jour : " . date('H:i')],
            "thumbnail" => ["url" => "https://cdn-icons-png.flaticon.com/512/3652/3652191.png"]
        ]]
    ];

    $log = $pdo->query("SELECT message_id FROM discord_logs WHERE id = 1")->fetch();
    $messageId = $log['message_id'] ?? null;
    $url = DISCORD_WEBHOOK_URL . ($messageId ? "/messages/" . $messageId : "?wait=true");
    $method = $messageId ? "PATCH" : "POST";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (!$messageId && isset($data['id'])) {
        $pdo->prepare("UPDATE discord_logs SET message_id = ? WHERE id = 1")->execute([$data['id']]);
    }
}

/* =========================
    LOGIQUE DE NAVIGATION & TRADUCTION
========================= */
$view = $_GET['view'] ?? 'week';
$dateParam = $_GET['date'] ?? date('Y-m-d');
$filterFormation = $_GET['filter_formation'] ?? '';

$moisFr = [
    'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars', 'April' => 'Avril',
    'May' => 'Mai', 'June' => 'Juin', 'July' => 'Juillet', 'August' => 'Août',
    'September' => 'Septembre', 'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
];

$moisCourtFr = [
    'Jan' => 'janv.', 'Feb' => 'févr.', 'Mar' => 'mars', 'Apr' => 'avril',
    'May' => 'mai', 'Jun' => 'juin', 'Jul' => 'juil.', 'Aug' => 'août',
    'Sep' => 'sept.', 'Oct' => 'oct.', 'Nov' => 'nov.', 'Dec' => 'déc.'
];

try {
    $dateRef = new DateTime($dateParam);
} catch (Exception $e) {
    $dateRef = new DateTime();
}

if ($view === 'month') {
    $dateRef->modify('first day of this month');
    $startNav = (clone $dateRef)->modify('monday this week')->format('Y-m-d');
    $endNav = (clone $dateRef)->modify('last day of this month')->modify('sunday this week')->format('Y-m-d');
    $prevDate = (clone $dateRef)->modify('-1 month')->format('Y-m-d');
    $nextDate = (clone $dateRef)->modify('+1 month')->format('Y-m-d');
    
    $nomMois = $moisFr[$dateRef->format('F')];
    $label = "Mois de " . $nomMois . " " . $dateRef->format('Y');
} else {
    $dateRef->modify('monday this week');
    $startNav = $dateRef->format('Y-m-d');
    $endNav = (clone $dateRef)->modify('+6 days')->format('Y-m-d');
    $prevDate = (clone $dateRef)->modify('-7 days')->format('Y-m-d');
    $nextDate = (clone $dateRef)->modify('+7 days')->format('Y-m-d');
    $label = "Semaine du " . date('d/m', strtotime($startNav)) . " au " . date('d/m', strtotime($endNav));
}

/* =========================
    GESTION DES ACTIONS (POST)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) || isset($_POST['edit'])) {
        $form_id = $_POST['formation_id'];
        $date = $_POST['date'];
        $heure = $_POST['heure'];
        $formateur = $_POST['formateur'];
        $id_existant = $_POST['id'] ?? 0;

        $stmt = $pdo->prepare("SELECT id, heure FROM planning WHERE date = ? AND id != ?");
        $stmt->execute([$date, $id_existant]);
        $existing = $stmt->fetchAll();
        $conflict = false;
        foreach ($existing as $ex) {
            if (abs(strtotime($heure) - strtotime($ex['heure'])) / 60 < 30) { $conflict = true; break; }
        }

        if ($conflict) {
            $_SESSION['flash'] = ["danger", "⚠️ Conflit horaire (moins de 30 min d'écart)."];
        } else {
            if (isset($_POST['add'])) {
                $pdo->prepare("INSERT INTO planning (formation_id, date, heure, formateur) VALUES (?, ?, ?, ?)")
                    ->execute([$form_id, $date, $heure, $formateur]);
                addLog($pdo, "Ajout session : Module $form_id le $date");
            } else {
                $pdo->prepare("UPDATE planning SET formation_id=?, date=?, heure=?, formateur=? WHERE id=?")
                    ->execute([$form_id, $date, $heure, $formateur, $id_existant]);
                addLog($pdo, "Modif session ID #$id_existant");
            }
            syncDiscordPlanning($pdo);
            $_SESSION['flash'] = ["success", "✨ Planning mis à jour avec succès !"];
        }
    }

    if (isset($_POST['delete'])) {
        $id_to_delete = $_POST['id'];
        $pdo->prepare("DELETE FROM planning WHERE id=?")->execute([$id_to_delete]);
        addLog($pdo, "Suppression session ID #$id_to_delete");
        syncDiscordPlanning($pdo);
        $_SESSION['flash'] = ["warning", "🗑️ Session supprimée."];
    }

    header("Location: admin_add_planning.php?view=$view&date=$dateParam&filter_formation=$filterFormation"); 
    exit();
}

/* =========================
    RÉCUPÉRATION DONNÉES
========================= */
$formations = $pdo->query("SELECT * FROM formations ORDER BY titre ASC")->fetchAll();
$formateurs_list = $pdo->query("SELECT pseudo FROM formateurs ORDER BY pseudo ASC")->fetchAll();

$sql = "SELECT p.*, f.titre as formation_titre, u.avatar 
        FROM planning p 
        LEFT JOIN formations f ON f.id = p.formation_id 
        LEFT JOIN formateurs fm ON p.formateur = fm.pseudo
        LEFT JOIN users u ON fm.discord_id = u.discord_id
        WHERE p.date BETWEEN ? AND ?";

$params = [$startNav, $endNav];
if (!empty($filterFormation)) {
    $sql .= " AND f.id = ?";
    $params[] = $filterFormation;
}
$sql .= " ORDER BY p.date, p.heure";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$planning = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null; 
unset($_SESSION['flash']);
?>

<style>
    :root { --accent-color: #6366f1; }
    body { background-color: var(--body-bg); }
    .glass-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .table thead th { background: var(--table-header); color: var(--text-main); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border: none; }
    .avatar-table { width: 35px; height: 35px; border-radius: 10px; object-fit: cover; border: 2px solid white; }
    .btn-action { width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; transition: all 0.2s; border: none; }
    .btn-edit { background: rgba(251, 191, 36, 0.1); color: #fbbf24; }
    .btn-delete { background: rgba(248, 113, 113, 0.1); color: #f87171; }
    .btn-edit:hover { background: #fbbf24; color: white; }
    .btn-delete:hover { background: #f87171; color: white; }
    .nav-pill-custom { background: var(--card-bg); padding: 5px; border-radius: 12px; border: 1px solid var(--border-color); }
    .nav-pill-custom .btn { border: none; border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: var(--text-main); }
    .nav-pill-custom .active { background: var(--accent-color); color: white !important; }
</style>

<div class="py-5">
    <div class="container">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-1">📅 Gestionnaire Planning</h2>
                <p class="text-muted mb-0"><?= $label ?></p>
            </div>
            <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                <button class="btn btn-primary px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal" style="border-radius: 12px;">
                    <i class="bi bi-plus-lg me-2"></i>Nouvelle Session
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <div class="nav-pill-custom shadow-sm d-flex gap-1">
                    <a href="?view=week&date=<?= $dateParam ?>&filter_formation=<?= $filterFormation ?>" class="btn <?= $view == 'week' ? 'active' : '' ?>">Semaine</a>
                    <a href="?view=month&date=<?= $dateParam ?>&filter_formation=<?= $filterFormation ?>" class="btn <?= $view == 'month' ? 'active' : '' ?>">Mois</a>
                </div>
                
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="view" value="<?= $view ?>">
                    <input type="hidden" name="date" value="<?= $dateParam ?>">
                    <select name="filter_formation" class="form-select filter-select shadow-sm" onchange="this.form.submit()">
                        <option value="">📚 Toutes les formations</option>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filterFormation == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="btn-group shadow-sm">
                <a href="?view=<?= $view ?>&date=<?= $prevDate ?>&filter_formation=<?= $filterFormation ?>" class="btn btn-light border"><i class="bi bi-chevron-left"></i></a>
                <a href="?view=<?= $view ?>&date=<?= date('Y-m-d') ?>&filter_formation=<?= $filterFormation ?>" class="btn btn-light border fw-bold px-3">Aujourd'hui</a>
                <a href="?view=<?= $view ?>&date=<?= $nextDate ?>&filter_formation=<?= $filterFormation ?>" class="btn btn-light border"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash[0] ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                <?= $flash[1] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="glass-card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Formation</th>
                            <th>Date & Heure</th>
                            <th>Formateur</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planning)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Aucune session trouvée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($planning as $p): 
                                $avatar = !empty($p['avatar']) ? $p['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($p['formateur']).'&background=6366f1&color=fff';
                                $mois_label = $moisCourtFr[date('M', strtotime($p['date']))];
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($p['formation_titre']) ?></div>
                                    <div class="text-muted small">ID #<?= $p['id'] ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="p-2 bg-primary bg-opacity-10 rounded-3 me-3 text-center" style="min-width: 50px;">
                                            <div class="small fw-bold text-primary"><?= date('d', strtotime($p['date'])) ?></div>
                                            <div class="text-uppercase text-primary" style="font-size: 0.6rem;"><?= $mois_label ?></div>
                                        </div>
                                        <span class="badge bg-dark text-white px-3 py-2 rounded-pill fw-bold">
                                            <i class="bi bi-clock me-1"></i><?= substr($p['heure'], 0, 5) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $avatar ?>" class="avatar-table me-2">
                                        <span class="fw-semibold"><?= htmlspecialchars($p['formateur']) ?></span>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn-action btn-edit me-1" onclick='openEditModal(<?= json_encode($p) ?>)'><i class="bi bi-pencil-fill"></i></button>
                                    <button class="btn-action btn-delete" onclick='openDeleteModal(<?= $p['id'] ?>, "<?= addslashes($p['formation_titre']) ?>")'><i class="bi bi-trash3-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">✨ Nouvelle Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Module de formation</label>
                    <select name="formation_id" class="form-select" required>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">Heure</label>
                        <input type="time" name="heure" class="form-control" required>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold">Formateur</label>
                    <select name="formateur" class="form-select" required>
                        <?php foreach ($formateurs_list as $f): ?>
                            <option value="<?= htmlspecialchars($f['pseudo']) ?>"><?= htmlspecialchars($f['pseudo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="add" class="btn btn-primary w-100 py-2 fw-bold rounded-3">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">✏️ Modifier Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Module</label>
                    <select name="formation_id" id="edit_formation" class="form-select" required>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="date" id="edit_date" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">Heure</label>
                        <input type="time" name="heure" id="edit_heure" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Formateur</label>
                    <select name="formateur" id="edit_formateur" class="form-select" required>
                        <?php foreach ($formateurs_list as $f): ?>
                            <option value="<?= htmlspecialchars($f['pseudo']) ?>"><?= htmlspecialchars($f['pseudo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="edit" class="btn btn-warning w-100 py-2 fw-bold rounded-3">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <input type="hidden" name="id" id="delete_id">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3"><i class="bi bi-trash3-fill fs-1"></i></div>
                <h5 class="fw-bold">Supprimer ?</h5>
                <p id="delete_text" class="text-muted small"></p>
                <div class="row g-2 mt-2">
                    <div class="col-6"><button type="button" class="btn btn-light w-100 rounded-3" data-bs-dismiss="modal">Non</button></div>
                    <div class="col-6"><button type="submit" name="delete" class="btn btn-danger w-100 rounded-3">Oui</button></div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    var bsEditModal, bsDeleteModal;
    document.addEventListener('DOMContentLoaded', function() {
        // Initialisation des objets Modal de Bootstrap
        bsEditModal = new bootstrap.Modal(document.getElementById('editModal'));
        bsDeleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    });

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_formation').value = data.formation_id;
        document.getElementById('edit_date').value = data.date;
        document.getElementById('edit_heure').value = data.heure;
        document.getElementById('edit_formateur').value = data.formateur;
        bsEditModal.show();
    }

    function openDeleteModal(id, titre) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_text').innerText = "Voulez-vous vraiment supprimer la session : " + titre + " ?";
        bsDeleteModal.show();
    }
</script>

<?php require 'includes/footer.php'; ?>