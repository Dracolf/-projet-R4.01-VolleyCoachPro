<?php
session_start();

// Vérifier la session
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
 * (1) Traitement POST => update_score_notes_front
 * On construit le body pour l'API : s1e, s1a,... s5e, s5a
 * + noteAVG, noteAVC, etc.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_score_notes_front') {
    $idR = $_POST['id_rencontre'] ?? null;
    if (!$idR) {
        $error   = true;
        $message = "ID rencontre manquant.";
    } else {
        // Construction du body JSON
        $body = [
            // 5 sets
            "s1e" => (int)($_POST["set1_equipe"]  ??0),
            "s1a" => (int)($_POST["set1_adverse"] ??0),
            "s2e" => (int)($_POST["set2_equipe"]  ??0),
            "s2a" => (int)($_POST["set2_adverse"] ??0),
            "s3e" => (int)($_POST["set3_equipe"]  ??0),
            "s3a" => (int)($_POST["set3_adverse"] ??0),
            "s4e" => (int)($_POST["set4_equipe"]  ??0),
            "s4a" => (int)($_POST["set4_adverse"] ??0),
            "s5e" => (int)($_POST["set5_equipe"]  ??0),
            "s5a" => (int)($_POST["set5_adverse"] ??0),

            // 12 champs "noteXXX"
            "noteAVG" => (int)($_POST["noteAVG"] ?? 0),
            "noteAVC" => (int)($_POST["noteAVC"] ?? 0),
            "noteAVD" => (int)($_POST["noteAVD"] ?? 0),
            "noteARG" => (int)($_POST["noteARG"] ?? 0),
            "noteARD" => (int)($_POST["noteARD"] ?? 0),
            "noteLIB" => (int)($_POST["noteLIB"] ?? 0),

            "noteR1"  => (int)($_POST["noteR1"]  ?? 0),
            "noteR2"  => (int)($_POST["noteR2"]  ?? 0),
            "noteR3"  => (int)($_POST["noteR3"]  ?? 0),
            "noteR4"  => (int)($_POST["noteR4"]  ?? 0),
            "noteR5"  => (int)($_POST["noteR5"]  ?? 0),
            "noteR6"  => (int)($_POST["noteR6"]  ?? 0),
        ];

        // PUT /matchs/{idR}
        $resUp = sendCurlRequest($api_url.$idR, "PUT", $token, $body);
        if ($resUp['code']===200) {
            $message = "Score + notes mis à jour (API).";
        } else {
            $error   = true;
            $message = "Échec update (code=".$resUp['code']."): "
                     . ($resUp['response']['status_message'] ?? "??");
        }
    }
}

/**
 * (2) Récup liste rencontres => GET /matchs/
 */
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
        $scoreTxt = ($setsEq===0 && $setsAd===0)? '—' : ($setsEq." : ".$setsAd);

        $past = ($dt <= $now);
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

      <!-- Les 5 sets -->
      <table class="table">
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
              <input type="number" name="set<?= $i ?>_equipe"
                     id="sn_set<?= $i ?>_equipe"
                     min="0" max="50" value="0">
            </td>
            <td>
              <input type="number" name="set<?= $i ?>_adverse"
                     id="sn_set<?= $i ?>_adverse"
                     min="0" max="50" value="0">
            </td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <h3 style="margin-top:15px;">Notes par rôle (1 à 5)</h3>
      <!-- 12 champs, statiques, pour chaque rôle -->
      <div style="display:flex;flex-wrap:wrap; gap:15px; margin-top:10px;">
        <label>Avant Gauche (AVG):
          <input type="number" name="noteAVG" min="1" max="5" value="1">
        </label>

        <label>Avant Centre (AVC):
          <input type="number" name="noteAVC" min="1" max="5" value="1">
        </label>

        <label>Avant Droit (AVD):
          <input type="number" name="noteAVD" min="1" max="5" value="1">
        </label>

        <label>Arrière Gauche (ARG):
          <input type="number" name="noteARG" min="1" max="5" value="1">
        </label>

        <label>Arrière Droit (ARD):
          <input type="number" name="noteARD" min="1" max="5" value="1">
        </label>

        <label>Libéro (LIB):
          <input type="number" name="noteLIB" min="1" max="5" value="1">
        </label>

        <label>Remplaçant #1 (R1):
          <input type="number" name="noteR1" min="1" max="5" value="1">
        </label>

        <label>Remplaçant #2 (R2):
          <input type="number" name="noteR2" min="1" max="5" value="1">
        </label>

        <label>Remplaçant #3 (R3):
          <input type="number" name="noteR3" min="1" max="5" value="1">
        </label>

        <label>Remplaçant #4 (R4):
          <input type="number" name="noteR4" min="1" max="5" value="1">
        </label>

        <label>Remplaçant #5 (R5):
          <input type="number" name="noteR5" min="1" max="5" value="1">
        </label>

        <label>Remplaçant #6 (R6):
          <input type="number" name="noteR6" min="1" max="5" value="1">
        </label>
      </div>

      <div class="modal-actions" style="margin-top:20px;">
        <button type="submit" class="btn btn-primary">Valider</button>
        <button type="button" class="btn btn-secondary" onclick="closeScoreNotesModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
// Ouvrir la modale
function openScoreNotesModal(idR){
  document.getElementById('score-notes-modal').style.display='flex';
  document.getElementById('sn_id_rencontre').value = idR;

  // (A) Optionnel : reset sets
  for(let i=1;i<=5;i++){
    document.getElementById(`sn_set${i}_equipe`).value=0;
    document.getElementById(`sn_set${i}_adverse`).value=0;
  }
  // (B) Récup les sets existants => get_sets.php si tu veux, ou laisse tel quel
  fetch('get_sets.php?id='+idR)
    .then(r=>r.json())
    .then(obj=>{
      if(obj.error){
        console.error(obj.error);
      } else {
        for(let i=1;i<=5;i++){
          document.getElementById(`sn_set${i}_equipe`).value = obj[`set${i}_equipe`]||0;
          document.getElementById(`sn_set${i}_adverse`).value= obj[`set${i}_adverse`]||0;
        }
      }
    })
    .catch(e=>console.error(e));

  // (C) Optionnel : Récup notes existantes => un endpoint qui renvoie
  // { noteAVG, noteAVC, ... noteR6 }, si tu veux pré-remplir
  // Sinon, on laisse tout par défaut =1
}

// Fermer la modale
function closeScoreNotesModal(){
  document.getElementById('score-notes-modal').style.display='none';
}
</script>
</body>
</html>
