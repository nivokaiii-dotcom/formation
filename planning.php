<?php
require_once 'config.php';
require_once 'includes/header.php';

// 1. Gestion de la date
try {
    $dateRef = isset($_GET['week']) ? new DateTime($_GET['week']) : new DateTime();
} catch (Exception $e) {
    $dateRef = new DateTime();
}

$dateRef->modify('monday this week');

$startOfWeek = $dateRef->format('Y-m-d');
$endOfWeek = (clone $dateRef)->modify('+6 days')->format('Y-m-d');

// Navigation
$prevWeek = (clone $dateRef)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $dateRef)->modify('+7 days')->format('Y-m-d');
$today    = (new DateTime())->format('Y-m-d');

// 2. Récupération des données avec avatars
$stmt = $pdo->prepare("
    SELECT p.*, f.titre, u.avatar 
    FROM planning p
    JOIN formations f ON p.formation_id = f.id
    LEFT JOIN formateurs fm ON p.formateur = fm.pseudo
    LEFT JOIN users u ON fm.discord_id = u.discord_id
    WHERE p.date BETWEEN :start AND :end
    ORDER BY p.date ASC, p.heure ASC
");
$stmt->execute(['start' => $startOfWeek, 'end' => $endOfWeek]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Organisation
$calendar = [];
foreach ($sessions as $s) {
    $calendar[$s['date']][] = $s;
}

$joursFr = [
    'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi', 
    'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche'
];

// Helper pour l'avatar
function getTrainerAvatar($url) {
    return (!empty($url)) ? $url : 'https://ui-avatars.com/api/?name=Staff&background=6366f1&color=fff';
}
?>

<style>
    /* Structure pour le footer en bas */
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

    .calendar-container { max-width: 1400px; margin: auto; }
    
    @media (min-width: 992px) {
        .row-cols-md-7 > * { flex: 0 0 auto; width: 14.2857%; }
    }

    .card-day { 
        border: 1px solid var(--bs-border-color); 
        border-radius: 12px; 
        background: var(--bs-card-bg);
        min-height: 450px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        transition: background 0.3s ease;
    }
    
    .bg-today { 
        background-color: rgba(99, 102, 241, 0.05) !important;
        border: 2px solid #6366f1 !important;
    }

    .event-card {
        background: var(--bs-tertiary-bg);
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 12px;
        border: 1px solid var(--bs-border-color);
        transition: all 0.2s ease;
    }
    
    .event-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        border-color: #6366f1;
    }

    .time-badge {
        font-size: 0.7rem;
        font-weight: 800;
        color: white;
        background: #6366f1;
        padding: 2px 8px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 8px;
    }

    .formation-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--bs-body-color);
        line-height: 1.3;
        margin-bottom: 10px;
    }

    .trainer-info {
        display: flex;
        align-items: center;
        gap: 8px;
        border-top: 1px solid var(--bs-border-color);
        padding-top: 8px;
    }

    .avatar-trainer {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        object-fit: cover;
    }

    .trainer-name {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--bs-body-color);
        opacity: 0.8;
    }

    .btn-outline-custom {
        border: 1px solid var(--bs-border-color);
        color: var(--bs-body-color);
        background: var(--bs-card-bg);
    }
    
    .text-day-title { color: var(--bs-body-color); opacity: 0.6; }
    .text-day-number { color: var(--bs-body-color); font-weight: 900; }
</style>

<div class="main-content">
    <div class="container-fluid calendar-container py-5">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: var(--bs-heading-color);">📅 Planning Global</h2>
                <p class="mb-0 small text-uppercase fw-bold opacity-75">
                    Semaine du <?= date('d/m', strtotime($startOfWeek)) ?> au <?= date('d/m/Y', strtotime($endOfWeek)) ?>
                </p>
            </div>
            
            <div class="btn-group shadow-sm">
                <a href="?week=<?= $prevWeek ?>" class="btn btn-outline-custom px-3"><i class="bi bi-chevron-left"></i></a>
                <a href="?week=<?= $today ?>" class="btn btn-primary px-4">Aujourd'hui</a>
                <a href="?week=<?= $nextWeek ?>" class="btn btn-outline-custom px-3"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-7 g-3">
            <?php 
            for ($i = 0; $i < 7; $i++): 
                $currentLoopDate = (clone $dateRef)->modify("+$i days");
                $dateString = $currentLoopDate->format('Y-m-d');
                $dayNameEn  = $currentLoopDate->format('l');
                $isToday    = ($dateString == $today);
            ?>
                <div class="col">
                    <div class="card card-day h-100 <?= $isToday ? 'bg-today' : '' ?>">
                        <div class="card-header border-0 bg-transparent text-center pt-3 pb-0">
                            <h6 class="fw-bold mb-0 text-uppercase small text-day-title" style="font-size: 0.65rem;"><?= $joursFr[$dayNameEn] ?></h6>
                            <span class="fs-4 text-day-number <?= $isToday ? 'text-primary' : '' ?>">
                                <?= $currentLoopDate->format('d') ?>
                            </span>
                        </div>
                        
                        <div class="card-body p-2 mt-2">
                            <?php if (isset($calendar[$dateString])): ?>
                                <?php foreach ($calendar[$dateString] as $event): ?>
                                    <div class="event-card">
                                        <span class="time-badge">
                                            <?= date('H:i', strtotime($event['heure'])) ?>
                                        </span>
                                        <div class="formation-title">
                                            <?= htmlspecialchars($event['titre']) ?>
                                        </div>
                                        <div class="trainer-info">
                                            <img src="<?= getTrainerAvatar($event['avatar']) ?>" class="avatar-trainer" alt="Profil">
                                            <span class="trainer-name">
                                                <?= htmlspecialchars($event['formateur']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5 opacity-25">
                                    <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                                    <span class="small fw-bold">REPOS</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
