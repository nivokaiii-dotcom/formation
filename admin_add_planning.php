<?php
require 'config.php';
require 'includes/header.php';

/* =========================
    CONFIGURATION DISCORD
========================= */
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/1471797709320224889/2aMqQOguDj5Y163sghyyvHThxo3eX_9NGg4kGd_OF7o_54jce1F1s8PwCiQ1nXhzF_dv');

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

        $fields[] = [
            "name" => "─── {$dayLabel} ───",
            "value" => $text,
            "inline" => false
        ];
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

    $url = DISCORD_WEBHOOK_URL . "?wait=true";
    $method = "POST";

    if ($messageId) {
        $url = DISCORD_WEBHOOK_URL . "/messages/" . $messageId;
        $method = "PATCH";
    }

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
    GESTION POST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO planning (formation_id, date, heure, formateur) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['formation_id'], $_POST['date'], $_POST['heure'], $_POST['formateur']]);
        syncDiscordPlanning($pdo);
        $_SESSION['flash'] = ["success", "Session ajoutée avec succès !"];
        header("Location: admin_add_planning.php"); exit();
    }
    if (isset($_POST['edit'])) {
        $stmt = $pdo->prepare("UPDATE planning SET formation_id=?, date=?, heure=?, formateur=? WHERE id=?");
        $stmt->execute([$_POST['formation_id'], $_POST['date'], $_POST['heure'], $_POST['formateur'], $_POST['id']]);
        syncDiscordPlanning($pdo);
        $_SESSION['flash'] = ["info", "Session mise à jour !"];
        header("Location: admin_add_planning.php"); exit();
    }
    if (isset($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM planning WHERE id=?");
        $stmt->execute([$_POST['id']]);
        syncDiscordPlanning($pdo);
        $_SESSION['flash'] = ["danger", "Session supprimée !"];
        header("Location: admin_add_planning.php"); exit();
    }
}

/* =========================
    REQUÊTES AFFICHAGE
========================= */
$formations = $pdo->query("SELECT * FROM formations ORDER BY titre ASC")->fetchAll(PDO::FETCH_ASSOC);
$formateurs = $pdo->query("SELECT f.pseudo, u.avatar FROM formateurs f LEFT JOIN users u ON f.discord_id = u.discord_id ORDER BY f.pseudo ASC")->fetchAll(PDO::FETCH_ASSOC);

$planning = $pdo->query("
    SELECT p.*, f.titre AS formation_titre, u.avatar
    FROM planning p
    LEFT JOIN formations f ON f.id = p.formation_id
    LEFT JOIN formateurs fm ON p.formateur = fm.pseudo
    LEFT JOIN users u ON fm.discord_id = u.discord_id
    WHERE YEARWEEK(p.date, 1) = YEARWEEK(CURDATE(), 1)
    ORDER BY p.date ASC, p.heure ASC
")->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<style>
    /* Correction pour le footer en bas */
    html, body {
        height: 100%;
        margin: 0;
    }
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    .main-content {
        flex: 1 0 auto;
    }

    .avatar-table { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color); }
    .table-container { 
        background: var(--card-bg); 
        color: var(--text-main);
        border-radius: 15px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        padding: 20px; 
    }
    [data-bs-theme="dark"] .table { color: #f1f5f9; }
    [data-bs-theme="dark"] .text-dark { color: #fff !important; }
    [data-bs-theme="dark"] .modal-content { background-color: var(--card-bg); border: 1px solid var(--border-color); }
    .btn-add { border-radius: 10px; font-weight: 600; padding: 10px 20px; }
</style>

<div class="main-content">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">⚙️ Gestion du Planning</h2>
                <p class="text-muted">Semaine du <?= date('d/m', strtotime('monday this week')) ?> au <?= date('d/m', strtotime('sunday this week')) ?></p>
            </div>
            <button class="btn btn-success btn-add shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle me-2"></i> Nouvelle Session
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash[0] ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
                <?= $flash[1] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-container shadow-sm mb-5">
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
                            <tr><td colspan="4" class="text-center py-4 text-muted">Aucune session cette semaine.</td></tr>
                        <?php else: ?>
                            <?php foreach ($planning as $p): 
                                $avatarUrl = !empty($p['avatar']) ? $p['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($p['formateur']??'').'&background=random';
                            ?>
                            <tr>
                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($p['formation_titre'] ?? 'N/A') ?></span></td>
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
        <form method="POST" class="modal-content shadow">
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
                        <?php foreach ($formateurs as $f): ?>
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
        <form method="POST" class="modal-content shadow">
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
                        <?php foreach ($formateurs as $f): ?>
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
        <form method="POST" class="modal-content border-0 shadow">
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
        const editModalEl = document.getElementById('editModal');
        const deleteModalEl = document.getElementById('deleteModal');
        
        window.editModalObj = new bootstrap.Modal(editModalEl);
        window.deleteModalObj = new bootstrap.Modal(deleteModalEl);
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