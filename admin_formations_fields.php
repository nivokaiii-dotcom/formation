<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
require_once 'config.php';
require_once 'helpers/FormationFieldsHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$helper = null;
$error_msg = "";
$success_msg = "";

// Initialiser le helper
try {
    $helper = new FormationFieldsHelper($pdo);
} catch (Exception $e) {
    $error_msg = "Erreur lors de l'initialisation : " . $e->getMessage();
    error_log($error_msg);
    $helper = new FormationFieldsHelper($pdo); // Essayer quand même
}

/* ==================================
    FONCTION DE LOGS
================================== */
function addLog($pdo, $action) {
    $user = 'Anonyme';
    if (isset($_SESSION['user']['username'])) {
        $user = $_SESSION['user']['username'];
    } elseif (isset($_SESSION['username'])) {
        $user = $_SESSION['username'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO logs (utilisateur, action, date_action) VALUES (?, ?, NOW())");
        $stmt->execute([$user, $action]);
    } catch (PDOException $e) {
        error_log("Erreur Log : " . $e->getMessage());
    }
}

// --- TRAITEMENT DES REQUÊTES ---

// Ajouter un champ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_field'])) {
    try {
        $formation_id = (int)$_POST['formation_id'];
        $label = $_POST['label'] ?? '';
        $field_type = $_POST['field_type'] ?? 'text';
        $placeholder = $_POST['placeholder'] ?? '';
        $required = isset($_POST['required']) ? 1 : 0;
        
        if (empty($label)) {
            throw new Exception("Le libellé du champ est obligatoire");
        }

        if (empty($field_type)) {
            throw new Exception("Le type de champ est obligatoire");
        }
        
        $config = null;
        
        // Gérer les options pour les selects
        if ($field_type === 'select' && !empty($_POST['select_options'])) {
            $options = array_filter(array_map('trim', explode("\n", $_POST['select_options'])));
            if (empty($options)) {
                throw new Exception("Veuillez entrer au moins une option pour le champ sélection");
            }
            $config = ['options' => array_values($options)];
        }
        
        // Gérer la config des boutons
        if ($field_type === 'button') {
            $config = [
                'action' => $_POST['button_action'] ?? 'link',
                'button_text' => $_POST['button_text'] ?? 'Cliquez ici',
                'button_color' => $_POST['button_color'] ?? 'primary'
            ];
        }

        $field_id = $helper->addField($formation_id, $label, $field_type, $placeholder, $required, $config);
        
        if ($field_id) {
            addLog($pdo, "Champs dynamiques : Ajout du champ '$label' ($field_type) à la formation ID $formation_id");
            header("Location: " . $_SERVER['PHP_SELF'] . "?formation_id=$formation_id&success=field_added");
            exit;
        } else {
            throw new Exception("Impossible d'ajouter le champ");
        }
    } catch (Exception $e) {
        $error_msg = "Erreur lors de l'ajout du champ : " . $e->getMessage();
        error_log($error_msg);
    }
}

// Mettre à jour un champ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_field'])) {
    try {
        $field_id = (int)$_POST['field_id'];
        $formation_id = (int)$_POST['formation_id'];
        $label = $_POST['label'] ?? '';
        $placeholder = $_POST['placeholder'] ?? '';
        $required = isset($_POST['required']) ? 1 : 0;
        
        if (empty($label)) {
            throw new Exception("Le libellé du champ est obligatoire");
        }

        $config = null;
        
        // Récupérer le type du champ
        $stmtType = $pdo->prepare("SELECT field_type FROM formation_custom_fields WHERE id = ?");
        $stmtType->execute([$field_id]);
        $field_type = $stmtType->fetchColumn();
        
        if (!$field_type) {
            throw new Exception("Champ non trouvé");
        }
        
        if ($field_type === 'select' && !empty($_POST['select_options'])) {
            $options = array_filter(array_map('trim', explode("\n", $_POST['select_options'])));
            if (empty($options)) {
                throw new Exception("Veuillez entrer au moins une option pour le champ sélection");
            }
            $config = ['options' => array_values($options)];
        }
        
        if ($field_type === 'button') {
            $config = [
                'action' => $_POST['button_action'] ?? 'link',
                'button_text' => $_POST['button_text'] ?? 'Cliquez ici',
                'button_color' => $_POST['button_color'] ?? 'primary'
            ];
        }

        if ($helper->updateField($field_id, $label, $placeholder, $required, $config)) {
            addLog($pdo, "Champs dynamiques : Mise à jour du champ '$label'");
            header("Location: " . $_SERVER['PHP_SELF'] . "?formation_id=$formation_id&success=field_updated");
            exit;
        } else {
            throw new Exception("Impossible de mettre à jour le champ");
        }
    } catch (Exception $e) {
        $error_msg = "Erreur lors de la mise à jour : " . $e->getMessage();
        error_log($error_msg);
    }
}

