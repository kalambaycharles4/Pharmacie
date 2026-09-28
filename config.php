<?php
// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration base de données (compatible WAMP local et Cloud)
$db_host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$db_port = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
$db_name = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'pharmacie_db');
$db_user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '');

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

// Configuration application - LUBUMBASHI
define('WHATSAPP_NUMBER', '+243901726290');
define('NOM_PHARMACIE', 'Pharmacie Sorayah');
define('ADRESSE_PHARMACIE', '47, Avenue Baluba, Quartier Ndjanja, Kamalondo');
define('TELEPHONE_PHARMACIE', '+243 901 726 290');
define('EMAIL_PHARMACIE', 'contact@pharmacielubumbashi.cd');

// Connexion BDD
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
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