<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['token'])) {
    header("Location: logout.php");
    exit;
}

// Si c’est guest => on redirige vers rencontre.php
if ($_SESSION['user'] === 'guest') {
    header("Location: rencontre.php");
    exit;
}

$token = $_SESSION['token'];
$api_url_matchs   = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/";
$api_url_joueurs  = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/";

$message = "";
$error   = false;

/**
 * Fonction cURL générique
 */
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
    $response    = curl_exec($ch);
    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        "code"     => $http_code,
        "response" => json_decode($response, true)
    ];
}

// ---------------------------------------------------------------------
// Récupération liste des joueurs Actifs pour l’affectation
// ---------------------------------------------------------------------
$joueursActifs = [];
$repJ = sendCurlRequest($api_url_joueurs, "GET", $token, null);
if ($repJ['code'] === 200) {
    $dataJ = $repJ['response']['data'] ?? [];
    foreach ($dataJ as $j) {
        if (($j['Statut'] ?? '') === 'Actif') {
            $joueursActifs[] = $j;
        }
    }
} else {
    $message = "Impossible de récupérer les joueurs (code=".$repJ['code'].")";
    $error   = true;
}

// ---------------------------------------------------------------------
// Traitement du formulaire
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['main_action'])) {
    $action = $_POST['main_action'];

    // (1) Ajouter une rencontre => POST /matchs/
    if ($action === 'add_match') {
        $dr        = $_POST['date_rencontre'] ?? '';
        $ne        = $_POST['nom_equipe']     ?? '';
        $l         = $_POST['lieu']           ?? 'Domicile';
        $roleSlots = $_POST['roleSlots']      ?? [];

        // Vérif que tous les slots sont remplis
        foreach ($roleSlots as $slot => $idJ) {
            if (empty($idJ)) {
                $error   = true;
                $message = "Tous les rôles doivent être remplis (slot '$slot' vide).";
                break;
            }
        }
        if (!$error) {
            // Construction du body
            $mapSlots = [
                "avant_droit"   => "avd",
                "avant_centre"  => "avc",
                "avant_gauche"  => "avg",
                "arriere_droit" => "ard",
                "arriere_gauche"=> "arg",
                "libero"        => "lib",
                "remp1"         => "r1",
                "remp2"         => "r2",
                "remp3"         => "r3",
                "remp4"         => "r4",
                "remp5"         => "r5",
                "remp6"         => "r6",
            ];
            $postData = [
                "date"       => $dr,
                "adversaire" => $ne,
                "domext"     => $l,
                // Score initial
                "s1e" => 0,"s1a" => 0,
                "s2e" => 0,"s2a" => 0,
                "s3e" => 0,"s3a" => 0,
                "s4e" => 0,"s4a" => 0,
                "s5e" => 0,"s5a" => 0
            ];
            // Rôles
            foreach ($roleSlots as $slotName => $idJoueur) {
                if (isset($mapSlots[$slotName])) {
                    $postData[$mapSlots[$slotName]] = (int)$idJoueur;
                }
            }
            // Envoi POST
            $resAdd = sendCurlRequest($api_url_matchs, "POST", $token, $postData);
            if ($resAdd['code'] === 201) {
                $message = "Rencontre créée via l'API avec succès.";
            } else {
                $error   = true;
                $message = "Échec d’ajout (code=".$resAdd['code'].") : "
                          .($resAdd['response']['status_message'] ?? '??');
            }
        }
    }

    // (2) Modifier l’équipe => PUT /matchs/{id} en envoyant avd, avc, etc.
    elseif ($action === 'update_team') {
        $idR = $_POST['id_rencontre'] ?? null;
        if (!$idR) {
            $error   = true;
            $message = "Erreur: pas d'idRencontre (update_team).";
        } else {
            $roleSlots = $_POST['roleSlots']??[];
            foreach ($roleSlots as $slot=>$idJ){
                if(empty($idJ)){
                    $error   = true;
                    $message = "Tous les rôles doivent être remplis (slot '$slot' vide).";
                    break;
                }
            }
            if(!$error){
                $mapSlots = [
                    "avant_droit"   => "avd",
                    "avant_centre"  => "avc",
                    "avant_gauche"  => "avg",
                    "arriere_droit" => "ard",
                    "arriere_gauche"=> "arg",
                    "libero"        => "lib",
                    "remp1"         => "r1",
                    "remp2"         => "r2",
                    "remp3"         => "r3",
                    "remp4"         => "r4",
                    "remp5"         => "r5",
                    "remp6"         => "r6",
                ];
                $body = [];
                foreach($roleSlots as $slotName=>$idJoueur){
                    if(isset($mapSlots[$slotName])){
                        $body[ $mapSlots[$slotName] ] = (int)$idJoueur;
                    }
                }
                $resUp = sendCurlRequest($api_url_matchs.$idR, "PUT", $token, $body);
                if($resUp['code']===200){
                    $message = "Équipe mise à jour avec succès.";
                } else {
                    $error   = true;
                    $message = "Échec update équipe (code=".$resUp['code'].") : "
                              .($resUp['response']['status_message'] ?? '??');
                }
            }
        }
    }

    // (3) Modifier Score + Notes => PUT /matchs/{id} (s1e..., noteAVG..., etc.)
    elseif ($action === 'update_score_notes') {
        $idR = $_POST['id_rencontre'] ?? null;
        if (!$idR) {
            $error   = true;
            $message = "Erreur: pas d'idRencontre (update_score_notes).";
        } else {
            // Récup sets
            $body = [
                "s1e" => (int)($_POST["set1_equipe"]  ?? 0),
                "s1a" => (int)($_POST["set1_adverse"] ?? 0),
                "s2e" => (int)($_POST["set2_equipe"]  ?? 0),
                "s2a" => (int)($_POST["set2_adverse"] ?? 0),
                "s3e" => (int)($_POST["set3_equipe"]  ?? 0),
                "s3a" => (int)($_POST["set3_adverse"] ?? 0),
                "s4e" => (int)($_POST["set4_equipe"]  ?? 0),
                "s4a" => (int)($_POST["set4_adverse"] ?? 0),
                "s5e" => (int)($_POST["set5_equipe"]  ?? 0),
                "s5a" => (int)($_POST["set5_adverse"] ?? 0),
            ];
            // Notes => noteAVG, noteAVC, etc.
            $notesFields = [
                "noteAVG","noteAVC","noteAVD","noteARG","noteARD","noteLIB",
                "noteR1","noteR2","noteR3","noteR4","noteR5","noteR6"
            ];
            foreach($notesFields as $f){
                if(isset($_POST[$f])){
                    $val = (int)$_POST[$f];
                    if($val<0) $val=0;
                    if($val>5) $val=5;
                    $body[$f] = $val;
                }
            }
            // Envoi PUT
            $resUp = sendCurlRequest($api_url_matchs.$idR, "PUT", $token, $body);
            if($resUp['code']===200){
                $message = "Score + notes modifiés.";
            } else {
                $error   = true;
                $message = "Échec update score/notes (code=".$resUp['code'].") : "
                          .($resUp['response']['status_message'] ?? '??');
            }
        }
    }

    // (4) Modifier Infos => PUT /matchs/{id} ( date, adversaire, domext )
    elseif ($action === 'update_info') {
        $idR = $_POST['id_rencontre'] ?? null;
        if(!$idR){
            $error   = true;
            $message = "Erreur: pas d'idRencontre (update_info).";
        } else {
            $dr = $_POST['date_rencontre'] ?? '';
            $ne = $_POST['nom_equipe']     ?? '';
            $l  = $_POST['lieu']           ?? 'Domicile';
            $body = [
                "date"       => $dr,
                "adversaire" => $ne,
                "domext"     => $l
            ];
            $resUp = sendCurlRequest($api_url_matchs.$idR, "PUT", $token, $body);
            if($resUp['code']===200){
                $message = "Infos du match modifiées.";
            } else {
                $error   = true;
                $message = "Échec update infos (code=".$resUp['code'].") : "
                          .($resUp['response']['status_message'] ?? '??');
            }
        }
    }
}

