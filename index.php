<?php
require_once 'config.php'; // ✅ Utilise config.php (AES + PDO + session)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>A4 Taxi - Accueil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- ✅ CSS corrigé -->
    <link rel="stylesheet" href="Asset/CSS/style.css">
</head>
<body>
<header class="header">
    <div class="container">
        <a href="index.php" class="logo">
            <img src="logo.png" alt="A4 Taxi Logo">
            <span>A4 Taxi</span>
        </a>

        <!-- Bouton hamburger -->
        <button class="hamburger" onclick="toggleMenu()">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <nav class="nav" id="navMenu">
            <a href="estimation.php">Estimation</a>
            <a href="reservation.php">Réservation</a>
            <a href="login.php">Connexion</a>
            <a href="register.php">Inscription</a>
            <a href="contact.php">Contact</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="card">
        <h2>Bienvenue sur A4 Taxi</h2>
        <p>
            Réservez votre taxi en quelques clics ! Choisissez l'heure exacte de prise en charge 
            et obtenez une estimation précise de votre trajet.
        </p>
        <p>
            Notre application s'adapte parfaitement à tous vos écrans : ordinateur, tablette ou smartphone.
        </p>

        <div class="home-actions">
            <a href="reservation.php" class="btn-primary">
                <i class="fas fa-car"></i> Réserver maintenant
            </a>
            <a href="dashboard_client.php?role=CLIENT" class="btn-secondary">Espace client</a>
            <a href="dashboard_admin.php?role=ADMIN" class="btn-secondary">Espace ADMIN</a>
            <a href="dashboard_chauffeur.php?role=CHAUFFEUR" class="btn-secondary">Espace CHAUFFEUR</a>
        </div>
    </section>

    <section class="card">
        <h3>🚕 Nos services</h3>
        <ul class="feature-list">
            <li><i class="fas fa-map-marker-alt"></i> Course simple/aller-retour/programmée</li>
            <li><i class="fas fa-users"></i> Jusqu'à 5 passagers</li>
            <li><i class="fas fa-clock"></i> Heure de départ précise</li>
            <li><i class="fas fa-suitcase"></i> Gestion bagages</li>
            <li><i class="fas fa-shield-alt"></i> Paiement sécurisé</li>
            <li><i class="fas fa-mobile-alt"></i> 100% responsive</li>
        </ul>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> A4 Taxi</p>
        <p style="font-size: 0.8rem; opacity: 0.7;">Service de réservation de taxis local et fiable</p>
    </div>
</footer>

<!-- Font Awesome pour icônes -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<script>
// Fonction pour basculer le menu hamburger
function toggleMenu() {
    const nav = document.getElementById('navMenu');
    const hamburger = document.querySelector('.hamburger');

    nav.classList.toggle('active');
    hamburger.classList.toggle('active');
}

// Fermer le menu si on clique en dehors
document.addEventListener('click', function(event) {
    const nav = document.getElementById('navMenu');
    const hamburger = document.querySelector('.hamburger');

    if (!event.target.closest('.hamburger') && !event.target.closest('#navMenu')) {
        nav.classList.remove('active');
        hamburger.classList.remove('active');
    }
});
</script>
</body>
</html>
