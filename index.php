<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Config
$host = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

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
    $reqMatchsWin = $pdo->prepare("SELECT COUNT(*) as nb FROM Rencontre WHERE (
    (Set1_equipe > Set1_adverse) + 
    (Set2_equipe > Set2_adverse) + 
    (Set3_equipe > Set3_adverse) + 
    (Set4_equipe > Set4_adverse) + 
    (Set5_equipe > Set5_adverse)
    ) >= 3 AND Date_rencontre < NOW()");
    $reqMatchsWin->execute();
    $nbMatchsWin =  $reqMatchsWin->fetchColumn();

    $reqMatchsLoose = $pdo->prepare("SELECT COUNT(*) as nb FROM Rencontre WHERE (
    (Set1_equipe < Set1_adverse) + 
    (Set2_equipe < Set2_adverse) + 
    (Set3_equipe < Set3_adverse) + 
    (Set4_equipe < Set4_adverse) + 
    (Set5_equipe < Set5_adverse)
    ) >= 3 AND Date_rencontre < NOW()");
    $reqMatchsLoose->execute();
    $nbMatchsLoose =  $reqMatchsLoose->fetchColumn();

    // Calcul du "total des rencontres jouées"
    // On juge "jouée" si l'une des équipes a >= 3 sets gagnés.
    $allMatches = $pdo->query("SELECT * FROM Rencontre")->fetchAll(PDO::FETCH_ASSOC);
    $rencontresJouees = 0;
    foreach ($allMatches as $m) {
        $sets = computeSets($m);
        if ($sets['eq'] >= 3 || $sets['ad'] >= 3) {
            $rencontresJouees++;
        }
    }
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
          $dt = new DateTime($prochaineRencontre['Date_rencontre']);
          $formattedDate = strftime('%e %B %Y', $dt->getTimestamp());
          $formattedTime = $dt->format('H\hi');
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
