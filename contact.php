<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>A4 Taxi - Contact</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="Asset/CSS/contact.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header class="header">
    <div class="container">
        <a href="index.php" class="logo">
            <img src="../Design-sans-titre.jpg" alt="A4 Taxi Logo">
            <span>A4 Taxi</span>
        </a>

        <!-- Bouton hamburger -->
        <button class="hamburger" onclick="toggleMenu()">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <nav class="nav" id="navMenu">
            <a href="index.php" title="Accueil">Accueil</a>
            <a href="reservation.php" title="Réservation">Réservation</a>
            <a href="login.php" title="Connexion">Connexion</a>
            <a href="contact.php" title="Contact" class="active">Contact</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="card contact-hero">
        <h1><i class="fas fa-phone"></i> Contactez-nous</h1>
        <p>Appelez-nous ou remplissez le formulaire pour une demande rapide</p>
    </section>

    <div class="contact-grid">
        <!-- 📞 Numéro de téléphone principal -->
        <div class="contact-item phone">
            <div class="contact-icon">
                <i class="fas fa-phone-volume"></i>
            </div>
            <div class="contact-content">
                <h3>Réservation rapide</h3>
                <a href="tel:+33321161607" class="contact-phone">06 12 34 56 78</a>
                <p>Disponible 24h/24 - 7j/7</p>
            </div>
        </div>

        <!-- 📍 Adresse -->
        <div class="contact-item address">
            <div class="contact-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="contact-content">
                <h3>Notre agence</h3>
                <p>5bis, Boulevard De Strasbourg<br>
                   62000 Arras<br>
                   Hauts-de-France</p>
                <a href="https://maps.google.com/?q=12+Rue+de+la+Gare,59500+Douai" target="_blank">
                    <i class="fas fa-directions"></i> Itinéraire
                </a>
            </div>
        </div>

        <!-- ✉️ Email -->
        <div class="contact-item email">
            <div class="contact-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="contact-content">
                <h3>Email</h3>
                <a href="mailto:contact@a4taxi.fr">contact@a4taxi.fr</a>
                <p>Réponse sous 24h</p>
            </div>
        </div>
    </div> 

    <!-- Formulaire de contact -->
    <section class="card">
        <h3><i class="fas fa-paper-plane"></i> Formulaire de contact</h3>
        <form method="POST" action="contact-submit.php" class="contact-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Nom complet</label>
                    <input type="text" name="nom" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="5" placeholder="Dites-nous en plus sur votre demande..." required></textarea>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-paper-plane"></i> Envoyer
            </button>
        </form>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> A4 Taxi - Arras, Hauts-de-France</p>
        <div class="footer-links">
            <a href="tel:+33612345678">03 21 16 16 07</a> |
            <a href="mailto:contact@a4taxi.fr">contact@a4taxi.fr</a> |
            <a href="#">Mentions légales</a>
        </div>
    </div>
</footer>

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
