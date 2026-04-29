<?php
/**
 * Helper pour gérer les champs personnalisés des formations
 */

class FormationFieldsHelper
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les champs d'une formation
     */
    public function getFormationFields($formation_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT fcf.*, ffv.value 
                FROM formation_custom_fields fcf
                LEFT JOIN formation_field_values ffv 
                    ON fcf.id = ffv.custom_field_id AND fcf.formation_id = ffv.formation_id
                WHERE fcf.formation_id = ?
                ORDER BY fcf.order_index ASC
            ");
            $stmt->execute([$formation_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getFormationFields : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ajoute un nouveau champ à une formation
     */
    public function addField($formation_id, $label, $field_type, $placeholder = null, $required = 0, $config = null)
    {
        try {
            // Générer un field_name unique basé sur le label
            $field_name = $this->generateFieldName($label);
            
            // Obtenir le prochain order_index
            $stmtOrder = $this->pdo->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 as next_order FROM formation_custom_fields WHERE formation_id = ?");
            $stmtOrder->execute([$formation_id]);
            $next_order = $stmtOrder->fetch(PDO::FETCH_ASSOC)['next_order'];

            $stmt = $this->pdo->prepare("
                INSERT INTO formation_custom_fields 
                (formation_id, field_name, field_type, label, placeholder, required, order_index, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $config_json = $config ? json_encode($config) : null;
            
            $result = $stmt->execute([
                $formation_id,
                $field_name,
                $field_type,
                $label,
                $placeholder,
                $required,
                $next_order,
                $config_json
            ]);

            return $result ? $this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Erreur addField : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour un champ
     */
    public function updateField($field_id, $label, $placeholder = null, $required = 0, $config = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE formation_custom_fields 
                SET label = ?, placeholder = ?, required = ?, config = ?
                WHERE id = ?
            ");

            $config_json = $config ? json_encode($config) : null;
            
            return $stmt->execute([
                $label,
                $placeholder,
                $required,
                $config_json,
                $field_id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur updateField : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un champ
     */
    public function deleteField($field_id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM formation_custom_fields WHERE id = ?");
            return $stmt->execute([$field_id]);
        } catch (PDOException $e) {
            error_log("Erreur deleteField : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sauvegarde la valeur d'un champ
     */
    public function saveFieldValue($formation_id, $custom_field_id, $value)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO formation_field_values (formation_id, custom_field_id, value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
            ");

            return $stmt->execute([$formation_id, $custom_field_id, $value]);
        } catch (PDOException $e) {
            error_log("Erreur saveFieldValue : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Réordonne les champs
     */
    public function reorderFields($formation_id, $field_ids)
    {
        try {
            $order = 0;
            foreach ($field_ids as $field_id) {
                $stmt = $this->pdo->prepare("UPDATE formation_custom_fields SET order_index = ? WHERE id = ? AND formation_id = ?");
                $stmt->execute([$order, $field_id, $formation_id]);
                $order++;
            }
            return true;
        } catch (PDOException $e) {
            error_log("Erreur reorderFields : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les types de champs disponibles
     */
    public function getFieldTypes()
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM formation_custom_field_types ORDER BY label ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getFieldTypes : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Génère un field_name unique à partir du label
     */
    private function generateFieldName($label)
    {
        // Convertir en minuscules et remplacer espaces/caractères spéciaux
        $name = strtolower(trim($label));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name ?: 'field_' . time();
    }

    /**
     * Rend un champ HTML
     */
    public function renderField($field, $value = '')
    {
        $html = '';
        $field_id = "field_{$field['id']}";
        $value = htmlspecialchars($value ?? '');
        $label_class = $field['required'] ? 'fw-bold' : '';

        switch ($field['field_type']) {
            case 'text':
                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}' for='{$field_id}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <input type='text' 
                            class='form-control' 
                            id='{$field_id}'
                            name='custom_fields[{$field['id']}]'
                            placeholder='{$field['placeholder']}'
                            value='{$value}'
                            " . ($field['required'] ? 'required' : '') . ">
                    </div>
                ";
                break;

            case 'textarea':
                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}' for='{$field_id}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <textarea 
                            class='form-control' 
                            id='{$field_id}'
                            name='custom_fields[{$field['id']}]'
                            placeholder='{$field['placeholder']}'
                            rows='4'
                            " . ($field['required'] ? 'required' : '') . ">{$value}</textarea>
                    </div>
                ";
                break;

            case 'url':
                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}' for='{$field_id}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <input type='url' 
                            class='form-control' 
                            id='{$field_id}'
                            name='custom_fields[{$field['id']}]'
                            placeholder='https://...'
                            value='{$value}'
                            " . ($field['required'] ? 'required' : '') . ">
                    </div>
                ";
                break;

            case 'pdf':
                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}' for='{$field_id}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <input type='url' 
                            class='form-control' 
                            id='{$field_id}'
                            name='custom_fields[{$field['id']}]'
                            placeholder='https://exemple.com/document.pdf'
                            value='{$value}'
                            " . ($field['required'] ? 'required' : '') . ">
                        <small class='text-muted d-block mt-1'>Entrez l'URL du fichier PDF</small>
                    </div>
                ";
                break;

            case 'badge':
                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}' for='{$field_id}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <input type='text' 
                            class='form-control' 
                            id='{$field_id}'
                            name='custom_fields[{$field['id']}]'
                            placeholder='Texte du badge'
                            value='{$value}'
                            " . ($field['required'] ? 'required' : '') . ">
                    </div>
                ";
                break;

            case 'button':
                $config = $field['config'] ? json_decode($field['config'], true) : [];
                $action = $config['action'] ?? 'link';
                $button_text = $config['button_text'] ?? 'Cliquez ici';
                $button_color = $config['button_color'] ?? 'primary';

                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <div class='row g-2'>
                            <div class='col-md-8'>
                                <input type='text' 
                                    class='form-control' 
                                    id='{$field_id}'
                                    name='custom_fields[{$field['id']}]'
                                    placeholder='{$field['placeholder']}'
                                    value='{$value}'
                                    " . ($field['required'] ? 'required' : '') . ">
                            </div>
                            <div class='col-md-4'>
                                <select class='form-select' name='button_action[{$field['id']}]'>
                                    <option value='link' " . ($action === 'link' ? 'selected' : '') . ">Lien externe</option>
                                    <option value='copy' " . ($action === 'copy' ? 'selected' : '') . ">Copier</option>
                                    <option value='email' " . ($action === 'email' ? 'selected' : '') . ">Email</option>
                                </select>
                            </div>
                        </div>
                    </div>
                ";
                break;

            case 'select':
                $config = $field['config'] ? json_decode($field['config'], true) : [];
                $options = $config['options'] ?? [];
                
                $option_html = '';
                foreach ($options as $opt) {
                    $selected = ($value === $opt) ? 'selected' : '';
                    $option_html .= "<option value='" . htmlspecialchars($opt) . "' {$selected}>" . htmlspecialchars($opt) . "</option>";
                }

                $html = "
                    <div class='mb-3'>
                        <label class='form-label {$label_class}' for='{$field_id}'>
                            {$field['label']}
                            " . ($field['required'] ? '<span class=\"text-danger\">*</span>' : '') . "
                        </label>
                        <select class='form-select' 
                            id='{$field_id}'
                            name='custom_fields[{$field['id']}]'
                            " . ($field['required'] ? 'required' : '') . ">
                            <option value=''>-- Sélectionner --</option>
                            {$option_html}
                        </select>
                    </div>
                ";
                break;
        }

        return $html;
    }

    /**
     * Affiche un champ en lecture seule (pour le dashboard)
     */
    public function displayField($field, $value = '')
    {
        $value = htmlspecialchars($value ?? '');
        $label = htmlspecialchars($field['label']);

        switch ($field['field_type']) {
            case 'text':
            case 'textarea':
                return "<div class='mb-2'><small class='text-muted'>{$label}</small><p class='mb-0'>{$value}</p></div>";

            case 'url':
            case 'pdf':
                if (empty($value)) return "<div class='mb-2'><small class='text-muted'>{$label}</small><p class='text-danger mb-0'>Non défini</p></div>";
                $icon = $field['field_type'] === 'pdf' ? 'bi-file-pdf' : 'bi-link-45deg';
                return "<div class='mb-2'><a href='" . htmlspecialchars($value) . "' target='_blank' class='btn btn-sm btn-outline-primary'><i class='bi {$icon}'></i> {$label}</a></div>";

            case 'badge':
                if (empty($value)) return '';
                return "<span class='badge bg-info'>" . htmlspecialchars($value) . "</span> ";

            case 'button':
                $config = $field['config'] ? json_decode($field['config'], true) : [];
                $action = $config['action'] ?? 'link';
                $button_text = $config['button_text'] ?? $label;
                $button_color = $config['button_color'] ?? 'primary';

                if (empty($value)) return "<div class='mb-2'><small class='text-muted'>{$label}</small><p class='text-danger mb-0'>Non configuré</p></div>";

                $onclick = '';
                $href = htmlspecialchars($value);
                
                if ($action === 'copy') {
                    $onclick = "onclick='navigator.clipboard.writeText(\"" . addslashes($value) . "\"); alert(\"Copié !\");' style='cursor: pointer;'";
                    $href = '#';
                } elseif ($action === 'email') {
                    $href = "mailto:" . htmlspecialchars($value);
                }

                return "<a href='{$href}' class='btn btn-sm btn-{$button_color}' target='_blank' {$onclick}><i class='bi bi-cursor-fill'></i> {$button_text}</a> ";

            case 'select':
                return "<div class='mb-2'><small class='text-muted'>{$label}</small><p class='badge bg-secondary mb-0'>" . htmlspecialchars($value) . "</p></div>";

            default:
                return "<div class='mb-2'><small>{$label}</small><p>" . htmlspecialchars($value) . "</p></div>";
        }
    }
}
?>