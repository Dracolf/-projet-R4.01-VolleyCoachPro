<?php
session_start();

// Si pas de session => login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if(!isset($_SESSION['token'])){
  header("Location: logout.php");
  exit;
}

// Si c'est guest => on redirige vers rencontre.php
if ($_SESSION['user'] === 'guest') {
    header("Location: rencontre.php");
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

// Informations BDD
$host     = "mysql-volleycoachpro.alwaysdata.net";
$username = "403542";
$password = "Iutinfo!";
$database = "volleycoachpro_bd";

$message    = "";
$error      = false;

// Indicateurs pour rouvrir la modale “score + notes”
$showModal  = false;
$modalId    = 0;       // IdRencontre où on a eu une erreur
$postSets   = [];      // Valeurs saisies pour les sets
$postNotes  = [];      // Valeurs saisies pour les notes (max=5)

/**
 * Valide un match de volley : 4 sets à 25, 5e à 15, 2 pts d’écart, 3 sets gagnants max.
 */
function validerVolleyScores(array $scores): array
{
    $setsEquipe  = 0;
    $setsAdverse = 0;
    for ($i = 1; $i <= 5; $i++) {
        $eq = (int)($scores["set{$i}_equipe"]  ?? 0);
        $ad = (int)($scores["set{$i}_adverse"] ?? 0);

        // Set non joué => skip
        if ($eq === 0 && $ad === 0) {
            continue;
        }
        // Si déjà 3 sets gagnés => plus de set
        if (($setsEquipe === 3 || $setsAdverse === 3) && ($eq > 0 || $ad > 0)) {
            return ['valid' => false, 'message' => "Set $i joué alors qu'une équipe avait déjà 3 sets gagnés."];
        }
        $isTie    = ($i === 5);
        $minPoints= $isTie ? 15 : 25;
        if($eq < $minPoints && $ad < $minPoints){
            return ['valid'=>false,'message'=>"Set $i : aucun n'a atteint $minPoints pts."];
        }
        if(abs($eq - $ad) < 2){
            return ['valid'=>false,'message'=>"Set $i : écart < 2 pts."];
        }
        if($eq > $ad) {
            $setsEquipe++;
        } elseif($ad > $eq) {
            $setsAdverse++;
        } else {
            return ['valid'=>false,'message'=>"Set $i : égalité impossible"];
        }
    }
    // Besoin de 3 sets gagnés
    if($setsEquipe < 3 && $setsAdverse < 3){
        return ['valid'=>false,'message'=>"Match incomplet (pas 3 sets)."];
    }
    return ['valid' => true, 'message' => "Ok"];
}

try {
    // Connexion PDO
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // On récupère la liste des joueurs Actifs pour l’affectation
    $stmtJ = $pdo->prepare("
        SELECT IdJoueur, Numéro_de_license, Nom, Prénom
        FROM Joueur
        WHERE Statut='Actif'
        ORDER BY Nom ASC
    ");
    $stmtJ->execute();
    $joueursActifs = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    // Traitement des formulaires
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['main_action'])) {
        $action = $_POST['main_action'];

        // -------------------------------------------------
        // AJOUTER UNE RENCONTRE + EQUIPE
        // -------------------------------------------------
        if ($action === 'add_match') {
            $dr = $_POST['date_rencontre'] ?? '';
            $ne = $_POST['nom_equipe']     ?? '';
            $l  = $_POST['lieu']           ?? 'Domicile';

            $roleSlots = $_POST['roleSlots'] ?? [];
            // Vérif que tous les slots sont remplis
            foreach ($roleSlots as $slot => $idJ) {
                if (empty($idJ)) {
                    $message = "Tous les rôles doivent être remplis (slot '$slot' vide).";
                    $error   = true;
                    break;
                }
            }
            if(!$error){
                // Insérer la rencontre
                $ins = $pdo->prepare("
                  INSERT INTO Rencontre(
                    Date_rencontre, Nom_équipe, Domicile_ou_exterieur,
                    Set1_equipe,Set1_adverse, Set2_equipe,Set2_adverse,
                    Set3_equipe,Set3_adverse, Set4_equipe,Set4_adverse,
                    Set5_equipe,Set5_adverse
                  ) VALUES(
                    :dr, :ne, :l,
                    0,0,0,0,0,0,0,0,0,0
                  )
                ");
                $ins->execute([':dr'=>$dr, ':ne'=>$ne, ':l'=>$l]);
                $newId = $pdo->lastInsertId();

                // Insérer Participer
                foreach($roleSlots as $slotName => $idJoueur){
                    $isRemp=(strpos($slotName,'remp')===0);
                    $tituOrRemp=$isRemp?'Remplaçant':'Titulaire';
                    $pdo->prepare("
                      INSERT INTO Participer(IdJoueur,IdRencontre,Rôle,Titulaire_ou_remplacant)
                      VALUES(:idj,:idr,:r,:t)
                    ")->execute([
                      ':idj'=>$idJoueur,
                      ':idr'=>$newId,
                      ':r'=>$slotName,
                      ':t'=>$tituOrRemp
                    ]);
                }
                $message = "Rencontre créée avec succès.";
            }
        }

        // -------------------------------------------------
        // MODIFIER L’EQUIPE
        // -------------------------------------------------
        elseif($action==='update_team'){
            $idR=$_POST['id_rencontre']??null;
            if(!$idR){
                $message="Erreur: pas d'idRencontre (update_team).";
                $error=true;
            } else {
                $roleSlots=$_POST['roleSlots']??[];
                foreach($roleSlots as $slot=>$idJ){
                    if(empty($idJ)){
                        $message="Tous les rôles doivent être remplis (slot '$slot' vide).";
                        $error=true;
                        break;
                    }
                }
                if(!$error){
                    // Suppr Participer
                    $pdo->prepare("DELETE FROM Participer WHERE IdRencontre=:id")
                        ->execute([':id'=>$idR]);
                    // Réinsère
                    foreach($roleSlots as $slotName=>$idJoueur){
                        $isRemp=(strpos($slotName,'remp')===0);
                        $tituOrRemp=$isRemp?'Remplaçant':'Titulaire';
                        $pdo->prepare("
                          INSERT INTO Participer(IdJoueur,IdRencontre,Rôle,Titulaire_ou_remplacant)
                          VALUES(:idj,:idr,:r,:t)
                        ")->execute([
                          ':idj'=>$idJoueur,':idr'=>$idR,
                          ':r'=>$slotName,':t'=>$tituOrRemp
                        ]);
                    }
                    $message="Équipe mise à jour avec succès.";
                }
            }
        }

        // -------------------------------------------------
        // MODIFIER SCORE + NOTES (5 sets, notes max=5)
        // -------------------------------------------------
        elseif($action==='update_score_notes'){
            $idR=$_POST['id_rencontre']??null;
            if(!$idR){
                $message="Erreur: pas d'idRencontre (update_score_notes).";
                $error=true;
            } else {
                // Récup sets
                for($i=1;$i<=5;$i++){
                    $postSets["set{$i}_equipe"] =(int)($_POST["set{$i}_equipe"]??0);
                    $postSets["set{$i}_adverse"]=(int)($_POST["set{$i}_adverse"]??0);
                }
                // Récup notes => max=5
                if(isset($_POST['notes']) && is_array($_POST['notes'])){
                    foreach($_POST['notes'] as $idJ=>$valNote){
                        $n=(int)$valNote;
                        if($n<0)$n=0;
                        if($n>5)$n=5;
                        $postNotes[$idJ]=$n;
                    }
                }
                // Validation volley
                $check=validerVolleyScores($postSets);
                if(!$check['valid']){
                    $message="Erreur score volley : ".$check['message'];
                    $error=true;
                    $showModal=true;
                    $modalId=(int)$idR;
                } else {
                    // Update sets
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
                    // Update notes
                    foreach($postNotes as $idJ=>$n){
                        $pdo->prepare("
                          UPDATE Participer
                          SET Note=:n
                          WHERE IdRencontre=:idr AND IdJoueur=:idj
                        ")->execute([
                          ':n'=>$n, ':idr'=>$idR, ':idj'=>$idJ
                        ]);
                    }
                    $message="Score + notes mis à jour avec succès.";
                }
            }
        }

        // -------------------------------------------------
        // MODIFIER INFOS
        // -------------------------------------------------
        elseif($action==='update_info'){
            $idR=$_POST['id_rencontre']??null;
            if(!$idR){
                $message="Erreur: pas d'idRencontre (update_info).";
                $error=true;
            } else {
                $dr=$_POST['date_rencontre']??'';
                $ne=$_POST['nom_equipe']    ??'';
                $l =$_POST['lieu']          ??'Domicile';

                $pdo->prepare("
                  UPDATE Rencontre
                  SET Date_rencontre=:dr, Nom_équipe=:ne, Domicile_ou_exterieur=:l
                  WHERE IdRencontre=:id
                ")->execute([
                  ':dr'=>$dr, ':ne'=>$ne, ':l'=>$l, ':id'=>$idR
                ]);
                $message="Infos du match modifiées avec succès.";
            }
        }
    }
    elseif(isset($_GET['action']) && $_GET['action']==='delete'){
        $idDel=$_GET['id']??null;
        if($idDel){
            $pdo->prepare("DELETE FROM Participer WHERE IdRencontre=:id")->execute([':id'=>$idDel]);
            $pdo->prepare("DELETE FROM Rencontre   WHERE IdRencontre=:id")->execute([':id'=>$idDel]);
            $message="Rencontre supprimée.";
        }
    }

    // Récup listes de rencontres
    $rencontres = sendCurlRequest($api_url, "GET", $token, null);
    if ($rencontres['code'] !== 200) {
        header("Location: logout.php");
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
  <title>Gestion des Rencontres</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- NAV -->
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
        <a href="rencontre.php">Rencontres</a>
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

<!-- Indicateurs JS -->
<script>
var showModal   = <?= $showModal?'true':'false' ?>;
var modalId     = <?= (int)$modalId ?>;
// postSets => ex: { "set1_equipe":10, "set1_adverse":25, ... }
var postSets    = <?= json_encode($postSets) ?>;
// postNotes => ex: { "1":3, "2":5, ... }
var postNotes   = <?= json_encode($postNotes) ?>;
</script>


<div class="content-container">
<h1 class="page-title">Gestion des Rencontres</h1>

<!-- Bouton AJOUT -->
<div class="search-container">
  <button class="button-add" onclick="openAddMatchModal()">Ajouter une nouvelle rencontre</button>
</div>

<!-- Tableau rencontres -->
<div class="content-box">
<table class="table table-rencontres">
  <thead>
    <tr>
      <th>Date/Heure</th>
      <th>Équipe adverse</th>
      <th>Lieu</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rencontres as $r):
        $dt = new DateTime($r['Date_rencontre']);
        $affDate = $dt->format('d/m/Y H:i');
        $isPast = $dt < new DateTime(); // Vérifie si la date est passée
    ?>
    <tr>
        <td><?= htmlspecialchars($affDate) ?></td>
        <td><?= htmlspecialchars($r['Nom_équipe']) ?></td>
        <td><?= htmlspecialchars($r['Domicile_ou_exterieur']) ?></td>
        <td>
            <button class="button-edit" onclick="openUpdateChoiceModal(<?= $r['IdRencontre'] ?>)">Modifier</button>
            <a class="button-delete <?= $isPast ? 'disabled' : '' ?>"
                href="<?= $isPast ? '#' : '?action=delete&id=' . $r['IdRencontre'] ?>"
                onclick="<?= $isPast ? 'return false;' : 'return confirm(\'Supprimer ?\');' ?>">
                Supprimer
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Bouton pour aller à rencontre.php -->
        <div class="bottom-button-container">
            <a href="rencontre.php" class="button-manage">Voir les rencontres</a>
        </div>
    </div>

