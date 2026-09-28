<?php
// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'pharmacie_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration application - LUBUMBASHI
define('WHATSAPP_NUMBER', '+243901726290');
define('NOM_PHARMACIE', 'Pharmacie Sorayah');
define('ADRESSE_PHARMACIE', '47, Avenue Baluba, Quartier Ndjanja, Kamalondo');
define('TELEPHONE_PHARMACIE', '+243 901 726 290');
define('EMAIL_PHARMACIE', 'contact@pharmacielubumbashi.cd');

// Connexion BDD
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonctions utilitaires
function format_prix($montant) {
    if ($montant === null || $montant === '') return '0 FC';
    return number_format((float)$montant, 0, ',', '.') . ' FC';
}

function generer_numero_facture() {
    $date = date('Ymd');
    $unique = strtoupper(substr(uniqid(), -4));
    return "FACT-{$date}-{$unique}";
}

function securiser($texte) {
    return htmlspecialchars(trim($texte), ENT_QUOTES, 'UTF-8');
}

// Vérifier si l'utilisateur est connecté
function est_connecte() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Vérifier si l'utilisateur est admin
function est_admin() {
    return est_connecte() && $_SESSION['user_role'] === 'admin';
}

// Vérifier le rôle
function verifier_acces($role_requis) {
    if (!est_connecte()) {
        header('Location: login.php');
        exit;
    }
    if ($role_requis === 'admin' && !est_admin()) {
        header('Location: utilisateur.php');
        exit;
    }
}

// Redirection si non connecté (sauf pour login.php et logout.php)
$script_name = basename($_SERVER['PHP_SELF']);
if (!est_connecte() && $script_name != 'login.php' && $script_name != 'logout.php') {
    header('Location: login.php');
    exit;
}
?>