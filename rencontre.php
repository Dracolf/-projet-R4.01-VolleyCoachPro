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
        // Envoi du body en JSON
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
      "code"     => $http_code,
      "response" => json_decode($response, true)
    ];
}

/**
 * 1) Traitement du POST => "update_score_notes_front"
 *    On envoie s1e, s1a,... s5e, s5a + "notes": {IdJoueur => note}
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_score_notes_front') {
    $idR = $_POST['id_rencontre'] ?? null;
    if (!$idR) {
        $error   = true;
        $message = "ID rencontre manquant.";
    } else {
        // Construction du body JSON
        $body = [
          "s1e" => (int)($_POST['set1_equipe']  ?? 0),
          "s1a" => (int)($_POST['set1_adverse'] ?? 0),
          "s2e" => (int)($_POST['set2_equipe']  ?? 0),
          "s2a" => (int)($_POST['set2_adverse'] ?? 0),
          "s3e" => (int)($_POST['set3_equipe']  ?? 0),
          "s3a" => (int)($_POST['set3_adverse'] ?? 0),
          "s4e" => (int)($_POST['set4_equipe']  ?? 0),
          "s4a" => (int)($_POST['set4_adverse'] ?? 0),
          "s5e" => (int)($_POST['set5_equipe']  ?? 0),
          "s5a" => (int)($_POST['set5_adverse'] ?? 0),
          "notes" => []
        ];

        // notes => notes[idJoueur]
        if (isset($_POST['notes']) && is_array($_POST['notes'])) {
            foreach ($_POST['notes'] as $idJoueur => $valNote) {
                $n = (int)$valNote;
                if ($n < 1) $n = 1;  // note mini
                if ($n > 5) $n = 5;  // note maxi
                $body["notes"][$idJoueur] = $n;
            }
        }

        // PUT /matchs/{idR}
        $resUp = sendCurlRequest($api_url.$idR, "PUT", $token, $body);
        if ($resUp['code'] === 200) {
            $message = "Score + notes mis à jour (API).";
        } else {
            $error   = true;
            $message = "Échec update (code=".$resUp['code']."): "
                     . ($resUp['response']['status_message'] ?? "??");
        }
    }
}

/**
 * 2) Récup liste des rencontres => GET /matchs/
 */