<!-- MODALES -->

<!-- Ajout match + équipe -->
<div id="add-match-modal" class="modal">
  <div class="modal-content">
    <h2>Ajouter une nouvelle rencontre</h2>
    <form method="POST" onsubmit="return checkNoEmpty('add_match_slotsContainer');">
      <input type="hidden" name="main_action" value="add_match">
      <label>Date/Heure :
        <input type="datetime-local" name="date_rencontre" required>
      </label>
      <br><br>
      <label>Nom équipe adverse :
        <input type="text" name="nom_equipe" required>
      </label>
      <br><br>
      <label>Lieu :
        <select name="lieu">
          <option value="Domicile">Domicile</option>
          <option value="Extérieur">Extérieur</option>
        </select>
      </label>
      <hr class="marge">
      <h3>Affectation des rôles</h3>
      <div id="add_match_slotsContainer"></div>
      <div class="modal-actions">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeAddMatchModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Choice -->
<div id="update-choice-modal" class="modal">
  <div class="modal-content">
    <h2>Modifier la rencontre</h2>
    <input type="hidden" id="updChoice_id">
    <p>Que souhaitez-vous modifier ?</p>
    <div class="flex-column-gap">
      <button onclick="updateTeam(document.getElementById('updChoice_id').value)">Modifier l'équipe</button>
      <button onclick="updateScoreNotes(document.getElementById('updChoice_id').value)">Modifier le score + notes</button>
      <button onclick="updateInfo(document.getElementById('updChoice_id').value)">Modifier les infos</button>
    </div>
    <div class="modal-actions">
      <button type="button" onclick="closeUpdateChoiceModal()">Annuler</button>
    </div>
  </div>
