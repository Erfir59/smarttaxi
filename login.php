<?php
require_once 'config.php';
require_once 'auth.php';

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (login($pdo, $email, $password)) {
        
        // ✅ Redirection selon le rôle
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: dashboard_admin.php');
                break;
            case 'chauffeur':
                header('Location: dashboard_chauffeur.php');
                break;
            case 'client':
            default:
                header('Location: dashboard_client.php');
                break;
        }
        exit();
    } else {
        $erreur = "Identifiants incorrects.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SmartTaxi - Connexion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="Asset/CSS/login.css">
</head>
<body>

<header class="header">
    <div class="container">
        <h1 class="logo">🚖 SmartTaxi</h1>
        <nav class="nav">
            <a href="estimation.php">Estimation</a>
            <a href="reservation.php">Réserver</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="card">
        <h2>Connexion</h2>

        <?php if ($erreur): ?>
            <div class="alert error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="animated-button">
                    <span class="circle"></span>
                    <svg class="arr-2" viewBox="0 0 24 24"><path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 12.0001L12.2218 19.7785L10.8076 18.3643L16.1716 12.9999H4V10.9999H16.1716Z"/></svg>
                    <span class="text">Se connecter</span>
                    <svg class="arr-1" viewBox="0 0 24 24"><path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 12.0001L12.2218 19.7785L10.8076 18.3643L16.1716 12.9999H4V10.9999H16.1716Z"/></svg>
                </button>
                <a href="index.php" class="btn-secondary-animated">
                    <svg class="arrow-back" viewBox="0 0 24 24"><path d="M7.82843 10.9999H20V12.9999H7.82843L13.1924 18.3643L11.7782 19.7785L4 12.0001L11.7782 4.22168L13.1924 5.63589L7.82843 10.9999Z"/></svg>
                    <span class="text-back">Retour à l'accueil</span>
                    <span class="circle-back"></span>
                </a>
            </div>
        </form>
    </section>

    <section class="card" style="margin-top:2rem; text-align:center;">
        <p>Pas encore de compte ?</p>
        <a href="register.php" style="color:#1976d2; font-weight:600;">S'inscrire ici</a>
    </section>
</main>

</body>
</html>