// ---------------------------------------------------------------------
// Supprimer => DELETE /matchs/{id}
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action']==='delete') {
    $idDel = $_GET['id'] ?? null;
    if($idDel){
        $resDel = sendCurlRequest($api_url_matchs.$idDel, "DELETE", $token, null);
        if($resDel['code']===200){
            $message = "Rencontre d'ID $idDel supprimée";
        } else {
            $error   = true;
            $message = "Échec suppression (code=".$resDel['code'].") : "
                      .($resDel['response']['status_message'] ?? '??');
        }
    }
}

// ---------------------------------------------------------------------
// Récup liste des rencontres => GET /matchs/
// ---------------------------------------------------------------------
$rencontres = [];
$resMatches = sendCurlRequest($api_url_matchs, "GET", $token, null);
if ($resMatches['code']===200){
    $rencontres = $resMatches['response']['data'] ?? [];
} else {
    $error   = true;
    $message = "Impossible de récupérer les rencontres (code=".$resMatches['code'].").";
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
<p class="message <?= $error ? 'error-message':'info-message' ?>">
  <?= htmlspecialchars($message) ?>
</p>
<?php endif; ?>

<div class="content-container">
  <h1 class="page-title">Gestion des Rencontres</h1>

  <div class="search-container">
    <button class="button-add" onclick="openAddMatchModal()">Ajouter une nouvelle rencontre</button>
  </div>

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
        <?php
        foreach ($rencontres as $r):
            $dt = new DateTime($r['Date_rencontre']);
            $affDate = $dt->format('d/m/Y H:i');
            $isPast  = ($dt < new DateTime());
        ?>
        <tr>
          <td><?= htmlspecialchars($affDate) ?></td>
          <td><?= htmlspecialchars($r['Nom_équipe']) ?></td>
          <td><?= htmlspecialchars($r['Domicile_ou_exterieur']) ?></td>
          <td>
            <button class="button-edit" 
                    onclick="openUpdateChoiceModal(<?= $r['IdRencontre'] ?>)">
              Modifier
            </button>
            <a class="button-delete <?= $isPast?'disabled':'' ?>"
               href="<?= $isPast?'#':'?action=delete&id='.$r['IdRencontre'] ?>"
               onclick="<?= $isPast?'return false;':'return confirm(\'Supprimer ?\');' ?>">
              Supprimer
            </a>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>

  <div class="bottom-button-container">
    <a href="rencontre.php" class="button-manage">Voir les rencontres</a>
  </div>
</div>

<!-- Modales -->
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

<div id="update-score-modal" class="modal">
  <div class="modal-content">
    <h2>Modifier Score + Notes</h2>
    <p id="scoreModalError" class="error-message"></p>
    <form method="POST">
      <input type="hidden" name="main_action" value="update_score_notes">
      <input type="hidden" name="id_rencontre" id="upd_score_id_rencontre">

      <table class="table">
        <thead>
          <tr><th>Set</th><th>Équipe</th><th>Adverse</th></tr>
        </thead>
        <tbody>
        <?php for($i=1;$i<=5;$i++): ?>
          <tr>
            <td><?= $i ?></td>
            <td><input type="number" name="set<?= $i ?>_equipe" id="upd_score_set<?= $i ?>_equipe" min="0" max="50" value="0"></td>
            <td><input type="number" name="set<?= $i ?>_adverse" id="upd_score_set<?= $i ?>_adverse" min="0" max="50" value="0"></td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>

      <h3>Notes Joueurs (0 à 5)</h3>
      <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        <label>Avant Gauche (AVG):
          <input type="number" name="noteAVG" min="0" max="5" value="0">
        </label>
        <label>Avant Centre (AVC):
          <input type="number" name="noteAVC" min="0" max="5" value="0">
        </label>
        <label>Avant Droit (AVD):
          <input type="number" name="noteAVD" min="0" max="5" value="0">
        </label>
        <label>Arrière Gauche (ARG):
          <input type="number" name="noteARG" min="0" max="5" value="0">
        </label>
        <label>Arrière Droit (ARD):
          <input type="number" name="noteARD" min="0" max="5" value="0">
        </label>
        <label>Libéro (LIB):
          <input type="number" name="noteLIB" min="0" max="5" value="0">
        </label>
        <label>Remplaçant #1 (R1):
          <input type="number" name="noteR1" min="0" max="5" value="0">
        </label>
        <label>Remplaçant #2 (R2):
          <input type="number" name="noteR2" min="0" max="5" value="0">
        </label>
        <label>Remplaçant #3 (R3):
          <input type="number" name="noteR3" min="0" max="5" value="0">
        </label>
        <label>Remplaçant #4 (R4):
          <input type="number" name="noteR4" min="0" max="5" value="0">
        </label>
        <label>Remplaçant #5 (R5):
          <input type="number" name="noteR5" min="0" max="5" value="0">
        </label>
        <label>Remplaçant #6 (R6):
          <input type="number" name="noteR6" min="0" max="5" value="0">
        </label>
      </div>

      <div class="modal-actions">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeUpdateScoreModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

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
var joueursActifs = <?php echo json_encode($joueursActifs, JSON_HEX_TAG|JSON_HEX_AMP); ?>;

/** Rôles fixés */
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
      let oldVal=selected[r.slot]||'';
      let newVal=sel.value||'';
      if(oldVal!==newVal){
        if(newVal){
          selected[r.slot]=newVal;
        } else {
          delete selected[r.slot];
        }
        fillAllSlots(containerId, selected);
      }
    });

    row.appendChild(lab);
    row.appendChild(sel);
    container.appendChild(row);
  });
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

