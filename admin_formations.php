<?php
ob_start();
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================
    FONCTION DE LOGS (SQL Server)
================================== */
function addLog($pdo, $action) {
    $user = 'Anonyme';
    if (isset($_SESSION['user']['username'])) {
        $user = $_SESSION['user']['username'];
    } elseif (isset($_SESSION['username'])) {
        $user = $_SESSION['username'];
    }

    try {
        // GETDATE() au lieu de NOW()
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, GETDATE())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur Log : " . $e->getMessage());
    }
}

// --- LOGIQUE DE TRAITEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Ajouter une nouvelle formation
    if (isset($_POST['add_formation'])) {
        $titre = !empty($_POST['titre']) ? $_POST['titre'] : 'Nouveau Module';
        $stmt = $pdo->prepare("INSERT INTO formations (titre) VALUES (?)");
        $stmt->execute([$titre]);
        
        addLog($pdo, "Modules : Création du module '$titre'");
    }

    // 2. Supprimer une formation
    if (isset($_POST['delete_formation'])) {
        $id = (int)$_POST['id'];
        
        $st = $pdo->prepare("SELECT titre FROM formations WHERE id = ?");
        $st->execute([$id]);
        $oldTitre = $st->fetchColumn();

        $pdo->prepare("DELETE FROM formation_staff WHERE formation_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM formations WHERE id = ?")->execute([$id]);
        
        addLog($pdo, "Modules : Suppression définitive du module '$oldTitre' (ID: $id)");
    }

    // 3. Mise à jour formation
    if (isset($_POST['update_forma'])) {
        $lead = !empty($_POST['lead_id']) ? $_POST['lead_id'] : null;
        $titre = $_POST['titre'];
        $stmt = $pdo->prepare("UPDATE formations SET titre=?, referent_id=?, doc_link_2026=?, qst_link=? WHERE id=?");
        $stmt->execute([$titre, $lead, $_POST['doc'], $_POST['qst'], $_POST['id']]);
        
        addLog($pdo, "Modules : Mise à jour des informations du module '$titre'");
    }

    // 4. Ajout Staff (Version SQL Server sans INSERT IGNORE)
    if (isset($_POST['add_staff'])) {
        $f_id = $_POST['f_id'];
        $staff_id = $_POST['staff_id'];
        
        // Emulation de INSERT IGNORE pour SQL Server
        $sqlAdd = "INSERT INTO formation_staff (formation_id, formateur_id) 
                   SELECT ?, ?
                   WHERE NOT EXISTS (
                       SELECT 1 FROM formation_staff 
                       WHERE formation_id = ? AND formateur_id = ?
                   )";
        $stmt = $pdo->prepare($sqlAdd);
        $stmt->execute([$f_id, $staff_id, $f_id, $staff_id]);
        
        $stF = $pdo->prepare("SELECT titre FROM formations WHERE id = ?");
        $stF->execute([$f_id]);
        $fTitre = $stF->fetchColumn();
        
        $stS = $pdo->prepare("SELECT pseudo FROM formateurs WHERE id = ?");
        $stS->execute([$staff_id]);
        $sPseudo = $stS->fetchColumn();

        addLog($pdo, "Modules : Ajout du formateur '$sPseudo' au module '$fTitre'");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 5. Retrait Staff (via GET)
if (isset($_GET['remove_staff'], $_GET['f_id'], $_GET['s_id'])) {
    $f_id = (int)$_GET['f_id'];
    $s_id = (int)$_GET['s_id'];

    $stF = $pdo->prepare("SELECT titre FROM formations WHERE id = ?");
    $stF->execute([$f_id]);
    $fTitre = $stF->fetchColumn();
    
    $stS = $pdo->prepare("SELECT pseudo FROM formateurs WHERE id = ?");
    $stS->execute([$s_id]);
    $sPseudo = $stS->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM formation_staff WHERE formation_id = ? AND formateur_id = ?");
    $stmt->execute([$f_id, $s_id]);
    
    addLog($pdo, "Modules : Retrait du formateur '$sPseudo' du module '$fTitre'");
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- RÉCUPÉRATION DES DONNÉES (Version SQL Server) ---
$formateurs = $pdo->query("SELECT id, pseudo FROM formateurs ORDER BY pseudo ASC")->fetchAll();

/** * Utilisation de STRING_AGG au lieu de GROUP_CONCAT. 
 * Note : On CAST les IDs en VARCHAR pour pouvoir concaténer avec le pseudo.
 */
$sql = "SELECT f.*, 
        STRING_AGG(CAST(s.id AS VARCHAR) + ':' + s.pseudo, '|') WITHIN GROUP (ORDER BY s.pseudo ASC) as staff_data
        FROM formations f
        LEFT JOIN formation_staff fs ON f.id = fs.formation_id
        LEFT JOIN formateurs s ON fs.formateur_id = s.id
        GROUP BY f.id, f.titre, f.referent_id, f.doc_link_2026, f.qst_link 
        ORDER BY f.titre ASC";
        
$formations = $pdo->query($sql)->fetchAll();

require_once 'includes/header.php';
?>

<style>
    html, body { height: 100%; margin: 0; }
    body { display: flex; flex-direction: column; min-height: 100vh; background-color: var(--bs-body-bg); }
    .main-content { flex: 1 0 auto; }
    .card { border-radius: 15px; overflow: hidden; background: var(--bs-card-bg); border: 1px solid var(--bs-border-color); }
    .btn-delete-module { 
        background: rgba(220, 53, 69, 0.1); 
        color: #dc3545; 
        border: 1px solid rgba(220, 53, 69, 0.2);
        transition: 0.3s;
    }
    .btn-delete-module:hover { background: #dc3545; color: white; }
    .badge-staff { background: var(--bs-tertiary-bg); color: var(--bs-body-color); border: 1px solid var(--bs-border-color); }
</style>

<div class="main-content">
    <div class="container py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold m-0">⚙️ Configuration des Modules</h2>
                <p class="text-muted small">Gérez les programmes, liens et formateurs rattachés</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddForma">
                <i class="bi bi-plus-circle me-2"></i>Nouveau Module
            </button>
        </div>

        <?php foreach ($formations as $f): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
                <span class="badge bg-dark">ID #<?= $f['id'] ?></span>
                <form method="POST" onsubmit="return confirm('⚠️ Action irréversible : Supprimer ce module ?')">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" name="delete_formation" class="btn btn-sm btn-delete-module">
                        <i class="bi bi-trash3 me-1"></i> Supprimer
                    </button>
                </form>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    
                    <div class="col-md-3">
                        <label class="small fw-bold mb-1">Nom du Module</label>
                        <input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($f['titre'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="small fw-bold mb-1 text-primary">Lead Référent</label>
                        <select name="lead_id" class="form-select border-primary">
                            <option value="">-- Aucun --</option>
                            <?php foreach($formateurs as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($f['referent_id'] == $s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['pseudo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold mb-1">Documentation 2026</label>
                        <input type="url" name="doc" class="form-control" value="<?= htmlspecialchars($f['doc_link_2026'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold mb-1 text-danger">Lien Questionnaire</label>
                        <input type="url" name="qst" class="form-control" value="<?= htmlspecialchars($f['qst_link'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" name="update_forma" class="btn btn-sm btn-success px-4 rounded-pill">
                            <i class="bi bi-save me-1"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h6 class="fw-bold mb-3 small text-uppercase text-muted">Équipe de formation</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php 
                            if (!empty($f['staff_data'])):
                                $staff_members = explode('|', $f['staff_data']);
                                foreach($staff_members as $member): 
                                    list($s_id, $s_pseudo) = explode(':', $member);
                            ?>
                                <span class="badge badge-staff p-2 d-flex align-items-center rounded-pill">
                                    <span class="me-2"><?= htmlspecialchars($s_pseudo) ?></span>
                                    <a href="?remove_staff=1&f_id=<?= $f['id'] ?>&s_id=<?= $s_id ?>" 
                                       class="text-danger lh-1 remove-staff-link" 
                                       onclick="return confirm('Retirer <?= htmlspecialchars($s_pseudo) ?> de ce module ?')">
                                         <i class="bi bi-x-circle-fill"></i>
                                    </a>
                                </span>
                            <?php 
                                endforeach; 
                            else:
                                echo '<span class="text-muted small">Aucun formateur assigné.</span>';
                            endif;
                            ?>
                        </div>
                    </div>
                    
                    <div class="col-md-5 mt-3 mt-md-0">
                        <form method="POST" class="input-group input-group-sm">
                            <input type="hidden" name="f_id" value="<?= $f['id'] ?>">
                            <select name="staff_id" class="form-select" required>
                                <option value="" disabled selected>Ajouter un formateur...</option>
                                <?php foreach($formateurs as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['pseudo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="add_staff" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="modalAddForma" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nouveau Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="small fw-bold mb-2">Titre de la formation</label>
                <input type="text" name="titre" class="form-control" placeholder="ex: Procédures Modération" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="add_formation" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const scrollpos = localStorage.getItem('scrollpos');
        if (scrollpos) window.scrollTo(0, scrollpos);

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                localStorage.setItem('scrollpos', window.scrollY);
            });
        });

        document.querySelectorAll('.remove-staff-link').forEach(link => {
            link.addEventListener('click', () => {
                localStorage.setItem('scrollpos', window.scrollY);
            });
        });
    });
</script>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>
