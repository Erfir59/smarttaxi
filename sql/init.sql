-- init.sql
-- Initialisation BDD SmartTaxi (schéma basé sur le MCD fourni)

CREATE DATABASE IF NOT EXISTS smarttaxi;
USE smarttaxi;

-- Utilisateurs génériques (pour login/inscription)
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100),
  prenom VARCHAR(100),
  email VARCHAR(255) UNIQUE,
  password LONGTEXT,
  telephone VARCHAR(30),
  role ENUM('client','chauffeur','admin') NOT NULL DEFAULT 'client',
  adresse VARCHAR(255) DEFAULT NULL,
  disponible      TINYINT(1) DEFAULT 0,
  immatriculation VARCHAR(20) DEFAULT NULL,
  vehicule   VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Types d'employés
DROP TABLE IF EXISTS type_employe;
CREATE TABLE type_employe (
  id_type_employe INT AUTO_INCREMENT PRIMARY KEY,
  libelle_type_employe VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Types de clients
DROP TABLE IF EXISTS type_client;
CREATE TABLE type_client (
  id_type_client INT AUTO_INCREMENT PRIMARY KEY,
  libelle_type_client VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Employés
DROP TABLE IF EXISTS employe;
CREATE TABLE employe (
  id_employe INT AUTO_INCREMENT PRIMARY KEY,
  id_type_employe INT NOT NULL,
  nom_employe VARCHAR(100) NOT NULL,
  prenom_employe VARCHAR(100) NOT NULL,
  telephone_employe VARCHAR(30),
  email_employe VARCHAR(255) UNIQUE,
  password LONGTEXT,
  num_licence VARCHAR(100),
  statut_activite ENUM('ACTIF','INACTIF') DEFAULT 'ACTIF',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_type_employe) REFERENCES type_employe(id_type_employe) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Clients
DROP TABLE IF EXISTS client;
CREATE TABLE client (
  id_client INT AUTO_INCREMENT PRIMARY KEY,
  nom_client VARCHAR(100) NOT NULL,
  prenom_client VARCHAR(100) NOT NULL,
  telephone_client VARCHAR(30),
  email_client VARCHAR(255) UNIQUE,
  password LONGTEXT,
  adresse_client VARCHAR(255),
  id_type_client INT DEFAULT NULL,
  nb_client INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_type_client) REFERENCES type_client(id_type_client) ON DELETE SET NULL
) ENGINE=InnoDB;

-- GPS (1:1 avec véhicule)
DROP TABLE IF EXISTS GPS;
CREATE TABLE GPS (
  id_GPS INT AUTO_INCREMENT PRIMARY KEY,
  firmware_version VARCHAR(100),
  statut_connexion BOOLEAN DEFAULT FALSE,
  signal_GPS DECIMAL(6,2),
  batterie_percent TINYINT UNSIGNED,
  latitude DECIMAL(10,7),
  longitude DECIMAL(10,7),
  maj_position TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Véhicules
DROP TABLE IF EXISTS Vehicule;
CREATE TABLE Vehicule (
  id_vehicule INT AUTO_INCREMENT PRIMARY KEY,
  immatriculation VARCHAR(20) UNIQUE NOT NULL,
  marque VARCHAR(100),
  nb_places TINYINT UNSIGNED DEFAULT 4,
  pmr BOOLEAN DEFAULT FALSE,
  modele VARCHAR(100),
  nb_bagages TINYINT UNSIGNED DEFAULT 0,
  etat_vehicule VARCHAR(50) DEFAULT 'DISPONIBLE',
  id_GPS INT DEFAULT NULL,
  id_employe INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_GPS) REFERENCES GPS(id_GPS) ON DELETE SET NULL,
  FOREIGN KEY (id_employe) REFERENCES employe(id_employe) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tarifs
DROP TABLE IF EXISTS Tarifs;
CREATE TABLE Tarifs (
  id_tarifs INT AUTO_INCREMENT PRIMARY KEY,
  prix_charge DECIMAL(8,2) NOT NULL,
  prix_max_km DECIMAL(8,2) DEFAULT NULL,
  prix_max_heure DECIMAL(8,2) DEFAULT NULL,
  prix_supp_passager DECIMAL(6,2) DEFAULT 0.00,
  prix_supp_bagage DECIMAL(6,2) DEFAULT 0.00,
  prix_jour DECIMAL(8,2) DEFAULT NULL,
  prix_nuit DECIMAL(8,2) DEFAULT NULL,
  prix_jours_feries_dimanche DECIMAL(8,2) DEFAULT NULL,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Réservations
DROP TABLE IF EXISTS Reservation;
CREATE TABLE Reservation (
  id_reservation INT AUTO_INCREMENT PRIMARY KEY,
  date_heure_reservation DATETIME NOT NULL,
  adresse_depart VARCHAR(255) NOT NULL,
  adresse_arrivee VARCHAR(255) NOT NULL,
  nb_passager TINYINT UNSIGNED DEFAULT 1,
  statut ENUM('EN_ATTENTE','CONFIRMEE','ANNULEE','TERMINEE') DEFAULT 'EN_ATTENTE',
  id_client INT NOT NULL,
  id_chauffeur INT DEFAULT NULL,
  id_vehicule INT DEFAULT NULL,
  id_tarifs INT DEFAULT NULL,
  prix_estime DECIMAL(9,2) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_client) REFERENCES client(id_client) ON DELETE CASCADE,
  FOREIGN KEY (id_chauffeur) REFERENCES employe(id_employe) ON DELETE SET NULL,
  FOREIGN KEY (id_vehicule) REFERENCES Vehicule(id_vehicule) ON DELETE SET NULL,
  FOREIGN KEY (id_tarifs) REFERENCES Tarifs(id_tarifs) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insérer des données de test
INSERT INTO type_employe (libelle_type_employe) VALUES ('Chauffeur'), ('Administrateur');
INSERT INTO type_client (libelle_type_client) VALUES ('Particulier'), ('Entreprise');

 
