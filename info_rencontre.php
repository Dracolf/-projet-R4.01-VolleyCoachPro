<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(["error"=>"Accès refusé"]);
    exit;
}
if(!isset($_SESSION['token'])){
    http_response_code(403);
    echo json_encode(["error"=>"Token manquant"]);
    exit;
}
$token = $_SESSION['token'];

$idRencontre = isset($_GET['id'])?(int)$_GET['id']:0;
if($idRencontre<=0){
    http_response_code(400);
    echo json_encode(["error"=>"ID invalide"]);
    exit;
}

$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/$idRencontre";

function sendCurlRequest($url, $method, $token){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    $res = curl_exec($ch);
    $code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ["code"=>$code, "response"=>json_decode($res,true)];
}

$res = sendCurlRequest($api_url, "GET", $token);
if($res['code']===200){
    $data = $res['response']['data'] ?? null;
    if(!$data){
        http_response_code(404);
        echo json_encode(["error"=>"Rencontre introuvable"]);
        exit;
    }

    // Convertir la date
    $dt = new DateTime($data['Date_rencontre']);
    $datetimeLocal = $dt->format('Y-m-d\TH:i');

    $result = [
      "DateRencontre" => $datetimeLocal,
      "NomEquipe"     => $data['Nom_équipe'],
      "Lieu"          => $data['Domicile_ou_exterieur']
    ];
    echo json_encode($result);
} else {
    http_response_code($res['code']);
    echo json_encode([
      "error"=>"Impossible de récupérer la rencontre",
      "api_response"=>$res['response']
    ]);
}
