<?php
ob_start();
require_once 'config.php';

// Start session early so included files (like header) can rely on session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'helpers/FormationFieldsHelper.php';
require_once 'includes/header.php';

// --- GESTION DE LA NAVIGATION TEMPORELLE ---
$currentMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$currentYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Calcul pour les boutons Précédent / Suivant
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// --- RÉPARATION DE L'ERREUR DEPRECATED / compatibilité si intl manquant ---
$dateObj = new DateTime();
$dateObj->setDate($currentYear, $currentMonth, 1);

if (class_exists('IntlDateFormatter')) {
    $formatter = new IntlDateFormatter(
        'fr_FR',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        null,
        null,
        'MMMM yyyy'
    );
    $displayDate = ucfirst((string) $formatter->format($dateObj));
} else {
    // Fallback: use strftime with locale if intl extension is not available
    // Try to set a French locale; this may vary per system.
    setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');
    $displayDate = ucfirst(strftime('%B %Y', $dateObj->getTimestamp()));
}

// --- CONFIGURATION ---
$webhook = $_ENV['DISCORD_WEBHOOK_URL_DISPATCH'] ?? getenv('DISCORD_WEBHOOK_URL_DISPATCH');
$satisfaction = $_ENV['LIEN_FORMULAIRE_SATISFACTION'] ?? getenv('LIEN_FORMULAIRE_SATISFACTION'); // Lien du formulaire
define('DISCORD_WEBHOOK_URL', $webhook);
define('satisfaction_url', $satisfaction);

function displayAvatar($url, $sizeClass = 'avatar-sm')
{
    $src = (!empty($url)) ? $url : 'https://ui-avatars.com/api/?name=Staff&background=4f46e5&color=fff';
    return '<img src="' . htmlspecialchars((string) $src) . '" class="' . $sizeClass . '" alt="Avatar" style="object-fit: cover; border-radius: 50%;">';
}

/**
 * Envoi du DISPATCH sur Discord avec gestion de limite de caractères
 */
function sendDiscordWebhook($formations)
{
    if (empty($formations) || !defined('DISCORD_WEBHOOK_URL') || empty(DISCORD_WEBHOOK_URL))
        return false;

    $dateStr = date('d/m/Y');
    $header = "# 📑 Update du " . $dateStr . "\n";
    $header .= "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬\n";
    $header .= "Si vous n'êtes pas assigné, ou que vous voulez rejoindre d'autres formations, veuillez me le faire savoir le plus rapidement. ||<@&1346591500921995369> <@&1461359032798416957>||\n\n";

    $emojis = [
        'Support' => '🍀',
        'Modérateur' => '💎',
        'Logs' => '🔍',
        'Remboursement' => '💳',
        'Ticket' => '📩',
        'WIPE' => '🔄',
        'Ilegal' => '☠️',
        'Légal' => '⛲',
        'FreeKill' => '🔫',
        'Management' => '🧑‍💼'
    ];

    $fullMessage = $header;
    foreach ($formations as $f) {
        $titre = $f['titre'] ?? 'Sans titre';
        $emoji = '💠';
        foreach ($emojis as $key => $e) {
            if (stripos($titre, $key) !== false) {
                $emoji = $e;
                break;
            }
        }

        $staffCount = !empty($f['staff_info']) ? count(explode(';;', $f['staff_info'])) : 0;
        $isManagement = (stripos($titre, 'Management') !== false);
        $placeText = "";
        if (!$isManagement) {
            $remains = 5 - $staffCount;
            $placeText = ($remains <= 0) ? " (COMPLET)" : " ($remains places restantes)";
        }

        $superviseur = !empty($f['lead_discord_id']) ? "<@" . $f['lead_discord_id'] . ">" : ($f['lead_nom'] ?? "À reprendre");

        $item = "- **" . $emoji . " Formation " . $titre . "**" . $placeText . "\n";
        $item .= "🏆 Superviseur : " . $superviseur . "\n";

        if (!empty($f['staff_info'])) {
            $staffs = explode(';;', $f['staff_info']);
            $mentions = [];
            foreach ($staffs as $s) {
                $p = explode('|', $s);
                $mentions[] = (!empty($p[2])) ? "<@" . $p[2] . ">" : ($p[0] ?? 'Inconnu');
            }
            $item .= "👥 Staffs : " . implode(' - ', $mentions) . "\n\n";
        } else {
            $item .= "👥 Aucun staff assigné\n\n";
        }

        // Sécurité anti-limite 2000 caractères Discord
        if (strlen($fullMessage . $item) > 1900) {
            postToDiscord($fullMessage);
            $fullMessage = "";
        }
        $fullMessage .= $item;
    }

    $fullMessage .= "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬";
    postToDiscord($fullMessage);
    return true;
}

