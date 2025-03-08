<?php
header('Content-Type: application/json');

$host = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

try{
    $pdo=new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4",
                 $username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch(PDOException $e){
    echo json_encode([]);
    exit;
}

$idRencontre=isset($_GET['id'])?(int)$_GET['id']:0;
if($idRencontre<=0){
    echo json_encode([]);
    exit;
}

$stmt=$pdo->prepare("SELECT * FROM Rencontre WHERE IdRencontre=:id");
$stmt->execute([':id'=>$idRencontre]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$row){
    echo json_encode([]);
    exit;
}

// On construit un tableau associatif
$result=[
  'set1_equipe'=>$row['Set1_equipe'],
  'set1_adverse'=>$row['Set1_adverse'],
  'set2_equipe'=>$row['Set2_equipe'],
  'set2_adverse'=>$row['Set2_adverse'],
  'set3_equipe'=>$row['Set3_equipe'],
  'set3_adverse'=>$row['Set3_adverse'],
  'set4_equipe'=>$row['Set4_equipe'],
  'set4_adverse'=>$row['Set4_adverse'],
  'set5_equipe'=>$row['Set5_equipe'],
  'set5_adverse'=>$row['Set5_adverse']
];

echo json_encode($result);
