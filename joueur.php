<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['token'])) {
  header("Location: login.php");
  exit;
}

// user=guest ?
$isGuest = ($_SESSION['user']==='guest');

// Récupérer le token JWT stocké en session
$token = $_SESSION['token'];

// URL de l'API
$url = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/";

// Initialisation de cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);

// Exécuter la requête
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Vérifier la réponse
if ($http_code !== 200) {
    die("Erreur lors de la récupération des joueurs.");
}

// Convertir la réponse JSON en tableau PHP
$data = json_decode($response, true);

// Récup joueurs
$joueurs = $data['data'];

// Gestion des commentaires via l'API de gestion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commentaire'], $_POST['IdJoueur'])) {
    if ($_SESSION['user'] === 'guest') {
        die("Vous n'avez pas le droit d'ajouter un commentaire en tant qu'invité.");
    }

    $IdJoueur = $_POST['IdJoueur'];
    $licence = $_POST['licence'];
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $naissance = $_POST['naissance'];
    $taille = $_POST['taille'];
    $poids = $_POST['poids'];
    $statut = $_POST['statut'];
    $commentaire = $_POST['commentaire'];

    $update_url = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/$IdJoueur";
    $update_data = json_encode([
        "licence" => $licence,
        "nom" => $nom,
        "prenom" => $prenom,
        "naissance" => $naissance,
        "taille" => $taille,
        "poids" => $poids,
        "commentaire" => $commentaire,
        "statut" => $statut
    ]);

    $ch = curl_init($update_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);


    if ($http_code === 200) {
        header("Location: joueur.php");
        exit;
    } else {
        die("Erreur lors de la mise à jour du joueur.");
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Liste des Joueurs</title>
  <link rel="stylesheet" href="styles.css">
  <script>
  function openModal(id, licence, nom, prenom, naissance, taille, poids, statut){
    <?php if($isGuest): ?>
      alert("Vous n'avez pas le droit d'ajouter/modifier un commentaire en tant qu'invité.");
      return;
    <?php endif; ?>

    const m = document.getElementById('modal');
    m.style.display='flex';
    document.getElementById('idInput').value=id;
    document.getElementById('licenceInput').value=licence;
    document.getElementById('nomInput').value = nom;
    document.getElementById('prenomInput').value = prenom;
    document.getElementById('naissanceInput').value = naissance;
    document.getElementById('tailleInput').value = taille;
    document.getElementById('poidsInput').value = poids;
    document.getElementById('statutInput').value = statut;
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
          <th>Date Naissance</th>
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
                onclick="openModal(
                '<?= htmlspecialchars($j['IdJoueur'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Numéro_de_license'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Prénom'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Date_de_naissance'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Taille'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Poids'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                '<?= htmlspecialchars($j['Statut'] ?? '', ENT_QUOTES, 'UTF-8') ?>'
            )"
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
      <input type="hidden" name="IdJoueur" id="idInput">
      <input type="hidden" name="licence" id="licenceInput">
      <input type="hidden" name="nom" id="nomInput">
      <input type="hidden" name="prenom" id="prenomInput">
      <input type="hidden" name="naissance" id="naissanceInput">
      <input type="hidden" name="taille" id="tailleInput">
      <input type="hidden" name="poids" id="poidsInput">
      <input type="hidden" name="statut" id="statutInput">
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
