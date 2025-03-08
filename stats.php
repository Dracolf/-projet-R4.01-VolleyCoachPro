<?php
session_start();

if (!isset($_SESSION['user'])) {
    // Redirige vers la page de connexion si la session n'existe pas
    header("Location: login.php");
    exit;}

$server = 'sql312.infinityfree.com';
$login = 'if0_37676623';
$mdp = 'theadmin31';
$db = 'if0_37676623_gestionvolley';

try {
    // Connexion au serveur MySQL
    $linkpdo = new PDO("mysql:host=$server;dbname=$db;charset=utf8mb4", $login, $mdp);
    $linkpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    date_default_timezone_set('Europe/Paris');
    $dateToday = date("Y-m-d H:i:s");

    // Préparer et exécuter la requête
    $rencontresAvecScore = $linkpdo->prepare("
        SELECT COUNT(*) as nb 
        FROM Rencontre 
        WHERE (Set1_equipe + Set2_equipe + Set3_equipe + Set4_equipe + Set5_equipe) > 0 
          AND (Set1_adverse + Set2_adverse + Set3_adverse + Set4_adverse + Set5_adverse) > 0
          AND Date_rencontre < :dateToday ;
    ");
    $rencontresAvecScore->execute(['dateToday' => $dateToday]);
    $nbMatchsJoues = $rencontresAvecScore->fetch(PDO::FETCH_ASSOC);

    $reqMatchsWin = $linkpdo->prepare("SELECT COUNT(*) as nb FROM Rencontre WHERE (
    (Set1_equipe > Set1_adverse) + 
    (Set2_equipe > Set2_adverse) + 
    (Set3_equipe > Set3_adverse) + 
    (Set4_equipe > Set4_adverse) + 
    (Set5_equipe > Set5_adverse)
    ) >= 3 AND Date_rencontre < :dateToday");
    $reqMatchsWin->execute(['dateToday' => $dateToday]);
    $nbMatchsWin =  $reqMatchsWin->fetch(PDO::FETCH_ASSOC);

    $reqMatchsLoose = $linkpdo->prepare("SELECT COUNT(*) as nb FROM Rencontre WHERE (
    (Set1_equipe < Set1_adverse) + 
    (Set2_equipe < Set2_adverse) + 
    (Set3_equipe < Set3_adverse) + 
    (Set4_equipe < Set4_adverse) + 
    (Set5_equipe < Set5_adverse)
    ) >= 3 AND Date_rencontre < :dateToday");
    $reqMatchsLoose->execute(['dateToday' => $dateToday]);
    $nbMatchsLoose =  $reqMatchsLoose->fetch(PDO::FETCH_ASSOC);

    if (!$nbMatchsJoues) {
        $nbMatchsJoues['nb'] = 0; // Par défaut si aucune donnée n'est trouvée
    }
    if (!$nbMatchsWin) {
        $nbMatchsWin['nb'] = 0; // Par défaut si aucune donnée n'est trouvée
    }
    if (!$nbMatchsLoose) {
        $nbMatchsLoose['nb'] = 0; // Par défaut si aucune donnée n'est trouvée
    }

    $MEGAREQUETE = $linkpdo->prepare("
    SELECT 
    J.Nom, 
    J.Prénom,
    J.Statut,
    COALESCE((
        SELECT P1.Rôle 
        FROM Participer P1
        WHERE P1.IdJoueur = J.IdJoueur
        AND P1.Titulaire_ou_remplacant = 'Titulaire'
        GROUP BY P1.Rôle
        ORDER BY COUNT(*) DESC, P1.Rôle ASC
        LIMIT 1
    ), '/') AS PostePréféré,
    COUNT(CASE WHEN P.Titulaire_ou_remplacant = 'Titulaire' AND Date_rencontre < :dateToday THEN 1 END) AS NbTitularisations,
    COUNT(CASE WHEN P.Titulaire_ou_remplacant = 'Remplaçant' AND Date_rencontre < :dateToday THEN 1 END) AS NbRemplacements,
    COALESCE(ROUND(AVG(P.Note), 2), '/') AS NoteMoyenne,
    CASE 
        WHEN COUNT(CASE WHEN Date_rencontre < :dateToday THEN 1 END) = 0 THEN '/'
        ELSE ROUND(
            (SUM(
                CASE 
                    WHEN (
                        (Set1_equipe > Set1_adverse) + 
                        (Set2_equipe > Set2_adverse) + 
                        (Set3_equipe > Set3_adverse) + 
                        (Set4_equipe > Set4_adverse) + 
                        (Set5_equipe > Set5_adverse)
                    ) >= 3 
                    AND P.IdJoueur IS NOT NULL 
                    AND Date_rencontre < :dateToday
                THEN 1 ELSE 0 END
            ) * 100.0) 
            / COUNT(
                CASE 
                    WHEN P.IdJoueur IS NOT NULL 
                    AND Date_rencontre < :dateToday 
                    THEN 1 ELSE NULL END
            ), 
            2
        )
    END AS PourcentageVictoires
FROM 
    Joueur J
LEFT JOIN 
    Participer P ON J.IdJoueur = P.IdJoueur
LEFT JOIN 
    Rencontre R ON P.IdRencontre = R.IdRencontre
GROUP BY 
    J.IdJoueur, J.Nom, J.Prénom, J.Statut
ORDER BY 
    J.Statut = 'Absent' ASC, J.Statut = 'Blessé' ASC, J.Statut = 'Actif' ASC, J.Nom ASC
    ");
    $MEGAREQUETE->execute(['dateToday' => $dateToday, 'nbMatchsJoues' => $nbMatchsJoues]);

} catch (PDOException $e) {
    die('Erreur : '.$e->getMessage()); // Afficher une erreur explicite
}
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
            <p>Matchs joués : <strong><?= htmlspecialchars($nbMatchsJoues['nb']) ?></strong> </p>
            <p>Matchs gagnés : <strong><?= htmlspecialchars(round(($nbMatchsWin['nb']*100)/$nbMatchsJoues['nb'],2).'% ('.htmlspecialchars($nbMatchsWin['nb']).'/'.htmlspecialchars($nbMatchsJoues['nb']).')') ?></strong> </p>
            <p>Matchs perdus : <strong><?= htmlspecialchars(round(($nbMatchsLoose['nb']*100)/$nbMatchsJoues['nb'],2).'% ('.htmlspecialchars($nbMatchsLoose['nb']).'/'.htmlspecialchars($nbMatchsJoues['nb']).')') ?></strong> </p>
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
                <?php while ($row = $MEGAREQUETE->fetch(PDO::FETCH_ASSOC)) {  ?>
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
