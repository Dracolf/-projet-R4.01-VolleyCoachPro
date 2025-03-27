<?php
/*****************************************************************************/
/* GESTION_RENCONTRE.PHP - Version avec la même modale Score+Notes que rencontre.php */
/*****************************************************************************/
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['token'])) {
    header("Location: logout.php");
    exit;
}

// Bloquer si c’est guest
if ($_SESSION['user'] === 'guest') {
    header("Location: rencontre.php");
    exit;
}

$token = $_SESSION['token'];

/*****************************************************************************/
/* URLs de l’API  */
/*****************************************************************************/
$api_url_matchs  = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/";
$api_url_joueurs = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/";

$message = "";
$error   = false;

/*****************************************************************************/
/* FONCTION cURL GÉNÉRIQUE */
/*****************************************************************************/
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
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
       "code"     => $code,
       "response" => json_decode($resp, true)
    ];
}

/*****************************************************************************/
/* 1) Récupération liste joueurs Actifs */
/*****************************************************************************/
$joueursActifs = [];
$resJoueurs = sendCurlRequest($api_url_joueurs, "GET", $token);
if ($resJoueurs['code']===200){
    $dataJ = $resJoueurs['response']['data'] ?? [];
    foreach($dataJ as $j){
        if(($j['Statut']??'')==='Actif'){
            $joueursActifs[] = $j;
        }
    }
} else {
    $error = true;
    $message="Impossible de récupérer joueurs (code=".$resJoueurs['code'].")";
}

/*****************************************************************************/
/* 2) Traitement des actions (Add, update_team, update_score_notes, update_info) */
/*****************************************************************************/
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['main_action'])){
    $action = $_POST['main_action'];

    // (A) Ajouter une rencontre => POST /matchs/
    if($action==='add_match'){
        // ...
    }

    // (B) Modifier l’équipe => PUT /matchs/{id}
    elseif($action==='update_team'){
        // ...
    }

    // (C) Modifier Score + Notes => PUT /matchs/{id}
    elseif($action==='update_score_notes'){
        // ...
    }

    // (D) Modifier Infos => PUT /matchs/{id}
    elseif($action==='update_info'){
        // ...
    }
}

/*****************************************************************************/
/* 3) Supprimer => DELETE /matchs/{id} */
/*****************************************************************************/
if(isset($_GET['action']) && $_GET['action']==='delete'){
    // ...
}

/*****************************************************************************/
/* 4) Récup liste => GET /matchs/ */
/*****************************************************************************/
$rencontres = [];
$resMatches = sendCurlRequest($api_url_matchs, "GET", $token, null);
if($resMatches['code']===200){
    $rencontres = $resMatches['response']['data'] ?? [];
} else {
    $error=true;
    $message="Impossible de récupérer rencontres (code=".$resMatches['code'].")";
}

/*****************************************************************************/
/* 5) HTML */
/*****************************************************************************/
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
<p class="message <?= $error?'error-message':'info-message' ?>">
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
      $now=new DateTime();
      foreach($rencontres as $r):
        $dt=new DateTime($r['Date_rencontre']);
        $affDate=$dt->format('d/m/Y H:i');
        $isPast=($dt<$now);
      ?>
        <tr>
          <td><?= htmlspecialchars($affDate) ?></td>
          <td><?= htmlspecialchars($r['Nom_équipe']) ?></td>
          <td><?= htmlspecialchars($r['Domicile_ou_exterieur']) ?></td>
          <td>
            <button class="button-edit" onclick="openUpdateChoiceModal(<?= $r['IdRencontre'] ?>)">Modifier</button>
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

<!-- MODALES -->

<!-- AJOUT -->
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
      <hr style="margin:20px 0;">
      <h3>Affectation des rôles</h3>
      <div id="add_match_slotsContainer"></div>

      <div class="modal-actions">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeAddMatchModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- CHOIX -->
