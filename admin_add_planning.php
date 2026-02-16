<?php
require 'config.php';
require 'includes/header.php';

/* =========================
    CONFIGURATION & LOGS
========================= */
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/1471797709320224889/2aMqQOguDj5Y163sghyyvHThxo3eX_9NGg4kGd_OF7o_54jce1F1s8PwCiQ1nXhzF_dv');

// Fonction pour ajouter un log dans la table logs
function addLog($pdo, $action) {
    // On essaie de récupérer le pseudo dans l'ordre de priorité des clés de session courantes
    $user = 'Anonyme';
    if (isset($_SESSION['user']['username'])) {
        $user = $_SESSION['user']['username'];
    } elseif (isset($_SESSION['username'])) {
        $user = $_SESSION['username'];
    } elseif (isset($_SESSION['user_pseudo'])) {
        $user = $_SESSION['user_pseudo'];
    } elseif (isset($_SESSION['pseudo'])) {
        $user = $_SESSION['pseudo'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, NOW())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        // Optionnel : logger l'erreur SQL dans un fichier pour le debug
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
    LOGIQUE DE NAVIGATION
========================= */
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$dateNav = new DateTime();
$dateNav->modify('monday this week');
if ($weekOffset !== 0) { $dateNav->modify("$weekOffset weeks"); }
$mondayNav = $dateNav->format('Y-m-d');
$sundayNav = (clone $dateNav)->modify('+6 days')->format('Y-m-d');

/* =========================
    GESTION POST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) || isset($_POST['edit'])) {
        $form_id = $_POST['formation_id'];
        $date = $_POST['date'];
        $heure = $_POST['heure'];
        $formateur = $_POST['formateur'];
        $id_existant = $_POST['id'] ?? 0;

        $stmtF = $pdo->prepare("SELECT titre FROM formations WHERE id = ?");
        $stmtF->execute([$form_id]);
        $formation_nom = $stmtF->fetchColumn();

        $stmt = $pdo->prepare("SELECT heure FROM planning WHERE date = ? AND id != ?");
        $stmt->execute([$date, $id_existant]);
        $existing_sessions = $stmt->fetchAll();
        
        $conflict = false;
        $new_time = strtotime($heure);
        foreach ($existing_sessions as $sess) {
            $sess_time = strtotime($sess['heure']);
            $diff = abs($new_time - $sess_time) / 60;
            if ($diff < 30) { $conflict = true; break; }
        }

        if ($conflict) {
            $_SESSION['flash'] = ["danger", "Conflit : Une session existe déjà à moins de 30 min d'intervalle."];
        } else {
            if (isset($_POST['add'])) {
                $stmt = $pdo->prepare("INSERT INTO planning (formation_id, date, heure, formateur) VALUES (?, ?, ?, ?)");
                $stmt->execute([$form_id, $date, $heure, $formateur]);
                addLog($pdo, "Planning : Ajout session '$formation_nom' le $date à $heure");
                $_SESSION['flash'] = ["success", "Session ajoutée !"];
            } else {
                $stmt = $pdo->prepare("UPDATE planning SET formation_id=?, date=?, heure=?, formateur=? WHERE id=?");
                $stmt->execute([$form_id, $date, $heure, $formateur, $id_existant]);
                addLog($pdo, "Planning : Modification session ID #$id_existant ($formation_nom)");
                $_SESSION['flash'] = ["info", "Session mise à jour !"];
            }
            syncDiscordPlanning($pdo);
        }
        header("Location: admin_add_planning.php?week=$weekOffset"); exit();
    }

    if (isset($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM planning WHERE id=?");
        $stmt->execute([$_POST['id']]);
        addLog($pdo, "Planning : Suppression session ID #".$_POST['id']);
        syncDiscordPlanning($pdo);
        $_SESSION['flash'] = ["danger", "Session supprimée !"];
        header("Location: admin_add_planning.php?week=$weekOffset"); exit();
    }
}

/* =========================
    REQUÊTES AFFICHAGE
========================= */
$formations = $pdo->query("SELECT * FROM formations ORDER BY titre ASC")->fetchAll(PDO::FETCH_ASSOC);
$formateurs_list = $pdo->query("SELECT f.pseudo FROM formateurs f ORDER BY f.pseudo ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT p.*, f.titre AS formation_titre, u.avatar
    FROM planning p
    LEFT JOIN formations f ON f.id = p.formation_id
    LEFT JOIN formateurs fm ON p.formateur = fm.pseudo
    LEFT JOIN users u ON fm.discord_id = u.discord_id
    WHERE p.date BETWEEN ? AND ?
    ORDER BY p.date ASC, p.heure ASC
");
$stmt->execute([$mondayNav, $sundayNav]);
$planning = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<style>
    body { display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1 0 auto; }
    .avatar-table { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .table-container { background: var(--bs-card-bg); border-radius: 15px; padding: 20px; }
    .nav-week { background: var(--bs-card-bg); padding: 10px 20px; border-radius: 10px; display: inline-flex; align-items: center; gap: 15px; }
</style>

<div class="main-content">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-0">⚙️ Gestion du Planning</h2>
                <div class="mt-2 nav-week shadow-sm border">
                    <a href="?week=<?= $weekOffset - 1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                    <span class="fw-bold small">Semaine du <?= date('d/m', strtotime($mondayNav)) ?> au <?= date('d/m', strtotime($sundayNav)) ?></span>
                    <a href="?week=<?= $weekOffset + 1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                    <?php if($weekOffset !== 0): ?>
                        <a href="admin_add_planning.php" class="btn btn-sm btn-link text-decoration-none">Aujourd'hui</a>
                    <?php endif; ?>
                </div>
            </div>
            <button class="btn btn-success px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle me-2"></i> Nouvelle Session
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash[0] ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
                <?= $flash[1] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-container shadow-sm border">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Formation</th>
                            <th>Date & Heure</th>
                            <th>Formateur</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planning)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Aucune session cette semaine.</td></tr>
                        <?php else: ?>
                            <?php foreach ($planning as $p): 
                                $avatarUrl = !empty($p['avatar']) ? $p['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($p['formateur']??'').'&background=random';
                            ?>
                            <tr>
                                <td><span class="fw-bold"><?= htmlspecialchars($p['formation_titre'] ?? 'N/A') ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 me-2 text-primary"></i>
                                        <?= date('d/m/Y', strtotime($p['date'])) ?>
                                        <span class="badge bg-primary-subtle text-primary ms-2"><?= substr($p['heure'], 0, 5) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <img src="<?= $avatarUrl ?>" class="avatar-table me-2">
                                    <span class="small fw-semibold"><?= htmlspecialchars($p['formateur'] ?? 'Inconnu') ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-outline-warning btn-sm border-0" 
                                        onclick='openEditModal(<?= $p['id'] ?>, <?= $p['formation_id'] ?>, "<?= $p['date'] ?>", "<?= $p['heure'] ?>", "<?= htmlspecialchars($p['formateur'], ENT_QUOTES) ?>")'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm border-0" 
                                        onclick='openDeleteModal(<?= $p['id'] ?>, "<?= htmlspecialchars($p['formation_titre'], ENT_QUOTES) ?>", "<?= $p['date'] ?>")'>
                                        <i class="bi bi-trash3"></i>
                                    </button>
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
        <form method="POST" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Ajouter une session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Module</label>
                    <select name="formation_id" class="form-select" required>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row mb-3">
                    <div class="col-6"><label class="small fw-bold">Date</label><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-6"><label class="small fw-bold">Heure</label><input type="time" name="heure" class="form-control" required></div>
                </div>
                <div class="mb-0">
                    <label class="small fw-bold">Formateur</label>
                    <select name="formateur" class="form-select" required>
                        <?php foreach ($formateurs_list as $f): ?>
                            <option value="<?= htmlspecialchars($f['pseudo']) ?>"><?= htmlspecialchars($f['pseudo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" name="add" class="btn btn-success w-100">Enregistrer</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold">Modifier la session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Module</label>
                    <select name="formation_id" id="edit_formation" class="form-select" required>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row mb-3">
                    <div class="col-6"><label class="small fw-bold">Date</label><input type="date" name="date" id="edit_date" class="form-control" required></div>
                    <div class="col-6"><label class="small fw-bold">Heure</label><input type="time" name="heure" id="edit_heure" class="form-control" required></div>
                </div>
                <div>
                    <label class="small fw-bold">Formateur</label>
                    <select name="formateur" id="edit_formateur" class="form-select" required>
                        <?php foreach ($formateurs_list as $f): ?>
                            <option value="<?= htmlspecialchars($f['pseudo']) ?>"><?= htmlspecialchars($f['pseudo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" name="edit" class="btn btn-warning w-100">Mettre à jour</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form method="POST" class="modal-content border-0">
            <input type="hidden" name="id" id="delete_id">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle text-danger fs-1"></i>
                <h5 class="fw-bold mt-3">Supprimer ?</h5>
                <p id="delete_text" class="text-muted small"></p>
                <div class="d-flex gap-2 mt-4">
                    <button type="button" class="btn btn-light flex-grow-1" data-bs-dismiss="modal">Non</button>
                    <button type="submit" name="delete" class="btn btn-danger flex-grow-1">Oui</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.editModalObj = new bootstrap.Modal(document.getElementById('editModal'));
        window.deleteModalObj = new bootstrap.Modal(document.getElementById('deleteModal'));
    });

    function openEditModal(id, formationId, date, heure, formateur) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_formation').value = formationId;
        document.getElementById('edit_date').value = date;
        document.getElementById('edit_heure').value = heure;
        document.getElementById('edit_formateur').value = formateur;
        window.editModalObj.show();
    }

    function openDeleteModal(id, titre, date) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_text').innerText = titre + " le " + date;
        window.deleteModalObj.show();
    }
</script>

<?php require 'includes/footer.php'; ?>
