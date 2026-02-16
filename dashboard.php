<?php
ob_start(); 
require_once 'config.php';
require_once 'includes/header.php';

// Vérification admin
$isAdmin = (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');

// --- LOG SYSTEME ---
insertLog($pdo, "A consulté le tableau de bord");

$stats = ['total_formations' => 0, 'total_sessions' => 0, 'total_formateurs' => 0, 'total_reussites' => 0];
$formations_data = [];
$formateursData = [];
$historiqueGlobal = [];

try {
    /** 1. STATISTIQUES GÉNÉRALES */
    $statsQuery = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM formations) as total_formations,
        (SELECT COUNT(*) FROM planning) as total_sessions,
        (SELECT COUNT(*) FROM formateurs) as total_formateurs,
        (SELECT COUNT(*) FROM membres_formes) as total_reussites");
    if ($statsQuery) $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    /** 2. RÉPARTITION DU STAFF + LIENS (DOCS/QST) */
    $formations_data = $pdo->query("SELECT f.*, fr.pseudo as lead_nom, u.avatar as lead_avatar,
        (SELECT GROUP_CONCAT(CONCAT(IFNULL(fm.pseudo, 'Inconnu'), '|', IFNULL(us.avatar, '')) SEPARATOR ';;') 
         FROM formation_staff fs
         JOIN formateurs fm ON fs.formateur_id = fm.id 
         LEFT JOIN users us ON fm.discord_id = us.discord_id
         WHERE fs.formation_id = f.id) as staff_info
        FROM formations f 
        LEFT JOIN formateurs fr ON f.referent_id = fr.id
        LEFT JOIN users u ON fr.discord_id = u.discord_id
        ORDER BY f.titre ASC")->fetchAll(PDO::FETCH_ASSOC);

    /** 3. QUOTAS DU MOIS */
    $formateursData = $pdo->query("SELECT f.pseudo, u.avatar, COUNT(p.id) as total_sessions
        FROM formateurs f
        LEFT JOIN users u ON f.discord_id = u.discord_id
        LEFT JOIN planning p ON f.pseudo = p.formateur 
            AND MONTH(p.date) = MONTH(CURRENT_DATE) 
            AND YEAR(p.date) = YEAR(CURRENT_DATE)
        GROUP BY f.id, f.pseudo, u.avatar
        ORDER BY total_sessions DESC")->fetchAll(PDO::FETCH_ASSOC);

    /** 4. HISTORIQUE DES RÉUSSITES */
    $historiqueGlobal = $pdo->query("SELECT m.*, f.titre as formation_titre
        FROM membres_formes m
        LEFT JOIN formations f ON m.formation_id = f.id
        ORDER BY m.date_reussite DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger m-3'>Erreur SQL : " . $e->getMessage() . "</div>";
}

/** HELPER : Affichage Avatar sécurisé */
function displayAvatar($url, $sizeClass = 'avatar-sm') {
    $src = (!empty($url)) ? $url : 'https://ui-avatars.com/api/?name=Staff&background=4f46e5&color=fff';
    return '<img src="'.htmlspecialchars((string)$src).'" class="'.$sizeClass.'" alt="Avatar">';
}
?>

<style>
    .table-container { border-radius: 16px; background: var(--card-bg); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; border: 1px solid var(--border-color); }
    .lead-badge { background: var(--primary-accent); color: white; padding: 2px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; margin-bottom: 5px; }
    .avatar-sm { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color); }
    .avatar-md { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid var(--card-bg); box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 10px; }
    .staff-item { background: var(--table-header); color: var(--text-main); padding: 5px 10px; border-radius: 8px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; border: 1px solid var(--border-color); }
    .link-box { margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-color); }
    .btn-link-custom { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; width: 100%; margin-bottom: 5px; border-radius: 8px; }
    .progress { background-color: var(--border-color); border-radius: 10px; height: 8px; }
    .dynamic-text { color: var(--text-main) !important; }
    .dynamic-subtext { color: var(--text-main); opacity: 0.7; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="row g-3 mb-4">
        <?php 
        $cards = [
            ['Modules', $stats['total_formations'], 'bi-book', 'bg-primary'],
            ['Sessions', $stats['total_sessions'], 'bi-calendar-event', 'bg-success'],
            ['Réussites', $stats['total_reussites'], 'bi-person-check', 'bg-info'],
            ['Formateurs', $stats['total_formateurs'], 'bi-people', 'bg-dark']
        ];
        foreach ($cards as $card): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 <?= $card[3] ?> text-white" style="border-radius: 16px;">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="text-uppercase small fw-bold opacity-75 mb-1"><?= $card[0] ?></h6>
                        <h2 class="fw-bold mb-0"><?= number_format($card[1] ?? 0, 0, '.', ' ') ?></h2>
                    </div>
                    <div class="fs-1 opacity-25"><i class="bi <?= $card[2] ?>"></i></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-container">
        <div class="p-3 border-bottom"><h5 class="fw-bold mb-0 dynamic-text">Répartition & Documentation</h5></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead style="background: var(--table-header); color: var(--text-main);">
                    <tr class="text-center small fw-bold">
                        <?php foreach($formations_data as $f): ?>
                            <th style="min-width: 260px; border-color: var(--border-color);"><?= htmlspecialchars((string)($f['titre'] ?? 'Sans titre')) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody style="background: var(--card-bg);">
                    <tr>
                        <?php foreach($formations_data as $f): ?>
                        <td class="p-3 align-top text-center" style="border-color: var(--border-color);">
                            <div class="mb-3">
                                <?= displayAvatar($f['lead_avatar'], 'avatar-md') ?>
                                <br><span class="lead-badge">Référent</span><br>
                                <span class="fw-bold dynamic-text d-block"><?= htmlspecialchars((string)($f['lead_nom'] ?? 'À pourvoir')) ?></span>
                            </div>

                            <div class="text-start mb-3">
                                <label class="dynamic-subtext fw-bold small mb-2 d-block" style="font-size: 0.65rem;">ÉQUIPE</label>
                                <?php 
                                if(!empty($f['staff_info'])) {
                                    $staffs = explode(';;', $f['staff_info']);
                                    foreach($staffs as $s) {
                                        $parts = explode('|', $s);
                                        echo "<div class='staff-item'>" . displayAvatar($parts[1] ?? '', 'avatar-sm') . "<span>" . htmlspecialchars((string)($parts[0] ?? 'Inconnu')) . "</span></div>";
                                    }
                                } else { echo '<div class="text-muted small text-center py-2">Aucun staff</div>'; }
                                ?>
                            </div>

                            <div class="link-box text-start">
                                <?php if(!empty($f['doc_link_2026'])): ?>
                                    <a href="<?= htmlspecialchars((string)$f['doc_link_2026']) ?>" target="_blank" class="btn btn-primary btn-sm btn-link-custom shadow-sm"><i class="bi bi-file-earmark-text me-1"></i> Doc 2026</a>
                                <?php endif; ?>
                                <?php if(!empty($f['qst_link'])): ?>
                                    <a href="<?= htmlspecialchars((string)$f['qst_link']) ?>" target="_blank" class="btn btn-info btn-sm btn-link-custom text-white shadow-sm"><i class="bi bi-patch-question me-1"></i> Questionnaire</a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-container shadow-sm border-0">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 dynamic-text">📜 Dernières Réussites</h6>
                    <input type="text" id="searchMembre" class="form-control form-control-sm" placeholder="Filtrer..." style="width: 150px; background: var(--table-header); border:none; color: var(--text-main);">
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0 small" id="histTable">
                        <thead class="sticky-top" style="background: var(--table-header);">
                            <tr><th>Membre</th><th>Formation</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historiqueGlobal as $h): ?>
                            <tr class="hist-row">
                                <td class="fw-bold dynamic-text"><?= htmlspecialchars((string)($h['pseudo'] ?? 'Inconnu')) ?></td>
                                <td><span class="badge bg-soft-primary border border-primary text-primary"><?= htmlspecialchars((string)($h['formation_titre'] ?? 'N/A')) ?></span></td>
                                <td class="dynamic-subtext"><?= date('d/m/Y', strtotime($h['date_reussite'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-container shadow-sm border-0">
                <div class="p-3 border-bottom fw-bold small text-uppercase dynamic-subtext" style="background: var(--table-header);">🎯 Quotas</div>
                <div class="p-4" style="background: var(--card-bg);">
                    <?php foreach ($formateursData as $f): 
                        $target = 3; $perc = min(($f['total_sessions'] / $target) * 100, 100);
                        $color = ($f['total_sessions'] >= $target) ? 'success' : 'warning';
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold dynamic-text" style="font-size: 0.85rem;"><?= htmlspecialchars((string)($f['pseudo'] ?? 'Inconnu')) ?></span>
                            <span class="small"><?= (int)$f['total_sessions'] ?> / <?= $target ?></span>
                        </div>
                        <div class="progress shadow-sm"><div class="progress-bar bg-<?= $color ?>" style="width: <?= $perc ?>%"></div></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('searchMembre').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('.hist-row').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>
