<?php
// Démarre la session pour pouvoir utiliser les variables utilisateur si nécessaire.
session_start();
// Indique que la réponse sera envoyée au format JSON pour être lue par JavaScript.
header('Content-Type: application/json');

// Paramètres de connexion à la base de données MySQL.
$host = 'localhost';
$dbname = 'pharmacie_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Active l'affichage des erreurs SQL pour faciliter le débogage.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Si un identifiant valide est fourni, on cherche le médicament correspondant.
    if ($id > 0) {
        // Prépare la requête pour récupérer le prix de vente du médicament.
        $stmt = $pdo->prepare("SELECT prix_vente FROM medicaments WHERE id = ?");
        $stmt->execute([$id]);
        $medicament = $stmt->fetch();

        // Si le médicament existe, on renvoie son prix au format JSON.
        if ($medicament) {
            echo json_encode([
                'success' => true,
                'prix_vente' => $medicament['prix_vente']
            ]);
        } else {
            // Si aucun médicament n'a été trouvé, on renvoie un échec.
            echo json_encode(['success' => false]);
        }
    } else {
        // Si l'identifiant est absent ou invalide, on renvoie aussi un échec.
        echo json_encode(['success' => false]);
    }
} catch(Exception $e) {
    // En cas d'erreur, on renvoie un message d'erreur au format JSON.
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>