// Supprimer un champ
if (isset($_GET['delete_field'], $_GET['formation_id'])) {
    try {
        $field_id = (int)$_GET['delete_field'];
        $formation_id = (int)$_GET['formation_id'];
        
        $stmt = $pdo->prepare("SELECT label FROM formation_custom_fields WHERE id = ?");
        $stmt->execute([$field_id]);
        $label = $stmt->fetchColumn();
        
        if (!$label) {
            throw new Exception("Champ non trouvé");
        }
        
        if ($helper->deleteField($field_id)) {
            addLog($pdo, "Champs dynamiques : Suppression du champ '$label'");
            header("Location: " . $_SERVER['PHP_SELF'] . "?formation_id=$formation_id&success=field_deleted");
            exit;
        } else {
            throw new Exception("Impossible de supprimer le champ");
        }
    } catch (Exception $e) {
        $error_msg = "Erreur lors de la suppression : " . $e->getMessage();
        error_log($error_msg);
    }
}

// Réordonnage des champs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_fields'])) {
    try {
        $formation_id = (int)$_POST['formation_id'];
        $field_ids = isset($_POST['field_order']) ? explode(',', $_POST['field_order']) : [];
        
        if (!empty($field_ids)) {
            $field_ids = array_map('intval', $field_ids);
            $helper->reorderFields($formation_id, $field_ids);
            addLog($pdo, "Champs dynamiques : Réorganisation des champs");
            header("Location: " . $_SERVER['PHP_SELF'] . "?formation_id=$formation_id&success=reordered");
            exit;
        }
    } catch (Exception $e) {
        $error_msg = "Erreur lors de la réorganisation : " . $e->getMessage();
        error_log($error_msg);
    }
}

// Sauvegarder les valeurs des champs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_values'])) {
    try {
        $formation_id = (int)$_POST['formation_id'];
        
        if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            foreach ($_POST['custom_fields'] as $field_id => $value) {
                $helper->saveFieldValue($formation_id, (int)$field_id, $value);
            }
            addLog($pdo, "Champs dynamiques : Mise à jour des valeurs");
            header("Location: " . $_SERVER['PHP_SELF'] . "?formation_id=$formation_id&success=values_saved");
            exit;
        } else {
            throw new Exception("Aucun champ à enregistrer");
        }
    } catch (Exception $e) {
        $error_msg = "Erreur lors de la sauvegarde : " . $e->getMessage();
        error_log($error_msg);
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$formation_id = isset($_GET['formation_id']) ? (int)$_GET['formation_id'] : null;
$field_types = [];
$custom_fields = [];
$formation = null;
$all_formations = [];

try {
    $field_types = $helper->getFieldTypes();
} catch (Exception $e) {
    $error_msg = "Erreur lors de la récupération des types : " . $e->getMessage();
    error_log($error_msg);
}

if ($formation_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        $formation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($formation) {
            $custom_fields = $helper->getFormationFields($formation_id);
        }
    } catch (Exception $e) {
        $error_msg = "Erreur lors de la récupération de la formation : " . $e->getMessage();
        error_log($error_msg);
    }
}

// Récupération de toutes les formations
try {
    $stmt = $pdo->query("SELECT id, titre FROM formations ORDER BY titre ASC");
    $all_formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_formations = [];
    $error_msg = "Erreur lors de la récupération des formations : " . $e->getMessage();
    error_log($error_msg);
}

require_once 'includes/header.php';
?>