<div id="update-choice-modal" class="modal">
  <div class="modal-content">
    <h2>Modifier la rencontre</h2>
    <input type="hidden" id="updChoice_id">
    <p>Que souhaitez-vous modifier ?</p>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <button onclick="updateTeam(document.getElementById('updChoice_id').value)">Modifier l'équipe</button>
      <button onclick="updateScoreNotes(document.getElementById('updChoice_id').value)">Modifier le score + notes</button>
      <button onclick="updateInfo(document.getElementById('updChoice_id').value)">Modifier les infos</button>
    </div>
    <div class="modal-actions">
      <button type="button" onclick="closeUpdateChoiceModal()">Annuler</button>
    </div>
  </div>
</div>

<!-- UPDATE TEAM -->
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

<!-- UPDATE SCORE + NOTES -->
<div id="update-score-modal" class="modal">
  <!-- EXACTEMENT la même modale que dans rencontre.php -->
  <div class="modal-content modal-content-score-notes">
    <h2>Modifier Score + Notes</h2>
    <p id="scoreModalError" class="error-message"></p>
    <form method="POST">
      <input type="hidden" name="main_action" value="update_score_notes">
      <input type="hidden" name="id_rencontre" id="upd_score_id_rencontre">

      <table class="table">
        <thead>
          <tr>
            <th>SET</th>
            <th>ÉQUIPE</th>
            <th>ADVERSE</th>
          </tr>
        </thead>
        <tbody>
          <!-- 5 sets -->
          <?php for($i=1;$i<=5;$i++): ?>
          <tr>
            <td><?= $i ?></td>
            <td><input type="number" name="set<?= $i ?>_equipe"
                       id="upd_score_set<?= $i ?>_equipe"
                       min="0" max="50" value="0"></td>
            <td><input type="number" name="set<?= $i ?>_adverse"
                       id="upd_score_set<?= $i ?>_adverse"
                       min="0" max="50" value="0"></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <h3 style="margin-top:15px;">Notes Joueurs (1 à 5)</h3>
      <!-- DIV vide, qu’on remplit en JS -->
      <div style="display:flex;flex-wrap:wrap;gap:10px;" id="upd_score_players"></div>

      <div class="modal-actions" style="margin-top:20px;">
        <button type="submit" class="btn btn-primary">Valider</button>
        <button type="button" class="btn btn-secondary" onclick="closeUpdateScoreModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- UPDATE INFOS -->
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

      <div class="modal-actions" style="margin-top:20px;">
        <button type="submit">Valider</button>
        <button type="button" onclick="closeUpdateInfoModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
/** LISTE JOUEURS ACTIFS */
var joueursActifs = <?php echo json_encode($joueursActifs, JSON_HEX_TAG|JSON_HEX_AMP); ?>;

/** RÔLES */
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

/** Ouvrir/Ajout */
function openAddMatchModal(){
  document.getElementById('add-match-modal').style.display='flex';
  fillAllSlots('add_match_slotsContainer', {});
}
function closeAddMatchModal(){
  document.getElementById('add-match-modal').style.display='none';
}

/** Remplir l’équipe */
function fillAllSlots(containerId, assigned){
  let container = document.getElementById(containerId);
  container.innerHTML='';

  rolesFixed.forEach(r=>{
    let row=document.createElement('div');
    row.className='role-slot-row';

    let lab=document.createElement('label');
    lab.textContent = r.label+" :";

    let sel=document.createElement('select');
    sel.name=`roleSlots[${r.slot}]`;

    let optNone=document.createElement('option');
    optNone.value='';
    optNone.textContent='(Aucun)';
    sel.appendChild(optNone);

    joueursActifs.forEach(j=>{
      let opt=document.createElement('option');
      opt.value=j.IdJoueur;
      opt.textContent=`${j.Nom} ${j.Prénom} (#${j.Numéro_de_license})`;
      sel.appendChild(opt);
    });

    if(assigned[r.slot]){
      sel.value=assigned[r.slot];
    }

    row.appendChild(lab);
    row.appendChild(sel);
    container.appendChild(row);
  });
}

function checkNoEmpty(containerId){
  let sel = document.querySelectorAll('#'+containerId+' select');
  for(let s of sel){
    if(!s.value){
      alert("Tous les rôles doivent être remplis.");
      return false;
    }
  }
  return true;
}

