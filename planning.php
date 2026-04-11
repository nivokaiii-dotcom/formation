<?php
require_once 'config.php';
require_once 'includes/header.php';

// 1. Paramètres de navigation et Filtres
$view = isset($_GET['view']) ? $_GET['view'] : 'week';
$dateParam = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filterFormation = isset($_GET['filter_formation']) ? $_GET['filter_formation'] : '';

try {
    $dateRef = new DateTime($dateParam);
} catch (Exception $e) {
    $dateRef = new DateTime();
}

$today = (new DateTime())->format('Y-m-d');

// 2. Traductions
$joursFr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$moisFr  = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];

// 3. Calcul des périodes et navigation
if ($view === 'month') {
    $dateRef->modify('first day of this month');
    $startPeriod = (clone $dateRef)->modify('monday this week')->format('Y-m-d');
    $endPeriod   = (clone $dateRef)->modify('last day of this month')->modify('sunday this week')->format('Y-m-d');
    
    $prev = (clone $dateRef)->modify('-1 month')->format('Y-m-d');
    $next = (clone $dateRef)->modify('+1 month')->format('Y-m-d');
    
    // Traduction du mois en français
    $monthNameEn = $dateRef->format('F');
    $monthNameFr = $moisFr[$monthNameEn] ?? $monthNameEn;
    $label = "Mois de " . $monthNameFr . " " . $dateRef->format('Y');
} else {
    $dateRef->modify('monday this week');
    $startPeriod = $dateRef->format('Y-m-d');
    $endPeriod   = (clone $dateRef)->modify('+6 days')->format('Y-m-d');
    
    $prev = (clone $dateRef)->modify('-7 days')->format('Y-m-d');
    $next = (clone $dateRef)->modify('+7 days')->format('Y-m-d');
    $label = "Semaine du " . date('d/m', strtotime($startPeriod)) . " au " . date('d/m', strtotime($endPeriod));
}

// 4. Récupération des formations pour le filtre
$allFormations = $pdo->query("SELECT id, titre FROM formations ORDER BY titre ASC")->fetchAll(PDO::FETCH_ASSOC);

// 5. Récupération des données SQL
$sql = "
    SELECT p.*, f.titre, u.avatar 
    FROM planning p
    JOIN formations f ON p.formation_id = f.id
    LEFT JOIN formateurs fm ON p.formateur = fm.pseudo
    LEFT JOIN users u ON fm.discord_id = u.discord_id
    WHERE p.date BETWEEN :start AND :end
";

$params = ['start' => $startPeriod, 'end' => $endPeriod];

if (!empty($filterFormation)) {
    $sql .= " AND f.id = :form_id";
    $params['form_id'] = $filterFormation;
}

$sql .= " ORDER BY p.date ASC, p.heure ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$calendar = [];
foreach ($sessions as $s) {
    $calendar[$s['date']][] = $s;
}

function getTrainerAvatar($url, $name) {
    return (!empty($url)) ? $url : 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=6366f1&color=fff';
}
?>