function openUpdateChoiceModal(idR){
  document.getElementById('update-choice-modal').style.display='flex';
  document.getElementById('updChoice_id').value=idR;
}
function closeUpdateChoiceModal(){
  document.getElementById('update-choice-modal').style.display='none';
}

function updateTeam(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-team-modal').style.display='flex';
  document.getElementById('upd_team_id_rencontre').value=idR;
  // Pas d’API GET participants => on laisse l’utilisateur ressaisir l’équipe
  fillAllSlots('upd_team_slotsContainer', {});
}
function closeUpdateTeamModal(){
  document.getElementById('update-team-modal').style.display='none';
}

function updateScoreNotes(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-score-modal').style.display='flex';
  document.getElementById('upd_score_id_rencontre').value=idR;
  // Remettre sets à 0
  for(let i=1;i<=5;i++){
    document.getElementById(`upd_score_set${i}_equipe`).value=0;
    document.getElementById(`upd_score_set${i}_adverse`).value=0;
  }
  document.getElementById('scoreModalError').textContent='';
}
function closeUpdateScoreModal(){
  document.getElementById('update-score-modal').style.display='none';
}

function updateInfo(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-info-modal').style.display='flex';
  document.getElementById('upd_info_id_rencontre').value=idR;
  // Pas de fetch => on laisse l’utilisateur ressaisir
  document.getElementById('upd_info_date').value='';
  document.getElementById('upd_info_nom').value='';
  document.getElementById('upd_info_lieu').value='Domicile';
}
function closeUpdateInfoModal(){
  document.getElementById('update-info-modal').style.display='none';
}
</script>
</body>
</html>
