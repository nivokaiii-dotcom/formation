<?php
require 'config.php';
require 'includes/header.php';

// Sécurité : Vérification du rôle admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Accès refusé.");
}

$message = "";

/* =========================
   TRAITEMENT (AJOUT/MODIF/SUPPR)
========================= */

// AJOUT
if (isset($_POST['add'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO formateurs (discord_id, pseudo) VALUES (?, ?)");
        $stmt->execute([$_POST['discord_id'], $_POST['pseudo']]);
        header("Location: admin_formateurs.php");
        exit();
    } catch (PDOException $e) {
        $message = ($e->getCode() == 23000) ? "Erreur : Cet ID Discord est déjà utilisé." : "Erreur : " . $e->getMessage();
    }
}

// MODIFICATION
if (isset($_POST['edit'])) {
    try {
        $stmt = $pdo->prepare("UPDATE formateurs SET discord_id = ?, pseudo = ? WHERE id = ?");
        $stmt->execute([$_POST['discord_id'], $_POST['pseudo'], $_POST['id']]);
        header("Location: admin_formateurs.php");
        exit();
    } catch (PDOException $e) {
        $message = "Erreur modification : " . $e->getMessage();
    }
}

// SUPPRESSION
if (isset($_POST['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM formateurs WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header("Location: admin_formateurs.php");
    exit();
}

// RÉCUPÉRATION DES DONNÉES
$query = "SELECT f.*, u.avatar 
          FROM formateurs f 
          LEFT JOIN users u ON f.discord_id = u.discord_id 
          ORDER BY f.id DESC";
$formateurs = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
$totalFormateurs = count($formateurs);
?>

<style>
    /* Intégration du thème dynamique */
    .admin-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; }
    .dynamic-text { color: var(--text-main); }
    .dynamic-subtext { color: var(--text-main); opacity: 0.6; }
    
    .table thead { background: var(--table-header); }
    .table thead th { 
        color: var(--text-main); 
        text-transform: uppercase; 
        font-size: 0.75rem; 
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--border-color);
    }
    .table td { color: var(--text-main); border-bottom: 1px solid var(--border-color); }
    
    .profile-img { object-fit: cover; transition: all 0.2s ease; border: 2px solid var(--border-color); }
    .profile-img:hover { transform: scale(1.1); }
    
    .custom-input { 
        background-color: var(--table-header) !important; 
        border: 1px solid var(--border-color) !important; 
        color: var(--text-main) !important; 
    }
    .custom-input::placeholder { color: var(--text-main); opacity: 0.4; }
    
    code { font-size: 0.9rem; background: rgba(120, 120, 120, 0.1); color: var(--primary-accent); padding: 2px 6px; border-radius: 4px; }
    
    /* Modals Dark Mode */
    .modal-content { background-color: var(--card-bg); color: var(--text-main); border: 1px solid var(--border-color); }
    .modal-header { border-bottom: 1px solid var(--border-color); }
    .modal-footer { border-top: 1px solid var(--border-color); }
</style>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 dynamic-text">👥 Gestion des Formateurs</h2>
            <p class="dynamic-subtext">Total : <span id="memberCount" class="fw-bold text-primary"><?= $totalFormateurs ?></span> formateur(s)</p>
        </div>
        <button class="btn btn-success shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Ajouter un formateur
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="input-group shadow-sm">
                <span class="input-group-text custom-input">🔍</span>
                <input type="text" id="searchInput" class="form-control custom-input ps-2"
                    placeholder="Rechercher un pseudo ou un ID Discord...">
            </div>
        </div>
    </div>

    <div class="admin-card shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-3" style="width: 80px;">Avatar</th>
                        <th>Pseudo</th>
                        <th>ID Discord</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="formateurTableBody">
                    <?php foreach ($formateurs as $f): 
                        $pp = !empty($f['avatar']) ? $f['avatar'] : "https://unavatar.io/discord/" . $f['discord_id'] . "?fallback=https://ui-avatars.com/api/?name=" . urlencode($f['pseudo']) . "&background=random";
                    ?>
                        <tr class="formateur-row">
                            <td class="ps-3">
                                <img src="<?= $pp ?>" alt="Avatar" class="rounded-circle profile-img" width="45" height="45">
                            </td>
                            <td>
                                <span class="fw-bold pseudo-name dynamic-text"><?= htmlspecialchars($f['pseudo']); ?></span>
                            </td>
                            <td class="discord-id">
                                <code><?= htmlspecialchars($f['discord_id']); ?></code>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $f['id']; ?>">Modifier</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $f['id']; ?>">Retirer</button>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal<?= $f['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Modifier <?= htmlspecialchars($f['pseudo']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?= $f['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label dynamic-text">ID Discord</label>
                                                <input type="text" name="discord_id" class="form-control custom-input" value="<?= htmlspecialchars($f['discord_id']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label dynamic-text">Pseudo</label>
                                                <input type="text" name="pseudo" class="form-control custom-input" value="<?= htmlspecialchars($f['pseudo']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-link dynamic-subtext text-decoration-none" data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" name="edit" class="btn btn-warning px-4">Enregistrer</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="deleteModal<?= $f['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header border-0"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body text-center py-4">
                                            <input type="hidden" name="id" value="<?= $f['id']; ?>">
                                            <i class="bi bi-exclamation-octagon text-danger" style="font-size: 3.5rem;"></i>
                                            <h4 class="mt-3 dynamic-text">Confirmer le retrait ?</h4>
                                            <p class="dynamic-subtext">Voulez-vous vraiment retirer <strong><?= htmlspecialchars($f['pseudo']); ?></strong> de la liste des formateurs ?</p>
                                        </div>
                                        <div class="modal-footer border-0 justify-content-center">
                                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" name="delete" class="btn btn-danger px-4">Supprimer</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noResults" class="text-center py-5 d-none">
            <p class="dynamic-subtext mb-0">Aucun formateur ne correspond à votre recherche.</p>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Nouveau Formateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label dynamic-text">ID Discord</label>
                        <input type="text" name="discord_id" class="form-control custom-input" placeholder="Ex: 882717172575653928" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label dynamic-text">Pseudo</label>
                        <input type="text" name="pseudo" class="form-control custom-input" placeholder="Nom du formateur" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link dynamic-subtext text-decoration-none" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" name="add" class="btn btn-success px-4">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Filtre de recherche temps réel
    document.getElementById('searchInput').addEventListener('keyup', function () {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.formateur-row');
        let visibleCount = 0;
        let noResults = document.getElementById('noResults');

        rows.forEach(row => {
            let pseudo = row.querySelector('.pseudo-name').textContent.toLowerCase();
            let discordId = row.querySelector('.discord-id').textContent.toLowerCase();

            if (pseudo.includes(filter) || discordId.includes(filter)) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        document.getElementById('memberCount').textContent = visibleCount;
        noResults.classList.toggle('d-none', visibleCount > 0);
    });
</script>

<?php require 'includes/footer.php'; ?>