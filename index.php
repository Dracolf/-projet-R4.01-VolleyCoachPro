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

// Config
$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

// Récupérer le token JWT stocké en session
$token = $_SESSION['token'];

// URL de l'API
$url = "https://volleycoachpro.alwaysdata.net/volleyapi/statistiques/statistiques.php";

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
    die("Erreur lors de la récupération des statistiques.");
}

// Convertir la réponse JSON en tableau PHP
$stats = json_decode($response, true);

// Petite fonction pour calculer combien de sets chaque équipe a gagnés,
// sans valider strictement la règle de 25 pts / 2 pts d’écart, juste un >/<.
function computeSets(array $rencontre): array
{
    $setsEquipe = 0;
    $setsAdverse = 0;
    for ($i = 1; $i <= 5; $i++) {
        $eq = (int)($rencontre["Set{$i}_equipe"] ?? 0);
        $ad = (int)($rencontre["Set{$i}_adverse"] ?? 0);
        if ($eq === 0 && $ad === 0) {
            continue; // set non joué
        }
        if ($eq > $ad) {
            $setsEquipe++;
        } elseif ($ad > $eq) {
            $setsAdverse++;
        }
    }
    return ['eq' => $setsEquipe, 'ad' => $setsAdverse];
}

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);

    // Récup data pour tableau de bord (joueurs)
    $totalJoueurs    = $pdo->query("SELECT COUNT(*) FROM Joueur")->fetchColumn();
    $joueursBlessés  = $pdo->query("SELECT COUNT(*) FROM Joueur WHERE Statut = 'Blessé'")->fetchColumn();
    $joueursActifs   = $pdo->query("SELECT COUNT(*) FROM Joueur WHERE Statut = 'Actif'")->fetchColumn();

    // Prochaine rencontre
    $prochaineRencontre = $pdo->query("
        SELECT * 
        FROM Rencontre
        WHERE Date_rencontre >= NOW()
        ORDER BY Date_rencontre ASC 
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    // Data pour les stats
    $nbMatchsWin = $stats['data']['nbWins'];
    $nbMatchsLoose = $stats['data']['nbLooses'];

    // Calcul du "total des rencontres jouées"
    // On juge "jouée" si l'une des équipes a >= 3 sets gagnés.
    $rencontresJouees = $stats['data']['nbMatchs'];
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
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
