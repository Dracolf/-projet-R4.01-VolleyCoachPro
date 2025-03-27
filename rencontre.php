<?php
session_start();

// Vérifier session
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['token'])) {
    header("Location: logout.php");
    exit;
}

$token = $_SESSION['token'];
$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/matchs/";

$message = "";
$error   = false;

/**
 * cURL générique
 */
function sendCurlRequest($url, $method, $token, $data=null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    if($data){
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
      "code"=>$code,
      "response"=>json_decode($resp, true)
    ];
}

// (1) Traitement du POST => "update_score_notes_front"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_score_notes_front') {
    $idR = $_POST['id_rencontre'] ?? null;
    if (!$idR) {
        $error   = true;
        $message = "ID rencontre manquant.";
    } else {
        // On construit le JSON pour PUT
        // On suppose que l’API attend s1e, s1a, ... s5e, s5a, + "notes": { IdJoueur: note }
        $body = [
          "s1e" => (int)($_POST['set1_equipe']  ??0),
          "s1a" => (int)($_POST['set1_adverse'] ??0),
          "s2e" => (int)($_POST['set2_equipe']  ??0),
          "s2a" => (int)($_POST['set2_adverse'] ??0),
          "s3e" => (int)($_POST['set3_equipe']  ??0),
          "s3a" => (int)($_POST['set3_adverse'] ??0),
          "s4e" => (int)($_POST['set4_equipe']  ??0),
          "s4a" => (int)($_POST['set4_adverse'] ??0),
          "s5e" => (int)($_POST['set5_equipe']  ??0),
          "s5a" => (int)($_POST['set5_adverse'] ??0),
          "notes" => []
        ];

        // notes => notes[idJoueur]
        if (isset($_POST['notes']) && is_array($_POST['notes'])) {
            foreach($_POST['notes'] as $idJoueur => $valNote) {
                $n = (int)$valNote;
                if($n<1) $n=1;  // min = 1
                if($n>5) $n=5;  // max = 5
                $body["notes"][$idJoueur] = $n;
            }
        }

        // PUT /matchs/{idR}
        $resUp = sendCurlRequest($api_url.$idR, "PUT", $token, $body);
        if($resUp['code']===200){
            $message = "Score + notes mis à jour (API).";
        } else {
            $error   = true;
            $message = "Échec update (code=".$resUp['code']."): "
                     . ($resUp['response']['status_message'] ?? "??");
        }
    }
}

