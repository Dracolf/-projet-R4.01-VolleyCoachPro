<?php
header('Content-Type: application/json');

$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

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

$sql="SELECT Date_rencontre, Nom_équipe, Domicile_ou_exterieur FROM Rencontre WHERE IdRencontre=:idr";
$stmt=$pdo->prepare($sql);
$stmt->execute([':idr'=>$idRencontre]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row){
    echo json_encode([]);
    exit;
}

$dt=new DateTime($row['Date_rencontre']);
$datetimeLocal = $dt->format('Y-m-d\TH:i');

$result=[
  'DateRencontre'=>$datetimeLocal, 
  'NomEquipe'=>$row['Nom_équipe'],
  'Lieu'=>$row['Domicile_ou_exterieur']
];

echo json_encode($result);
