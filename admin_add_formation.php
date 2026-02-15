<?php
/**
 * Gestion des Formations - Admin Panel
 */

// 1. Initialisation de la session AVANT tout accès à $_SESSION

require 'includes/header.php';

require_once 'config.php';
// Note: Le header est inclus après la logique de redirection pour éviter l'erreur "Headers already sent"
// require 'includes/header.php'; 

// 2. Sécurité : Vérification admin stricte
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit("Accès refusé.");
}

// 3. Génération du Token CSRF préventive
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==================================
   4. TRAITEMENT DES ACTIONS (POST)
================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erreur de sécurité : Jeton invalide.");
    }

    try {
        // ACTION : AJOUTER
        if (isset($_POST['add'])) {
            $titre = trim($_POST['titre'] ?? '');
            if (!empty($titre)) {
                $stmt = $pdo->prepare("INSERT INTO formations (titre) VALUES (?)");
                $stmt->execute([$titre]);
                $_SESSION['flash'] = ["success", "La formation a été ajoutée avec succès !"];
            } else {
                $_SESSION['flash'] = ["warning", "Le titre ne peut pas être vide."];
            }
        }
        // ACTION : MODIFIER
        elseif (isset($_POST['edit'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $titre = trim($_POST['titre'] ?? '');

            if ($id && !empty($titre)) {
                $stmt = $pdo->prepare("UPDATE formations SET titre = ? WHERE id = ?");
                $stmt->execute([$titre, $id]);
                $_SESSION['flash'] = ["info", "La formation a été mise à jour."];
            }
        }
        // ACTION : SUPPRIMER
        elseif (isset($_POST['delete'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM formations WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash'] = ["danger", "La formation a été supprimée définitivement."];
            }
        }

        // Redirection pour éviter le renvoi du formulaire (Pattern PRG)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch (PDOException $e) {
        $_SESSION['flash'] = ["danger", "Erreur SQL : " . $e->getMessage()];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 5. RÉCUPÉRATION DES DONNÉES
$formations = $pdo->query("SELECT * FROM formations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Formations | Staff Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', sans-serif;
        }

        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary-custom {
            background: #6366f1;
            border: none;
            transition: all 0.2s;
            color: white;
        }

        .btn-primary-custom:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .alert-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }
    </style>
</head>

<body>

    <div class="container py-5">

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash[0] ?> alert-dismissible fade show alert-floating shadow-lg border-0"
                role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div><?= htmlspecialchars($flash[1]) ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">

                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary text-white p-3 rounded-3 me-3">
                        <i class="bi bi-mortarboard-fill fs-3"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0">Configuration des Formations</h2>
                        <p class="text-muted mb-0">Gestion des modules de formation du staff.</p>
                    </div>
                </div>

                <div class="card card-custom p-4 mb-5">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="row g-3">
                            <div class="col-md-9">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Nom de la nouvelle
                                    formation</label>
                                <input type="text" name="titre" class="form-control form-control-lg bg-light border-0"
                                    placeholder="Ex: Formation Modération..." required>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary-custom w-100 py-2 fw-bold" name="add" type="submit">
                                    <i class="bi bi-plus-lg me-2"></i>AJOUTER
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-container shadow-sm">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="ps-4 py-3">ID</th>
                                <th class="py-3">NOM DU MODULE</th>
                                <th class="text-end pe-4 py-3">OPTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($formations)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">Aucune formation créée.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($formations as $f): ?>
                                    <tr>
                                        <td class="ps-4 text-muted">#<?= $f['id']; ?></td>
                                        <td><span class="fw-bold text-dark"><?= htmlspecialchars($f['titre']); ?></span></td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary border-0 me-2"
                                                onclick="openEditModal(<?= $f['id']; ?>, '<?= htmlspecialchars(addslashes($f['titre'])); ?>')">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger border-0"
                                                onclick="openDeleteModal(<?= $f['id']; ?>, '<?= htmlspecialchars(addslashes($f['titre'])); ?>')">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialisation des instances de Modal
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        function openEditModal(id, titre) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_titre').value = titre;
            editModal.show();
        }

        function openDeleteModal(id, titre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_titre_text').innerText = titre;
            deleteModal.show();
        }
    </script>

</body>

</html>