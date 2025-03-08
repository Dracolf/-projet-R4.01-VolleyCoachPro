<?php
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers la page principale
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Informations de connexion à la base de données
$host     = "sql312.infinityfree.com";
$username = "if0_37676623";
$password = "theadmin31";
$database = "if0_37676623_gestionvolley";

$message = "";

// Connexion PDO
try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Formulaire standard (login + pass)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'], $_POST['password'])) {
        $login         = $_POST['login']    ?? '';
        $passwordInput = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE Login = :login");
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // On hash le mdp saisi
            $hashedInput = hash_hmac('sha256', $passwordInput, 'ripbozo');
            if ($hashedInput === $user['Mdp']) {
                // Connexion OK
                $_SESSION['user'] = $user['Login'];
                header("Location: index.php");
                exit;
            } else {
                $message = "Identifiant ou mot de passe incorrect.";
            }
        } else {
            $message = "Identifiant ou mot de passe incorrect.";
        }
    }

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Volley CoachPro - Connexion</title>
    <link rel="stylesheet" href="styles.css">
    <style>
      .login-container { /* ajustez au besoin */
        max-width: 400px;
        margin: 50px auto;
        text-align: center;
      }
      .message {
        color: red;
        margin-bottom: 10px;
      }
      .button-guest {
        margin-top: 15px;
        padding: 8px 16px;
        cursor: pointer;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
      }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>VolleyCoachPro</h1>
        <p>Connexion sécurisée</p>

        <?php if (!empty($message)): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- Formulaire principal (login + password) -->
        <form action="" method="POST">
            <input type="text" name="login" placeholder="Entrez votre identifiant" required>
            <input type="password" name="password" placeholder="Entrez votre mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>

        <!-- Bouton "Naviguer en tant qu’invité" -->
        <button type="button" onclick="fillGuestCredentials()" class="button-guest">
          Naviguer en tant qu'invité
        </button>
    </div>

    <script>
    /**
     * Remplit automatiquement les champs Login/Password
     * avec "guest" et "invite", respectivement.
     */
    function fillGuestCredentials() {
      document.querySelector('input[name="login"]').value    = 'guest';
      document.querySelector('input[name="password"]').value = 'invite';
    }
    </script>
</body>
</html>