// (2) Récup liste rencontres => GET /matchs/
$rencontres = [];
$res = sendCurlRequest($api_url, "GET", $token, null);
if ($res['code']===200) {
    $rencontres = $res['response']['data'] ?? [];
} else {
    $error   = true;
    $message = "Impossible de récupérer les rencontres (code=".$res['code'].")";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rencontres</title>
  <link rel="stylesheet" href="styles.css">

  <style>
    /* conteneur flex multi-ligne pour les joueurs */
    .players-grid {
      display: flex;
      flex-wrap: wrap;  /* autorise retour à la ligne */
      gap: 15px 20px;
      margin-top: 15px;
    }
    .player-item {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .player-item label {
      font-weight: bold;
      margin-bottom: 5px;
    }
    .player-item input[type="number"] {
      width: 50px;
      text-align: center;
    }
  </style>
</head>
<body>

<!-- Navbar -->
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
  <p class="message <?= $error?'error-message':'info-message' ?>">
    <?= htmlspecialchars($message) ?>
  </p>
<?php endif; ?>

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
      $now = new DateTime();
      foreach($rencontres as $r):
        $dt = new DateTime($r['Date_rencontre']);
        $dateAff = $dt->format('d/m/Y');
        $timeAff = $dt->format('H\hi');

        // calcul setsEq:setsAd
        $setsEq=0; 
        $setsAd=0;
        for($i=1;$i<=5;$i++){
          $se = $r["Set{$i}_equipe"];
          $sa = $r["Set{$i}_adverse"];
          if($se>$sa) $setsEq++;
          elseif($sa>$se) $setsAd++;
        }
        $scoreTxt=($setsEq===0 && $setsAd===0)? '—' : ($setsEq." : ".$setsAd);

        $past=($dt <= $now);
      ?>
        <tr>
          <td><?= htmlspecialchars($dateAff) ?></td>
          <td><?= htmlspecialchars($timeAff) ?></td>
          <td><?= htmlspecialchars($r['Nom_équipe']) ?></td>
          <td><?= htmlspecialchars($r['Domicile_ou_exterieur']) ?></td>
          <td><?= htmlspecialchars($scoreTxt) ?></td>
          <td>
            <?php if($past): ?>
              <button class="button-add-score"
                      onclick="openScoreNotesModal(<?= $r['IdRencontre'] ?>)">
                Accès au Score et aux Notes
              </button>
            <?php else: ?>
              <span class="button-disabled">Match à venir</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>

  <!-- Bouton pour aller a gestion_rencontre.php -->
  <div class="bottom-button-container" style="margin-bottom: 20px;">
    <a href="gestion_rencontre.php" class="button-manage">Gérer les rencontres</a>
  </div>

</div>

<!-- MODALE SCORE + NOTES -->
<div id="score-notes-modal" class="modal">
  <div class="modal-content modal-content-score-notes">
    <h2>Modifier Score + Notes</h2>
    <p id="modalError" class="error-message"></p>

    <form method="POST">
      <input type="hidden" name="action" value="update_score_notes_front">
      <input type="hidden" name="id_rencontre" id="sn_id_rencontre">

      <!-- 5 sets -->
      <table class="table">
        <thead>
          <tr>
            <th>SET</th>
            <th>ÉQUIPE</th>
            <th>ADVERSE</th>
          </tr>
        </thead>
        <tbody>
          <?php for($i=1;$i<=5;$i++): ?>
          <tr>
            <td><?= $i ?></td>
            <td><input type="number" name="set<?= $i ?>_equipe" id="sn_set<?= $i ?>_equipe" min="0" max="50" value="0"></td>
            <td><input type="number" name="set<?= $i ?>_adverse" id="sn_set<?= $i ?>_adverse" min="0" max="50" value="0"></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <h3 style="margin-top: 15px;">Notes Joueurs (1 à 5)</h3>
      <div class="players-grid" id="playersNotesGrid"></div>

      <div class="modal-actions" style="margin-top: 20px;">
        <button type="submit" class="btn btn-primary">Valider</button>
        <button type="button" class="btn btn-secondary" onclick="closeScoreNotesModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openScoreNotesModal(idR){
  // ouvre la modale
  document.getElementById('score-notes-modal').style.display='flex';
  document.getElementById('sn_id_rencontre').value = idR;

  // 1) Récup sets => get_sets.php
  for(let i=1;i<=5;i++){
    document.getElementById(`sn_set${i}_equipe`).value=0;
    document.getElementById(`sn_set${i}_adverse`).value=0;
  }
  fetch('get_sets.php?id='+idR)
    .then(r=>r.json())
    .then(data=>{
      if(data.error){
        console.error(data.error);
      } else {
        for(let i=1;i<=5;i++){
          document.getElementById(`sn_set${i}_equipe`).value = data[`set${i}_equipe`]||0;
          document.getElementById(`sn_set${i}_adverse`).value= data[`set${i}_adverse`]||0;
        }
      }
    })
    .catch(err=>console.error(err));

  // 2) Récup joueurs + notes => participants_notes.php
  // => renvoie [ {IdJoueur, Nom, Prénom, Note}, ... ] (après avoir fait 2 requêtes : /matchs/equipe + /joueurs/id)
  const grid = document.getElementById('playersNotesGrid');
  grid.innerHTML = ""; // reset

  fetch('participants_notes.php?id='+idR)
    .then(r=>r.json())
    .then(list=>{
      if(list.error){
        console.error(list.error);
        return;
      }
      // On crée un bloc par joueur
      list.forEach(player=>{
        // ex: player = { "IdJoueur":12, "Nom":"Cobain", "Prénom":"Kurt", "Note":5 }
        let item = document.createElement('div');
        item.className = "player-item";

        let lab = document.createElement('label');
        lab.textContent = player.Nom + " " + player.Prénom;

        let inp = document.createElement('input');
        inp.type = "number";
        inp.name = `notes[${player.IdJoueur}]`; // ex: notes[12]
        inp.min  = "1";
        inp.max  = "5";
        inp.value= (player.Note!==null) ? player.Note : 1;

        item.appendChild(lab);
        item.appendChild(inp);
        grid.appendChild(item);
      });
    })
    .catch(err=>console.error(err));
}

function closeScoreNotesModal(){
  document.getElementById('score-notes-modal').style.display='none';
}
</script>

</body>
</html>
