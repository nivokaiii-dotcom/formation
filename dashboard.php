<?php
ob_start(); 
require_once 'config.php';
require_once 'includes/header.php';

// --- CONFIGURATION WEBHOOK DISCORD ---
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/1474350571166367864/lb2JlKbhZMSP_FULwg9A83kTxgFoQ6ELr9ljX0YBvkpC_1N-3XJ9wQVN1scWKR5cKPss');

/**
 * Fonction pour afficher l'avatar
 */
function displayAvatar($url, $sizeClass = 'avatar-sm') {
    $src = (!empty($url)) ? $url : 'https://ui-avatars.com/api/?name=Staff&background=4f46e5&color=fff';
    return '<img src="'.htmlspecialchars((string)$src).'" class="'.$sizeClass.'" alt="Avatar" style="object-fit: cover; border-radius: 50%;">';
}

/**
 * Envoi du DISPATCH sur Discord
 */
function sendDiscordWebhook($formations) {
    if (empty($formations)) return false;

    $dateStr = date('d/m/Y');
    $content = "# 📑 Update du " . $dateStr . "\n";
    $content .= "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬\n";
    $content .= "Si vous n'êtes pas assigné, ou que vous voulez rejoindre d'autres formations, veuillez me le faire savoir le plus rapidement. ||<@&1346591500921995369> <@&1461359032798416957>||\n\n";

    $emojis = [
        'Support' => '🍀', 'Modérateur' => '💎', 'Logs' => '🔍', 'Remboursement' => '💳',
        'Ticket' => '📩', 'WIPE' => '🔄', 'Ilegal' => '☠️', 'Légal' => '⛲',
        'FreeKill' => '🔫', 'Management' => '🧑‍💼'
    ];

    foreach ($formations as $f) {
        $titre = $f['titre'] ?? 'Sans titre';
        $emoji = '💠';
        foreach ($emojis as $key => $e) {
            if (stripos($titre, $key) !== false) { $emoji = $e; break; }
        }

        $superviseur = !empty($f['lead_discord_id']) ? "<@" . $f['lead_discord_id'] . ">" : ($f['lead_nom'] ?? "À reprendre");
        
        $content .= "- **" . $emoji . " Formation " . $titre . "**\n";
        $content .= "🏆 Superviseur : " . $superviseur . "\n";
        
        if (!empty($f['staff_info'])) {
            $staffs = explode(';;', $f['staff_info']);
            $mentions = [];
            foreach($staffs as $s) {
                $p = explode('|', $s); 
                $mentions[] = (!empty($p[2])) ? "<@" . $p[2] . ">" : ($p[0] ?? 'Inconnu');
            }
            $content .= "👥 Staffs : " . implode(' - ', $mentions) . "\n\n";
        } else {
            $content .= "👥 Aucun staff assigné\n\n";
        }
    }
    $content .= "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬";

    $payload = json_encode(["content" => $content]);
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
    return true;
}

