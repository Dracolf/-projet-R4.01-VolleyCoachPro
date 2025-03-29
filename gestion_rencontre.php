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
      $data = ['date' => $_POST['date_rencontre'], 'adversaire' => $_POST['nom_equipe'], 'domext' => $_POST['lieu']];

      $roleSlots = $_POST['roleSlots'] ?? [];

      foreach($roleSlots as $role => $idJoueur) {
          if(!empty($idJoueur)) {
              $data[$role] = $idJoueur;
          }
      }

      $addmatch = sendCurlRequest($api_url_matchs, "POST", $token, $data);
      if ($addmatch["code"] === 201) {
        $message = "Rencontre ajoutée avec succès !";
      } else {
        $error = true;
        $message = $addmatch["response"]["status_message"];
      }
    }

    // (B) Modifier l’équipe => PUT /matchs/{id}
    elseif($action === 'update_team') {
      $data = [
          'avg' => $_POST['roleSlots']['avg'],
          'avc' => $_POST['roleSlots']['avc'],
          'avd' => $_POST['roleSlots']['avd'],
          'arg' => $_POST['roleSlots']['arg'],
          'ard' => $_POST['roleSlots']['ard'],
          'lib' => $_POST['roleSlots']['lib'],
          'r1'  => $_POST['roleSlots']['r1'],
          'r2'  => $_POST['roleSlots']['r2'],
          'r3'  => $_POST['roleSlots']['r3'],
          'r4'  => $_POST['roleSlots']['r4'],
          'r5'  => $_POST['roleSlots']['r5'],
          'r6'  => $_POST['roleSlots']['r6']
      ];
  
      $response = sendCurlRequest(
          $api_url_matchs . $_POST['id_rencontre'],
          "PUT",
          $token,
          $data
      );

      if($response['code'] === 200) {
          $message = "Équipe mise à jour avec succès";
      } else {
          $error = true;
          $message = "Erreur lors de la modification (Code: {$response['code']}) - " 
                   . ($response['response']['status_message'] ?? '');
      }
    }

    // (C) Modifier Score + Notes => PUT /matchs/{id}
    elseif($action === 'update_score_notes') {
      // 1. Préparation des données de base
      $data = [
          's1e' => intval($_POST['s1e'] ?? 0),
          's1a' => intval($_POST['s1a'] ?? 0),
          's2e' => intval($_POST['s2e'] ?? 0),
          's2a' => intval($_POST['s2a'] ?? 0),
          's3e' => intval($_POST['s3e'] ?? 0),
          's3a' => intval($_POST['s3a'] ?? 0),
          's4e' => intval($_POST['s4e'] ?? 0),
          's4a' => intval($_POST['s4a'] ?? 0),
          's5e' => intval($_POST['s5e'] ?? 0),
          's5a' => intval($_POST['s5a'] ?? 0),
          // Notes avec valeur par défaut à 1
          'noteAVG' => 1, 'noteAVC' => 1, 'noteAVD' => 1,
          'noteARG' => 1, 'noteARD' => 1, 'noteLIB' => 1,
          'noteR1' => 1, 'noteR2' => 1, 'noteR3' => 1,
          'noteR4' => 1, 'noteR5' => 1, 'noteR6' => 1
      ];
  
      // 2. Récupération de la composition exacte
      $idRencontre = $_POST['id_rencontre'];
      $response = sendCurlRequest("{$api_url_matchs}/equipe/{$idRencontre}", "GET", $token);
      
      if ($response['code'] === 200) {
          $composition = $response['response']['data'] ?? [];
          $notesFromForm = $_POST['notes'] ?? [];
          foreach ($composition as $joueur) {
              $idJoueur = $joueur['IdJoueur'] ?? 0;
              $roleDB = $joueur['Rôle'] ?? '';
              $note = $notesFromForm[$idJoueur] ?? null;
              
              if ($idJoueur > 0 && $roleDB !== '' && $note !== null) {
                  switch ($roleDB) {
                      case 'avant_gauche': $data['noteAVG'] = intval($note); break;
                      case 'avant_centre': $data['noteAVC'] = intval($note); break;
                      case 'avant_droit':  $data['noteAVD'] = intval($note); break;
                      case 'arriere_gauche': $data['noteARG'] = intval($note); break;
                      case 'arriere_droit': $data['noteARD'] = intval($note); break;
                      case 'libero': $data['noteLIB'] = intval($note); break;
                      case 'remp1': $data['noteR1'] = intval($note); break;
                      case 'remp2': $data['noteR2'] = intval($note); break;
                      case 'remp3': $data['noteR3'] = intval($note); break;
                      case 'remp4': $data['noteR4'] = intval($note); break;
                      case 'remp5': $data['noteR5'] = intval($note); break;
                      case 'remp6': $data['noteR6'] = intval($note); break;
                  }
              }
          }
      }
  
      // 4. Debug critique
      error_log("DONNÉES FINALES POUR L'API:");
      error_log(print_r($data, true));
  
      // 5. Envoi à l'API
      $response = sendCurlRequest($api_url_matchs.$idRencontre, "PUT", $token, $data);

      if ($response['code'] === 200) {
          if (isset($response['response']['status_message'])) {
            $message = "Scores et notes mis à jour avec succès";
          } else {
            $error = true;
            $message = "Erreurs lors de la modification : scores incohérents";
          }
      } else {
          $error = true;
          $message = "Erreur lors de la modification (Code: {$response['code']}) - " 
                  . ($response['response']['status_message'] ?? $response['response']);
          error_log("ERREUR API DÉTAILLÉE:");
          error_log(print_r($response, true));
      }
      
    }

    // (D) Modifier Infos => PUT /matchs/{id}
    elseif($action==='update_info'){
      $data = ['date' => $_POST['date_rencontre'], 'adversaire' => $_POST['nom_equipe'], 'domext' => $_POST['lieu']];
      $updateInfos = sendCurlRequest($api_url_matchs.$_POST['id_rencontre'], "PUT", $token, $data);
      if ($updateInfos["code"] === 200) {
        $message = $updateInfos["response"]["status_message"];
      } else {
        $error = true;
        $message = $updateInfos["response"]["status_message"];
      }
    }
}

