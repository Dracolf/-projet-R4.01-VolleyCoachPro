<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Accès refusé');
}

// Identifiants de connexion
$host = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

$idToRemove = $_GET['idToRemove'] ?? null;
if (!$idToRemove) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Récupérer la liste des matchs auxquels participe le joueur à retirer
    $stmtM = $pdo->prepare("
        SELECT IdRencontre 
        FROM Participer
        WHERE IdJoueur = :id
    ");
    $stmtM->execute([':id' => $idToRemove]);
    $matches = $stmtM->fetchAll(PDO::FETCH_COLUMN);

    if (!$matches) {
        $matches = [];
    }

    // Construire la clause pour exclure les joueurs déjà dans ces rencontres
    // ex:  "AND j.IdJoueur NOT IN (SELECT IdJoueur FROM Participer WHERE IdRencontre IN (?,?,?))"
    $inClause = "";
    if (count($matches) > 0) {
        $placeholders = rtrim(str_repeat('?,', count($matches)), ',');
        $inClause = "AND j.IdJoueur NOT IN (
            SELECT IdJoueur 
            FROM Participer 
            WHERE IdRencontre IN ($placeholders)
        )";
    }

    // 2) Sélectionner tous les joueurs “Actif” en excluant le joueur lui-même 
    //    et ceux qui sont déjà dans les mêmes rencontres
    $sql = "
        SELECT j.IdJoueur, j.Nom, j.Prénom
        FROM Joueur j
        WHERE j.IdJoueur != ?
          AND j.Statut = 'Actif'
          $inClause
        ORDER BY j.Nom ASC
    ";

    // On bind l'idToRemove + la liste des $matches
    $bindParams = [$idToRemove];
    if ($inClause) {
        $bindParams = array_merge($bindParams, $matches);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindParams);
    $validPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // On renvoie la liste en JSON
    echo json_encode($validPlayers);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
