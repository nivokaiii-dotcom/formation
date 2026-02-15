<?php
require 'config.php';
require 'auth.php';

$formateurs = $pdo->query("SELECT * FROM formateurs")->fetchAll();
$types = $pdo->query("SELECT * FROM formations_types")->fetchAll();

if(isset($_POST['add'])){
    $stmt = $pdo->prepare("INSERT INTO formations_donnees (formateur_id, formation_id, date, participants) VALUES (?,?,?,?)");
    $stmt->execute([$_POST['formateur'], $_POST['formation'], $_POST['date'], $_POST['participants']]);
}
?>

<form method="POST">
<select name="formateur">
<?php foreach($formateurs as $f): ?>
<option value="<?= $f['id'] ?>"><?= $f['pseudo'] ?></option>
<?php endforeach; ?>
</select>

<select name="formation">
<?php foreach($types as $t): ?>
<option value="<?= $t['id'] ?>"><?= $t['nom'] ?></option>
<?php endforeach; ?>
</select>

<input type="date" name="date" required>
<input type="number" name="participants">
<button name="add">Ajouter</button>
</form>