</div>

<!-- Update Team -->
<div id="update-team-modal" class="modal">
  <div class="modal-content">
    <h2>Modifier l'équipe</h2>
    <form method="POST" onsubmit="return checkNoEmpty('upd_team_slotsContainer');">
      <input type="hidden" name="main_action" value="update_team">
      <input type="hidden" name="id_rencontre" id="upd_team_id_rencontre">
      <div id="upd_team_slotsContainer"></div>
      <div class="modal-actions">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeUpdateTeamModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Score+Notes (5 sets toujours visibles, notes max=5) -->
<div id="update-score-modal" class="modal">
  <div class="modal-content">
    <h2>Modifier Score + Notes</h2>
    <p id="scoreModalError" class="error-message"></p>
    <form method="POST">
      <input type="hidden" name="main_action" value="update_score_notes">
      <input type="hidden" name="id_rencontre" id="upd_score_id_rencontre">

      <table class="table">
        <thead>
          <tr><th>Set</th><th>Équipe (max25/15)</th><th>Adverse</th></tr>
        </thead>
        <tbody>
        <?php for($i=1;$i<=5;$i++):
          $maxVal=($i===5)?15:25;
        ?>
          <tr>
            <td><?= $i ?></td>
            <td>
              <input type="number" name="set<?= $i ?>_equipe"
                     id="upd_score_set<?= $i ?>_equipe"
                     min="0" max="<?= $maxVal ?>"
                     value="0" class=".modal-content-score-notes input[type="number"]">
            </td>
            <td>
              <input type="number" name="set<?= $i ?>_adverse"
                     id="upd_score_set<?= $i ?>_adverse"
                     min="0" max="<?= $maxVal ?>"
                     value="0" class=".modal-content-score-notes input[type="number"]">
            </td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>

      <h3>Notes Joueurs (0 à 5)</h3>
      <div id="score_notes_players" class="note-container"></div>

      <div class="modal-actions">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeUpdateScoreModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Info -->
