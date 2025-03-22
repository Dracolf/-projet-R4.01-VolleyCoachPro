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

// Bloquer si c’est guest
if ($_SESSION['user'] === 'guest') {
    header("Location: joueur.php");
    exit;
}

$token = $_SESSION['token'];
$api_url = "https://volleycoachpro.alwaysdata.net/volleyapi/joueurs/";

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

$message = "";
$error = false;
$duplicateError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $result = null; // Évite les "Undefined variable"

    if ($action === 'add' || $action === 'update') {
        $license       = $_POST['license']       ?? null;
        $nom           = $_POST['nom']           ?? null;
        $prenom        = $_POST['prenom']        ?? null;
        $dateNaissance = $_POST['date_naissance'] ?? null;
        $taille        = $_POST['taille']        ?? null;
        $poids         = $_POST['poids']         ?? null;
        $commentaire   = $_POST['commentaire']   ?? null;
        $statut        = $_POST['statut']        ?? null;

        $data = [
            "licence"    => $license,
            "nom"        => $nom,
            "prenom"     => $prenom,
            "naissance"  => $dateNaissance,
            "taille"     => $taille,
            "poids"      => $poids,
            "commentaire"=> $commentaire,
            "statut"     => $statut
        ];
    }

    if ($action === 'add') {
        $result = sendCurlRequest($api_url, "POST", $token, $data);
    } elseif ($action === 'update') {
        $id = $_POST['IdJoueur'] ?? null;
        if ($id) {
            $result = sendCurlRequest($api_url . "$id", "PUT", $token, $data);
        } else {
            $message = "ID du joueur manquant.";
            $error = true;
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id_joueur_to_remove'] ?? null;
        if ($id) {
            $result = sendCurlRequest($api_url . "$id", "DELETE", $token);
        } else {
            $message = "ID du joueur manquant.";
            $error = true;
        }
    }

    // Vérification avant d’accéder à $result
    if ($result !== null && $result["code"] === 200) {
        $message = match ($action) {
            'add' => "Nouveau joueur ajouté avec succès.",
            'update' => "Le joueur a été mis à jour avec succès.",
            'delete' => "Le joueur a été supprimé avec succès.",
            default => "Opération réussie."
        };
        $error = false;
    } elseif ($result !== null) {
        $error = true;
        if ($result["code"] === 400 && isset($result["response"]["status_message"])) {
            $duplicateError = $result["response"]["status_message"];
        } else {
            $message = "Erreur lors de l’opération sur le joueur.";
        }
    }
}

