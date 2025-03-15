<?php
session_start();

// Vérifier la session
if(!isset($_SESSION['user'])){
  header("Location: login.php");
  exit;
}

if(!isset($_SESSION['token'])){
  header("Location: logout.php");
  exit;
}

$token = $_SESSION['token'];
$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/";

function sendCurlRequest($url, $method, $token, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ["code" => $http_code, "response" => json_decode($response, true)];
}

// Paramètres BDD
$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

// Variables globales
$message   = "";
$error     = false;
$showModal = false;
$modalId   = 0;
$postSets  = [];
$postNotes = [];

/**
 * Valide un match de volley en 5 sets max :
 * - sets 1..4 => 25 pts, set5 => 15 pts
 * - Ecart >=2
 * - 3 sets gagnants => on arrête
 */
function validerVolleyScores(array $scores): array
{
    $setsEquipe  = 0;
    $setsAdverse = 0;
    for($i=1; $i<=5; $i++){
        $eq = (int)($scores["set{$i}_equipe"]  ?? 0);
        $ad = (int)($scores["set{$i}_adverse"] ?? 0);
        // set non joué => skip
        if($eq===0 && $ad===0) continue;

        // si déjà 3 sets => plus de set
        if(($setsEquipe===3 || $setsAdverse===3) && ($eq>0 || $ad>0)){
            return ['valid'=>false,'message'=>"Set $i joué alors qu'une équipe avait déjà 3 sets gagnés."];
        }
        $isTie = ($i===5);
        $minPts= $isTie?15:25;
        if($eq<$minPts && $ad<$minPts){
            return ['valid'=>false,'message'=>"Set $i : aucun n'a atteint $minPts pts."];
        }
        if(abs($eq-$ad)<2){
            return ['valid'=>false,'message'=>"Set $i : écart < 2 pts."];
        }
        if($eq>$ad) {
            $setsEquipe++;
        } elseif($ad>$eq) {
            $setsAdverse++;
        } else {
            return ['valid'=>false,'message'=>"Set $i : égalité impossible"];
        }
    }
    // au moins 3 sets gagnés
    if($setsEquipe<3 && $setsAdverse<3){
        return ['valid'=>false,'message'=>"Match incomplet (pas 3 sets)."];
    }
    return ['valid'=>true,'message'=>"Ok"];
}

