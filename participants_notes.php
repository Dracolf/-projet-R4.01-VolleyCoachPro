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

$idRencontre = isset($_GET['id'])?(int)$_GET['id']:0;
if($idRencontre<=0){
    echo json_encode([]);
    exit;
}

// On joint Participer + Joueur pour avoir Nom, Prénom, Note
$sql="
  SELECT p.IdJoueur, j.Nom, j.Prénom, p.Note
  FROM Participer p
  JOIN Joueur j ON p.IdJoueur=j.IdJoueur
  WHERE p.IdRencontre=:idr
  ORDER BY j.Nom
";
$stmt=$pdo->prepare($sql);
$stmt->execute([':idr'=>$idRencontre]);
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);
