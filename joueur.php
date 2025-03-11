<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// user=guest ?
$isGuest = ($_SESSION['user']==='guest');

$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    // Récup joueurs
    $q = $pdo->prepare("SELECT * FROM Joueur ORDER BY Statut='Absent' ASC, Statut='Blessé' ASC, Statut='Actif' ASC, Nom ASC");
    $q->execute();
    $joueurs = $q->fetchAll(PDO::FETCH_ASSOC);

    // Gestion des commentaires
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['commentaire'],$_POST['license'])) {
        if($_SESSION['user']==='guest'){
            die("Vous n'avez pas le droit d'ajouter un commentaire en tant qu'invité.");
        }
        $license = $_POST['license'];
        $commentaire = $_POST['commentaire'];

        $update = $pdo->prepare("UPDATE Joueur SET Commentaire=:c WHERE Numéro_de_license=:l");
        $update->execute([':c' => $commentaire, ':l' => $license]);

        header("Location: joueur.php");
        exit;
    }

} catch(PDOException $e){
    die("Erreur: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Liste des Joueurs</title>
  <link rel="stylesheet" href="styles.css">
  <script>
  function openModal(license){
    <?php if($isGuest): ?>
      alert("Vous n'avez pas le droit d'ajouter/modifier un commentaire en tant qu'invité.");
      return;
    <?php endif; ?>

    const m = document.getElementById('modal');
    m.style.display='flex';
    document.getElementById('licenseInput').value=license;
  }
  function closeModal(){
    document.getElementById('modal').style.display='none';
  }
  </script>
</head>
<body>
<div class="navbar">
  <div class="navbar-title">
    <a href="index.php">VolleyCoachPro</a>
  </div>
  <div class="navbar-menus">
    <div class="menu-item">
      <button class="menu-button">Menu ▼</button>
      <div class="dropdown">
        <a href="stats.php">Statistiques</a>
        <a href="rencontre.php">Rencontres</a>
        <a href="logout.php">Déconnexion</a>
      </div>
    </div>
  </div>
</div>

<div class="content-container">
<h1 class="content-title">Liste des Joueurs</h1>

<div class="content-box">
  <?php if($joueurs): ?>
    <table class="table">
      <thead>
        <tr>
          <th>Licence</th>
          <th>Nom</th>
          <th>Prénom</th>
          <th>Date Naiss</th>
          <th>Taille</th>
          <th>Poids</th>
          <th>Commentaire</th>
          <th>Statut</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($joueurs as $j): ?>
        <tr>
          <td><?= htmlspecialchars($j['Numéro_de_license'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Nom'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Prénom'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Date_de_naissance'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Taille'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Poids'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Commentaire'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($j['Statut'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <button
                <?= ($isGuest ? 'disabled' : '') ?>
                class="button-comment <?= ($isGuest ? 'button-disabled' : '') ?>"
                onclick="openModal('<?= htmlspecialchars($j['Numéro_de_license'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"
            >
                Ajouter un commentaire
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>Aucun joueur.</p>
  <?php endif; ?>
</div>

<?php if($_SESSION['user']==='guest'): ?>
  <div class="bottom-button-container">
    <p class="access-restricted">
        Vous n'avez pas accès à la gestion des joueurs en tant qu'invité.
    </p>
  </div>
<?php else: ?>
  <div class="bottom-button-container">
    <a href="gestion_joueur.php" class="button-manage">Gérer les joueurs</a>
  </div>
<?php endif; ?>
</div>

<div id="modal" class="modal">
  <div class="modal-content">
    <h2>Ajouter un Commentaire</h2>
    <form method="POST">
      <input type="hidden" name="license" id="licenseInput">
      <textarea name="commentaire" rows="4" required></textarea>
      <div>
        <button type="submit">Enregistrer</button>
        <button type="button" onclick="closeModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
