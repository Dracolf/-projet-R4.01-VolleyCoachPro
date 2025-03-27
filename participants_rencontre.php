<?php
session_start();
header('Content-Type: application/json');

// 1) Vérif session
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

// 2) Récupérer l’idRencontre
$idRencontre = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($idRencontre <= 0){
    http_response_code(400);
    echo json_encode(["error"=>"ID de rencontre invalide"]);
    exit;
}

// 3) cURL générique
function sendCurlRequest($url, $method, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer $token",
      "Content-Type: application/json"
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
      "code"     => $code,
      "response" => json_decode($resp, true)
    ];
}

// 4) Appel GET /volleyapi/matchs/equipe/{idRencontre}
$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/equipe/$idRencontre";

$res = sendCurlRequest($api_url, "GET", $token);

// 5) Si code != 200 => on renvoie l’erreur
if($res['code']!==200){
    http_response_code($res['code']);
    echo json_encode([
      "error" => "Impossible de récupérer l’équipe du match #$idRencontre",
      "api_response" => $res['response']
    ]);
    exit;
}

// 6) On a un objet style :
//    {
//      "status_code":200,
//      "status_message":"Requête GET réussie",
//      "data":[{ "IdJoueur":..., "Rôle":"...", ...}, ... ]
//    }

// On le renvoie tel quel pour que "gestion_rencontre.php" le traite
echo json_encode($res['response']);
