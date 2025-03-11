<?php
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers la page principale
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Formulaire standard (login + pass)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'], $_POST['password'])) {
    $login         = $_POST['login']    ?? '';
    $passwordInput = $_POST['password'] ?? '';

    $url = "https://volleycoachpro.alwaysdata.net/authapi/";
    $data = [
                "login" => $login,
                "password" => $passwordInput
            ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    // Convertir la réponse JSON en tableau PHP
    $data = json_decode($response, true);

    if (isset($data['token'])) {
        // Démarrer la session et stocker l'utilisateur
        $_SESSION['user'] = $data['user']; // Stocker le login ou l'ID utilisateur
        $_SESSION['token'] = $data['token']; // Stocker le JWT (utile pour les futures requêtes API)

        header("Location: index.php");
        exit;
    } else {
        $message = isset($data['error']) ? $data['error'] : "Échec de connexion";
    }

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