$rencontres = [];
$res = sendCurlRequest($api_url, "GET", $token, null);
if ($res['code'] === 200) {
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
    /* conteneur flex multi-ligne pour l'affichage des joueurs et notes */
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

<?php if ($message): ?>
  <p class="message <?= $error ? 'error-message' : 'info-message' ?>">
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
      foreach ($rencontres as $r):
        $dt = new DateTime($r['Date_rencontre']);
        $dateAff = $dt->format('d/m/Y');
        $timeAff = $dt->format('H\hi');

        // calcul du score setsEq:setsAd
        $setsEq = 0; 
        $setsAd = 0;
        for ($i=1;$i<=5;$i++){
          $se = $r["Set{$i}_equipe"];
          $sa = $r["Set{$i}_adverse"];
          if ($se > $sa) $setsEq++;
          elseif ($sa > $se) $setsAd++;
        }
        $scoreTxt = ($setsEq === 0 && $setsAd === 0)? '—' : ($setsEq . " : " . $setsAd);

        $past = ($dt <= $now);
      ?>
        <tr>
          <td><?= htmlspecialchars($dateAff) ?></td>
          <td><?= htmlspecialchars($timeAff) ?></td>
          <td><?= htmlspecialchars($r['Nom_équipe']) ?></td>
          <td><?= htmlspecialchars($r['Domicile_ou_exterieur']) ?></td>
          <td><?= htmlspecialchars($scoreTxt) ?></td>
          <td>
            <?php if ($past): ?>
              <button class="button-add-score"
                      onclick="openScoreNotesModal(<?= $r['IdRencontre'] ?>)">
                Accès au Score et Notes
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

  <!-- Bouton pour aller à gestion_rencontre.php -->
  <div class="bottom-button-container" style="margin-bottom: 20px;">
    <?php if($_SESSION['user'] !=="guest") {
      echo '<a href="gestion_rencontre.php" class="button-manage">Gérer les rencontres</a>';
    } else {
      echo "<p class='access-restricted'>Vous n'avez pas accès à la gestion des rencontres en tant qu'invité.</p>";
    }
    ?>
  </div>
</div>

<!-- MODALE SCORE + NOTES -->
<div id="score-notes-modal" class="modal">
  <div class="modal-content modal-content-score-notes">
    <div class="modal-header">
      <h2>Score & Notes</h2>
      <p id="modalError" class="error-message"></p>
    </div>
    
    <div class="modal-scrollable-content">
      <!-- 5 sets - Mode lecture seule -->
      <table class="table score-table">
        <thead>
          <tr>
            <th>Set</th>
            <th>ÉQUIPE</th>
            <th>ADVERSE</th>
          </tr>
        </thead>
        <tbody>
          <?php for($i=1;$i<=5;$i++): ?>
          <tr>
            <td><?= $i ?></td>
            <td>
              <span id="sn_set<?= $i ?>_equipe_display" class="score-value">0</span>
            </td>
            <td>
              <span id="sn_set<?= $i ?>_adverse_display" class="score-value">0</span>
            </td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <h3 class="notes-title">Notes Joueurs</h3>
      <!-- Conteneur pour les notes en lecture seule -->
      <div class="players-grid-container">
        <div class="players-grid" id="playersNotesGrid"></div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-back" onclick="closeScoreNotesModal()">Retour</button>
    </div>
  </div>
</div>

<script>
// Ouvrir la modale
function openScoreNotesModal(idR){
  // ouvre la modale
  document.getElementById('score-notes-modal').style.display='flex';
  
  // reset des valeurs
  for(let i=1;i<=5;i++){
    document.getElementById(`sn_set${i}_equipe_display`).textContent='0';
    document.getElementById(`sn_set${i}_adverse_display`).textContent='0';
  }
  
  // Récupération des sets
  fetch('get_sets.php?id='+idR)
    .then(r=>r.json())
    .then(data=>{
      if(data.error){
        console.error(data.error);
      } else {
        for(let i=1;i<=5;i++){
          document.getElementById(`sn_set${i}_equipe_display`).textContent = data[`set${i}_equipe`]||0;
          document.getElementById(`sn_set${i}_adverse_display`).textContent= data[`set${i}_adverse`]||0;
        }
      }
    })
    .catch(err=>console.error(err));

  // Récupération des joueurs + notes
  const grid = document.getElementById('playersNotesGrid');
  grid.innerHTML = "";

  fetch('participants_notes.php?id='+idR)
    .then(r=>r.json())
    .then(list=>{
        if(list.error) {
            console.error(list.error);
            return;
        }
        
        const grid = document.getElementById('playersNotesGrid');
        grid.innerHTML = "";
        
        list.forEach(player => {
            const item = document.createElement('div');
            item.className = "player-item";
            
            const lab = document.createElement('label');
            lab.textContent = `${player.Prénom} ${player.Nom}`;
            
            const stars = document.createElement('div');
            stars.className = "note-stars";
            const noteValue = player.Note !== null ? Math.max(1, Math.min(5, parseInt(player.Note))) : 0;
            stars.innerHTML = '★'.repeat(noteValue) + '☆'.repeat(5 - noteValue);
            stars.title = `Note: ${noteValue}/5`;
            
            item.appendChild(lab);
            item.appendChild(stars);
            grid.appendChild(item);
        });
    })
    .catch(err => console.error(err));
}

// Fermer la modale
function closeScoreNotesModal(){
  document.getElementById('score-notes-modal').style.display='none';
}
</script>

</body>
</html>