<style>
    :root { --accent-color: #6366f1; }
    .calendar-container { max-width: 1400px; margin: auto; }
    
    .calendar-grid { 
        display: grid; 
        grid-template-columns: repeat(1, 1fr); 
        gap: 15px; 
    }
    @media (min-width: 992px) {
        .calendar-grid { grid-template-columns: repeat(7, 1fr); }
    }

    .card-day { 
        border: 1px solid var(--bs-border-color); 
        border-radius: 16px; 
        background: var(--bs-card-bg);
        min-height: <?= $view === 'month' ? '180px' : '450px' ?>;
        transition: transform 0.2s ease;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .bg-today { border: 2px solid var(--accent-color) !important; background: rgba(99, 102, 241, 0.03) !important; }
    .other-month { opacity: 0.4; background: var(--bs-tertiary-bg); }

    .event-card {
        background: var(--bs-tertiary-bg);
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 12px;
        border-left: 4px solid var(--accent-color);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .time-badge {
        font-size: 0.7rem;
        font-weight: 800;
        background: var(--accent-color);
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .formation-title { 
        font-size: 0.85rem; 
        font-weight: 700; 
        line-height: 1.3; 
        color: var(--bs-heading-color);
        margin-bottom: 8px;
    }

    .avatar-trainer { width: 24px; height: 24px; border-radius: 8px; object-fit: cover; }
    
    .nav-pill-custom { background: var(--bs-card-bg); padding: 5px; border-radius: 12px; border: 1px solid var(--bs-border-color); }
    .nav-pill-custom .btn { border: none; border-radius: 8px; font-weight: 600; font-size: 0.85rem; }
    .nav-pill-custom .active-view { background: var(--accent-color) !important; color: white !important; }
    
    .filter-select { 
        border-radius: 12px; 
        border: 1px solid var(--bs-border-color); 
        font-size: 0.9rem; 
        padding: 0.5rem 1rem;
        min-width: 220px;
    }
</style>

<div class="main-content py-5">
    <div class="container-fluid calendar-container">
        
        <div class="row align-items-center mb-5">
            <div class="col-lg-5">
                <h2 class="fw-bold mb-1">📅 Planning Global</h2>
                <p class="text-muted mb-0 fw-semibold"><?= $label ?></p>
            </div>
            <div class="col-lg-7 d-flex flex-wrap justify-content-lg-end gap-3 mt-3 mt-lg-0">
                
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="view" value="<?= $view ?>">
                    <input type="hidden" name="date" value="<?= $dateParam ?>">
                    <select name="filter_formation" class="form-select filter-select shadow-sm" onchange="this.form.submit()">
                        <option value="">📚 Toutes les formations</option>
                        <?php foreach ($allFormations as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filterFormation == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['titre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="nav-pill-custom shadow-sm d-flex gap-1">
                    <a href="?view=week&date=<?= $dateParam ?>&filter_formation=<?= $filterFormation ?>" class="btn <?= $view == 'week' ? 'active-view' : '' ?>">Semaine</a>
                    <a href="?view=month&date=<?= $dateParam ?>&filter_formation=<?= $filterFormation ?>" class="btn <?= $view == 'month' ? 'active-view' : '' ?>">Mois</a>
                </div>

                <div class="btn-group shadow-sm">
                    <a href="?view=<?= $view ?>&date=<?= $prev ?>&filter_formation=<?= $filterFormation ?>" class="btn btn-light border"><i class="bi bi-chevron-left"></i></a>
                    <a href="?view=<?= $view ?>&date=<?= $today ?>&filter_formation=<?= $filterFormation ?>" class="btn btn-light border fw-bold px-3">Aujourd'hui</a>
                    <a href="?view=<?= $view ?>&date=<?= $next ?>&filter_formation=<?= $filterFormation ?>" class="btn btn-light border"><i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        </div>

        <div class="calendar-grid">
            <?php 
            $currentIter = new DateTime($startPeriod);
            $endIter     = new DateTime($endPeriod);

            while ($currentIter <= $endIter):
                $dateStr = $currentIter->format('Y-m-d');
                $isToday = ($dateStr == $today);
                $isOtherMonth = ($view === 'month' && $currentIter->format('m') !== $dateRef->format('m'));
            ?>
                <div class="card-day <?= $isToday ? 'bg-today' : '' ?> <?= $isOtherMonth ? 'other-month' : '' ?>">
                    <div class="p-3 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-uppercase opacity-50" style="font-size:0.7rem;">
                            <?= $joursFr[$currentIter->format('l')] ?>
                        </span>
                        <span class="fs-5 fw-bold <?= $isToday ? 'text-primary' : '' ?>"><?= $currentIter->format('d') ?></span>
                    </div>
                    
                    <div class="card-body p-2 pt-0">
                        <?php if (isset($calendar[$dateStr])): ?>
                            <?php foreach ($calendar[$dateStr] as $event): ?>
                                <div class="event-card">
                                    <div class="time-badge">
                                        <i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($event['heure'])) ?>
                                    </div>
                                    <div class="formation-title"><?= htmlspecialchars($event['titre']) ?></div>
                                    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top opacity-75">
                                        <img src="<?= getTrainerAvatar($event['avatar'], $event['formateur']) ?>" class="avatar-trainer shadow-sm" alt="Avatar">
                                        <span class="fw-bold" style="font-size: 0.75rem;"><?= htmlspecialchars($event['formateur']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($view === 'week'): ?>
                            <div class="text-center py-5 opacity-25 mt-auto mb-auto">
                                <i class="bi bi-calendar-x d-block fs-2 mb-2"></i>
                                <span class="small fw-bold text-uppercase">Libre</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                $currentIter->modify('+1 day');
            endwhile; 
            ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>