function postToDiscord($content)
{
    if (empty($content))
        return;
    $payload = json_encode(["content" => $content]);
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

$isAdmin = (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');
$helper = new FormationFieldsHelper($pdo);

try {
    // 1. Stats
    $stmtStats = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM formateurs) as total_formateurs,
        (SELECT COUNT(*) FROM planning WHERE MONTH(date) = ? AND YEAR(date) = ?) as total_forma_mois,
        (SELECT COUNT(*) FROM membres_formes WHERE MONTH(date_reussite) = ? AND YEAR(date_reussite) = ?) as total_personnes_mois
    ");
    $stmtStats->execute([$currentMonth, $currentYear, $currentMonth, $currentYear]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // 2. Recap Sessions
    $stmtRecap = $pdo->prepare("SELECT f.titre, COUNT(p.id) as nb 
        FROM formations f 
        LEFT JOIN planning p ON f.id = p.formation_id 
        AND MONTH(p.date) = ? AND YEAR(p.date) = ?
        GROUP BY f.id ORDER BY nb DESC");
    $stmtRecap->execute([$currentMonth, $currentYear]);
    $recapFormations = $stmtRecap->fetchAll(PDO::FETCH_ASSOC);

    // 3. Formations Data
    $stmt = $pdo->query("SELECT f.*, fr.pseudo as lead_nom, fr.discord_id as lead_discord_id, u.avatar as lead_avatar,
        (SELECT GROUP_CONCAT(CONCAT(IFNULL(fm.pseudo, 'Inconnu'), '|', IFNULL(us.avatar, ''), '|', IFNULL(fm.discord_id, '')) SEPARATOR ';;') 
         FROM formation_staff fs
         JOIN formateurs fm ON fs.formateur_id = fm.id 
         LEFT JOIN users us ON fm.discord_id = us.discord_id
         WHERE fs.formation_id = f.id) as staff_info
        FROM formations f 
        LEFT JOIN formateurs fr ON f.referent_id = fr.id
        LEFT JOIN users u ON fr.discord_id = u.discord_id
        ORDER BY f.titre ASC");
    $formations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($isAdmin && isset($_GET['sync_discord'])) {
        sendDiscordWebhook($formations_data);
        header("Location: " . $_SERVER['PHP_SELF'] . "?month=$currentMonth&year=$currentYear&notif=webhook_ok");
        exit();
    }

    // 4. Objectifs formateurs
    $stmtFormateurs = $pdo->prepare("SELECT f.pseudo, u.avatar, COUNT(p.id) as total_sessions 
        FROM formateurs f 
        LEFT JOIN users u ON f.discord_id = u.discord_id 
        LEFT JOIN planning p ON f.pseudo = p.formateur AND MONTH(p.date) = ? AND YEAR(p.date) = ?
        GROUP BY f.id, f.pseudo, u.avatar ORDER BY total_sessions DESC");
    $stmtFormateurs->execute([$currentMonth, $currentYear]);
    $formateursData = $stmtFormateurs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Historique
    $historiqueGlobal = $pdo->query("SELECT m.*, f.titre as formation_titre FROM membres_formes m LEFT JOIN formations f ON m.formation_id = f.id ORDER BY m.date_reussite DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>

<style>
    :root {
        --primary-color: #4f46e5;
        --card-bg: #fff;
    }

    .table-container {
        border-radius: 16px;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
    }

    .stat-val {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--primary-color);
        display: block;
    }

    .stat-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.6;
        font-weight: 700;
        letter-spacing: 1px;
        color: var(--text-main);
    }

    .lead-badge {
        background: #ffc107;
        color: #000;
        padding: 2px 10px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: bold;
        text-transform: uppercase;
        display: inline-block;
        margin-bottom: 5px;
    }

    .avatar-md {
        width: 50px;
        height: 50px;
        border: 3px solid var(--primary-color);
        margin-bottom: 10px;
        border-radius: 50%;
    }

    .avatar-sm {
        width: 30px;
        height: 30px;
        border-radius: 50%;
    }

    .btn-discord {
        background: #5865F2;
        color: white !important;
        border: none;
        font-weight: bold;
        padding: 10px 20px;
        border-radius: 12px;
        text-decoration: none;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
    }

    .btn-nav {
        padding: 8px 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-main);
        text-decoration: none;
        transition: 0.2s;
    }

    .btn-nav:hover,
    .btn-nav.active {
        background: var(--primary-color);
        color: white !important;
    }

    .staff-item {
        background: rgba(120, 120, 120, 0.1);
        padding: 5px 10px;
        border-radius: 8px;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
        color: var(--text-main);
    }

    .badge-alert {
        background: #dc3545 !important;
        color: white;
    }

    .btn-doc {
        padding: 8px 12px;
        font-size: 0.75rem;
        border-radius: 8px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        font-weight: 600;
        color: white !important;
    }

    .btn-canva {
        background: #00c4cc;
    }

    .btn-forms {
        background: #673ab7;
    }

    /* STYLE BOUTON SATISFACTION */
    .btn-satisfaction {
        background: #10b981;
        color: white !important;
        font-weight: bold;
        padding: 10px 22px;
        border-radius: 12px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: 0.3s;
        box-shadow: 0 4px 0 #059669;
    }

    .btn-satisfaction:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 0 #059669;
        background: #059669;
    }

    .btn-satisfaction:active {
        transform: translateY(2px);
        box-shadow: none;
    }

    .custom-field-display {
        background: rgba(79, 70, 229, 0.05);
        border: 1px solid rgba(79, 70, 229, 0.2);
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .custom-field-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.7;
        font-weight: 600;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 4px;
    }

    .custom-field-value {
        word-break: break-word;
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-0" style="color: var(--text-main);">Formation Panel | Dispatch</h2>
            <div class="d-flex align-items-center gap-2 mt-1">
                <span class="badge bg-primary px-3 shadow-sm">Période : <?= $displayDate ?></span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="<?= $satisfaction ?>" target="_blank" class="btn-satisfaction me-lg-3">
                <i class="bi bi-star-fill me-2"></i> Avis Formations
            </a>
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn-nav"><i class="bi bi-chevron-left"></i>
                Précédent</a>
            <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>"
                class="btn-nav <?= (!isset($_GET['month']) || $_GET['month'] == date('m')) ? 'active' : '' ?>">Aujourd'hui</a>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn-nav">Suivant <i
                    class="bi bi-chevron-right"></i></a>

            <?php if ($isAdmin): ?>
                <a href="?sync_discord=1&month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="btn-discord ms-lg-3"
                    onclick="return confirm('Publier l\'update sur Discord ?')">
                    <i class="bi bi-discord me-2"></i> Publier
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card"><span class="stat-label">Total Formateurs</span><span
                    class="stat-val"><?= (int) $stats['total_formateurs'] ?></span></div>
        </div>
        <div class="col-md-4">
            <div class="stat-card"><span class="stat-label">Sessions (Ce mois)</span><span
                    class="stat-val"><?= (int) $stats['total_forma_mois'] ?></span></div>
        </div>
        <div class="col-md-4">
            <div class="stat-card"><span class="stat-label">Membres Formés (Ce mois)</span><span
                    class="stat-val"><?= (int) $stats['total_personnes_mois'] ?></span></div>
        </div>
    </div>

    <div class="table-container">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light-subtle">
            <h5 class="fw-bold mb-0" style="color: var(--text-main);">Organisation des Modules</h5>
            <span class="badge bg-dark">Année <?= $currentYear ?></span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr class="text-center small fw-bold">
                        <?php foreach ($formations_data as $f): ?>
                            <th style="min-width: 280px; border-color: var(--border-color); padding: 15px;">
                                <span style="color: var(--text-main);"><?= htmlspecialchars($f['titre']) ?></span>
                                <?php
                                $isMgmt = (stripos($f['titre'], 'Management') !== false);
                                $staffs = !empty($f['staff_info']) ? explode(';;', $f['staff_info']) : [];
                                $countS = count($staffs);
                                $hasLead = !empty($f['lead_nom']);
                                if (!$isMgmt):
                                    $remaining = 5 - $countS;
                                    $alertClass = ($countS >= 5 && !$hasLead) ? 'badge-alert' : 'bg-secondary';
                                    ?>
                                    <div class="mt-1">
                                        <span class="badge <?= $alertClass ?>" style="font-size: 10px;">
                                            <?= ($countS >= 5 && !$hasLead) ? "ERREUR : SUPERVISEUR REQUIS" : (($remaining <= 0) ? "COMPLET" : $remaining . " PLACES LIBRES") ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($formations_data as $f):
                            $isMgmt = (stripos($f['titre'], 'Management') !== false);
                            $staffs = !empty($f['staff_info']) ? explode(';;', $f['staff_info']) : [];
                            $countS = count($staffs);
                            $hasLead = !empty($f['lead_nom']);
                            $errorRed = (!$isMgmt && $countS >= 5 && !$hasLead);
                            ?>
                            <td class="p-3 align-top text-center"
                                style="border-right: 1px solid var(--border-color); <?= $errorRed ? 'background: rgba(220, 53, 69, 0.1);' : '' ?>">
                                <div class="mb-4">
                                    <?php if (!empty($f['doc_link_2026'])): ?>
                                        <a href="<?= htmlspecialchars($f['doc_link_2026']) ?>" target="_blank"
                                            class="btn-doc btn-canva w-100 justify-content-center"><i
                                                class="bi bi-file-earmark-pdf"></i> Support Canva</a>
                                    <?php endif; ?>
                                    <?php if (!empty($f['qst_link'])): ?>
                                        <a href="<?= htmlspecialchars($f['qst_link']) ?>" target="_blank"
                                            class="btn-doc btn-forms w-100 justify-content-center"><i
                                                class="bi bi-card-checklist"></i> Examen</a>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <?= displayAvatar($f['lead_avatar'], 'avatar-md') ?><br>
                                    <span class="lead-badge"
                                        style="<?= !$hasLead ? 'background: #dc3545; color: white;' : '' ?>">
                                        <?= !$hasLead ? 'À RECRUTER' : 'Superviseur' ?>
                                    </span><br>
                                    <span class="fw-bold d-block"
                                        style="color: var(--text-main);"><?= htmlspecialchars($f['lead_nom'] ?? 'VACANT') ?></span>
                                </div>

                                <div class="text-start">
                                    <label class="fw-bold small mb-2 d-block opacity-50 text-uppercase"
                                        style="color: var(--text-main);">Équipe (<?= $countS ?>/5)</label>
                                    <?php
                                    if (!empty($staffs)) {
                                        foreach ($staffs as $s) {
                                            $parts = explode('|', $s);
                                            echo "<div class='staff-item'>" . displayAvatar($parts[1] ?? '', 'avatar-sm') . "<span>" . htmlspecialchars($parts[0] ?? 'Inconnu') . "</span></div>";
                                        }
                                    } else {
                                        echo '<div class="text-muted small text-center py-2">Aucun staff</div>';
                                    }
                                    ?>
                                </div>

                                <!-- AFFICHAGE DES CHAMPS DYNAMIQUES -->
                                <?php
                                $customFields = $helper->getFormationFields($f['id']);
                                if (!empty($customFields)):
                                    echo '<hr class="my-3">';
                                    echo '<div class="mt-3">';
                                    echo '<label class="fw-bold small mb-2 d-block opacity-50 text-uppercase" style="color: var(--text-main);">Champs Personnalisés</label>';
                                    foreach ($customFields as $field):
                                        echo $helper->displayField($field, $field['value']);
                                    endforeach;
                                    echo '</div>';
                                endif;
                                ?>
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
                <h6 class="fw-bold mb-3 border-bottom pb-2">📊 Sessions par Module</h6>
                <div class="table-responsive">
                    <table class="table table-hover small">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th class="text-end">Total</th>
                            </tr>
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
                <h6 class="fw-bold mb-3 border-bottom pb-2">📜 Dernières Réussites</h6>
                <div class="table-responsive">
                    <table class="table table-hover small">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th class="text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historiqueGlobal as $h): ?>
                                <tr>
                                    <td><span class="fw-bold"><?= htmlspecialchars($h['pseudo']) ?></span><br><small
                                            class="text-muted"><?= htmlspecialchars($h['formation_titre'] ?? 'N/A') ?></small></td>
                                    <td class="text-end text-muted"><?= date('d/m/Y', strtotime($h['date_reussite'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-container p-3">
                <h6 class="fw-bold mb-3 border-bottom pb-2">🎯 Objectifs (Cible: 3)</h6>
                <?php foreach ($formateursData as $f):
                    $target = 3;
                    $val = (int) $f['total_sessions'];
                    $perc = min(($val / $target) * 100, 100);
                    $color = ($val >= $target) ? 'success' : 'warning';
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1 align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <?= displayAvatar($f['avatar'], 'avatar-sm') ?><span
                                    class="small fw-bold"><?= htmlspecialchars($f['pseudo']) ?></span></div>
                            <span class="small"><?= $val ?> / <?= $target ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
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