<div id="update-info-modal" class="modal">
  <div class="modal-content">
    <h2>Modifier les infos</h2>
    <form method="POST">
      <input type="hidden" name="main_action" value="update_info">
      <input type="hidden" name="id_rencontre" id="upd_info_id_rencontre">

      <label>Date/Heure :
        <input type="datetime-local" name="date_rencontre" id="upd_info_date" required>
      </label>
      <br><br>
      <label>Nom équipe adverse :
        <input type="text" name="nom_equipe" id="upd_info_nom" required>
      </label>
      <br><br>
      <label>Lieu :
        <select name="lieu" id="upd_info_lieu">
          <option value="Domicile">Domicile</option>
          <option value="Extérieur">Extérieur</option>
        </select>
      </label>
      <div class="modal-actions">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeUpdateInfoModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
// Liste joueurs Actifs
var joueursActifs = <?php echo json_encode($joueursActifs, JSON_HEX_TAG|JSON_HEX_AMP); ?>;

// Rôles (6 fixes + 6 remplaçants)
let rolesFixed = [
  {slot:'avant_droit',   label:'Avant droit'},
  {slot:'avant_centre',  label:'Avant centre'},
  {slot:'avant_gauche',  label:'Avant gauche'},
  {slot:'arriere_droit', label:'Arrière droit'},
  {slot:'arriere_gauche',label:'Arrière gauche'},
  {slot:'libero',        label:'Libéro'}
];
for(let i=1;i<=6;i++){
  rolesFixed.push({slot:'remp'+i, label:'Remplaçant #'+i});
}