$isAdmin = (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');

try {
    // 1. STATS HAUT DE PAGE (Adaptation SQL Server DATEPART)
    $stats = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM formateurs) as total_formateurs,
        (SELECT COUNT(*) FROM planning WHERE DATEPART(month, date) = DATEPART(month, GETDATE()) AND DATEPART(year, date) = DATEPART(year, GETDATE())) as total_forma_mois,
        (SELECT COUNT(*) FROM membres_formes WHERE DATEPART(month, date_reussite) = DATEPART(month, GETDATE()) AND DATEPART(year, date_reussite) = DATEPART(year, GETDATE())) as total_personnes_mois
    ")->fetch(PDO::FETCH_ASSOC);

    // 2. RÉCAPITULATIF DES FORMATIONS
    $recapFormations = $pdo->query("SELECT f.titre, COUNT(p.id) as nb 
        FROM formations f 
        LEFT JOIN planning p ON f.id = p.formation_id 
        AND DATEPART(month, p.date) = DATEPART(month, GETDATE()) AND DATEPART(year, p.date) = DATEPART(year, GETDATE())
        GROUP BY f.id, f.titre ORDER BY nb DESC")->fetchAll(PDO::FETCH_ASSOC);

    // 3. DONNÉES DISPATCH (Utilisation de STRING_AGG pour SQL Server 2017+)
    $stmt = $pdo->query("SELECT f.*, fr.pseudo as lead_nom, fr.discord_id as lead_discord_id, u.avatar as lead_avatar,
        (SELECT STRING_AGG(CONCAT(COALESCE(fm.pseudo, 'Inconnu'), '|', COALESCE(us.avatar, ''), '|', COALESCE(fm.discord_id, '')), ';;') 
         FROM formation_staff fs
         JOIN formateurs fm ON fs.formateur_id = fm.id 
         LEFT JOIN users us ON fm.discord_id = us.discord_id
         WHERE fs.formation_id = f.id) as staff_info
        FROM formations f 
        LEFT JOIN formateurs fr ON f.referent_id = fr.id
        LEFT JOIN users u ON fr.discord_id = u.discord_id
        ORDER BY f.titre ASC");
    $formations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Action Discord
    if ($isAdmin && isset($_GET['sync_discord'])) {
        sendDiscordWebhook($formations_data);
        header("Location: " . $_SERVER['PHP_SELF'] . "?notif=webhook_ok");
        exit();
    }

    // 4. OBJECTIFS ET HISTORIQUE (SQL Server TOP pour limiter les résultats)
    $formateursData = $pdo->query("SELECT f.pseudo, u.avatar, COUNT(p.id) as total_sessions 
        FROM formateurs f 
        LEFT JOIN users u ON f.discord_id = u.discord_id 
        LEFT JOIN planning p ON CAST(f.pseudo AS NVARCHAR(MAX)) = CAST(p.formateur AS NVARCHAR(MAX)) 
        AND DATEPART(month, p.date) = DATEPART(month, GETDATE()) AND DATEPART(year, p.date) = DATEPART(year, GETDATE())
        GROUP BY f.id, f.pseudo, u.avatar ORDER BY total_sessions DESC")->fetchAll(PDO::FETCH_ASSOC);

    $historiqueGlobal = $pdo->query("SELECT TOP 8 m.*, f.titre as formation_titre 
        FROM membres_formes m 
        LEFT JOIN formations f ON m.formation_id = f.id 
        ORDER BY m.date_reussite DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>

<style>
    :root { --border-color: rgba(255,255,255,0.1); --card-bg: #1a1a27; }
    .table-container { border-radius: 16px; background: var(--card-bg); border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 2rem; }
    .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center; }
    .stat-val { font-size: 1.8rem; font-weight: 800; color: #4f46e5; display: block; }
    .stat-label { font-size: 0.75rem; text-transform: uppercase; opacity: 0.6; font-weight: 700; letter-spacing: 1px; }
    .lead-badge { background: #ffc107; color: #000; padding: 2px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; margin-bottom: 5px; }
    .avatar-md { width: 50px; height: 50px; border: 3px solid #4f46e5; margin-bottom: 10px; border-radius: 50%; }
    .avatar-sm { width: 30px; height: 30px; border-radius: 50%; }
    .btn-discord { background: #5865F2; color: white; border: none; font-weight: bold; padding: 10px 20px; border-radius: 12px; text-decoration: none; transition: 0.3s; }
    .btn-discord:hover { background: #4752c4; transform: translateY(-2px); color: white; }
    .btn-doc { padding: 8px 12px; font-size: 0.75rem; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-weight: 600; color: white !important; }
    .btn-canva { background: #00c4cc; }
    .btn-forms { background: #673ab7; }
    .staff-item { background: rgba(255,255,255,0.05); padding: 5px 10px; border-radius: 8px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; border: 1px solid transparent; }
</style>

<div class="container-fluid px-4 py-4 text-white">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Tableau de Bord Dispatch</h2>
            <p class="text-muted mb-0">Statistiques et répartition en temps réel</p>
        </div>
        <?php if($isAdmin): ?>
            <a href="?sync_discord=1" class="btn-discord" onclick="return confirm('Publier l\'update sur Discord ?')">
                <i class="bi bi-discord me-2"></i> Publier l'Update
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-card"><span class="stat-label">Formateurs</span><span class="stat-val"><?= (int)$stats['total_formateurs'] ?></span></div></div>
        <div class="col-md-4"><div class="stat-card"><span class="stat-label">Sessions (Ce mois)</span><span class="stat-val"><?= (int)$stats['total_forma_mois'] ?></span></div></div>
        <div class="col-md-4"><div class="stat-card"><span class="stat-label">Membres Formés</span><span class="stat-val"><?= (int)$stats['total_personnes_mois'] ?></span></div></div>
    </div>

    <div class="table-container">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Organisation des Modules</h5>
            <span class="badge bg-primary">Année 2026</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 text-white">
                <thead>
                    <tr class="text-center small fw-bold" style="background: rgba(0,0,0,0.2);">
                        <?php foreach($formations_data as $f): ?>
                            <th style="min-width: 280px; border-color: var(--border-color); padding: 15px;"><?= htmlspecialchars($f['titre']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach($formations_data as $f): ?>
                        <td class="p-3 align-top text-center" style="border-right: 1px solid var(--border-color);">
                            <div class="mb-4">
                                <?php if(!empty($f['doc_link_2026'])): ?>
                                    <a href="<?= $f['doc_link_2026'] ?>" target="_blank" class="btn-doc btn-canva w-100 justify-content-center"><i class="bi bi-file-earmark-pdf"></i> Support Canva</a>
                                <?php endif; ?>
                                <?php if(!empty($f['qst_link'])): ?>
                                    <a href="<?= $f['qst_link'] ?>" target="_blank" class="btn-doc btn-forms w-100 justify-content-center"><i class="bi bi-card-checklist"></i> Examen</a>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <?= displayAvatar($f['lead_avatar'], 'avatar-md') ?><br>
                                <span class="lead-badge">Superviseur</span><br>
                                <span class="fw-bold d-block"><?= htmlspecialchars($f['lead_nom'] ?? 'À reprendre') ?></span>
                            </div>

                            <div class="text-start">
                                <label class="fw-bold small mb-2 d-block opacity-50 text-uppercase">Équipe Assignée</label>
                                <?php 
                                if(!empty($f['staff_info'])) {
                                    $staffs = explode(';;', $f['staff_info']);
                                    foreach($staffs as $s) {
                                        $parts = explode('|', $s); 
                                        echo "<div class='staff-item'>" . displayAvatar($parts[1] ?? '', 'avatar-sm') . "<span>" . htmlspecialchars($parts[0] ?? 'Inconnu') . "</span></div>";
                                    }
                                } else { echo '<div class="text-muted small text-center py-2">Aucun staff</div>'; }
                                ?>
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="table-container p-3">
                <h6 class="fw-bold mb-3 border-bottom pb-2">📊 Sessions par Module (Mois)</h6>
                <div class="table-responsive">
                    <table class="table table-hover text-white small">
                        <thead>
                            <tr><th>Module</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recapFormations as $rf): ?>
                            <tr>
                                <td><?= htmlspecialchars($rf['titre']) ?></td>
                                <td class="text-end"><span class="badge bg-primary"><?= $rf['nb'] ?> fait(s)</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-container p-3">
                <h6 class="fw-bold mb-3 border-bottom pb-2">📜 Historique Récent</h6>
                <div class="table-responsive">
                    <table class="table table-hover text-white small">
                        <thead>
                            <tr><th>Membre</th><th class="text-end">Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historiqueGlobal as $h): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($h['pseudo']) ?></span><br>
                                    <span class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($h['formation_titre']) ?></span>
                                </td>
                                <td class="text-end text-muted"><?= date('d/m/Y', strtotime($h['date_reussite'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-container p-3">
                <h6 class="fw-bold mb-3 border-bottom pb-2">🎯 Objectifs Formateurs</h6>
                <?php foreach ($formateursData as $f): 
                    $target = 3; $val = (int)$f['total_sessions'];
                    $perc = min(($val / $target) * 100, 100);
                    $color = ($val >= $target) ? 'success' : 'warning';
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <?= displayAvatar($f['avatar'], 'avatar-sm') ?>
                            <span class="small fw-bold"><?= htmlspecialchars($f['pseudo']) ?></span>
                        </div>
                        <span class="small"><?= $val ?> / <?= $target ?></span>
                    </div>
                    <div class="progress" style="height: 6px; background: rgba(255,255,255,0.05);">
                        <div class="progress-bar bg-<?= $color ?>" style="width: <?= $perc ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush(); 
?>