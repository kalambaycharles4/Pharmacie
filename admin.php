<?php
// ============================================
// CONFIGURATION ET SÉCURITÉ
// ============================================
// Ce fichier centralise l'administration de la pharmacie :
// - gestion des médicaments, utilisateurs, fournisseurs et ventes,
// - contrôle d'accès pour l'administrateur,
// - chargement des données pour l'affichage du tableau de bord.
require_once 'config.php';
verifier_acces('admin');

// Cette fonction garantit qu'un fournisseur existe bien dans la base avant de l'utiliser.
function assurerFournisseurExiste($pdo, $nom) {
    $nom = trim($nom);
    if ($nom === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM fournisseurs WHERE nom = ?");
        $stmt->execute([$nom]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO fournisseurs (nom, email, telephone, adresse, notes) VALUES (?, NULL, NULL, NULL, NULL)");
            $stmt->execute([$nom]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS fournisseurs (
                id int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
                nom varchar(150) NOT NULL COMMENT 'Nom du fournisseur',
                email varchar(150) DEFAULT NULL COMMENT 'Email du fournisseur',
                telephone varchar(50) DEFAULT NULL COMMENT 'Téléphone du fournisseur',
                adresse text DEFAULT NULL COMMENT 'Adresse du fournisseur',
                notes text DEFAULT NULL COMMENT 'Informations complémentaires',
                created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
                PRIMARY KEY (id),
                KEY idx_nom (nom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des fournisseurs';");
            $stmt = $pdo->prepare("INSERT INTO fournisseurs (nom, email, telephone, adresse, notes) VALUES (?, NULL, NULL, NULL, NULL)");
            $stmt->execute([$nom]);
        } else {
            throw $e;
        }
    }
}

// ============================================
// TRAITEMENT DES FORMULAIRES (POST)
// ============================================
// Les formulaires de l'interface envoient leurs données ici.
// Chaque bloc vérifie une action spécifique et applique la modification demandée.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // -----------------------------------------
    // 1. AJOUTER UN MÉDICAMENT (avec date de création manuelle)
    // -----------------------------------------
    if (isset($_POST['action_ajouter_medicament'])) {
        try {
            // Récupération et sécurisation des données du formulaire
            $code = securiser($_POST['code']);
            $nom = securiser($_POST['nom']);
            $description = securiser($_POST['description']);
            $prix_achat = (float)$_POST['prix_achat'];
            $prix_vente = (float)$_POST['prix_vente'];
            $quantite = (int)$_POST['quantite'];
            $seuil = (int)$_POST['seuil_alerte'];
            $date_exp = $_POST['date_expiration'] ?: null;
            $date_creation = $_POST['date_creation'] ?: date('Y-m-d');
            $laboratoire = securiser($_POST['laboratoire']);
            if (!empty($laboratoire)) {
                assurerFournisseurExiste($pdo, $laboratoire);
            }
            
            // Gestion de la catégorie
            $categorie = securiser($_POST['categorie']);
            if ($categorie === 'nouvelle' && !empty($_POST['nouvelle_categorie'])) {
                $categorie = securiser($_POST['nouvelle_categorie']);
            }
            
            // Gestion de l'upload de la photo
            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photo = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
            }
            
            // Insertion dans la base de données
            $stmt = $pdo->prepare("INSERT INTO medicaments 
                (code, nom, description, prix_achat, prix_vente, quantite_stock, 
                 seuil_alerte, photo, date_expiration, date_creation, laboratoire, categorie, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $nom, $description, $prix_achat, $prix_vente, 
                           $quantite, $seuil, $photo, $date_exp, $date_creation, 
                           $laboratoire, $categorie, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "✅ Médicament ajouté avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=medicaments');
        exit;
    }
    
    // -----------------------------------------
    // 2. MODIFIER UN MÉDICAMENT
    // -----------------------------------------
    if (isset($_POST['action_modifier_medicament'])) {
        try {
            $id = (int)$_POST['id'];
            $code = securiser($_POST['code']);
            $nom = securiser($_POST['nom']);
            $description = securiser($_POST['description']);
            $prix_achat = (float)$_POST['prix_achat'];
            $prix_vente = (float)$_POST['prix_vente'];
            $seuil = (int)$_POST['seuil_alerte'];
            $date_exp = $_POST['date_expiration'] ?: null;
            $date_creation = $_POST['date_creation'] ?: date('Y-m-d');
            $laboratoire = securiser($_POST['laboratoire']);
            if (!empty($laboratoire)) {
                assurerFournisseurExiste($pdo, $laboratoire);
            }
            
            // Gestion de la catégorie
            $categorie = securiser($_POST['categorie']);
            if ($categorie === 'nouvelle' && !empty($_POST['nouvelle_categorie'])) {
                $categorie = securiser($_POST['nouvelle_categorie']);
            }
            
            // Récupération de l'ancienne photo
            $stmt = $pdo->prepare("SELECT photo FROM medicaments WHERE id = ?");
            $stmt->execute([$id]);
            $current_photo = $stmt->fetchColumn();
            $photo = $current_photo;
            
            // Si une nouvelle photo est uploadée
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photo = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
            }
            
            // Mise à jour
            $stmt = $pdo->prepare("UPDATE medicaments SET 
                code=?, nom=?, description=?, prix_achat=?, prix_vente=?, 
                seuil_alerte=?, date_expiration=?, date_creation=?, laboratoire=?, categorie=?, photo=? 
                WHERE id=?");
            $stmt->execute([$code, $nom, $description, $prix_achat, $prix_vente, 
                           $seuil, $date_exp, $date_creation, $laboratoire, $categorie, $photo, $id]);
            
            $_SESSION['success'] = "✅ Médicament modifié avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=medicaments');
        exit;
    }
    
    // -----------------------------------------
    // 3. SUPPRIMER UN MÉDICAMENT
    // -----------------------------------------
    if (isset($_POST['action_supprimer_medicament'])) {
        try {
            $id = (int)$_POST['supprimer_medicament'];
            $stmt = $pdo->prepare("DELETE FROM medicaments WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "✅ Médicament supprimé avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=medicaments');
        exit;
    }
    
    // -----------------------------------------
    // 4. AJOUTER UN UTILISATEUR
    // -----------------------------------------
    if (isset($_POST['action_ajouter_user'])) {
        try {
            $nom = securiser($_POST['nom']);
            $email = securiser($_POST['email']);
            $telephone = securiser($_POST['telephone']);
            $mot_de_passe = hash('sha256', $_POST['mot_de_passe']);
            $role = $_POST['role'];
            
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, telephone, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $email, $telephone, $mot_de_passe, $role]);
            
            $_SESSION['success'] = "✅ Utilisateur ajouté avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=utilisateurs');
        exit;
    }
    
    // -----------------------------------------
    // 5. MODIFIER UN UTILISATEUR
    // -----------------------------------------
    if (isset($_POST['action_modifier_user'])) {
        try {
            $id = (int)$_POST['user_id'];
            $nom = securiser($_POST['nom']);
            $email = securiser($_POST['email']);
            $telephone = securiser($_POST['telephone']);
            $role = $_POST['role'];
            $statut = $_POST['statut'];
            
            if (!empty($_POST['mot_de_passe'])) {
                $mot_de_passe = hash('sha256', $_POST['mot_de_passe']);
                $stmt = $pdo->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, mot_de_passe=?, role=?, statut=? WHERE id=?");
                $stmt->execute([$nom, $email, $telephone, $mot_de_passe, $role, $statut, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, role=?, statut=? WHERE id=?");
                $stmt->execute([$nom, $email, $telephone, $role, $statut, $id]);
            }
            
            $_SESSION['success'] = "✅ Utilisateur modifié avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=utilisateurs');
        exit;
    }
    
    // -----------------------------------------
    // 6. SUPPRIMER UN UTILISATEUR
    // -----------------------------------------
    if (isset($_POST['action_supprimer_user'])) {
        try {
            $id = (int)$_POST['supprimer_user'];
            if ($id == $_SESSION['user_id']) {
                throw new Exception("Vous ne pouvez pas supprimer votre propre compte");
            }
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "✅ Utilisateur supprimé avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ " . $e->getMessage();
        }
        header('Location: admin.php?section=utilisateurs');
        exit;
    }

    // -----------------------------------------
    // 7. AJOUTER UN FOURNISSEUR
    // -----------------------------------------
    // Cette action enregistre un nouveau fournisseur dans la base.
    if (isset($_POST['action_ajouter_fournisseur'])) {
        try {
            $nom = securiser($_POST['nom']);
            $email = securiser($_POST['email']);
            $telephone = securiser($_POST['telephone']);
            $adresse = securiser($_POST['adresse']);
            $notes = securiser($_POST['notes']);

            $stmt = $pdo->prepare("INSERT INTO fournisseurs (nom, email, telephone, adresse, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $email, $telephone, $adresse, $notes]);

            $_SESSION['success'] = "✅ Fournisseur ajouté avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=fournisseurs');
        exit;
    }

    // -----------------------------------------
    // 8. MODIFIER UN FOURNISSEUR
    // -----------------------------------------
    // Cette action met à jour les informations modifiées d'un fournisseur existant.
    if (isset($_POST['action_modifier_fournisseur'])) {
        try {
            $id = (int)$_POST['fournisseur_id'];
            $nom = securiser($_POST['nom']);
            $email = securiser($_POST['email']);
            $telephone = securiser($_POST['telephone']);
            $adresse = securiser($_POST['adresse']);
            $notes = securiser($_POST['notes']);

            $stmt = $pdo->prepare("UPDATE fournisseurs SET nom=?, email=?, telephone=?, adresse=?, notes=? WHERE id=?");
            $stmt->execute([$nom, $email, $telephone, $adresse, $notes, $id]);

            $_SESSION['success'] = "✅ Fournisseur modifié avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=fournisseurs');
        exit;
    }

    // -----------------------------------------
    // 9. SUPPRIMER UN FOURNISSEUR
    // -----------------------------------------
    // Cette action supprime définitivement un fournisseur de la base.
    if (isset($_POST['action_supprimer_fournisseur'])) {
        try {
            $id = (int)$_POST['supprimer_fournisseur'];
            $stmt = $pdo->prepare("DELETE FROM fournisseurs WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "✅ Fournisseur supprimé avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=fournisseurs');
        exit;
    }

    // -----------------------------------------
    // 10. ENTRÉE DE STOCK
    // -----------------------------------------
    // Cette action augmente la quantité en stock d'un médicament et enregistre l'achat correspondant.
    if (isset($_POST['action_entree_stock'])) {
        try {
            $medicament_id = (int)$_POST['medicament_id'];
            $quantite = (int)$_POST['quantite'];
            $fournisseur = securiser($_POST['fournisseur']);
            if (!empty($fournisseur)) {
                assurerFournisseurExiste($pdo, $fournisseur);
            }
            
            $stmt = $pdo->prepare("SELECT prix_achat FROM medicaments WHERE id = ?");
            $stmt->execute([$medicament_id]);
            $prix_achat = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE medicaments SET quantite_stock = quantite_stock + ? WHERE id = ?");
            $stmt->execute([$quantite, $medicament_id]);
            
            $total = $quantite * $prix_achat;
            $stmt = $pdo->prepare("INSERT INTO achats (medicament_id, quantite, prix_achat_unitaire, total, fournisseur, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$medicament_id, $quantite, $prix_achat, $total, $fournisseur, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "✅ Stock mis à jour avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ Erreur: " . $e->getMessage();
        }
        header('Location: admin.php?section=stock');
        exit;
    }
    
    // -----------------------------------------
    // 11. CRÉER UNE FACTURE
    // -----------------------------------------
    if (isset($_POST['action_creer_facture_admin'])) {
        $items = json_decode($_POST['items'], true);
        $client_nom = securiser($_POST['client_nom'] ?: 'Client');
        $client_telephone = securiser($_POST['client_telephone'] ?? '');
        $client_adresse = securiser($_POST['client_adresse'] ?? '');
        $vendeur_id = $_SESSION['user_id'];
        $vendeur_nom = $_SESSION['user_nom'];
        
        if (empty($items)) {
            $_SESSION['error'] = "Panier vide";
            header('Location: admin.php?section=ventes');
            exit;
        }
        
        $pdo->beginTransaction();
        $numero_facture = generer_numero_facture();
        $total_facture = 0;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO factures (numero_facture, client_nom, client_telephone, client_adresse, total_ht, total_ttc, vendeur_id, vendeur_nom) 
                                  VALUES (?, ?, ?, ?, 0, 0, ?, ?)");
            $stmt->execute([$numero_facture, $client_nom, $client_telephone, $client_adresse, $vendeur_id, $vendeur_nom]);
            $facture_id = $pdo->lastInsertId();
            
            foreach ($items as $item) {
                $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ?");
                $stmt->execute([$item['id']]);
                $medicament = $stmt->fetch();
                
                if ($medicament['quantite_stock'] < $item['quantite']) {
                    throw new Exception("Stock insuffisant pour " . $medicament['nom']);
                }
                
                $new_stock = $medicament['quantite_stock'] - $item['quantite'];
                $stmt = $pdo->prepare("UPDATE medicaments SET quantite_stock = ? WHERE id = ?");
                $stmt->execute([$new_stock, $item['id']]);
                
                $total = $item['quantite'] * $medicament['prix_vente'];
                $total_facture += $total;
                
                $stmt = $pdo->prepare("INSERT INTO ventes (facture_id, medicament_id, medicament_nom, quantite, prix_unitaire, total, vendeur_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$facture_id, $item['id'], $medicament['nom'], $item['quantite'], 
                               $medicament['prix_vente'], $total, $vendeur_id]);
            }
            
            $stmt = $pdo->prepare("UPDATE factures SET total_ht = ?, total_ttc = ? WHERE id = ?");
            $stmt->execute([$total_facture, $total_facture, $facture_id]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "💰 Facture créée ! N° $numero_facture";
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "❌ " . $e->getMessage();
        }
        header('Location: admin.php?section=factures');
        exit;
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
// ============================================
// Ici, on charge les informations nécessaires pour remplir les tableaux,
// les formulaires et le tableau de bord de l'administration.

// Vérifier si la colonne date_creation existe, sinon utiliser created_at
try {
    // Tenter de récupérer avec date_creation
    $medicaments = $pdo->query("SELECT * FROM medicaments ORDER BY nom ASC")->fetchAll();
} catch(PDOException $e) {
    // Si la colonne n'existe pas, utiliser created_at
    $medicaments = $pdo->query("SELECT *, created_at as date_creation FROM medicaments ORDER BY nom ASC")->fetchAll();
}

// Liste des médicaments en stock
$medicaments_stock = $pdo->query("SELECT id, nom, code, prix_vente, quantite_stock, photo 
                                  FROM medicaments WHERE quantite_stock > 0 ORDER BY nom")->fetchAll();

// Récupérer toutes les catégories existantes
$categories_existantes = $pdo->query("SELECT DISTINCT categorie FROM medicaments 
                                      WHERE categorie IS NOT NULL AND categorie != '' 
                                      ORDER BY categorie")->fetchAll();

// Liste des utilisateurs
$utilisateurs = $pdo->query("SELECT * FROM utilisateurs ORDER BY created_at DESC")->fetchAll();

// Liste des fournisseurs
try {
    $fournisseurs = $pdo->query("SELECT * FROM fournisseurs ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() === '42S02') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS fournisseurs (
            id int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
            nom varchar(150) NOT NULL COMMENT 'Nom du fournisseur',
            email varchar(150) DEFAULT NULL COMMENT 'Email du fournisseur',
            telephone varchar(50) DEFAULT NULL COMMENT 'Téléphone du fournisseur',
            adresse text DEFAULT NULL COMMENT 'Adresse du fournisseur',
            notes text DEFAULT NULL COMMENT 'Informations complémentaires',
            created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
            PRIMARY KEY (id),
            KEY idx_nom (nom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des fournisseurs';");
        $fournisseurs = [];
    } else {
        throw $e;
    }
}

// Liste des factures
$factures = $pdo->query("SELECT f.*, (SELECT COUNT(*) FROM ventes WHERE facture_id = f.id) as nb_articles 
                         FROM factures f ORDER BY f.date_facture DESC LIMIT 50")->fetchAll();

// ============================================
// STATISTIQUES POUR LE DASHBOARD
// ============================================
// Ces variables servent à afficher les chiffres du tableau de bord :
// nombre de médicaments, stock total, chiffre d'affaires, utilisateurs et alertes.
$stats = [
    'medicaments' => $pdo->query("SELECT COUNT(*) FROM medicaments")->fetchColumn() ?: 0,
    'stock_total' => $pdo->query("SELECT SUM(quantite_stock) FROM medicaments")->fetchColumn() ?: 0,
    'ca_total' => $pdo->query("SELECT SUM(total_ttc) FROM factures")->fetchColumn() ?: 0,
    'ca_jour' => $pdo->query("SELECT SUM(total_ttc) FROM factures WHERE DATE(date_facture) = CURDATE()")->fetchColumn() ?: 0,
    'utilisateurs' => $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn() ?: 0,
    'alertes' => $pdo->query("SELECT COUNT(*) FROM medicaments WHERE quantite_stock <= seuil_alerte")->fetchColumn() ?: 0
];

// Ventes par vendeur aujourd'hui
$ventes_par_vendeur_aujourdhui = $pdo->query("
    SELECT 
        u.id, u.nom as vendeur_nom,
        COALESCE(SUM(f.total_ttc), 0) as ca_aujourdhui,
        COUNT(DISTINCT f.id) as nombre_factures,
        (SELECT COUNT(*) FROM ventes v WHERE v.vendeur_id = u.id AND DATE(v.date_vente) = CURDATE()) as articles_vendus
    FROM utilisateurs u
    LEFT JOIN factures f ON f.vendeur_id = u.id AND DATE(f.date_facture) = CURDATE()
    WHERE u.role = 'vendeur' OR u.role = 'admin'
    GROUP BY u.id, u.nom
    ORDER BY ca_aujourdhui DESC
")->fetchAll();

// Clients existants pour l'autocomplétion
$clients = $pdo->query("SELECT DISTINCT client_nom, client_telephone, client_adresse 
                        FROM factures WHERE client_nom IS NOT NULL 
                        ORDER BY date_facture DESC LIMIT 30")->fetchAll();

// Déterminer la section active
$page_active = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupérer un médicament pour modification
$medicament_edit = null;
if ($action == 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ?");
    $stmt->execute([$edit_id]);
    $medicament_edit = $stmt->fetch();
    // Si date_creation est NULL, utiliser created_at
    if ($medicament_edit && !isset($medicament_edit['date_creation'])) {
        $medicament_edit['date_creation'] = $medicament_edit['created_at'];
    }
}

// Récupérer une facture spécifique
$facture_detail = null;
$facture_articles = [];
$show_modal = false;
if (isset($_GET['view_facture']) && is_numeric($_GET['view_facture'])) {
    $facture_id = (int)$_GET['view_facture'];
    $stmt = $pdo->prepare("SELECT * FROM factures WHERE id = ?");
    $stmt->execute([$facture_id]);
    $facture_detail = $stmt->fetch();
    if ($facture_detail) {
        $stmt = $pdo->prepare("SELECT * FROM ventes WHERE facture_id = ?");
        $stmt->execute([$facture_id]);
        $facture_articles = $stmt->fetchAll();
        $show_modal = true;
    }
}

// -----------------------------------------
// 11. SUPPRIMER / MODIFIER UNE FACTURE
// -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SUPPRIMER UNE FACTURE (et ses lignes de ventes)
    if (isset($_POST['action_supprimer_facture'])) {
        try {
            $id = (int)$_POST['supprimer_facture'];
            // Supprimer d'abord les lignes liées dans ventes
            $stmt = $pdo->prepare("DELETE FROM ventes WHERE facture_id = ?");
            $stmt->execute([$id]);

            // Puis supprimer la facture
            $stmt = $pdo->prepare("DELETE FROM factures WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = "✅ Facture supprimée avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ " . $e->getMessage();
        }
        header('Location: admin.php?section=factures');
        exit;
    }

    // MODIFIER UNE FACTURE (champs simples: client, téléphone, adresse, date)
    if (isset($_POST['action_modifier_facture'])) {
        try {
            $id = (int)$_POST['facture_id'];
            $client_nom = securiser($_POST['client_nom'] ?? 'Client');
            $client_telephone = securiser($_POST['client_telephone'] ?? '');
            $client_adresse = securiser($_POST['client_adresse'] ?? '');
            $date_facture = $_POST['date_facture'] ?? null;

            $stmt = $pdo->prepare("UPDATE factures SET client_nom = ?, client_telephone = ?, client_adresse = ?, date_facture = ? WHERE id = ?");
            $stmt->execute([$client_nom, $client_telephone, $client_adresse, $date_facture, $id]);

            $_SESSION['success'] = "✅ Facture modifiée avec succès !";
        } catch(Exception $e) {
            $_SESSION['error'] = "❌ " . $e->getMessage();
        }
        header('Location: admin.php?section=factures');
        exit;
    }
}

?>

<!-- Le reste du fichier construit l'interface HTML de l'administration :
     barre latérale, tableau de bord, formulaires et tableaux de données. -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pharmacie </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 25px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .logo { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .logo h2 { font-size: 22px; color: #0f172a; }
        .logo span { color: #2563eb; }
        .user-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .badge { background: #dc2626; color: white; padding: 3px 10px; border-radius: 20px; font-size: 10px; display: inline-block; }
        .nav-links { list-style: none; }
        .nav-links li { margin-bottom: 5px; }
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #475569;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            background: #eff6ff;
            color: #2563eb;
        }
        
        .main-content { flex: 1; margin-left: 280px; padding: 25px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: #eff6ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #2563eb;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #0f172a; }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        tr:hover td { background: #fafcff; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; color: #334155; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-control:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .modal-body { padding: 25px; }
        .close-btn { font-size: 24px; cursor: pointer; color: #94a3b8; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        
        .vente-container {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
        }
        .panier-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .btn-qty {
            background: none;
            border: 1px solid #e2e8f0;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-remove {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-size: 16px;
        }
        
        .client-search { position: relative; }
        .client-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
            display: none;
        }
        .client-suggestions div {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }
        .client-suggestions div:hover { background: #eff6ff; }
        
        .photo-preview {
            margin-top: 10px;
            max-width: 120px;
        }
        .photo-preview img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .categorie-badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; }
            .vente-container, .dashboard-sections { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
        
        @media print {
            .sidebar, .header, .btn, .modal, .nav-links, .close-btn { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>🏥 <span>Pharmacie</span></h2>
            <p style="font-size:12px; color:#64748b;">Administrateur - Lubumbashi</p>
        </div>
        
        <div class="user-info">
            <h4><i class="fas fa-user-shield"></i> <?= securiser($_SESSION['user_nom']) ?></h4>
            <p><?= securiser($_SESSION['user_email']) ?></p>
            <span class="badge">Administrateur</span>
        </div>
        
        <ul class="nav-links">
            <li><a href="?section=dashboard" class="<?= $page_active == 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="?section=ventes" class="<?= $page_active == 'ventes' ? 'active' : '' ?>"><i class="fas fa-cash-register"></i> Ventes</a></li>
            <li><a href="?section=utilisateurs" class="<?= $page_active == 'utilisateurs' ? 'active' : '' ?>"><i class="fas fa-users"></i> Utilisateurs</a></li>
            <li><a href="?section=fournisseurs" class="<?= $page_active == 'fournisseurs' ? 'active' : '' ?>"><i class="fas fa-truck"></i> Fournisseurs</a></li>
            <li><a href="?section=medicaments" class="<?= $page_active == 'medicaments' ? 'active' : '' ?>"><i class="fas fa-pills"></i> Médicaments</a></li>
            <li><a href="?section=factures" class="<?= $page_active == 'factures' ? 'active' : '' ?>"><i class="fas fa-file-invoice"></i> Factures</a></li>
            <li><a href="?section=stock" class="<?= $page_active == 'stock' ? 'active' : '' ?>"><i class="fas fa-boxes"></i> Gestion stock</a></li>
            <li><a href="?section=rapports" class="<?= $page_active == 'rapports' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Rapports</a></li>
        </ul>
        
        <div style="margin-top: 30px;">
            <a href="logout.php" class="btn btn-danger" style="width:100%; justify-content:center;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>
    
    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- DASHBOARD -->
        <?php if ($page_active == 'dashboard'): ?>
            <div class="header">
                <h1><i class="fas fa-chart-line"></i> Tableau de bord</h1>
                <div style="background: white; padding: 8px 16px; border-radius: 20px;">
                    <i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i') ?>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <div><h3 style="font-size:13px; color:#64748b;">Médicaments</h3><div class="stat-number"><?= $stats['medicaments'] ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                    <div><h3 style="font-size:13px; color:#64748b;">Stock total</h3><div class="stat-number"><?= $stats['stock_total'] ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div><h3 style="font-size:13px; color:#64748b;">CA Total</h3><div class="stat-number"><?= format_prix($stats['ca_total']) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div><h3 style="font-size:13px; color:#64748b;">Utilisateurs</h3><div class="stat-number"><?= $stats['utilisateurs'] ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                    <div><h3 style="font-size:13px; color:#64748b;">CA Aujourd'hui</h3><div class="stat-number"><?= format_prix($stats['ca_jour']) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div><h3 style="font-size:13px; color:#64748b;">Alertes stock</h3><div class="stat-number"><?= $stats['alertes'] ?></div></div>
                </div>
            </div>
            
            <div class="card">
                <div class="header" style="margin-bottom: 15px; padding-bottom: 0;">
                    <h3><i class="fas fa-chart-simple"></i> Ventes par vendeur - Aujourd'hui</h3>
                    <span class="badge" style="background:#2563eb;"><?= date('d/m/Y') ?></span>
                </div>
                <?php if (count($ventes_par_vendeur_aujourdhui) > 0): ?>
                    <?php foreach ($ventes_par_vendeur_aujourdhui as $vendeur): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 10px;">
                        <div>
                            <h4><i class="fas fa-user-circle"></i> <?= securiser($vendeur['vendeur_nom']) ?></h4>
                            <p style="font-size:12px; color:#64748b;"><i class="fas fa-receipt"></i> <?= $vendeur['nombre_factures'] ?> facture(s) | <i class="fas fa-box"></i> <?= $vendeur['articles_vendus'] ?? 0 ?> article(s)</p>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: #059669;"><?= format_prix($vendeur['ca_aujourdhui']) ?></div>
                            <small>chiffre d'affaires</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#64748b; padding:30px;">Aucune vente aujourd'hui</p>
                <?php endif; ?>
            </div>

        <!-- VENTES -->
        <?php elseif ($page_active == 'ventes'): ?>
            <div class="header">
                <h1><i class="fas fa-cash-register" style="color:#2563eb;"></i> Vente (Admin)</h1>
                <button onclick="viderPanier()" class="btn btn-outline btn-sm" id="viderPanierBtn" style="display:none;">
                    <i class="fas fa-trash-alt"></i> Vider le panier
                </button>
            </div>
            
            <div class="vente-container">
                <div class="card">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-shopping-cart"></i> Panier d'achat</h3>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <select id="medicamentSelect" class="form-control" style="flex:1;">
                            <option value="">-- Sélectionner un médicament --</option>
                            <?php foreach ($medicaments_stock as $med): ?>
                            <option value="<?= $med['id'] ?>" data-nom="<?= securiser($med['nom']) ?>" data-prix="<?= $med['prix_vente'] ?>" data-stock="<?= $med['quantite_stock'] ?>">
                                <?= securiser($med['nom']) ?> - <?= format_prix($med['prix_vente']) ?> (Stock: <?= $med['quantite_stock'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="quantiteInput" min="1" value="1" class="form-control" style="width: 80px;">
                        <button id="ajouterPanierBtn" class="btn btn-primary"><i class="fas fa-cart-plus"></i> Ajouter</button>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:600; color:#64748b;">
                        <span style="flex:2;">Produit</span>
                        <span style="flex:1; text-align:center;">Quantité</span>
                        <span style="flex:1; text-align:right;">Total</span>
                        <span></span>
                    </div>
                    
                    <div id="panierVide" style="text-align: center; padding: 40px;">
                        <i class="fas fa-shopping-cart" style="font-size:48px; color:#cbd5e1; margin-bottom:10px;"></i>
                        <p style="color:#64748b;">Votre panier est vide</p>
                    </div>
                    <div id="panierItems" style="display: none;"></div>
                    <div id="panierTotal" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f1f5f9; text-align: right;">
                        <span style="font-size: 16px; font-weight: 600;">Total TTC : </span>
                        <span id="totalMontant" style="font-size: 22px; font-weight: 700; color: #059669;">0 FC</span>
                    </div>
                </div>
                
                <div class="card">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-user"></i> Informations client</h3>
                    <form method="POST" id="factureForm">
                        <input type="hidden" name="items" id="itemsInput">
                        
                        <div class="form-group client-search">
                            <label>Nom du client *</label>
                            <input type="text" name="client_nom" id="clientNom" class="form-control" required placeholder="Rechercher ou saisir..." autocomplete="off">
                            <div id="clientSuggestions" class="client-suggestions"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Téléphone</label>
                            <div style="display:flex;">
                                <span style="background:#f1f5f9; padding:10px 12px; border:1px solid #e2e8f0; border-right:none; border-radius:8px 0 0 8px;">+243</span>
                                <input type="text" name="client_telephone" id="clientTelephone" class="form-control" style="border-radius:0 8px 8px 0;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Adresse</label>
                            <input type="text" name="client_adresse" id="clientAdresse" class="form-control" placeholder="Lubumbashi, Golf">
                        </div>
                        
                        <button type="submit" name="action_creer_facture_admin" id="validerVenteBtn" class="btn btn-success" style="width:100%; padding: 14px;">
                            <i class="fas fa-check-circle"></i> Créer la facture
                        </button>
                    </form>
                </div>
            </div>

        <!-- UTILISATEURS -->
        <?php elseif ($page_active == 'utilisateurs'): ?>
            <div class="header">
                <h1><i class="fas fa-users"></i> Gestion des utilisateurs</h1>
                <button onclick="openModal('modalAjoutUser')" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nouvel utilisateur</button>
            </div>
            
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $user): ?>
                            <tr>
                                <td><?= securiser($user['nom']) ?></td>
                                <td><?= securiser($user['email']) ?></td>
                                <td><?= securiser($user['telephone']) ?></td>
                                <td><span class="badge" style="background: <?= $user['role'] == 'admin' ? '#dc2626' : '#059669' ?>;"><?= $user['role'] ?></span></td>
                                <td><span style="color: <?= $user['statut'] == 'actif' ? '#059669' : '#dc2626' ?>;"><?= $user['statut'] ?></span></td>
                                <td>
                                    <button onclick="editUser(<?= $user['id'] ?>, '<?= addslashes($user['nom']) ?>', '<?= $user['email'] ?>', '<?= $user['telephone'] ?>', '<?= $user['role'] ?>', '<?= $user['statut'] ?>')" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ?');">
                                        <input type="hidden" name="supprimer_user" value="<?= $user['id'] ?>">
                                        <button type="submit" name="action_supprimer_user" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
			
        <!-- FOURNISSEURS -->
        <?php elseif ($page_active == 'fournisseurs'): ?>
            <div class="header">
                <h1><i class="fas fa-truck"></i> Gestion des fournisseurs</h1>
                <button onclick="openModal('modalAjoutFournisseur')" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nouveau fournisseur</button>
            </div>
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Adresse</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fournisseurs as $four): ?>
                            <tr>
                                <td><?= securiser($four['nom']) ?></td>
                                <td><?= securiser($four['email']) ?></td>
                                <td><?= securiser($four['telephone']) ?></td>
                                <td><?= nl2br(securiser($four['adresse'])) ?></td>
                                <td>
                                    <button onclick="editFournisseur(<?= $four['id'] ?>, '<?= addslashes($four['nom']) ?>', '<?= addslashes($four['email']) ?>', '<?= addslashes($four['telephone']) ?>', '<?= addslashes($four['adresse']) ?>', '<?= addslashes($four['notes']) ?>')" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce fournisseur ?');">
                                        <input type="hidden" name="supprimer_fournisseur" value="<?= $four['id'] ?>">
                                        <button type="submit" name="action_supprimer_fournisseur" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- MÉDICAMENTS -->
        <?php elseif ($page_active == 'medicaments'): ?>
            <div class="header">
                <h1><i class="fas fa-pills"></i> Gestion des médicaments</h1>
                <a href="?section=medicaments&action=ajouter" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nouveau médicament</a>
            </div>
            
            <!-- FORMULAIRE D'AJOUT -->
            <?php if ($action == 'ajouter'): ?>
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-plus-circle"></i> Ajouter un médicament</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group"><label>Code *</label><input type="text" name="code" class="form-control" required placeholder="MED-001"></div>
                        <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required placeholder="Paracétamol"></div>
                    </div>
                    
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2" placeholder="Description..."></textarea></div>
                     
                    <div class="form-row">
                        <div class="form-group">
                            <label>Catégorie</label>
                            <select name="categorie" id="categorieSelect" class="form-control" onchange="toggleNouvelleCategorie()">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories_existantes as $cat): ?>
                                <option value="<?= securiser($cat['categorie']) ?>"><?= securiser($cat['categorie']) ?></option>
                                <?php endforeach; ?>
                                <option value="nouvelle">+ Créer une nouvelle catégorie</option>
                            </select>
                        </div>
                        <div class="form-group" id="nouvelleCategorieDiv" style="display: none;">
                            <label>Nouvelle catégorie</label>
                            <input type="text" name="nouvelle_categorie" class="form-control" placeholder="Ex: Antiviral...">
                        </div>
                        <div class="form-group">
                            <label>Fournisseur</label>
                            <select name="laboratoire" class="form-control">
                                <option value="">-- Aucun fournisseur --</option>
                                <?php foreach ($fournisseurs as $four): ?>
                                <option value="<?= securiser($four['nom']) ?>"><?= securiser($four['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                      
                    <div class="form-row">
                        <div class="form-group"><label>Prix d'achat (FC) *</label><input type="number" name="prix_achat" class="form-control" required placeholder="5000"></div>
                        <div class="form-group"><label>Prix de vente (FC) *</label><input type="number" name="prix_vente" class="form-control" required placeholder="7500"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label>Quantité initiale</label><input type="number" name="quantite" class="form-control" value="0"></div>
                        <div class="form-group"><label>Seuil d'alerte</label><input type="number" name="seuil_alerte" class="form-control" value="10"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de création *</label>
                            <input type="date" name="date_creation" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date d'expiration</label>
                            <input type="date" name="date_expiration" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Photo</label>
                        <input type="file" name="photo" accept="image/*" class="form-control">
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <a href="?section=medicaments" class="btn btn-outline">Annuler</a>
                        <button type="submit" name="action_ajouter_medicament" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
            
            <script>
                function toggleNouvelleCategorie() {
                    const select = document.getElementById('categorieSelect');
                    const divNouvelle = document.getElementById('nouvelleCategorieDiv');
                    divNouvelle.style.display = select.value === 'nouvelle' ? 'block' : 'none';
                }
            </script>
            
            <!-- FORMULAIRE DE MODIFICATION -->
            <?php elseif ($action == 'edit' && $medicament_edit): ?>
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-edit"></i> Modifier le médicament</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $medicament_edit['id'] ?>">
                    
                    <div class="form-row">
                        <div class="form-group"><label>Code</label><input type="text" name="code" class="form-control" value="<?= securiser($medicament_edit['code']) ?>" required></div>
                        <div class="form-group"><label>Nom</label><input type="text" name="nom" class="form-control" value="<?= securiser($medicament_edit['nom']) ?>" required></div>
                    </div>
                    
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"><?= securiser($medicament_edit['description']) ?></textarea></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Catégorie</label>
                            <select name="categorie" id="categorieEditSelect" class="form-control" onchange="toggleNouvelleCategorieEdit()">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories_existantes as $cat): ?>
                                <option value="<?= securiser($cat['categorie']) ?>" <?= ($medicament_edit['categorie'] == $cat['categorie']) ? 'selected' : '' ?>><?= securiser($cat['categorie']) ?></option>
                                <?php endforeach; ?>
                                <option value="nouvelle">+ Créer une nouvelle catégorie</option>
                            </select>
                        </div>
                        <div class="form-group" id="nouvelleCategorieEditDiv" style="display: none;">
                            <label>Nouvelle catégorie</label>
                            <input type="text" name="nouvelle_categorie" class="form-control" placeholder="Ex: Antiviral...">
                        </div>
                        <div class="form-group">
                            <label>Fournisseur</label>
                            <select name="laboratoire" class="form-control">
                                <option value="">-- Aucun fournisseur --</option>
                                <?php foreach ($fournisseurs as $four): ?>
                                <option value="<?= securiser($four['nom']) ?>" <?= $medicament_edit['laboratoire'] == $four['nom'] ? 'selected' : '' ?>><?= securiser($four['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label>Prix d'achat (FC)</label><input type="number" name="prix_achat" class="form-control" value="<?= $medicament_edit['prix_achat'] ?>" required></div>
                        <div class="form-group"><label>Prix de vente (FC)</label><input type="number" name="prix_vente" class="form-control" value="<?= $medicament_edit['prix_vente'] ?>" required></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label>Seuil d'alerte</label><input type="number" name="seuil_alerte" class="form-control" value="<?= $medicament_edit['seuil_alerte'] ?>"></div>
                        <div class="form-group"><label>Date d'expiration</label><input type="date" name="date_expiration" class="form-control" value="<?= $medicament_edit['date_expiration'] ?>"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de création</label>
                            <input type="date" name="date_creation" class="form-control" value="<?= $medicament_edit['date_creation'] ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Photo actuelle</label>
                        <?php if ($medicament_edit['photo'] && file_exists('uploads/'.$medicament_edit['photo'])): ?>
                            <div class="photo-preview"><img src="uploads/<?= $medicament_edit['photo'] ?>" alt=""></div>
                        <?php else: ?>
                            <p style="color:#64748b;">Aucune photo</p>
                        <?php endif; ?>
                        <input type="file" name="photo" accept="image/*" class="form-control" style="margin-top:10px;">
                        <small>Laissez vide pour conserver la photo</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <a href="?section=medicaments" class="btn btn-outline">Annuler</a>
                        <button type="submit" name="action_modifier_medicament" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
            
            <script>
                function toggleNouvelleCategorieEdit() {
                    const select = document.getElementById('categorieEditSelect');
                    const divNouvelle = document.getElementById('nouvelleCategorieEditDiv');
                    divNouvelle.style.display = select.value === 'nouvelle' ? 'block' : 'none';
                }
            </script>
            
            <!-- LISTE DES MÉDICAMENTS -->
            <?php else: ?>
            <div class="card">
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="flex:1; display: flex; align-items: center; gap: 10px; background:#f8fafc; padding: 0 15px; border-radius: 8px; border:1px solid #e2e8f0;">
                        <i class="fas fa-search" style="color:#94a3b8;"></i>
                        <input type="text" id="searchMedicament" placeholder="Rechercher..." style="flex:1; padding: 12px 0; border: none; background: transparent; outline: none;">
                    </div>
                    <select id="filterCategorie" class="form-control" style="width: 200px;">
                        <option value="">Toutes catégories</option>
                        <?php foreach ($categories_existantes as $cat): ?>
                        <option value="<?= securiser($cat['categorie']) ?>"><?= securiser($cat['categorie']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="table-container">
                    <table id="medicamentsTable">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Prix vente</th>
                                <th>Stock</th>
                                <th>Date création</th>
                                <th>Date expiration</th>
                                <th>Fournisseur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicaments as $med): ?>
                            <tr>
                                <td>
                                    <?php if ($med['photo'] && file_exists('uploads/'.$med['photo'])): ?>
                                        <img src="uploads/<?= $med['photo'] ?>" style="width:40px; height:40px; border-radius:8px; object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:40px; height:40px; background:#f1f5f9; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                                            <i class="fas fa-pills" style="color:#94a3b8;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= securiser($med['code']) ?></td>
                                <td><strong><?= securiser($med['nom']) ?></strong></td>
                                <td><span class="categorie-badge"><?= securiser($med['categorie'] ?: 'Non catégorisé') ?></span></td>
                                <td><?= format_prix($med['prix_vente']) ?></td>
                                <td style="color: <?= $med['quantite_stock'] <= $med['seuil_alerte'] ? '#dc2626' : '#059669' ?>;"><?= $med['quantite_stock'] ?></td>
                                <td><small><?= date('d/m/Y', strtotime($med['date_creation'] ?? $med['created_at'])) ?></small></td>
                                <td>
                                    <?php if ($med['date_expiration']): ?>
                                        <small style="color: <?= strtotime($med['date_expiration']) < time() ? '#dc2626' : '#64748b' ?>;">
                                            <?= date('d/m/Y', strtotime($med['date_expiration'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <small style="color:#94a3b8;">-</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= securiser($med['laboratoire'] ?: '—') ?></td>
                                <td>
                                    <a href="?section=medicaments&action=edit&id=<?= $med['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ?');">
                                        <input type="hidden" name="supprimer_medicament" value="<?= $med['id'] ?>">
                                        <button type="submit" name="action_supprimer_medicament" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <!-- FACTURES -->
        <?php elseif ($page_active == 'factures'): ?>
            <div class="header">
                <h1><i class="fas fa-file-invoice"></i> Toutes les factures</h1>
                <a href="?section=ventes" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nouvelle facture</a>
            </div>

            <?php if ($action == 'edit' && $edit_id > 0):
                $stmt = $pdo->prepare("SELECT * FROM factures WHERE id = ?");
                $stmt->execute([$edit_id]);
                $facture_edit = $stmt->fetch();
            ?>
            <div class="card">
                <h3><i class="fas fa-edit"></i> Modifier la facture</h3>
                <?php if ($facture_edit): ?>
                <form method="POST">
                    <input type="hidden" name="facture_id" value="<?= $facture_edit['id'] ?>">
                    <div class="form-group"><label>Nom du client</label><input type="text" name="client_nom" class="form-control" value="<?= securiser($facture_edit['client_nom']) ?>" required></div>
                    <div class="form-group"><label>Téléphone</label><input type="text" name="client_telephone" class="form-control" value="<?= securiser($facture_edit['client_telephone']) ?>"></div>
                    <div class="form-group"><label>Adresse</label><input type="text" name="client_adresse" class="form-control" value="<?= securiser($facture_edit['client_adresse']) ?>"></div>
                    <div class="form-group"><label>Date facture</label><input type="datetime-local" name="date_facture" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($facture_edit['date_facture'])) ?>"></div>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <a href="?section=factures" class="btn btn-outline">Annuler</a>
                        <button type="submit" name="action_modifier_facture" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
                <?php else: ?>
                    <p style="color:#64748b;">Facture introuvable.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

                <div class="card">
                    <?php if (count($factures) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th>N° Facture</th><th>Date</th><th>Client</th><th>Vendeur</th><th>Articles</th><th>Total</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($factures as $facture): ?>
                            <tr>
                                <td><strong><?= securiser($facture['numero_facture']) ?></strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($facture['date_facture'])) ?></td>
                                <td><?= securiser($facture['client_nom']) ?></td>
                                <td><?= securiser($facture['vendeur_nom']) ?></td>
                                <td><?= $facture['nb_articles'] ?></td>
                                <td><strong style="color:#059669;"><?= format_prix($facture['total_ttc']) ?></strong></td>
                                <td>
                                    <a href="?section=factures&view_facture=<?= $facture['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Voir</a>
                                    <a href="?section=factures&action=edit&id=<?= $facture['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Modifier</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette facture ?');">
                                        <input type="hidden" name="supprimer_facture" value="<?= $facture['id'] ?>">
                                        <button type="submit" name="action_supprimer_facture" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align:center; color:#64748b; padding:40px;">Aucune facture</p>
                <?php endif; ?>
            </div>
            
        <!-- STOCK -->
        <?php elseif ($page_active == 'stock'): ?>
            <div class="header">
                <h1><i class="fas fa-boxes"></i> Gestion du stock</h1>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-truck-loading"></i> Entrée de stock</h3>
                    <form method="POST">
                        <div class="form-group"><label>Médicament</label><select name="medicament_id" class="form-control" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($medicaments as $med): ?>
                            <option value="<?= $med['id'] ?>"><?= securiser($med['nom']) ?> - Stock: <?= $med['quantite_stock'] ?></option>
                            <?php endforeach; ?>
                        </select></div>
                        <div class="form-group"><label>Quantité</label><input type="number" name="quantite" min="1" class="form-control" required></div>
                        <div class="form-group">
                            <label>Fournisseur</label>
                            <select name="fournisseur" class="form-control">
                                <option value="">-- Aucun fournisseur --</option>
                                <?php foreach ($fournisseurs as $four): ?>
                                <option value="<?= securiser($four['nom']) ?>"><?= securiser($four['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="action_entree_stock" class="btn btn-success" style="width:100%;"><i class="fas fa-check-circle"></i> Valider</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> Alertes stock faible</h3>
                    <?php
                    $alertes_stock = $pdo->query("SELECT * FROM medicaments WHERE quantite_stock <= seuil_alerte ORDER BY quantite_stock ASC")->fetchAll();
                    ?>
                    <?php if (count($alertes_stock) > 0): ?>
                        <?php foreach ($alertes_stock as $alerte): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; background:#fef2f2; border-radius:10px; margin-bottom:10px;">
                            <div><strong><?= securiser($alerte['nom']) ?></strong><br><small style="color:#b91c1c;">Stock: <?= $alerte['quantite_stock'] ?> / Seuil: <?= $alerte['seuil_alerte'] ?></small></div>
                            <a href="?section=stock" class="btn btn-primary btn-sm">Réappro</a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; color:#059669; padding:30px;">✅ Aucune alerte</p>
                    <?php endif; ?>
                </div>
            </div>

        <!-- RAPPORTS -->
        <?php elseif ($page_active == 'rapports'): ?>
            <!-- En-tête de la page Rapports avec bouton d'impression -->
            <div class="header" style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
                <h1><i class="fas fa-chart-bar"></i> Rapports</h1>
                <!-- Bouton qui déclenche l'impression de la section rapport -->
                <button onclick="imprimerRapport()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer le rapport</button>
            </div>
            <?php
            // Récupère les 10 médicaments les plus vendus pour le rapport
            $top_medicaments = $pdo->query("SELECT medicament_nom, SUM(quantite) as total_vendu, SUM(total) as total_ca FROM ventes GROUP BY medicament_nom ORDER BY total_vendu DESC LIMIT 10")->fetchAll();
            // Récupère les ventes par mois pour le rapport
            $ventes_par_mois = $pdo->query("SELECT DATE_FORMAT(date_facture, '%Y-%m') as mois, COUNT(*) as nb_factures, SUM(total_ttc) as ca FROM factures GROUP BY DATE_FORMAT(date_facture, '%Y-%m') ORDER BY mois DESC LIMIT 6")->fetchAll();
            ?>
            <!-- Contenu du rapport qui sera copié et imprimé par JavaScript -->
            <div id="rapportContent">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card"><h3><i class="fas fa-trophy"></i> Top 10 médicaments</h3>
                    <?php foreach ($top_medicaments as $index => $top): ?>
                    <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e2e8f0;">
                        <span><strong>#<?= $index+1 ?></strong> <?= securiser($top['medicament_nom']) ?></span>
                        <span style="color:#059669;"><?= $top['total_vendu'] ?> vendus</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card"><h3><i class="fas fa-calendar"></i> Ventes par mois</h3>
                    <?php foreach ($ventes_par_mois as $mois): ?>
                    <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e2e8f0;">
                        <span><?= date('F Y', strtotime($mois['mois'].'-01')) ?></span>
                        <span><?= $mois['nb_factures'] ?> factures - <?= format_prix($mois['ca']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>

        <?php endif; ?>
    </div>

    
    <div id="modalAjoutUser" class="modal" style="display: none;">
        <div class="modal-content">
            <h3><i class="fas fa-user-plus"></i> Ajouter un utilisateur</h3>
            <form method="POST">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" class="form-control"></div>
                <div class="form-group"><label>Mot de passe</label><input type="password" name="mot_de_passe" class="form-control" required></div>
                <div class="form-group"><label>Rôle</label><select name="role" class="form-control"><option value="vendeur">Vendeur</option><option value="admin">Admin</option></select></div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('modalAjoutUser')" class="btn btn-outline">Annuler</button>
                    <button type="submit" name="action_ajouter_user" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditUser" class="modal" style="display: none;">
        <div class="modal-content">
            <h3><i class="fas fa-user-edit"></i> Modifier l'utilisateur</h3>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" id="edit_nom" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" id="edit_telephone" class="form-control"></div>
                <div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="mot_de_passe" class="form-control" placeholder="Laisser vide"></div>
                <div class="form-group"><label>Rôle</label><select name="role" id="edit_role" class="form-control"><option value="vendeur">Vendeur</option><option value="admin">Admin</option></select></div>
                <div class="form-group"><label>Statut</label><select name="statut" id="edit_statut" class="form-control"><option value="actif">Actif</option><option value="inactif">Inactif</option></select></div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('modalEditUser')" class="btn btn-outline">Annuler</button>
                    <button type="submit" name="action_modifier_user" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalAjoutFournisseur" class="modal" style="display: none;">
        <div class="modal-content">
            <h3><i class="fas fa-truck-loading"></i> Ajouter un fournisseur</h3>
            <form method="POST">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" class="form-control"></div>
                <div class="form-group"><label>Adresse</label><textarea name="adresse" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('modalAjoutFournisseur')" class="btn btn-outline">Annuler</button>
                    <button type="submit" name="action_ajouter_fournisseur" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditFournisseur" class="modal" style="display: none;">
        <div class="modal-content">
            <h3><i class="fas fa-edit"></i> Modifier le fournisseur</h3>
            <form method="POST">
                <input type="hidden" name="fournisseur_id" id="edit_fournisseur_id">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" id="edit_fournisseur_nom" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_fournisseur_email" class="form-control"></div>
                <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" id="edit_fournisseur_telephone" class="form-control"></div>
                <div class="form-group"><label>Adresse</label><textarea name="adresse" id="edit_fournisseur_adresse" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label>Notes</label><textarea name="notes" id="edit_fournisseur_notes" class="form-control" rows="2"></textarea></div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('modalEditFournisseur')" class="btn btn-outline">Annuler</button>
                    <button type="submit" name="action_modifier_fournisseur" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($show_modal && $facture_detail): ?>
    <div class="modal" id="factureModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice"></i> Détail facture</h3>
                <span class="close-btn" onclick="closeModalFacture()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="facture-print" id="factureContent">
                    <div class="facture-header">
                        <div class="facture-logo">
                            <h2><?= NOM_PHARMACIE ?></h2>
                            <p><?= ADRESSE_PHARMACIE ?></p>
                            <p>📞 <?= TELEPHONE_PHARMACIE ?></p>
                        </div>
                        <div class="facture-info">
                            <div class="numero"><?= securiser($facture_detail['numero_facture']) ?></div>
                            <p>Date: <?= date('d/m/Y', strtotime($facture_detail['date_facture'])) ?></p>
                            <p>Heure: <?= date('H:i', strtotime($facture_detail['date_facture'])) ?></p>
                        </div>
                    </div>
                    <div class="facture-client-info">
                        <p><strong>Client :</strong> <?= securiser($facture_detail['client_nom']) ?></p>
                        <?php if ($facture_detail['client_telephone']): ?>
                        <p><strong>Téléphone :</strong> <?= $facture_detail['client_telephone'] ?></p>
                        <?php endif; ?>
                        <p><strong>Vendeur :</strong> <?= securiser($facture_detail['vendeur_nom']) ?></p>
                    </div>
                    <table class="facture-table">
                        <thead>
                            <tr>
                                <th>Désignation</th>
                                <th style="text-align:right">Prix</th>
                                <th style="text-align:center">Qté</th>
                                <th style="text-align:right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facture_articles as $article): ?>
                            <tr>
                                <td><?= securiser($article['medicament_nom']) ?></td>
                                <td style="text-align:right"><?= format_prix($article['prix_unitaire']) ?></td>
                                <td style="text-align:center"><?= $article['quantite'] ?></td>
                                <td style="text-align:right"><?= format_prix($article['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="facture-total-big">
                        <p>Total TTC : <span class="montant"><?= format_prix($facture_detail['total_ttc']) ?></span></p>
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
                    <button onclick="imprimerFacture()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</button>
                    <a href="?section=factures" class="btn btn-outline">Fermer</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // GESTION DU PANIER
    let panier = [];
    const panierVide = document.getElementById('panierVide');
    const panierItems = document.getElementById('panierItems');
    const panierTotal = document.getElementById('panierTotal');
    const totalMontant = document.getElementById('totalMontant');
    const itemsInput = document.getElementById('itemsInput');
    const viderPanierBtn = document.getElementById('viderPanierBtn');
    
    function ajouterAuPanier() {
        const select = document.getElementById('medicamentSelect');
        const quantite = parseInt(document.getElementById('quantiteInput').value) || 1;
        if (!select.value) { alert('Sélectionnez un médicament'); return; }
        const option = select.options[select.selectedIndex];
        const id = parseInt(select.value);
        const nom = option.dataset.nom;
        const prix = parseFloat(option.dataset.prix);
        const stock = parseInt(option.dataset.stock);
        const existingItem = panier.find(item => item.id === id);
        const qtyInCart = existingItem ? existingItem.quantite : 0;
        if (qtyInCart + quantite > stock) { alert(`Stock insuffisant !`); return; }
        if (existingItem) {
            existingItem.quantite += quantite;
            existingItem.total = existingItem.quantite * existingItem.prix;
        } else {
            panier.push({ id: id, nom: nom, prix: prix, quantite: quantite, total: prix * quantite });
        }
        afficherPanier();
    }
    
    function afficherPanier() {
        if (panier.length === 0) {
            if (panierVide) panierVide.style.display = 'block';
            if (panierItems) panierItems.style.display = 'none';
            if (panierTotal) panierTotal.style.display = 'none';
            if (itemsInput) itemsInput.value = '';
            if (viderPanierBtn) viderPanierBtn.style.display = 'none';
            return;
        }
        if (panierVide) panierVide.style.display = 'none';
        if (panierItems) panierItems.style.display = 'block';
        if (panierTotal) panierTotal.style.display = 'block';
        if (viderPanierBtn) viderPanierBtn.style.display = 'inline-flex';
        
        let html = '';
        let total = 0;
        panier.forEach((item, index) => {
            total += item.total;
            html += `<div class="panier-item">
                <div style="flex:2;"><strong>${item.nom}</strong><br><small>${formatPrix(item.prix)} / unité</small></div>
                <div style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <button onclick="modifierQuantite(${index}, -1)" class="btn-qty">-</button>
                    <span style="font-weight:600;">${item.quantite}</span>
                    <button onclick="modifierQuantite(${index}, 1)" class="btn-qty">+</button>
                </div>
                <div style="flex:1; text-align:right; font-weight:600; color:#059669;">${formatPrix(item.total)}</div>
                <div><button onclick="retirerDuPanier(${index})" class="btn-remove"><i class="fas fa-trash-alt"></i></button></div>
            </div>`;
        });
        if (panierItems) panierItems.innerHTML = html;
        if (totalMontant) totalMontant.innerHTML = formatPrix(total);
        if (itemsInput) itemsInput.value = JSON.stringify(panier.map(item => ({ id: item.id, quantite: item.quantite })));
    }
    
    function viderPanier() { if (confirm('Vider le panier ?')) { panier = []; afficherPanier(); } }
    function modifierQuantite(index, delta) {
        const item = panier[index];
        const nouvelleQuantite = item.quantite + delta;
        if (nouvelleQuantite < 1) { retirerDuPanier(index); return; }
        if (nouvelleQuantite > 20) { alert('Quantité max: 20'); return; }
        item.quantite = nouvelleQuantite;
        item.total = item.quantite * item.prix;
        afficherPanier();
    }
    function retirerDuPanier(index) { panier.splice(index, 1); afficherPanier(); }
    function formatPrix(v) { return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' FC'; }
    
    if (document.getElementById('ajouterPanierBtn')) {
        document.getElementById('ajouterPanierBtn').addEventListener('click', ajouterAuPanier);
    }
    if (document.getElementById('validerVenteBtn')) {
        document.getElementById('validerVenteBtn').addEventListener('click', function(e) {
            if (panier.length === 0) { e.preventDefault(); alert('Panier vide !'); return false; }
        });
    }
    
    // AUTOCOMPLÉTION CLIENTS
    const clientsList = <?= json_encode($clients) ?>;
    const clientNomInput = document.getElementById('clientNom');
    const clientTelephoneInput = document.getElementById('clientTelephone');
    const clientAdresseInput = document.getElementById('clientAdresse');
    const suggestionsDiv = document.getElementById('clientSuggestions');
    
    if (clientNomInput) {
        clientNomInput.addEventListener('input', function() {
            const search = this.value.toLowerCase();
            if (search.length < 2) { if(suggestionsDiv) suggestionsDiv.style.display = 'none'; return; }
            const matches = clientsList.filter(c => c.client_nom && c.client_nom.toLowerCase().includes(search)).slice(0, 5);
            if (matches.length > 0 && suggestionsDiv) {
                suggestionsDiv.innerHTML = matches.map(c => `<div onclick="selectionnerClient('${c.client_nom.replace(/'/g, "\\'")}', '${c.client_telephone || ''}', '${c.client_adresse || ''}')">
                    <strong>${c.client_nom}</strong>${c.client_telephone ? `<br><small>📞 ${c.client_telephone}</small>` : ''}
                </div>`).join('');
                suggestionsDiv.style.display = 'block';
            } else if(suggestionsDiv) { suggestionsDiv.style.display = 'none'; }
        });
    }

    function selectionnerClient(nom, telephone, adresse) {
        if(clientNomInput) clientNomInput.value = nom;
        if(clientTelephoneInput && telephone) clientTelephoneInput.value = telephone;
        if(clientAdresseInput && adresse) clientAdresseInput.value = adresse;
        if(suggestionsDiv) suggestionsDiv.style.display = 'none';
    }
    
    document.addEventListener('click', function(e) {
        if (clientNomInput && suggestionsDiv && !clientNomInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.style.display = 'none';
        }
    });
    
    // RECHERCHE
    const searchMedicament = document.getElementById('searchMedicament');
    const filterCategorie = document.getElementById('filterCategorie');
    
    function filtrerMedicaments() {
        const searchTerm = searchMedicament ? searchMedicament.value.toLowerCase() : '';
        const categorie = filterCategorie ? filterCategorie.value.toLowerCase() : '';
        const rows = document.querySelectorAll('#medicamentsTable tbody tr');
        rows.forEach(row => {
            const nom = row.cells[2]?.innerText.toLowerCase() || '';
            const code = row.cells[1]?.innerText.toLowerCase() || '';
            const cat = row.cells[3]?.innerText.toLowerCase() || '';
            const matchSearch = searchTerm === '' || nom.includes(searchTerm) || code.includes(searchTerm);
            const matchCategorie = categorie === '' || cat.includes(categorie);
            row.style.display = matchSearch && matchCategorie ? '' : 'none';
        });
    }
    
    if (searchMedicament) searchMedicament.addEventListener('keyup', filtrerMedicaments);
    if (filterCategorie) filterCategorie.addEventListener('change', filtrerMedicaments);
    
    // MODALES
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    function editUser(id, nom, email, telephone, role, statut) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_nom').value = nom;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_telephone').value = telephone;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_statut').value = statut;
        openModal('modalEditUser');
    }

    function editFournisseur(id, nom, email, telephone, adresse, notes) {
        document.getElementById('edit_fournisseur_id').value = id;
        document.getElementById('edit_fournisseur_nom').value = nom;
        document.getElementById('edit_fournisseur_email').value = email;
        document.getElementById('edit_fournisseur_telephone').value = telephone;
        document.getElementById('edit_fournisseur_adresse').value = adresse;
        document.getElementById('edit_fournisseur_notes').value = notes;
        openModal('modalEditFournisseur');
    }
    
    function closeModalFacture() { window.location.href = '?section=factures'; }
    
    function imprimerRapport() {
        // Sélectionne la zone rapport dans la page actuelle
        const contenu = document.getElementById('rapportContent');
        if (!contenu) return; // Si l'élément n'existe pas, on ne peut rien imprimer

        // Crée une nouvelle fenêtre ou onglet vide pour préparer l'impression
        const printWindow = window.open('', '_blank');

        // Récupère le HTML du rapport et génère un document complet pour l'impression
        printWindow.document.write(`<html><head><title>Rapport</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"><style>
            *{margin:0;padding:0;box-sizing:border-box;}
            body{font-family:'Inter',sans-serif;padding:20px;}
            .card{background:white;border-radius:16px;padding:20px;margin-bottom:25px;border:1px solid #e2e8f0;box-shadow:0 1px 2px rgba(0,0,0,0.02);}
            .card h3{margin-bottom:16px;}
            .rapport-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e2e8f0;}
            .rapport-row:last-child{border-bottom:none;}
            @media print{body{padding:0;}}
        </style></head><body>${contenu.outerHTML}<script>window.print();window.close();<\/script></body></html>`);

        // Ferme le flux d'écriture et lance la boîte de dialogue d'impression
        printWindow.document.close();
    }
    
    function imprimerFacture() {
        const contenu = document.getElementById('factureContent').cloneNode(true);
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`<html><head><title>Facture</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"><style>
            *{margin:0;padding:0;box-sizing:border-box;}
            body{font-family:'Inter',sans-serif;padding:20px;}
            .facture-header{display:flex;justify-content:space-between;margin-bottom:30px;padding-bottom:20px;border-bottom:2px solid #f1f5f9;}
            .facture-logo h2{font-size:22px;color:#0f172a;}
            .facture-logo p{font-size:12px;color:#64748b;}
            .facture-info{text-align:right;}
            .facture-info .numero{font-size:18px;font-weight:700;color:#2563eb;}
            .facture-client-info{background:#f8fafc;padding:20px;border-radius:12px;margin-bottom:25px;}
            .facture-table{width:100%;border-collapse:collapse;margin-bottom:25px;}
            .facture-table th,.facture-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;}
            .facture-table th{background:#f8fafc;font-weight:600;}
            .facture-total-big{text-align:right;padding-top:15px;border-top:2px solid #f1f5f9;}
            .facture-total-big .montant{font-size:24px;font-weight:700;color:#059669;}
            @media print{body{padding:0;}}
        </style></head><body>${contenu.outerHTML}<script>window.print();window.close();<\/script></body></html>`);
        printWindow.document.close();
    }
    </script>
</body>
</html>