$joueurs = sendCurlRequest($api_url, "GET", $token, null);
if ($joueurs['code'] !== 200) {
    header("Location: logout.php");
}
$joueurs = $joueurs['response']['data'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Joueurs</title>
  <link rel="stylesheet" href="styles.css">

  <script>
    var allPlayers = <?php echo json_encode(
        array_map(function($j){
            return [
                'IdJoueur' => $j['IdJoueur'],
                'Nom'      => $j['Nom'],
                'Prenom'   => $j['Prénom'],
                'Statut'   => $j['Statut'],
                'License'  => $j['Numéro_de_license']
            ];
        }, $joueurs),
        JSON_HEX_TAG | JSON_HEX_AMP
    ); ?>;

    function openModal(action, data = {}) {
        console.log("Données reçues par openModal:", data); // Vérification des données reçues
        const modal = document.getElementById('modal');
        modal.style.display = 'flex';

        const modalTitle = document.getElementById('modal-title');
        modalTitle.textContent = (action === 'add')
            ? "Ajouter un nouveau joueur"
            : "Modifier un joueur";

        document.getElementById('action').value = action;
        document.getElementById('IdJoueur').value = data.id || ''; // Vérification ici

        if (action === 'add') {
            document.getElementById('license').value = '';
            document.getElementById('license').readOnly = false;
        } else {
            document.getElementById('license').value = data.license || '';
            document.getElementById('license').readOnly = true;
        }

        document.getElementById('nom').value = data.nom || '';
        document.getElementById('prenom').value = data.prenom || '';
        document.getElementById('date_naissance').value = data.date_naissance || '';
        document.getElementById('taille').value = data.taille || '';
        document.getElementById('poids').value = data.poids || '';
        document.getElementById('commentaire').value = data.commentaire || '';
        document.getElementById('statut').value = data.statut || 'Actif';
    }

    function closeModal() {
        document.getElementById('modal').style.display = 'none';
    }

    // --- Modale d'erreur quand le numéro est déjà pris ---
    function showDuplicateError(message) {
        const errorMsg = document.getElementById('duplicate-error-message');
        errorMsg.textContent = message;

        document.getElementById('error-modal').style.display = 'flex';
    }
    function closeErrorModal() {
        document.getElementById('error-modal').style.display = 'none';
    }

    // Recherche
    function searchPlayer(query) {
        const tableBody = document.querySelector('.table tbody');
        fetch(`search_player.php?query=${encodeURIComponent(query)}`)
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
                reinitializeEventHandlers(); 
            })
            .catch(error => console.error('Erreur lors de la recherche :', error));
    }
    function reinitializeEventHandlers() {
        const editButtons = document.querySelectorAll('.button-edit');
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const data = JSON.parse(button.getAttribute('data-player'));
                openModal('update', data);
            });
        });

        const deleteButtons = document.querySelectorAll('.button-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', () => {
                const idJoueur = button.getAttribute('data-idjoueur');
                openDeleteModal(idJoueur);
            });
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                searchPlayer(searchInput.value);
            });
        }
        reinitializeEventHandlers();

        // Si on a un message d'erreur de duplication côté serveur
        const duplicateError = "<?php echo $duplicateError; ?>";
        if (duplicateError) {
            // Ouvrir la modale d'erreur
            showDuplicateError(duplicateError);
        }
    });

    // Suppression
    function openDeleteModal(idJoueurToRemove) {
        document.getElementById('delete-modal').style.display = 'flex';
        document.getElementById('id_joueur_to_remove').value = idJoueurToRemove;
    }

    function closeDeleteModal() {
        document.getElementById('delete-modal').style.display = 'none';
    }
  </script>
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
                    <a href="stats.php">Statistiques</a>
                    <a href="joueur.php">Joueurs</a>
                    <a href="rencontre.php">Rencontres</a>
                    <a href="logout.php">Déconnexion</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Affichage d'un message global -->
    <?php if (!empty($message)): ?>
        <p class="message <?= $error ? 'error-message' : 'info-message' ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <!-- Contenu principal -->
    <div class="content-container">
        <h1 class="content-title">Gestion des Joueurs</h1>
        <button class="button-add" onclick="openModal('add')">Ajouter un joueur</button>

        <!-- Barre de recherche -->
        <div class="search-container">
            <input type="text" id="search" placeholder="Rechercher un joueur...">
        </div>

        <!-- Tableau des joueurs -->
        <div class="content-box">
            <table class="table">
                <thead>
                    <tr>
                        <th>Numéro de Licence</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($joueurs as $joueur): ?>
                        <tr>
                            <td><?= htmlspecialchars($joueur['Numéro_de_license']) ?></td>
                            <td><?= htmlspecialchars($joueur['Nom']) ?></td>
                            <td><?= htmlspecialchars($joueur['Prénom']) ?></td>
                            <td>
                                <!-- Bouton "Modifier" -->
                                <button class="button-edit" 
                                    data-player='<?= json_encode([
                                        'id'             => $joueur['IdJoueur'],
                                        'license'        => $joueur['Numéro_de_license'],
                                        'nom'            => $joueur['Nom'],
                                        'prenom'         => $joueur['Prénom'],
                                        'date_naissance' => $joueur['Date_de_naissance'],
                                        'taille'         => $joueur['Taille'],
                                        'poids'          => $joueur['Poids'],
                                        'commentaire'    => $joueur['Commentaire'],
                                        'statut'         => $joueur['Statut']
                                    ]) ?>'>
                                    Modifier
                                </button>

                                <!-- Bouton "Supprimer" -->
                                <button class="button-delete" 
                                        data-idjoueur="<?= $joueur['IdJoueur'] ?>">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Bouton pour aller à joueur.php -->
        <div class="bottom-button-container">
            <a href="joueur.php" class="button-manage">Voir les joueurs</a>
        </div>
    </div>

    <!-- Modale AJOUT / MODIFICATION d'un joueur -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <h2 id="modal-title"></h2>
            <form method="POST">
                <input type="hidden" name="action" id="action">
                
                <input type="hidden" name="IdJoueur" id="IdJoueur">
                <label>Numéro de Licence : 
                    <input type="text" name="license" id="license" maxlength="6" required pattern="\d{6}">
                </label>
                <label>Nom : 
                    <input type="text" name="nom" id="nom" required>
                </label>
                <label>Prénom : 
                    <input type="text" name="prenom" id="prenom" required>
                </label>
                <label>Date de Naissance : 
                    <input type="date" name="date_naissance" id="date_naissance" required>
                </label>
                <label>Taille (cm) : 
                    <input type="number" min="54" max="251" name="taille" id="taille" required>
                </label>
                <label>Poids (kg) : 
                    <input type="number" name="poids" id="poids" required>
                </label>
                <label>Commentaire : 
                    <textarea name="commentaire" id="commentaire"></textarea>
                </label>
                <label>Statut : 
                    <select name="statut" id="statut" required>
                        <option value="Actif">Actif</option>
                        <option value="Absent">Absent</option>
                        <option value="Blessé">Blessé</option>
                        <option value="Suspendu">Suspendu</option>
                    </select>
                </label>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Valider</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale SUPPRESSION -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <h2>Supprimer un joueur</h2>
            <p>Êtes-vous sûr de vouloir supprimer ce joueur ?</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <!-- On stocke ici l'IdJoueur à supprimer -->
                <input type="hidden" name="id_joueur_to_remove" id="id_joueur_to_remove">

                <div class="modal-actions" style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Confirmer la suppression</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale d'erreur pour la duplication de licence -->
    <div id="error-modal" class="modal">
        <div class="modal-content">
            <h2>Erreur</h2>
            <p id="duplicate-error-message" class="duplicate-error"></p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeErrorModal()">Fermer</button>
            </div>
        </div>
    </div>
</body>
</html>
