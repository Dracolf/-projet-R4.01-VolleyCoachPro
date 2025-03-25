<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['token'])) {
  header("Location: logout.php");
  exit;
}

$token = $_SESSION['token'];
$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/";

function sendCurlRequest($url, $method, $token, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ["code" => $http_code, "response" => json_decode($response, true)];
}

$joueurs = sendCurlRequest($api_url."joueurs/", "GET", $token, null);
if ($joueurs['code'] !== 200) {
    header("Location: logout.php");
}
$joueurs = $joueurs['response']['data'];
$totalJoueurs = 0;
$joueursActifs = 0;
$joueursBlessés = 0;

foreach($joueurs as $joueur) {
  $totalJoueurs = $totalJoueurs + 1;
  if ($joueur['Statut'] == "Actif") {
    $joueursActifs = $joueursActifs +1;
  } else if ($joueur['Statut'] == "Blessé") {
    $joueursBlessés = $joueursBlessés+1;
  }
}

// Prochaine rencontre
$rencontres = sendCurlRequest($api_url."matchs/", "GET", $token, null);
if ($rencontres['code'] !== 200) {
  header("Location: logout.php");
}
$rencontres = $rencontres['response']['data'];

$prochaineRencontre = null;

$now = new DateTime();
$now->format('Y-m-d H:i:s'); // Même format que NOW()

foreach($rencontres as $rencontre) {
  $dateRencontre = new DateTime($rencontre['Date_rencontre']);
  if ($dateRencontre >= $now) {
    $prochaineRencontre = $rencontre;
  }
}


$stats = sendCurlRequest($api_url."statistiques/", "GET", $token, null);
if ($stats['code'] !== 200) {
  header("Location: logout.php");
}
$stats = $stats['response']['data'];

// Data pour les stats
$nbMatchsWin = $stats['nbWins'];
$nbMatchsLoose = $stats['nbLooses'];
$rencontresJouees = $stats['nbMatchs'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord - VolleyCoachPro</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <!-- Barre de navigation -->
  <div class="navbar">
    <div class="navbar-title">
      <a href="index.php">VolleyCoachPro</a>
    </div>
    <div class="navbar-menus">
      <div class="menu-item">
        <button class="menu-button">Menu ▼</button>
        <div class="dropdown">
          <a href="joueur.php">Joueurs</a>
          <a href="stats.php">Statistiques</a>
          <a href="rencontre.php">Rencontres</a>
          <a href="logout.php">Déconnexion</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Tableau de bord -->
  <div class="dashboard-container">
    <h1 class="dashboard-title">Tableau de bord</h1>

    <!-- Résumé des joueurs -->
    <div class="dashboard-box">
      <h2>Résumé des joueurs</h2>
      <p>Total des joueurs : <strong><?= $totalJoueurs ?></strong></p>
      <p>Actifs : <strong><?= $joueursActifs ?></strong></p>
      <p>Blessés : <strong><?= $joueursBlessés ?></strong></p>
      <a href="joueur.php">Voir les joueurs</a>
    </div>

    <!-- Prochaine rencontre -->
    <div class="dashboard-box">
      <h2>Prochaine rencontre</h2>
      <?php if ($prochaineRencontre): ?>
        <?php
          $mois_fr = [
            'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
            'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
            'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
            'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'
          ];
          
          $dt = new DateTime($prochaineRencontre['Date_rencontre']);
          $formattedDate = $dt->format('j F Y'); // Ex : "10 March 2024"
          $formattedDate = strtr($formattedDate, $mois_fr); // Remplace "March" par "mars"
          
          $formattedTime = $dt->format('H\hi'); // Heure au format 24h
        ?>
        <p>Nom de l'équipe : <strong><?= htmlspecialchars($prochaineRencontre['Nom_équipe']) ?></strong></p>
        <p>Date : <strong><?= htmlspecialchars(ucfirst($formattedDate)) ?></strong></p>
        <p>Heure : <strong><?= htmlspecialchars($formattedTime) ?></strong></p>
        <p>Lieu :
          <strong>
            <?= ($prochaineRencontre['Domicile_ou_exterieur'] === 'Domicile') ? 'Domicile' : 'Extérieur' ?>
          </strong>
        </p>
        <a href="rencontre.php">Voir les rencontres</a>
      <?php else: ?>
        <p>Aucune rencontre programmée.</p>
      <?php endif; ?>
    </div>

    <!-- Statistiques globales -->
    <div class="dashboard-box">
      <h2>Statistiques globales</h2>
      <p>Total des rencontres jouées : <strong><?= $rencontresJouees ?></strong></p>
      <p>Nombre de rencontres remportées : <strong><?= $nbMatchsWin ?></strong></p>
      <p>Nombre de rencontres perdues : <strong><?= $nbMatchsLoose ?></strong></p>
      <a href="stats.php">Voir les statistiques</a>
    </div>
  </div>
</body>
</html>
