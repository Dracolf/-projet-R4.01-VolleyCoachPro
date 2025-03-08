<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    http_response_code(403); // Accès refusé
    exit('Accès refusé');
}

// Connexion à la base de données
$host = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $_GET['query'] ?? '';

    // Requête pour rechercher les rencontres par nom ou date
    $stmt = $pdo->prepare("
        SELECT * FROM Rencontre
        WHERE Nom_équipe LIKE :query
           OR Date_rencontre LIKE :query
        ORDER BY Date_rencontre ASC
    ");
    $stmt->execute([':query' => "%$query%"]);
    $rencontres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rencontres as $rencontre) {
        echo "<tr>
                <td>" . htmlspecialchars($rencontre['Date_rencontre']) . "</td>
                <td>" . htmlspecialchars($rencontre['Nom_équipe']) . "</td>
                <td>" . htmlspecialchars($rencontre['Domicile_ou_exterieur']) . "</td>
                <td>
                    <button class='button-edit' onclick='openModal(\"update\", {
                        id_rencontre: \"" . htmlspecialchars($rencontre['IdRencontre']) . "\",
                        date_rencontre: \"" . htmlspecialchars($rencontre['Date_rencontre']) . "\",
                        nom_equipe: \"" . htmlspecialchars($rencontre['Nom_équipe']) . "\",
                        lieu: \"" . htmlspecialchars($rencontre['Domicile_ou_exterieur']) . "\"
                    })'>Modifier</button>
                    <form method='POST' style='display:inline;' onsubmit='return confirm(\"Confirmez la suppression ?\");'>
                        <input type='hidden' name='action' value='delete'>
                        <input type='hidden' name='id_rencontre' value='" . htmlspecialchars($rencontre['IdRencontre']) . "'>
                        <button type='submit' class='button-delete'>Supprimer</button>
                    </form>
                </td>
            </tr>";
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Erreur : ' . $e->getMessage();
}
