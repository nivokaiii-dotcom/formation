<?php
ob_start(); 
require_once 'config.php';
require_once 'includes/header.php';

// --- CONFIGURATION WEBHOOK DISCORD ---
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/1474350571166367864/lb2JlKbhZMSP_FULwg9A83kTxgFoQ6ELr9ljX0YBvkpC_1N-3XJ9wQVN1scWKR5cKPss');

/**
 * Envoi du DISPATCH en format texte structuré pour Discord
 */
function sendDiscordWebhook($formations) {
    $dateStr = date('d/m/Y');
    
    // Construction du message
    $content = "# Update du " . $dateStr . "\n\n";
    $content .= "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬\n";
    $content .= "Si vous n'êtes pas assigné, ou que vous voulez rejoindre d'autres formations, veuillez me le faire savoir le plus rapidement. ||<@&1346591500921995369> <@&1461359032798416957>||\n\n";

    // Mapping des emojis selon le nom de la formation
    $emojis = [
        'Support' => '🍀',
        'Modérateur' => '💎',
        'Logs' => '🔍',
        'Remboursement' => '💳',
        'Ticket' => '📩',
        'WIPE' => '🔄',
        'Illégale' => '☠️',
        'Légale' => '⛲',
        'Freekill' => '🔫',
        'Management' => '🧑‍💼'
    ];

    foreach ($formations as $f) {
        $titre = $f['titre'] ?? 'Sans titre';
        $emoji = '💠'; // Emoji par défaut
        
        // Sélection de l'emoji correspondant
        foreach ($emojis as $key => $e) {
            if (stripos($titre, $key) !== false) {
                $emoji = $e;
                break;
            }
        }

        $superviseur = !empty($f['lead_nom']) ? $f['lead_nom'] : "À reprendre";
        
        $content .= "- **" . $emoji . " Formation " . $titre . "**\n";
        $content .= "🏆 " . $superviseur . "\n";
        
        if (!empty($f['staff_info'])) {
            $staffs = explode(';;', $f['staff_info']);
            $names = [];
            foreach($staffs as $s) {
                $p = explode('|', $s);
                $names[] = $p[0];
            }
            $content .= "👥 " . implode(' - ', $names) . "\n\n";
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
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return $error ? "Erreur cURL : " . $error : true;
}

// Vérification admin
$isAdmin = (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');

// Log d'activité
insertLog($pdo, "A consulté le tableau de bord (Dispatch)");

try {
    // 1. STATS
    $statsQuery = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM formations) as total_formations,
        (SELECT COUNT(*) FROM planning) as total_sessions,
        (SELECT COUNT(*) FROM formateurs) as total_formateurs,
        (SELECT COUNT(*) FROM membres_formes) as total_reussites");
    $stats = $statsQuery ? $statsQuery->fetch(PDO::FETCH_ASSOC) : ['total_formations'=>0,'total_sessions'=>0,'total_formateurs'=>0,'total_reussites'=>0];

    // 2. DONNÉES FORMATIONS
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

    // 3. SYNC DISCORD
    if ($isAdmin && isset($_GET['sync_discord'])) {
        $result = sendDiscordWebhook($formations_data);
        if ($result === true) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?notif=webhook_ok");
            exit();
        } else { $webhook_error = $result; }
    }

    // 4. ACTIVITÉ
    $formateursData = $pdo->query("SELECT f.pseudo, u.avatar, COUNT(p.id) as total_sessions FROM formateurs f LEFT JOIN users u ON f.discord_id = u.discord_id LEFT JOIN planning p ON f.pseudo = p.formateur AND MONTH(p.date) = MONTH(CURRENT_DATE) AND YEAR(p.date) = YEAR(CURRENT_DATE) GROUP BY f.id, f.pseudo, u.avatar ORDER BY total_sessions DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. HISTORIQUE
    $historiqueGlobal = $pdo->query("SELECT m.*, f.titre as formation_titre FROM membres_formes m LEFT JOIN formations f ON m.formation_id = f.id ORDER BY m.date_reussite DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erreur SQL : " . $e->getMessage();
}

function displayAvatar($url, $sizeClass = 'avatar-sm') {
    $src = (!empty($url)) ? $url : 'https://ui-avatars.com/api/?name=Staff&background=4f46e5&color=fff';
    return '<img src="'.htmlspecialchars((string)$src).'" class="'.$sizeClass.'" alt="Avatar">';
}
?>

<style>
    .table-container { border-radius: 16px; background: var(--card-bg); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; border: 1px solid var(--border-color); }
    .lead-badge { background: #ffc107; color: #000; padding: 2px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; margin-bottom: 5px; }
    .avatar-sm { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color); }
    .avatar-md { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid var(--card-bg); box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 10px; }
    .staff-item { background: var(--table-header); color: var(--text-main); padding: 5px 10px; border-radius: 8px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; border: 1px solid var(--border-color); }
    .btn-discord { background: #5865F2; color: white; border: none; font-weight: bold; padding: 10px 20px; border-radius: 12px; transition: 0.3s; text-decoration: none; display: inline-block; }
    .btn-discord:hover { background: #4752c4; color: white; transform: translateY(-2px); }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Dispatch Formateurs</h2>
        <?php if($isAdmin): ?>
            <a href="?sync_discord=1" class="btn-discord shadow-sm">
                <i class="bi bi-discord me-2"></i> Publier le Dispatch
            </a>
        <?php endif; ?>
    </div>

    <?php if(isset($webhook_error)): ?>
        <div class="alert alert-danger shadow-sm mb-4"><?= $webhook_error ?></div>
    <?php endif; ?>

    <?php if(isset($_GET['notif']) && $_GET['notif'] == 'webhook_ok'): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <i class="bi bi-check-circle-fill me-2"></i> Dispatch publié avec succès sur Discord !
        </div>
    <?php endif; ?>

    <div class="table-container">
        <div class="p-3 border-bottom"><h5 class="fw-bold mb-0">Répartition & Superviseurs</h5></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead style="background: var(--table-header); color: var(--text-main);">
                    <tr class="text-center small fw-bold">
                        <?php foreach($formations_data as $f): ?>
                            <th style="min-width: 260px; border-color: var(--border-color);"><?= htmlspecialchars($f['titre'] ?? 'Sans titre') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach($formations_data as $f): ?>
                        <td class="p-3 align-top text-center" style="border-color: var(--border-color);">
                            <div class="mb-3">
                                <?= displayAvatar($f['lead_avatar'], 'avatar-md') ?>
                                <br><span class="lead-badge">Superviseur</span><br>
                                <span class="fw-bold d-block"><?= htmlspecialchars($f['lead_nom'] ?? 'À pourvoir') ?></span>
                            </div>

                            <div class="text-start mb-3">
                                <label class="fw-bold small mb-2 d-block opacity-50">ÉQUIPE</label>
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
        <div class="col-lg-8">
            <div class="table-container shadow-sm border-0">
                <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">📜 Historique récent</h6></div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="sticky-top" style="background: var(--table-header);">
                            <tr><th>Membre</th><th>Module</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historiqueGlobal as $h): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($h['pseudo'] ?? 'Inconnu') ?></td>
                                <td><span class="badge bg-soft-primary border border-primary text-primary"><?= htmlspecialchars($h['formation_titre'] ?? 'N/A') ?></span></td>
                                <td><?= date('d/m/Y', strtotime($h['date_reussite'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-container shadow-sm border-0">
                <div class="p-3 border-bottom fw-bold small text-uppercase opacity-50">🎯 Activité (Mois)</div>
                <div class="p-4">
                    <?php foreach ($formateursData as $f): 
                        $target = 3; $perc = min(($f['total_sessions'] / $target) * 100, 100);
                        $color = ($f['total_sessions'] >= $target) ? 'success' : 'warning';
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($f['pseudo'] ?? 'Inconnu') ?></span>
                            <span class="small"><?= (int)$f['total_sessions'] ?> / <?= $target ?></span>
                        </div>
                        <div class="progress shadow-sm" style="height: 8px; background-color: rgba(0,0,0,0.05);"><div class="progress-bar bg-<?= $color ?>" style="width: <?= $perc ?>%"></div></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>
