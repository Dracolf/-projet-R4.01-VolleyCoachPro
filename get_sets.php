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
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ["code"=>$code, "response"=>json_decode($res,true)];
}

$res = sendCurlRequest($api_url, "GET", $token);
if($res['code']===200){
    // On suppose la réponse = { "status_code":200, "data": { ... } }
    $data = $res['response']['data'] ?? null;
    if(!$data){
        http_response_code(404);
        echo json_encode(["error"=>"Match introuvable"]);
        exit;
    }
    $out = [
      "set1_equipe" => $data['Set1_equipe'],
      "set1_adverse"=> $data['Set1_adverse'],
      "set2_equipe" => $data['Set2_equipe'],
      "set2_adverse"=> $data['Set2_adverse'],
      "set3_equipe" => $data['Set3_equipe'],
      "set3_adverse"=> $data['Set3_adverse'],
      "set4_equipe" => $data['Set4_equipe'],
      "set4_adverse"=> $data['Set4_adverse'],
      "set5_equipe" => $data['Set5_equipe'],
      "set5_adverse"=> $data['Set5_adverse'],
    ];
    echo json_encode($out);
} else {
    http_response_code($res['code']);
    echo json_encode([
      "error"=>"Impossible de récupérer ce match",
      "api_response"=>$res['response']
    ]);
}