/*****************************************************************************/
/* 3) Supprimer => DELETE /matchs/{id} */
/*****************************************************************************/
if(isset($_GET['action']) && $_GET['action']==='delete'){
    $deleteMatch = sendCurlRequest($api_url_matchs.$_GET['id'], "DELETE", $token);
    if ($deleteMatch["code"] === 200) {
      $message = "Rencontre supprimée avec succès !";
    } else {
      $error = true;
      $message = "Erreur lors de la suppression de la rencontre";
    }
}

/*****************************************************************************/
/* 4) Récup liste => GET /matchs/ */
/*****************************************************************************/
$rencontres = [];
$resMatches = sendCurlRequest($api_url_matchs, "GET", $token, null);
if($resMatches['code']===200){
    $rencontres = $resMatches['response']['data'] ?? [];
} else {
    header("Location : logout.php");
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
          <?php for($i=1;$i<=4;$i++): ?>
          <tr>
            <td><?= $i ?></td>
            <td><input type="number" name="s<?= $i ?>e"
                       id="upd_score_set<?= $i ?>_equipe"
                       min="0" max="25" value="0"></td>
            <td><input type="number" name="s<?= $i ?>a"
                       id="upd_score_set<?= $i ?>_adverse"
                       min="0" max="25" value="0"></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td><?= $i ?></td>
            <td><input type="number" name="s5e"
                       id="upd_score_set5_equipe"
                       min="0" max="15" value="0"></td>
            <td><input type="number" name="s5a"
                       id="upd_score_set5_adverse"
                       min="0" max="15" value="0"></td>
          </tr>
        </tbody>
      </table>

      <h3 style="margin-top:15px;">Notes Joueurs (1 à 5)</h3>
      <!-- DIV vide, qu’on remplit en JS -->
      <div style="display:flex;flex-wrap:wrap;gap:10px;max-height:150px;overflow-y:auto" id="upd_score_players"></div>

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
  {slot:'avd',   label:'Avant droit'},
  {slot:'avc',  label:'Avant centre'},
  {slot:'avg',  label:'Avant gauche'},
  {slot:'ard', label:'Arrière droit'},
  {slot:'arg', label:'Arrière gauche'},
  {slot:'lib', label:'Libéro'}
];
for(let i=1;i<=6;i++){
  rolesFixed.push({slot:'r'+i, label:'Remplaçant #'+i});
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
function fillAllSlots(containerId, assigned) {
    let container = document.getElementById(containerId);
    container.innerHTML = '';
    
    // Récupère tous les IDs déjà attribués (sauf les vides)
    let usedIds = Object.values(assigned).filter(id => id);
    
    rolesFixed.forEach(r => {
        let row = document.createElement('div');
        row.className = 'role-slot-row';

        let lab = document.createElement('label');
        lab.textContent = r.label + " :";

        let sel = document.createElement('select');
        sel.name = `roleSlots[${r.slot}]`;
        sel.setAttribute('data-role', r.slot);

        // Option vide par défaut
        let optNone = document.createElement('option');
        optNone.value = '';
        optNone.textContent = '(Aucun)';
        sel.appendChild(optNone);

        // Ajout des joueurs disponibles
        joueursActifs.forEach(j => {
            // Si le joueur est déjà utilisé ailleurs ET n'est pas celui actuellement sélectionné pour ce rôle
            if (usedIds.includes(String(j.IdJoueur)) && assigned[r.slot] !== String(j.IdJoueur)) {
                return; // On skip ce joueur
            }
            
            let opt = document.createElement('option');
            opt.value = j.IdJoueur;
            opt.textContent = `${j.Nom} ${j.Prénom} (#${j.Numéro_de_license})`;
            
            // Sélectionne l'option si c'est le joueur assigné à ce rôle
            if (assigned[r.slot] && assigned[r.slot] == j.IdJoueur) {
                opt.selected = true;
            }
            
            sel.appendChild(opt);
        });

        // Gestion du changement de sélection
        sel.addEventListener('change', function() {
            // Met à jour l'objet assigned avec la nouvelle valeur
            const newValue = this.value;
            const role = this.getAttribute('data-role');
            
            // Sauvegarde l'ancienne valeur
            const oldValue = assigned[role];
            
            // Met à jour la valeur
            if (newValue) {
                assigned[role] = newValue;
            } else {
                delete assigned[role];
            }
            
            // Rafraîchit tous les selects
            fillAllSlots(containerId, assigned);
            
            // Debug
            console.log(`Changement pour ${role}: ${oldValue} -> ${newValue}`);
            console.log('Nouveau assigned:', assigned);
        });

        row.appendChild(lab);
        row.appendChild(sel);
        container.appendChild(row);
    });
}

function updateAvailablePlayers(containerId, assigned) {
    // Récupère toutes les sélections actuelles
    let currentSelections = {};
    document.querySelectorAll(`#${containerId} select`).forEach(select => {
        const role = select.getAttribute('data-role');
        currentSelections[role] = select.value;
    });

    // Met à jour chaque liste déroulante
    document.querySelectorAll(`#${containerId} select`).forEach(select => {
        const role = select.getAttribute('data-role');
        const currentValue = select.value;
        
        // Sauvegarde l'option sélectionnée
        let selectedOption = currentValue;
        
        // Réinitialise la liste
        select.innerHTML = '';
        let optNone = document.createElement('option');
        optNone.value = '';
        optNone.textContent = '(Aucun)';
        select.appendChild(optNone);
        
        // Liste des IDs déjà utilisés dans d'autres rôles
        let usedIds = Object.values(currentSelections)
                          .filter((id, idx) => 
                              id && 
                              Object.keys(currentSelections)[idx] !== role);
        
        // Ajoute les options disponibles
        joueursActifs.forEach(j => {
            if (!usedIds.includes(String(j.IdJoueur)) || 
                (currentSelections[role] === String(j.IdJoueur))) {
                let opt = document.createElement('option');
                opt.value = j.IdJoueur;
                opt.textContent = `${j.Nom} ${j.Prénom} (#${j.Numéro_de_license})`;
                
                if (selectedOption === String(j.IdJoueur)) {
                    opt.selected = true;
                }
                
                select.appendChild(opt);
            }
        });
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
function updateTeam(idR) {
    closeUpdateChoiceModal();
    document.getElementById('update-team-modal').style.display = 'flex';
    document.getElementById('upd_team_id_rencontre').value = idR;

    fetch('participants_rencontre.php?id=' + idR)
        .then(r => r.json())
        .then(obj => {
            if (obj.error) {
                console.error(obj.error);
                fillAllSlots('upd_team_slotsContainer', {});
                return;
            }

            let arr = obj.data || [];
            let assigned = {};
            
            // Transformation des données de l'API en format attendu
            arr.forEach(p => {
                const roleMapping = {
                    'avant_gauche': 'avg',
                    'avant_centre': 'avc',
                    'avant_droit': 'avd',
                    'arriere_gauche': 'arg',
                    'arriere_droit': 'ard',
                    'libero': 'lib',
                    'remp1': 'r1',
                    'remp2': 'r2',
                    'remp3': 'r3',
                    'remp4': 'r4',
                    'remp5': 'r5',
                    'remp6': 'r6'
                };
                
                const slot = roleMapping[p.Rôle];
                if (slot && p.IdJoueur) {
                    assigned[slot] = String(p.IdJoueur); // Conversion en string pour comparaison
                }
            });

            console.log("Données transformées:", assigned);
            fillAllSlots('upd_team_slotsContainer', assigned);
        })
        .catch(err => {
            console.error("Erreur fetch:", err);
            fillAllSlots('upd_team_slotsContainer', {});
        });
}
function closeUpdateTeamModal(){
  document.getElementById('update-team-modal').style.display='none';
}

/** updateScoreNotes => EXACTEMENT comme rencontre.php */
function updateScoreNotes(idR) {
  closeUpdateChoiceModal();
  document.getElementById('update-score-modal').style.display='flex';
  document.getElementById('upd_score_id_rencontre').value=idR;

  // Reset des valeurs
  for(let i=1;i<=5;i++){
    document.getElementById(`upd_score_set${i}_equipe`).value=0;
    document.getElementById(`upd_score_set${i}_adverse`).value=0;
  }

  // Récupération des sets
  fetch('get_sets.php?id='+idR)
    .then(r=>r.json())
    .then(d=>{
      if(!d.error) {
        for(let i=1;i<=5;i++){
          document.getElementById(`upd_score_set${i}_equipe`).value = d[`set${i}_equipe`]||0;
          document.getElementById(`upd_score_set${i}_adverse`).value= d[`set${i}_adverse`]||0;
        }
      }
    });

  // Récupération des participants avec leurs notes
  let cont=document.getElementById('upd_score_players');
  cont.innerHTML='';
  
  fetch('participants_notes.php?id='+idR)
    .then(r=>r.json())
    .then(participants=>{
      if(!Array.isArray(participants)) return;
      
      participants.forEach(p=>{
        let wrap=document.createElement('div');
        wrap.className='player-note';
        
        wrap.innerHTML = `
          <label>
            ${p.Prénom} ${p.Nom}:
            <input type="number" 
                   name="notes[${p.IdJoueur}]" 
                   min="1" max="5" 
                   value="${p.Note || 1}">
          </label>
        `;
        cont.appendChild(wrap);
      });
    });
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
