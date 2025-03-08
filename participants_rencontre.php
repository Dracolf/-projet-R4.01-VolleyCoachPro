<?php
header('Content-Type: application/json');

// BDD
$host = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

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