try {
    // Connexion BDD
    $pdo=new PDO(
      "mysql:host=$host;dbname=$database;charset=utf8mb4",
      $username,
      $password,
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );

    // Traitement du POST “update_score_notes_front”
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update_score_notes_front'){
        $idR = (int)($_POST['id_rencontre']??0);

        // Récup sets
        for($i=1;$i<=5;$i++){
            $postSets["set{$i}_equipe"]  = (int)($_POST["set{$i}_equipe"]  ?? 0);
            $postSets["set{$i}_adverse"] = (int)($_POST["set{$i}_adverse"] ?? 0);
        }
        // Récup notes => max=5
        if(isset($_POST['notes']) && is_array($_POST['notes'])){
            foreach($_POST['notes'] as $idJ=>$val){
                $n=(int)$val;
                if($n<0)$n=0;
                if($n>5)$n=5;
                $postNotes[$idJ]=$n;
            }
        }

        // Si user=guest => on bloque
        if($_SESSION['user']==='guest'){
            $error    = true;
            $message  = "Vous n'avez pas le droit de modifier le score en tant qu'invité.";
            $showModal= true;
            $modalId  = $idR;
        } else {
            // Validation volley
            $check=validerVolleyScores($postSets);
            if(!$check['valid']){
                // Erreur volley => on affiche dans la modale
                $error    = true;
                $message  = "Erreur volley : ".$check['message'];
                $showModal= true;
                $modalId  = $idR;
            } else {
                // Mise à jour
                // 1) sets
                $pdo->prepare("
                  UPDATE Rencontre
                  SET
                    Set1_equipe=:s1e, Set1_adverse=:s1a,
                    Set2_equipe=:s2e, Set2_adverse=:s2a,
                    Set3_equipe=:s3e, Set3_adverse=:s3a,
                    Set4_equipe=:s4e, Set4_adverse=:s4a,
                    Set5_equipe=:s5e, Set5_adverse=:s5a
                  WHERE IdRencontre=:id
                ")->execute([
                  ':s1e'=>$postSets['set1_equipe'], ':s1a'=>$postSets['set1_adverse'],
                  ':s2e'=>$postSets['set2_equipe'], ':s2a'=>$postSets['set2_adverse'],
                  ':s3e'=>$postSets['set3_equipe'], ':s3a'=>$postSets['set3_adverse'],
                  ':s4e'=>$postSets['set4_equipe'], ':s4a'=>$postSets['set4_adverse'],
                  ':s5e'=>$postSets['set5_equipe'], ':s5a'=>$postSets['set5_adverse'],
                  ':id'=>$idR
                ]);
                // 2) notes
                foreach($postNotes as $idJ=>$noteVal){
                    $pdo->prepare("
                      UPDATE Participer
                      SET Note=:n
                      WHERE IdRencontre=:idr AND IdJoueur=:idj
                    ")->execute([
                      ':n'=>$noteVal, ':idr'=>$idR, ':idj'=>$idJ
                    ]);
                }
                $message="Score et notes enregistrés.";
            }
        }
    }

    // Récup la liste des rencontres
    $rencontres = sendCurlRequest($api_url, "GET", $token, null);
    if ($rencontres['code'] !== 200) {
        die("Erreur lors de la récupération des recontres.");
    }
    $rencontres = $rencontres['response']['data'];

} catch(PDOException $e){
    die("Erreur BDD: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rencontres</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- Barre de navigation -->
<div class="navbar">
  <div class="navbar-title">
    <a href="index.php">VolleyCoachPro</a>
  </div>
  <div class="navbar-menus">
    <div class="menu-item">
      <button class="menu-button">Menu ▼</button>
      <div class="dropdown">
        <a href="joueur.php">Joueurs</a>
        <a href="stats.php">Statistiques</a>
        <a href="logout.php">Déconnexion</a>
      </div>
    </div>
  </div>
</div>

<?php if($message): ?>
<p class="message <?= $error ? 'error-message' : 'info-message' ?>">
  <?= htmlspecialchars($message) ?>
</p>
<?php endif; ?>

<!-- Variables JS pour rouvrir la modale en cas d'erreur volley -->
<script>
var userIsGuest = <?= ($_SESSION['user']==='guest') ? 'true':'false' ?>;
var showModal   = <?= $showModal?'true':'false' ?>;
var modalId     = <?= (int)$modalId ?>;
var postSets    = <?= json_encode($postSets) ?>;
var postNotes   = <?= json_encode($postNotes) ?>;
</script>
<div class="content-container">
<h1 class="page-title">Rencontres</h1>
<div class="content-box">
<table class="table table-rencontres">
  <thead>
    <tr>
      <th>Date</th>
      <th>Heure</th>
      <th>Équipe adverse</th>
      <th>Lieu</th>
      <th>Score (sets)</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $now=new DateTime();
  foreach($rencontres as $r):
    $dt=new DateTime($r['Date_rencontre']);
    $dateAff=$dt->format('d/m/Y');
    $timeAff=$dt->format('H\hi');

    // Calcul setsEq:setsAd
    $setsEq=0; 
    $setsAd=0;
    for($i=1;$i<=5;$i++){
      $se=$r["Set{$i}_equipe"];
      $sa=$r["Set{$i}_adverse"];
      if($se>$sa) $setsEq++;
      else if($sa>$se) $setsAd++;
    }
    $scoreTxt=($setsEq===0 && $setsAd===0)? '—' : ($setsEq." : ".$setsAd);

    // Si match passé => bouton "Voir / Modifier"
    $past=($dt<=$now);
    $label="Match à venir";
    $class="button-disabled";
    $disabled="disabled";
    $onClick="";

    if($past){
      $label="Accès au Score et aux Notes";
      $class="button-add-score";
      $disabled="";
      $onClick="openScoreNotesModal({$r['IdRencontre']}, true)";
    }
  ?>
    <tr>
      <td><?= htmlspecialchars($dateAff) ?></td>
      <td><?= htmlspecialchars($timeAff) ?></td>
      <td><?= htmlspecialchars($r['Nom_équipe']) ?></td>
      <td><?= htmlspecialchars($r['Domicile_ou_exterieur']) ?></td>
      <td><?= htmlspecialchars($scoreTxt) ?></td>
      <td>
        <button class="<?= $class ?>" <?= $disabled ?>
                onclick="<?= $onClick ?>">
          <?= $label ?>
        </button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Bouton bas => gestion_rencontre.php -->
<div class="bottom-button-container">
  <?php if($_SESSION['user']==='guest'): ?>
    <p class="access-restricted">
      Vous n'avez pas accès à la gestion des rencontres en tant qu'invité.
    </p>
  <?php else: ?>
    <a href="gestion_rencontre.php" class="button-manage">
      Gérer les rencontres
    </a>
  <?php endif; ?>
</div>
</div>

<!-- MODALE Modifier Score + Notes -->
<div id="score-notes-modal" class="modal">
  <div class="modal-content modal-content-score-notes">
    <h2>Modifier Score + Notes</h2>

    <!-- Erreur volley -->
    <p id="modalError" class="error-message"></p>

    <form method="POST">
      <input type="hidden" name="action" value="update_score_notes_front">
      <input type="hidden" name="id_rencontre" id="sn_id_rencontre">

      <!-- Tableau pour les 5 sets -->
      <table class="table">
        <thead>
          <tr>
            <th>SET</th>
            <th>ÉQUIPE (MAX25/15)</th>
            <th>ADVERSE</th>
          </tr>
        </thead>
        <tbody>
          <?php for($i=1; $i<=5; $i++):
            $max = ($i === 5) ? 15 : 25;
          ?>
            <tr>
              <td><?= $i ?></td>
              <td>
                <input type="number" name="set<?= $i ?>_equipe" id="sn_set<?= $i ?>_equipe" min="0" max="<?= $max ?>" value="0" class="score-input">
              </td>
              <td>
                <input type="number" name="set<?= $i ?>_adverse" id="sn_set<?= $i ?>_adverse" min="0" max="<?= $max ?>" value="0" class="score-input">
              </td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <!-- Notes des joueurs -->
      <h3>Notes Joueurs (0 à 5)</h3>
      <div id="notesContainer" class="notes-container">
        <!-- Les notes des joueurs seront insérées ici -->
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">Valider</button>
        <button type="button" class="btn btn-secondary" onclick="closeScoreNotesModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>


<script>
function openScoreNotesModal(idR, canEditServer){
  // Affiche la modale
  document.getElementById('score-notes-modal').style.display='flex';
  document.getElementById('sn_id_rencontre').value=idR;

  let isGuest=(userIsGuest==='true');
  // canEdit => canEditServer && !isGuest
  let canEdit=(canEditServer && !isGuest);

  // 1) sets
  for(let i=1;i<=5;i++){
    let max=(i===5?15:25);
    let eqInp=document.getElementById('sn_set'+i+'_equipe');
    let adInp=document.getElementById('sn_set'+i+'_adverse');
    eqInp.value=0; eqInp.max=max; eqInp.disabled=!canEdit;
    adInp.value=0; adInp.max=max; adInp.disabled=!canEdit;
  }

  let cont=document.getElementById('notesContainer');
  cont.innerHTML='';

  // Si showModal && modalId==idR => on restore postSets
  if(showModal==='true' && parseInt(modalId)===idR && Object.keys(postSets).length>0){
    // restore sets
    for(let i=1;i<=5;i++){
      let eq=postSets[`set${i}_equipe`]||0;
      let ad=postSets[`set${i}_adverse`]||0;
      document.getElementById(`sn_set${i}_equipe`).value=eq;
      document.getElementById(`sn_set${i}_adverse`).value=ad;
    }
    // restore notes
    if(Object.keys(postNotes).length>0){
      for(let idJ in postNotes){
        let valN=postNotes[idJ];
        if(valN>5)valN=5;
        let dis=(canEdit?'':'disabled');
        cont.innerHTML += `
          <div class="note-item">
            <label>Joueur #${idJ}</label>
            <input type="number" name="notes[${idJ}]"
                   value="${valN}" min="0" max="5"
                   class="score-input" ${dis}>
          </div>
        `;
      }
    }
  } else {
    // fetch sets
    fetch('get_sets.php?id='+idR)
      .then(r=>r.json())
      .then(d=>{
        for(let i=1;i<=5;i++){
          let eq=d[`set${i}_equipe`]||0;
          let ad=d[`set${i}_adverse`]||0;
          let eqInp=document.getElementById(`sn_set${i}_equipe`);
          let adInp=document.getElementById(`sn_set${i}_adverse`);
          eqInp.value=eq; eqInp.disabled=!canEdit;
          adInp.value=ad; adInp.disabled=!canEdit;
        }
      })
      .catch(err=>console.error(err));

    // fetch participants+notes
    fetch('participants_notes.php?id='+idR)
      .then(r=>r.json())
      .then(list=>{
        if(!list||list.length===0){
          cont.innerHTML='<p>Aucun joueur</p>';
          return;
        }
        list.forEach(p=>{
          let n=(p.Note!==null?p.Note:0);
          if(n>5)n=5;
          let dis=(canEdit?'':'disabled');
          cont.innerHTML += `
            <div class="note-item">
              <label>${p.Nom} ${p.Prénom}</label>
              <input type="number" name="notes[${p.IdJoueur}]"
                     value="${n}" min="0" max="5"
                     class="score-input" ${dis}>
            </div>
          `;
        });
      })
      .catch(err=>console.error(err));
  }

  document.getElementById('modalError').textContent='';
}

function closeScoreNotesModal(){
  document.getElementById('score-notes-modal').style.display='none';
}

// Rouvrir la modale si $error => affichage du message
document.addEventListener('DOMContentLoaded', ()=>{
  if(<?= $error?'true':'false' ?> && <?= $showModal?'true':'false' ?> && <?= $modalId>0?'true':'false' ?>){
    document.getElementById('modalError').textContent=<?= json_encode($message) ?>;
    openScoreNotesModal(<?= $modalId ?>, true);
  }
});
</script>
</body>
</html>
