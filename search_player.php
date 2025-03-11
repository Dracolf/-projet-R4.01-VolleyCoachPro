<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    http_response_code(403); // Accès refusé
    exit('Accès refusé');
}

// Connexion à la base de données
$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $_GET['query'] ?? '';

    // Requête pour rechercher les joueurs par numéro de licence, nom ou prénom
    $stmt = $pdo->prepare("
        SELECT * FROM Joueur
        WHERE Numéro_de_license LIKE :query
           OR Nom LIKE :query
           OR Prénom LIKE :query
        ORDER BY Nom ASC
    ");
    $stmt->execute([':query' => "%$query%"]);
    $joueurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($joueurs as $joueur) {
        echo "<tr>
                <td>" . htmlspecialchars($joueur['Numéro_de_license']) . "</td>
                <td>" . htmlspecialchars($joueur['Nom']) . "</td>
                <td>" . htmlspecialchars($joueur['Prénom']) . "</td>
                <td>
                    <button class='button-edit' onclick='openModal(\"update\", {
                        license: \"" . htmlspecialchars($joueur['Numéro_de_license']) . "\",
                        nom: \"" . htmlspecialchars($joueur['Nom']) . "\",
                        prenom: \"" . htmlspecialchars($joueur['Prénom']) . "\",
                        date_naissance: \"" . htmlspecialchars($joueur['Date_de_naissance']) . "\",
                        taille: \"" . htmlspecialchars($joueur['Taille']) . "\",
                        poids: \"" . htmlspecialchars($joueur['Poids']) . "\",
                        commentaire: \"" . htmlspecialchars($joueur['Commentaire'] ?? '', ENT_QUOTES, 'UTF-8') . "\",
                        statut: \"" . htmlspecialchars($joueur['Statut']) . "\"
                    })'>Modifier</button>
                    <form method='POST' style='display:inline;' onsubmit='return confirm(\"Confirmez la suppression ?\");'>
                        <input type='hidden' name='action' value='delete'>
                        <input type='hidden' name='license' value='" . htmlspecialchars($joueur['Numéro_de_license']) . "'>
                        <button type='submit' class='button-delete'>Supprimer</button>
                    </form>
                </td>
            </tr>";
    }

} catch (PDOException $e) {
    http_response_code(500); // Erreur interne
    echo 'Erreur : ' . $e->getMessage();
}
?>