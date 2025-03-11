<?php
session_start();

if (!isset($_SESSION['user'])) {
    // Redirige vers la page de connexion si la session n'existe pas
    header("Location: login.php");
    exit;}
if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Europe/Paris');
$dateToday = date("Y-m-d H:i:s");

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

$nbMatchsJoues = $stats['data']['nbMatchs'];
$nbMatchsWin = $stats['data']['nbWins'];
$nbMatchsLoose = $stats['data']['nbLooses'];

$MEGAREQUETE = $stats['data']['joueursStats'];

?>

<!DOCTYPE HTML>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="styles.css"> 
    <title>Statistiques</title>

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
                    <a href="rencontre.php">Rencontres</a>
                    <a href="logout.php">Déconnexion</a>
                </div>
            </div>
        </div>
    </div>
    <div class="content-box">
        <h1 class="content-title">Statistiques</h1>
        <div class="stats-box">
            <p>Matchs joués : <strong><?= htmlspecialchars($nbMatchsJoues) ?></strong> </p>
            <p>Matchs gagnés : <strong><?= htmlspecialchars(round(($nbMatchsWin*100)/$nbMatchsJoues,2).'% ('.htmlspecialchars($nbMatchsWin).'/'.htmlspecialchars($nbMatchsJoues).')') ?></strong> </p>
            <p>Matchs perdus : <strong><?= htmlspecialchars(round(($nbMatchsLoose*100)/$nbMatchsJoues,2).'% ('.htmlspecialchars($nbMatchsLoose).'/'.htmlspecialchars($nbMatchsJoues).')') ?></strong> </p>
        </div>

        <table class="table table-rencontres">
            <thead>
                <tr>
                    <th>Nom joueur</th>
                    <th>Prénom joueur</th>
                    <th>Statut actuel</th>
                    <th>Post préféré</th>
                    <th>Nombre de titularisations</th>
                    <th>Nombre de fois remplaçant</th>
                    <th>Note moyenne</th>
                    <th>Pourcentage de victoires en sa présence</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($MEGAREQUETE as $row) {  ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Nom']) ?></td>
                        <td><?= htmlspecialchars($row['Prénom']) ?></td>
                        <td><?= htmlspecialchars($row['Statut']) ?></td>
                        <td><?= htmlspecialchars($row['PostePréféré']) ?></td>
                        <td><?= htmlspecialchars($row['NbTitularisations']) ?></td>
                        <td><?= htmlspecialchars($row['NbRemplacements']) ?></td>
                        <td><?= htmlspecialchars($row['NoteMoyenne']) ?></td>
                        <td><?= htmlspecialchars($row['PourcentageVictoires']) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>