// *** FONCTIONS AJOUT MATCH ***
function openAddMatchModal(){
  document.getElementById('add-match-modal').style.display='flex';
  fillAllSlots('add_match_slotsContainer', {});
}
function closeAddMatchModal(){
  document.getElementById('add-match-modal').style.display='none';
}
function fillAllSlots(containerId, selected){
  let container = document.getElementById(containerId);
  container.innerHTML='';
  let used=Object.values(selected).filter(v=>v);

  rolesFixed.forEach(r=>{
    let chosen=selected[r.slot]||'';
    let row=document.createElement('div');
    row.className='role-slot-row';

    let lab=document.createElement('label');
    lab.textContent=r.label+" :";

    let sel=document.createElement('select');
    sel.name=`roleSlots[${r.slot}]`;
    sel.id=`slot_${r.slot}`;

    let optNone=document.createElement('option');
    optNone.value='';
    optNone.textContent='(Aucun)';
    sel.appendChild(optNone);

    joueursActifs.forEach(j=>{
      if(used.includes(String(j.IdJoueur)) && String(j.IdJoueur)!==String(chosen)){
        // skip
      } else {
        let opt=document.createElement('option');
        opt.value=j.IdJoueur;
        opt.textContent=`${j.Nom} ${j.Prénom} (Lic:${j.Numéro_de_license})`;
        if(String(chosen)===String(j.IdJoueur)) opt.selected=true;
        sel.appendChild(opt);
      }
    });

    sel.addEventListener('change',()=>{
      onChangeSlot(containerId,selected,r.slot,sel);
    });

    row.appendChild(lab);
    row.appendChild(sel);
    container.appendChild(row);
  });
}
function onChangeSlot(containerId, selected, slotName, selectEl){
  let oldVal=selected[slotName]||'';
  let newVal=selectEl.value||'';
  if(oldVal===newVal)return;
  if(newVal){
    selected[slotName]=newVal;
  } else {
    delete selected[slotName];
  }
  fillAllSlots(containerId, selected);
}
function checkNoEmpty(containerId){
  let sel=document.querySelectorAll('#'+containerId+' select');
  for(let s of sel){
    if(!s.value){
      alert("Tous les rôles doivent être remplis.");
      return false;
    }
  }
  return true;
}

// *** FONCTIONS UPDATE CHOICE ***
function openUpdateChoiceModal(idR){
  document.getElementById('update-choice-modal').style.display='flex';
  document.getElementById('updChoice_id').value=idR;
}
function closeUpdateChoiceModal(){
  document.getElementById('update-choice-modal').style.display='none';
}

// *** updateTeam ***
function updateTeam(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-team-modal').style.display='flex';
  document.getElementById('upd_team_id_rencontre').value=idR;

  fetch('participants_rencontre.php?id='+idR)
    .then(r=>r.json())
    .then(selected=>{
      fillAllSlots('upd_team_slotsContainer', selected);
    })
    .catch(err=>console.error(err));
}
function closeUpdateTeamModal(){
  document.getElementById('update-team-modal').style.display='none';
}

