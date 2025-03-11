<?php
header('Content-Type: application/json');

// BDD
$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

try {
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

$sql="SELECT Rôle, IdJoueur FROM Participer WHERE IdRencontre=:idr";
$stmt=$pdo->prepare($sql);
$stmt->execute([':idr'=>$idRencontre]);

$result=[];
while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
    $role=$row['Rôle'];
    $idJ =$row['IdJoueur'];
    $result[$role]=$idJ;
}

echo json_encode($result);