<style>
    .field-item {
        background: var(--bs-card-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 12px;
        position: relative;
        transition: all 0.3s ease;
    }

    .field-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .field-item.dragging {
        opacity: 0.5;
        background: var(--bs-tertiary-bg);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .drag-handle {
        cursor: grab;
        color: #999;
        margin-right: 10px;
        font-size: 1.2rem;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    .btn-field-edit {
        padding: 6px 12px;
        font-size: 0.85rem;
        margin-left: 4px;
    }

    .config-section {
        background: rgba(0, 0, 0, 0.02);
        border-left: 4px solid var(--bs-primary);
        padding: 12px;
        border-radius: 8px;
        margin-top: 12px;
        display: none;
    }

    .config-section.show {
        display: block;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="container py-4">
    <?php if (!$formation_id): ?>
        <!-- SÉLECTION DE FORMATION -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 rounded-4" style="background: var(--bs-card-bg);">
                    <div class="card-body p-5 text-center">
                        <h2 class="fw-bold mb-4">📋 Gestionnaire de Champs Dynamiques</h2>
                        <p class="text-muted mb-4">Sélectionnez une formation pour ajouter des champs personnalisés</p>

                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($error_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($all_formations)): ?>
                            <div class="list-group">
                                <?php foreach ($all_formations as $f): ?>
                                    <a href="?formation_id=<?= $f['id'] ?>" class="list-group-item list-group-item-action rounded-3 mb-2 transition">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold"><?= htmlspecialchars($f['titre']) ?></span>
                                            <span class="badge bg-primary">ID #<?= $f['id'] ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-danger"><i class="bi bi-exclamation-circle me-2"></i>Aucune formation trouvée.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- GESTION DES CHAMPS POUR UNE FORMATION -->
        
        <div class="mb-4">
            <a href="?formation_id=" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour à la sélection
            </a>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php
                    $success = $_GET['success'];
                    if ($success === 'field_added') echo "✅ Champ ajouté avec succès !";
                    elseif ($success === 'field_updated') echo "✅ Champ mis à jour !";
                    elseif ($success === 'field_deleted') echo "✅ Champ supprimé !";
                    elseif ($success === 'reordered') echo "✅ Champs réorganisés !";
                    elseif ($success === 'values_saved') echo "✅ Valeurs enregistrées !";
                    else echo "✅ Opération réussie !";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($formation): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold m-0">⚙️ <?= htmlspecialchars($formation['titre']) ?></h2>
                    <p class="text-muted small">Gérez les champs personnalisés de cette formation</p>
                </div>
                <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAddField">
                    <i class="bi bi-plus-circle me-2"></i> Ajouter un champ
                </button>
            </div>

            <!-- ZONE DE VALEURS -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-light-subtle border-0 p-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Valeurs des Champs</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($custom_fields)): ?>
                        <p class="text-muted text-center py-4">
                            <i class="bi bi-inbox me-2"></i>
                            Aucun champ personnalisé. Commencez par en ajouter un !
                        </p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="formation_id" value="<?= $formation_id ?>">
                            
                            <?php foreach ($custom_fields as $field): ?>
                                <?php echo $helper->renderField($field, $field['value'] ?? ''); ?>
                            <?php endforeach; ?>

                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" name="save_values" class="btn btn-success btn-lg rounded-pill">
                                    <i class="bi bi-save me-2"></i> Enregistrer les valeurs
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ZONE DE GESTION DES CHAMPS -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-light-subtle border-0 p-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Configuration des Champs</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($custom_fields)): ?>
                        <p class="text-muted text-center py-4">
                            <i class="bi bi-inbox me-2"></i>
                            Aucun champ personnalisé
                        </p>
                    <?php else: ?>
                        <div id="fields-container" class="sortable-container">
                            <?php foreach ($custom_fields as $field): ?>
                                <div class="field-item" data-field-id="<?= $field['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <span class="drag-handle" title="Glissez pour réorganiser">
                                                <i class="bi bi-grip-vertical"></i>
                                            </span>
                                            <strong><?= htmlspecialchars($field['label']) ?></strong>
                                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($field['field_type']) ?></span>
                                            <?php if ($field['required']): ?>
                                                <span class="badge bg-danger">Requis</span>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-1">
                                                Champ: <code><?= htmlspecialchars($field['field_name']) ?></code>
                                            </div>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-field-edit" 
                                                    data-field-id="<?= $field['id'] ?>" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditField"
                                                    title="Éditer ce champ">
                                                <i class="bi bi-pencil"></i> Éditer
                                            </button>
                                            <a href="?formation_id=<?= $formation_id ?>&delete_field=<?= $field['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger btn-field-edit" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce champ ?')"
                                               title="Supprimer ce champ">
                                                <i class="bi bi-trash"></i> Supprimer
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Formation non trouvée.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- MODAL AJOUTER CHAMP -->
<div class="modal fade" id="modalAddField" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 bg-light-subtle">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Ajouter un Champ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="formation_id" value="<?= $formation_id ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold">Libellé du champ <span class="text-danger">*</span></label>
                    <input type="text" name="label" class="form-control" placeholder="ex: Documentation Additionnelle" required>
                    <small class="text-muted">Le nom du champ qui s'affichera à l'utilisateur</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Type de champ <span class="text-danger">*</span></label>
                    <select name="field_type" class="form-select" id="fieldTypeSelect" required>
                        <option value="">-- Sélectionner un type --</option>
                        <?php foreach ($field_types as $type): ?>
                            <option value="<?= $type['name'] ?>"><?= htmlspecialchars($type['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1" id="typeDescription"></small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Placeholder (optionnel)</label>
                    <input type="text" name="placeholder" class="form-control" placeholder="Texte d'aide affiché dans le champ">
                    <small class="text-muted">Texte qui s'affiche à l'intérieur du champ quand il est vide</small>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="required" id="requiredCheck">
                    <label class="form-check-label fw-bold" for="requiredCheck">
                        Champ obligatoire
                    </label>
                    <small class="text-muted d-block">Cochez si ce champ doit être rempli obligatoirement</small>
                </div>

                <!-- CONFIG SPECIFIQUE SELECT -->
                <div class="config-section" id="configSelect">
                    <label class="form-label fw-bold">Options <span class="text-danger">*</span></label>
                    <textarea name="select_options" class="form-control" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                    <small class="text-muted d-block mt-1">Entrez une option par ligne</small>
                </div>

                <!-- CONFIG SPECIFIQUE BUTTON -->
                <div class="config-section" id="configButton">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Texte du bouton</label>
                        <input type="text" name="button_text" class="form-control" placeholder="Cliquez ici" value="Cliquez ici">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Type d'action</label>
                        <select name="button_action" class="form-select">
                            <option value="link">Lien externe</option>
                            <option value="copy">Copier au presse-papiers</option>
                            <option value="email">Email</option>
                        </select>
                        <small class="text-muted d-block mt-1">
                            • Lien externe : ouvre une URL<br>
                            • Copier : copie le contenu au presse-papiers<br>
                            • Email : envoie un email
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Couleur du bouton</label>
                        <select name="button_color" class="form-select">
                            <option value="primary">Bleu (Primary)</option>
                            <option value="success">Vert (Success)</option>
                            <option value="info">Cyan (Info)</option>
                            <option value="warning">Orange (Warning)</option>
                            <option value="danger">Rouge (Danger)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light-subtle">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="add_field" class="btn btn-primary">
                    <i class="bi bi-check me-1"></i>Créer le champ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ÉDITER CHAMP -->
<div class="modal fade" id="modalEditField" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 bg-light-subtle">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Éditer le Champ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="formation_id" value="<?= $formation_id ?>">
                <input type="hidden" name="field_id" id="editFieldId">

                <div class="mb-3">
                    <label class="form-label fw-bold">Libellé du champ <span class="text-danger">*</span></label>
                    <input type="text" name="label" id="editLabel" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Type de champ</label>
                    <input type="text" id="editFieldType" class="form-control" disabled>
                    <small class="text-muted d-block mt-1">Le type de champ ne peut pas être modifié</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Placeholder (optionnel)</label>
                    <input type="text" name="placeholder" id="editPlaceholder" class="form-control">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="required" id="editRequiredCheck">
                    <label class="form-check-label fw-bold" for="editRequiredCheck">
                        Champ obligatoire
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light-subtle">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="update_field" class="btn btn-primary">
                    <i class="bi bi-check me-1"></i>Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du type de champ et affichage des configs
    const fieldTypeSelect = document.getElementById('fieldTypeSelect');
    const fieldTypes = <?= json_encode($field_types) ?>;

    if (fieldTypeSelect) {
        fieldTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            const type = fieldTypes.find(t => t.name === selectedType);
            
            const typeDescElement = document.getElementById('typeDescription');
            if (typeDescElement) {
                typeDescElement.textContent = type ? type.description : '';
            }
            
            document.querySelectorAll('[id^="config"]').forEach(el => el.classList.remove('show'));
            
            if (selectedType === 'select') {
                const configSelect = document.getElementById('configSelect');
                if (configSelect) configSelect.classList.add('show');
            } else if (selectedType === 'button') {
                const configButton = document.getElementById('configButton');
                if (configButton) configButton.classList.add('show');
            }
        });
    }

    // Édition des champs
    document.querySelectorAll('.btn-field-edit[data-field-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            const fieldId = this.dataset.fieldId;
            const container = document.querySelector(`[data-field-id="${fieldId}"]`);
            
            if (container) {
                const label = container.querySelector('strong')?.textContent || '';
                const fieldType = container.querySelector('.badge.bg-secondary')?.textContent || '';
                const required = container.querySelector('.badge.bg-danger') !== null;
                
                document.getElementById('editFieldId').value = fieldId;
                document.getElementById('editLabel').value = label;
                document.getElementById('editFieldType').value = fieldType;
                document.getElementById('editRequiredCheck').checked = required;
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>