// *** updateScoreNotes (modale “score+notes”) ***
function updateScoreNotes(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-score-modal').style.display='flex';
  document.getElementById('upd_score_id_rencontre').value=idR;

  // Réinitialiser sets
  for(let i=1;i<=5;i++){
    let max=(i===5)?15:25;
    document.getElementById('upd_score_set'+i+'_equipe').max=max;
    document.getElementById('upd_score_set'+i+'_equipe').value=0;
    document.getElementById('upd_score_set'+i+'_adverse').max=max;
    document.getElementById('upd_score_set'+i+'_adverse').value=0;
  }

  // Si showModal & modalId==idR => on a postSets => on les restaure
  if(showModal==='true' && parseInt(modalId)===parseInt(idR) && Object.keys(postSets).length>0){
    for(let i=1;i<=5;i++){
      let eq=postSets[`set${i}_equipe`]||0;
      let ad=postSets[`set${i}_adverse`]||0;
      document.getElementById(`upd_score_set${i}_equipe`).value=eq;
      document.getElementById(`upd_score_set${i}_adverse`).value=ad;
    }
  } else {
    // fetch sets
    fetch('get_sets.php?id='+idR)
      .then(r=>r.json())
      .then(d=>{
        for(let i=1;i<=5;i++){
          let eq=d[`set${i}_equipe`]||0;
          let ad=d[`set${i}_adverse`]||0;
          document.getElementById(`upd_score_set${i}_equipe`).value=eq;
          document.getElementById(`upd_score_set${i}_adverse`).value=ad;
        }
      })
      .catch(err=>console.error(err));
  }

  // Charger notes
  let cont=document.getElementById('score_notes_players');
  cont.innerHTML='';
  if(showModal==='true' && parseInt(modalId)===parseInt(idR) && Object.keys(postNotes).length>0){
    // Restaure
    for(let idJ in postNotes){
      let valN=postNotes[idJ];
      if(valN>5)valN=5;
      cont.innerHTML+=`
        <div class="margin-bottom-5">
            <label>Joueur #${idJ}</label>
            <input type="number" name="notes[${idJ}]"
                value="${valN}" min="0" max="5"
            class="input-small">
        </div>
      `;
    }
  } else {
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
          cont.innerHTML+=`
            <div style="margin-bottom:5px;">
              <label>${p.Nom} ${p.Prénom}</label>
              <input type="number" name="notes[${p.IdJoueur}]"
                     value="${n}" min="0" max="5"
                     style="width:50px;">
            </div>
          `;
        });
      })
      .catch(err=>console.error(err));
  }

  // Réinitialiser l’erreur
  document.getElementById('scoreModalError').textContent='';
}
function closeUpdateScoreModal(){
  document.getElementById('update-score-modal').style.display='none';
}

// *** updateInfo
function updateInfo(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-info-modal').style.display='flex';
  document.getElementById('upd_info_id_rencontre').value=idR;

  fetch('info_rencontre.php?id='+idR)
    .then(r=>r.json())
    .then(d=>{
      document.getElementById('upd_info_date').value = d.DateRencontre || '';
      document.getElementById('upd_info_nom').value  = d.NomEquipe     || '';
      document.getElementById('upd_info_lieu').value = d.Lieu          || 'Domicile';
    })
    .catch(err=>console.error(err));
}
function closeUpdateInfoModal(){
  document.getElementById('update-info-modal').style.display='none';
}

// *** Au chargement, si erreur => rouvrir la modale score+notes
document.addEventListener('DOMContentLoaded', ()=>{
  if(<?= $error?'true':'false' ?> && <?= $showModal?'true':'false' ?> && <?= $modalId>0?'true':'false' ?>){
    document.getElementById('scoreModalError').textContent=<?= json_encode($message) ?>;
    updateScoreNotes(<?= $modalId ?>);
  }
});
</script>
</body>
</html>
