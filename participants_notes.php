<?php
session_start();
header('Content-Type: application/json');

// Vérif session
if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(["error"=>"Accès refusé (pas de session)"]);
    exit;
}
if(!isset($_SESSION['token'])){
    http_response_code(403);
    echo json_encode(["error"=>"Token manquant"]);
    exit;
}
$token = $_SESSION['token'];

$idRencontre = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($idRencontre<=0){
    http_response_code(400);
    echo json_encode(["error"=>"ID de rencontre invalide"]);
    exit;
}

function sendCurlRequest($url, $method, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
      "code" => $code,
      "response" => json_decode($resp, true)
    ];
}

// 1) Récup la liste de participants => /matchs/equipe/<id>
$api_url_participants = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/equipe/$idRencontre";
$resPart = sendCurlRequest($api_url_participants, "GET", $token);

if($resPart['code'] !== 200) {
    http_response_code($resPart['code']);
    echo json_encode([
        "error" => "Impossible de récupérer participants (matchs/equipe/$idRencontre)",
        "api_response" => $resPart['response']
    ]);
    exit;
}

// --> ICI on prend "data"
$participantsApi = $resPart['response']['data'] ?? null;
if(!is_array($participantsApi)){
    // si data n’existe pas ou pas un tableau => on considère vide
    $participantsApi = [];
}

// 2) Pour chaque participant, GET /joueurs/{IdJoueur}
$final = [];

foreach($participantsApi as $p) {
    // p = { "IdJoueur":12, "Note":4, ... } etc
    $idJ = $p['IdJoueur'] ?? 0;
    if($idJ<=0) continue; // skip

    $note = isset($p['Note']) ? (int)$p['Note'] : 0;

    // GET /joueurs/<idJ>
    $urlJoueur = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/$idJ";
    $resJ = sendCurlRequest($urlJoueur, "GET", $token);
    if($resJ['code']===200) {
        // On récup data
        $joueur = $resJ['response']['data'] ?? null;
        if($joueur){
            $nom    = $joueur['Nom']    ?? "(??)";
            $prenom = $joueur['Prénom'] ?? "(??)";
            $final[] = [
                "IdJoueur" => $idJ,
                "Note"     => $note,
                "Nom"      => $nom,
                "Prénom"   => $prenom
            ];
        } else {
            // pas de data => on met inconnu
            $final[] = [
                "IdJoueur" => $idJ,
                "Note"     => $note,
                "Nom"      => "(inconnu)",
                "Prénom"   => "(inconnu)"
            ];
        }
    } else {
        // Erreur => on stocke un pseudo
        $final[] = [
            "IdJoueur" => $idJ,
            "Note"     => $note,
            "Nom"      => "(erreur Joueur)",
            "Prénom"   => "(erreur Joueur)"
        ];
    }
}

// 3) On echo le résultat final
echo json_encode($final);
