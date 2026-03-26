<?php
// auth.php - Authentification simple, mot de passe en clair
// session_start() géré dans config.php

// ✅ LOGIN
function login(PDO $pdo, string $email, string $password): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nom, prenom, email, password, role, telephone
            FROM users
            WHERE email = ? AND role IN ('client','chauffeur','admin')
            LIMIT 1
        ");
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        // ✅ Comparaison directe, pas de chiffrement
        if ($user['password'] === $password) {
            $_SESSION['users_id']  = (int)$user['id'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['nom']       = trim(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? ''));
            $_SESSION['telephone'] = $user['telephone'] ?? '';
            return true;
        }
        return false;

    } catch (Exception $e) {
        error_log("Erreur login: " . $e->getMessage());
        return false;
    }
}

// ✅ INSCRIPTION
function registerUser(PDO $pdo, array $data): array {
    try {
        $email     = trim($data['email']);
        $nom       = trim($data['nom']);
        $prenom    = trim($data['prenom']);
        $telephone = trim($data['telephone'] ?? '');
        $adresse   = trim($data['adresse']   ?? '');
        $password  = $data['password'];
        $role      = $data['role'] ?? 'client';

        if (!in_array($role, ['client', 'chauffeur', 'admin'])) {
            return ['success' => false, 'message' => 'Rôle invalide'];
        }

        if ($role === 'client' && empty($adresse)) {
            return ['success' => false, 'message' => '❌ L\'adresse est obligatoire pour les clients'];
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => '❌ Email déjà utilisé'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (nom, prenom, email, password, telephone, adresse, role, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$nom, $prenom, $email, $password, $telephone, $adresse, $role]);

        return $result
            ? ['success' => true,  'message' => '✅ Compte créé !']
            : ['success' => false, 'message' => '❌ Erreur inscription'];

        $user_id = $pdo->lastInsertId();

        // ✅ Si client → insert aussi dans table clients
        if ($role === 'client') {
            $stmt = $pdo->prepare("
                INSERT INTO client (users_id, nom, prenom, email, telephone, adresse, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $nom, $prenom, $email, $telephone, $adresse]);
        }

        // ✅ Si chauffeur → insert aussi dans table chauffeurs
        if ($role === 'chauffeur') {
            $stmt = $pdo->prepare("
                INSERT INTO employe (users_id, nom, prenom, email, telephone, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $nom, $prenom, $email, $telephone]);
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => '❌ Erreur: ' . $e->getMessage()];
    }
}

// 🔒 CONTRÔLE D'ACCÈS
function requireLogin(): void {
    if (!isset($_SESSION['users_id'])) {
        header('Location: login.php?error=non_connecte');
        exit();
    }
}

function requireRole(array $roles): void {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        header('Location: login.php?error=acces_refuse');
        exit();
    }
}

function requireClient(): void    { requireRole(['client']); }
function requireChauffeur(): void { requireRole(['chauffeur']); }
function requireAdmin(): void     { requireRole(['admin']); }

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

function getUser(PDO $pdo, int $users_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$users_id]);
    return $stmt->fetch() ?: null;
}
?>