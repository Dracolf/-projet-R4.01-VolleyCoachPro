<?php
session_start();
header('Content-Type: application/json');

// Vérif session
if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(["error" => "Accès refusé"]);
    exit;
}
if(!isset($_SESSION['token'])){
    http_response_code(403);
    echo json_encode(["error" => "Token manquant"]);
    exit;
}
$token = $_SESSION['token'];

$idRencontre = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($idRencontre <= 0){
    http_response_code(400);
    echo json_encode(["error" => "ID invalide"]);
    exit;
}

/**
 * cURL générique
 */
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
    return [ "code" => $code, "response" => json_decode($resp, true) ];
}

// 1) ---- Récupérer la liste de participants => /matchs/equipe/<idRencontre>
$api_url_participants = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/equipe/$idRencontre";
$resPart = sendCurlRequest($api_url_participants, "GET", $token);

if($resPart['code']!==200) {
    http_response_code($resPart['code']);
    echo json_encode([
        "error"=>"Impossible de récupérer participants (équipe/$idRencontre)",
        "api_response"=>$resPart['response']
    ]);
    exit;
}

$participants = $resPart['response']; // on s’attend à un tableau ex: [ {IdJoueur, IdRencontre, Rôle, Note, ...}, ... ]

if(!is_array($participants)){
    http_response_code(500);
    echo json_encode(["error"=>"Réponse inattendue (pas un tableau)"]);
    exit;
}

// 2) ---- Pour chaque participant, on va récupérer Nom + Prénom via /joueurs/<IdJoueur>
$final = [];

foreach($participants as $p) {
    // p ex : { "IdJoueur":12, "Note":4, "Rôle":"avant_gauche", ... }
    $idJoueur = $p['IdJoueur'] ?? 0;
    if($idJoueur <= 0) {
        // On skip ce record
        continue;
    }

    // appel /joueurs/<idJoueur> => renvoie { "data": { "IdJoueur":12, "Nom":"...", "Prénom":"...", ... } }
    $api_url_joueur = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/$idJoueur";
    $resJ = sendCurlRequest($api_url_joueur, "GET", $token);

    if($resJ['code']===200) {
        // On suppose la structure = { "status_code":200, "data": {...} }
        $dataJ = $resJ['response']['data'] ?? null;
        if($dataJ){
            // On fusionne Note, IdJoueur, Nom, Prénom, etc.
            $final[] = [
                "IdJoueur" => $idJoueur,
                "Note"     => $p['Note'] ?? 0,
                "Nom"      => $dataJ['Nom']      ?? "(inconnu)",
                "Prénom"   => $dataJ['Prénom']   ?? "(inconnu)"
                // on peut stocker + si besoin
            ];
        }
    } else {
        // echec => on peut ignorer ou remplir "Nom":"??"
        $final[] = [
            "IdJoueur" => $idJoueur,
            "Note"     => $p['Note'] ?? 0,
            "Nom"      => "(??)",
            "Prénom"   => "(??)"
        ];
    }
}

// 3) ---- On renvoie $final en JSON
echo json_encode($final);
