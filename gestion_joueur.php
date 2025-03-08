<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Bloquer si c’est guest
if ($_SESSION['user'] === 'guest') {
    header("Location: joueur.php");
    exit;
}


// Informations de connexion
$host = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

$message = "";
$error = false;

// Cette variable contiendra un message d'erreur à afficher dans une modale
$duplicateError = "";

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --------------------------------------------------------------------------------
    // 1) TRAITEMENT DES FORMULAIRES (ADD / UPDATE / DELETE+REPLACEMENT)
    // --------------------------------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        // === AJOUT d'un joueur ===
        if ($action === 'add') {
            $license       = $_POST['license'];
            $nom           = $_POST['nom'];
            $prenom        = $_POST['prenom'];
            $dateNaissance = $_POST['date_naissance'];
            $taille        = $_POST['taille'];
            $poids         = $_POST['poids'];
            $commentaire   = $_POST['commentaire'];
            $statut        = $_POST['statut'];

            try {
                $insertQuery = $pdo->prepare("
                    INSERT INTO Joueur (
                        Numéro_de_license, Nom, Prénom, Date_de_naissance, Taille, Poids, Commentaire, Statut
                    ) VALUES (
                        :license, :nom, :prenom, :dnaiss, :taille, :poids, :comm, :statut
                    )
                ");
                $insertQuery->execute([
                    ':license' => $license,
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':dnaiss' => $dateNaissance,
                    ':taille' => $taille,
                    ':poids' => $poids,
                    ':comm' => $commentaire,
                    ':statut' => $statut
                ]);
                $message = "Nouveau joueur ajouté avec succès.";
                $error = false;

            } catch (PDOException $e) {
                // Vérifier si c'est un duplicat
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    // Doublon sur la licence
                    $duplicateError = "Le numéro de licence $license est déjà utilisé. Veuillez en choisir un autre.";
                } else {
                    // Autre erreur
                    throw $e; // Laisser remonter l'exception
                }
            }
        }

        // === MODIFICATION d'un joueur ===
        else if ($action === 'update') {
            $license       = $_POST['license'];
            $nom           = $_POST['nom'];
            $prenom        = $_POST['prenom'];
            $dateNaissance = $_POST['date_naissance'];
            $taille        = $_POST['taille'];
            $poids         = $_POST['poids'];
            $commentaire   = $_POST['commentaire'];
            $statut        = $_POST['statut'];

            try {
                $updateQuery = $pdo->prepare("
                    UPDATE Joueur
                    SET Nom = :nom,
                        Prénom = :prenom,
                        Date_de_naissance = :dnaiss,
                        Taille = :taille,
                        Poids = :poids,
                        Commentaire = :comm,
                        Statut = :statut
                    WHERE Numéro_de_license = :license
                ");
                $updateQuery->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':dnaiss' => $dateNaissance,
                    ':taille' => $taille,
                    ':poids' => $poids,
                    ':comm' => $commentaire,
                    ':statut' => $statut,
                    ':license' => $license
                ]);
                $message = "Le joueur a été mis à jour avec succès.";
                $error = false;
            
            } catch (PDOException $e) {
                // Idem, si vous voulez empêcher de changer la licence vers une existante
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $duplicateError = "Le numéro de licence $license est déjà utilisé par un autre joueur.";
                } else {
                    throw $e;
                }
            }
        }

        // === SUPPRESSION avec remplacement + GESTION DES CONFLITS ===
        else if ($action === 'delete_with_replacement') {
            $idJoueurToRemove    = $_POST['id_joueur_to_remove'] ?? null;
            $idJoueurRemplacant  = $_POST['id_joueur_remplacant'] ?? null;

            if (!$idJoueurToRemove || !$idJoueurRemplacant) {
                $message = "Informations manquantes pour la suppression.";
                $error = true;
            } else {
                // Vérif existence du joueur à supprimer
                $checkQuery = $pdo->prepare("SELECT * FROM Joueur WHERE IdJoueur = :idRemove");
                $checkQuery->execute([':idRemove' => $idJoueurToRemove]);
                $joueurToRemove = $checkQuery->fetch(PDO::FETCH_ASSOC);

                if (!$joueurToRemove) {
                    $message = "Le joueur à supprimer n'existe pas (Id: $idJoueurToRemove).";
                    $error = true;
                } else {
                    // 1) Récupérer toutes les participations du joueur à supprimer
                    $participQuery = $pdo->prepare("
                        SELECT IdRencontre
                        FROM Participer
                        WHERE IdJoueur = :old
                    ");
                    $participQuery->execute([':old' => $idJoueurToRemove]);
                    $participations = $participQuery->fetchAll(PDO::FETCH_COLUMN);

                    // 2) Pour chaque match, vérifier si le remplaçant y est déjà
                    foreach ($participations as $idRencontre) {
                        $checkDouble = $pdo->prepare("
                            SELECT COUNT(*) 
                            FROM Participer
                            WHERE IdRencontre = :idR
                              AND IdJoueur = :remplacant
                        ");
                        $checkDouble->execute([
                            ':idR' => $idRencontre,
                            ':remplacant' => $idJoueurRemplacant
                        ]);
                        $alreadyPresent = $checkDouble->fetchColumn();

                        if ($alreadyPresent > 0) {
                            // Le remplaçant joue déjà dans ce match
                            // => on supprime la participation du joueur à retirer
                            $deleteParticipation = $pdo->prepare("
                                DELETE FROM Participer
                                WHERE IdRencontre = :idR
                                  AND IdJoueur = :old
                            ");
                            $deleteParticipation->execute([
                                ':idR' => $idRencontre,
                                ':old' => $idJoueurToRemove
                            ]);
                        } else {
                            // Sinon, on transfère la participation
                            $updateParticipation = $pdo->prepare("
                                UPDATE Participer
                                SET IdJoueur = :remplacant
                                WHERE IdRencontre = :idR
                                  AND IdJoueur = :old
                            ");
                            $updateParticipation->execute([
                                ':remplacant' => $idJoueurRemplacant,
                                ':idR' => $idRencontre,
                                ':old' => $idJoueurToRemove
                            ]);
                        }
                    }

                    // 3) Supprimer le joueur de la table Joueur
                    $deleteQuery = $pdo->prepare("
                        DELETE FROM Joueur WHERE IdJoueur = :idRemove
                    ");
                    $deleteQuery->execute([':idRemove' => $idJoueurToRemove]);

                    $message = "Joueur supprimé. Participations transférées ou supprimées pour éviter les doublons.";
                    $error = false;
                }
            }
        }
    }

    // --------------------------------------------------------------------------------
    // 2) RÉCUPÉRATION DES DONNÉES POUR AFFICHAGE
    // --------------------------------------------------------------------------------

    $query = $pdo->prepare("SELECT * FROM Joueur ORDER BY Nom ASC");
    $query->execute();
    $joueurs = $query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion ou d'exécution : " . $e->getMessage());
}
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
        const modal = document.getElementById('modal');
        modal.style.display = 'flex';

        const modalTitle = document.getElementById('modal-title');
        modalTitle.textContent = (action === 'add')
            ? "Ajouter un nouveau joueur"
            : "Modifier un joueur";

        document.getElementById('action').value = action;

        if (action === 'add') {
            // Formulaire vierge
            document.getElementById('license').value = '';
            document.getElementById('license').readOnly = false;
            document.getElementById('nom').value = '';
            document.getElementById('prenom').value = '';
            document.getElementById('date_naissance').value = '';
            document.getElementById('taille').value = '';
            document.getElementById('poids').value = '';
            document.getElementById('commentaire').value = '';
            document.getElementById('statut').value = 'Actif';
        } else {
            // "update"
            document.getElementById('license').value = data.license || '';
            document.getElementById('license').readOnly = true;
            document.getElementById('nom').value = data.nom || '';
            document.getElementById('prenom').value = data.prenom || '';
            document.getElementById('date_naissance').value = data.date_naissance || '';
            document.getElementById('taille').value = data.taille || '';
            document.getElementById('poids').value = data.poids || '';
            document.getElementById('commentaire').value = data.commentaire || '';
            document.getElementById('statut').value = data.statut || 'Actif';
        }
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
                openReplacementModal(idJoueur);
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

    // Suppression + Remplacement
    async function openReplacementModal(idJoueurToRemove) {
        document.getElementById('delete-modal').style.display = 'flex';
        document.getElementById('id_joueur_to_remove').value = idJoueurToRemove;

        const url = `valid_replacements.php?idToRemove=${idJoueurToRemove}`;
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Erreur réseau');
            const validReplacers = await response.json();

            const select = document.getElementById('id_joueur_remplacant');
            select.innerHTML = '';
            for (let v of validReplacers) {
                let opt = document.createElement('option');
                opt.value = v.IdJoueur;
                opt.textContent = v.Nom + ' ' + v.Prénom;
                select.appendChild(opt);
            }
        } catch (err) {
            console.error('Erreur fetch validReplacements:', err);
            const select = document.getElementById('id_joueur_remplacant');
            select.innerHTML = '<option value="">(Erreur de chargement)</option>';
        }
    }
    function closeReplacementModal() {
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

    <!-- Modale SUPPRESSION + CHOIX DU REMPLAÇANT -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <h2>Supprimer un joueur</h2>
            <p>Choisissez le joueur remplaçant pour toutes ses participations :</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_with_replacement">
                <!-- On stocke ici l'IdJoueur à supprimer -->
                <input type="hidden" name="id_joueur_to_remove" id="id_joueur_to_remove">

                <label for="id_joueur_remplacant">Remplaçant :</label>
                <select name="id_joueur_remplacant" id="id_joueur_remplacant" required>
                    <!-- Rempli dynamiquement via JS (fetch valid_replacements.php) -->
                </select>

                <div class="modal-actions" style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Confirmer la suppression</button>
                    <button type="button" class="btn btn-secondary" onclick="closeReplacementModal()">Annuler</button>
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