/** CHOIX */
function openUpdateChoiceModal(idR){
  document.getElementById('update-choice-modal').style.display='flex';
  document.getElementById('updChoice_id').value=idR;
}
function closeUpdateChoiceModal(){
  document.getElementById('update-choice-modal').style.display='none';
}

/** updateTeam => participants_rencontre => fillAllSlots */
function updateTeam(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-team-modal').style.display='flex';
  document.getElementById('upd_team_id_rencontre').value=idR;

  // participants_rencontre => { data:[ {Rôle, IdJoueur}, ... ] }
  fetch('participants_rencontre.php?id='+idR)
    .then(r=>r.json())
    .then(obj=>{
      if(obj.error){
        console.error(obj.error);
        fillAllSlots('upd_team_slotsContainer', {});
        return;
      }
      let arr = obj.data||[];
      let assigned={};
      arr.forEach(p=>{
        assigned[p.Rôle] = p.IdJoueur;
      });
      fillAllSlots('upd_team_slotsContainer', assigned);
    })
    .catch(err=>console.error(err));
}
function closeUpdateTeamModal(){
  document.getElementById('update-team-modal').style.display='none';
}

/** updateScoreNotes => EXACTEMENT comme rencontre.php */
function updateScoreNotes(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-score-modal').style.display='flex';
  document.getElementById('upd_score_id_rencontre').value=idR;

  // reset sets
  for(let i=1;i<=5;i++){
    document.getElementById(`upd_score_set${i}_equipe`).value=0;
    document.getElementById(`upd_score_set${i}_adverse`).value=0;
  }

  // GET sets => get_sets.php
  fetch('get_sets.php?id='+idR)
    .then(r=>r.json())
    .then(d=>{
      if(d.error){
        console.error(d.error);
      } else {
        for(let i=1;i<=5;i++){
          document.getElementById(`upd_score_set${i}_equipe`).value = d[`set${i}_equipe`]||0;
          document.getElementById(`upd_score_set${i}_adverse`).value= d[`set${i}_adverse`]||0;
        }
      }
    })
    .catch(err=>console.error(err));

  // GET participants_notes => { data:[ {IdJoueur, Nom, Prénom, Note}, ... ] }
  let cont=document.getElementById('upd_score_players');
  cont.innerHTML='';
  fetch('participants_notes.php?id='+idR)
    .then(r=>r.json())
    .then(obj=>{
      if(obj.error){
        console.error(obj.error);
        return;
      }
      let list = obj.data||[];
      list.forEach(p=>{
        // EXACT code "rencontre.php"
        let wrap=document.createElement('label');
        wrap.style.display='flex';
        wrap.style.flexDirection='column';
        wrap.style.alignItems='center';
        wrap.textContent = p.Nom+" "+p.Prénom+" :";

        let inp=document.createElement('input');
        inp.type='number';
        inp.name=`notes[${p.IdJoueur}]`;
        inp.min='1';
        inp.max='5';
        inp.value=(p.Note!==null)? p.Note : 1;

        wrap.appendChild(inp);
        cont.appendChild(wrap);
      });
    })
    .catch(err=>console.error(err));
}
function closeUpdateScoreModal(){
  document.getElementById('update-score-modal').style.display='none';
}

/** updateInfo => info_rencontre => date, nom, lieu */
function updateInfo(idR){
  closeUpdateChoiceModal();
  document.getElementById('update-info-modal').style.display='flex';
  document.getElementById('upd_info_id_rencontre').value=idR;

  fetch('info_rencontre.php?id='+idR)
    .then(r=>r.json())
    .then(d=>{
      if(d.error){
        console.error(d.error);
      } else {
        document.getElementById('upd_info_date').value = d.DateRencontre||'';
        document.getElementById('upd_info_nom').value  = d.NomEquipe||'';
        document.getElementById('upd_info_lieu').value = d.Lieu||'Domicile';
      }
    })
    .catch(err=>console.error(err));
}
function closeUpdateInfoModal(){
  document.getElementById('update-info-modal').style.display='none';
}
</script>
</body>
</html>
