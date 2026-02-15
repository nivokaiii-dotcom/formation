<?php
require_once 'config.php';

// --- LOGIQUE DE TRAITEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Ajouter une nouvelle formation
    if (isset($_POST['add_formation'])) {
        $titre = !empty($_POST['titre']) ? $_POST['titre'] : 'Nouveau Module';
        $stmt = $pdo->prepare("INSERT INTO formations (titre) VALUES (?)");
        $stmt->execute([$titre]);
    }

    // 2. Supprimer une formation (Action critique)
    if (isset($_POST['delete_formation'])) {
        $id = (int)$_POST['id'];
        // Supprime d'abord les liaisons staff pour éviter les erreurs de clés étrangères
        $pdo->prepare("DELETE FROM formation_staff WHERE formation_id = ?")->execute([$id]);
        // Supprime la formation
        $pdo->prepare("DELETE FROM formations WHERE id = ?")->execute([$id]);
    }

    // 3. Mise à jour formation
    if (isset($_POST['update_forma'])) {
        $lead = !empty($_POST['lead_id']) ? $_POST['lead_id'] : null;
        $stmt = $pdo->prepare("UPDATE formations SET titre=?, referent_id=?, doc_link_2026=?, qst_link=? WHERE id=?");
        $stmt->execute([$_POST['titre'], $lead, $_POST['doc'], $_POST['qst'], $_POST['id']]);
    }

    // 4. Ajout Staff
    if (isset($_POST['add_staff'])) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO formation_staff (formation_id, formateur_id) VALUES (?, ?)");
        $stmt->execute([$_POST['f_id'], $_POST['staff_id']]);
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 5. Retrait Staff
if (isset($_GET['remove_staff'], $_GET['f_id'], $_GET['s_id'])) {
    $stmt = $pdo->prepare("DELETE FROM formation_staff WHERE formation_id = ? AND formateur_id = ?");
    $stmt->execute([$_GET['f_id'], $_GET['s_id']]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- RÉCUPÉRATION DES DONNÉES ---
$formateurs = $pdo->query("SELECT id, pseudo FROM formateurs ORDER BY pseudo ASC")->fetchAll();
$sql = "SELECT f.*, 
        GROUP_CONCAT(CONCAT(s.id, ':', s.pseudo) SEPARATOR '|') as staff_data
        FROM formations f
        LEFT JOIN formation_staff fs ON f.id = fs.formation_id
        LEFT JOIN formateurs s ON fs.formateur_id = s.id
        GROUP BY f.id
        ORDER BY f.titre ASC";
$formations = $pdo->query($sql)->fetchAll();

require_once 'includes/header.php';
?>

<style>
    html, body { height: 100%; margin: 0; }
    body { display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1 0 auto; }
    .card { border-radius: 15px; overflow: hidden; }
    .btn-delete-module { 
        background: rgba(220, 53, 69, 0.1); 
        color: #dc3545; 
        border: 1px solid rgba(220, 53, 69, 0.2);
        transition: 0.3s;
    }
    .btn-delete-module:hover { background: #dc3545; color: white; }
</style>

<div class="main-content">
    <div class="container py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold m-0">Configuration des Modules</h2>
                <p class="text-muted small">Gérez les programmes et les intervenants</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddForma">
                <i class="bi bi-plus-circle me-2"></i>Nouveau Module
            </button>
        </div>

        <?php foreach ($formations as $f): ?>
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <span class="badge bg-light text-dark border">ID #<?= $f['id'] ?></span>
                <form method="POST" onsubmit="return confirm('⚠️ ATTENTION : Voulez-vous vraiment SUPPRIMER ce module ?\n\nCela supprimera définitivement le module et tous les liens avec les formateurs rattachés.')">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" name="delete_formation" class="btn btn-sm btn-delete-module">
                        <i class="bi bi-trash3 me-1"></i> Supprimer le module
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
                        <label class="small fw-bold mb-1 text-primary">LEAD (Responsable)</label>
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
                        <label class="small fw-bold mb-1">Doc 2026</label>
                        <input type="url" name="doc" class="form-control" value="<?= htmlspecialchars($f['doc_link_2026'] ?? '') ?>" placeholder="Lien documentation">
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold mb-1 text-danger">Questionnaire</label>
                        <input type="url" name="qst" class="form-control" value="<?= htmlspecialchars($f['qst_link'] ?? '') ?>" placeholder="Lien questionnaire">
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" name="update_forma" class="btn btn-sm btn-success px-4 rounded-pill">
                            <i class="bi bi-check-lg me-1"></i> Enregistrer les infos
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h6 class="fw-bold mb-3"><i class="bi bi-people-fill me-2"></i>Formateurs rattachés</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php 
                            if (!empty($f['staff_data'])):
                                $staff_members = explode('|', $f['staff_data']);
                                foreach($staff_members as $member): 
                                    list($s_id, $s_pseudo) = explode(':', $member);
                            ?>
                                <span class="badge bg-white text-dark border p-2 d-flex align-items-center">
                                    <?= htmlspecialchars($s_pseudo) ?> 
                                    <a href="?remove_staff=1&f_id=<?= $f['id'] ?>&s_id=<?= $s_id ?>" 
                                       class="text-danger ms-2" 
                                       onclick="return confirm('Retirer ce formateur ?')">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </a>
                                </span>
                            <?php 
                                endforeach; 
                            else:
                                echo '<span class="text-muted small italic">Aucun formateur rattaché.</span>';
                            endif;
                            ?>
                        </div>
                    </div>
                    
                    <div class="col-md-5 mt-3 mt-md-0">
                        <form method="POST" class="input-group input-group-sm">
                            <input type="hidden" name="f_id" value="<?= $f['id'] ?>">
                            <select name="staff_id" class="form-select" required>
                                <option value="" disabled selected>Rattacher un formateur...</option>
                                <?php foreach($formateurs as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['pseudo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="add_staff" class="btn btn-primary">
                                <i class="bi bi-plus"></i>
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
                <h5 class="modal-title fw-bold">Nouveau Module de Formation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="small fw-bold mb-2">Nom du module</label>
                <input type="text" name="titre" class="form-control" placeholder="ex: Communication Niveau 1" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="add_formation" class="btn btn-primary">Créer le module</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>