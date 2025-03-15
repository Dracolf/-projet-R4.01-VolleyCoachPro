<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    http_response_code(403); // Accès refusé
    exit('Accès refusé');
}

// Récupérer le token JWT depuis la session
if (!isset($_SESSION['token'])) {
    http_response_code(403); // Accès refusé
    exit('Token non trouvé');
}
$token = $_SESSION['token'];

// Récupérer le terme de recherche
$query = $_GET['query'] ?? '';

// URL de l'API pour rechercher les joueurs
$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/search/" . urlencode($query);

// Configuration de la requête cURL
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "Content-Type: application/json"
]);

// Exécution de la requête
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Vérifier si la requête a réussi
if ($http_code !== 200) {
    http_response_code($http_code); // Retourner le code d'erreur de l'API
    exit('Erreur lors de la récupération des joueurs');
}

// Décoder la réponse JSON
$joueurs = json_decode($response, true);

// Vérifier si des joueurs ont été trouvés
if (empty($joueurs['data'])) {
    echo "<tr><td colspan='4'>Aucun joueur trouvé</td></tr>";
    exit();
}

// Générer le HTML pour chaque joueur trouvé
foreach ($joueurs['data'] as $joueur) {
    echo "<tr>
            <td>" . htmlspecialchars($joueur['Numéro_de_license']) . "</td>
            <td>" . htmlspecialchars($joueur['Nom']) . "</td>
            <td>" . htmlspecialchars($joueur['Prénom']) . "</td>
            <td>
                <button class='button-edit' onclick='openModal(\"update\", {
                    id: \"" . htmlspecialchars($joueur['IdJoueur']) . "\",
                    license: \"" . htmlspecialchars($joueur['Numéro_de_license']) . "\",
                    nom: \"" . htmlspecialchars($joueur['Nom']) . "\",
                    prenom: \"" . htmlspecialchars($joueur['Prénom']) . "\",
                    date_naissance: \"" . htmlspecialchars($joueur['Date_de_naissance']) . "\",
                    taille: \"" . htmlspecialchars($joueur['Taille']) . "\",
                    poids: \"" . htmlspecialchars($joueur['Poids']) . "\",
                    commentaire: \"" . htmlspecialchars($joueur['Commentaire'] ?? '', ENT_QUOTES, 'UTF-8') . "\",
                    statut: \"" . htmlspecialchars($joueur['Statut']) . "\"
                })'>Modifier</button>";
    echo "<button class='button-delete' data-idjoueur='" . htmlspecialchars($joueur['IdJoueur']) . "'>Supprimer</button>      
            </td>
        </tr>";
}
?>