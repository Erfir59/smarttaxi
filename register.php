<?php
require_once 'config.php';
require_once 'auth.php';

$erreur = null;
$succes = null;
$type   = 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type             = $_POST['type']             ?? 'client';
    $email            = trim($_POST['email']            ?? '');
    $password         = $_POST['password']         ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nom              = trim($_POST['nom']              ?? '');
    $prenom           = trim($_POST['prenom']           ?? '');
    $telephone        = trim($_POST['telephone']        ?? '');
    $adresse          = trim($_POST['adresse']          ?? '');

    // --- Validations ---
    if (empty($email) || empty($password) || empty($nom) || empty($prenom)) {
        $erreur = "Champs requis manquants.";
    } elseif ($password !== $password_confirm) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $erreur = "Mot de passe trop court (8 caractères minimum).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse email invalide.";
    } else {
        $data = [
            'email'     => $email,
            'password'  => $password,
            'nom'       => $nom,
            'prenom'    => $prenom,
            'telephone' => $telephone,
            'adresse'   => $adresse,
            'role'      => $type,
        ];

        $result = registerUser($pdo, $data);

        if ($result['success']) {
            // ✅ Connexion automatique juste après l'inscription
            login($pdo, $email, $password);

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
            $erreur = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>SmartTaxi - Inscription</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="Asset/CSS/register.css">
</head>

<body>

    <header class="header">
        <div class="container">
            <h1 class="logo">🚖 SmartTaxi</h1>
            <nav class="nav">
                <a href="index.php">Accueil</a>
                <a href="login.php">Connexion</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="card">
            <h2>Créer un compte</h2>

            <?php if ($erreur): ?>
                <div class="alert error"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="post">
                <!-- RÔLE -->
                <div class="form-group">
                    <label>Rôle</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="type" value="client"
                                <?= ($type === 'client'    ? 'checked' : '') ?>> Client
                        </label>
                        <label>
                            <input type="radio" name="type" value="chauffeur"
                                <?= ($type === 'chauffeur' ? 'checked' : '') ?>> Chauffeur
                        </label>
                        <label>
                            <input type="radio" name="type" value="admin"
                                <?= ($type === 'admin'     ? 'checked' : '') ?>> Admin
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" name="prenom" required
                        value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" required
                        value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone"
                        value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="adresse"
                        value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Mot de passe *</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe *</label>
                    <input type="password" name="password_confirm" required minlength="8">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">S'inscrire</button>
                    <a href="login.php" class="btn-secondary">Déjà un compte ?</a>
                </div>

            </form>
        </section>
    </main>

</body>

</html>