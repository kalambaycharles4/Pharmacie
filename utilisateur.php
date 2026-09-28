<?php
// Inclusion de la configuration et vérification de l'accès vendeur.
require_once 'config.php';
verifier_acces('vendeur');

// Détermine la section active de l'interface selon l'URL.
$page_active = isset($_GET['section']) ? $_GET['section'] : 'ventes';
// Charge la liste des médicaments disponibles en stock pour l'interface de vente.
$medicaments = $pdo->query("SELECT id, nom, code, prix_vente, quantite_stock, photo FROM medicaments WHERE quantite_stock > 0 ORDER BY nom")->fetchAll();

// Récupère les clients déjà connus pour proposer une autocomplétion dans le formulaire.
$clients = $pdo->query("SELECT DISTINCT client_nom, client_telephone, client_adresse FROM factures WHERE client_nom IS NOT NULL ORDER BY date_facture DESC LIMIT 30")->fetchAll();

// Récupère les factures créées par le vendeur connecté pour les afficher dans l'interface.
$factures = $pdo->query("
    SELECT f.*, 
           (SELECT COUNT(*) FROM ventes WHERE facture_id = f.id) as nb_articles,
           (SELECT SUM(quantite) FROM ventes WHERE facture_id = f.id) as total_articles
    FROM factures f 
    WHERE f.vendeur_id = " . $_SESSION['user_id'] . " 
    ORDER BY f.date_facture DESC 
    LIMIT 50
")->fetchAll();

// Compte le nombre de médicaments dont le stock est à ou en dessous du seuil d'alerte.
$alertes_stock = $pdo->query("SELECT COUNT(*) FROM medicaments WHERE quantite_stock <= seuil_alerte")->fetchColumn() ?: 0;

// Prépare les variables utilisées pour afficher une facture spécifique dans un modal d'impression.
$facture_detail = null;
$facture_articles = [];
$show_modal = false;

if (isset($_GET['view_facture']) && is_numeric($_GET['view_facture'])) {
    $facture_id = (int)$_GET['view_facture'];
    $stmt = $pdo->prepare("SELECT * FROM factures WHERE id = ? AND vendeur_id = ?");
    $stmt->execute([$facture_id, $_SESSION['user_id']]);
    $facture_detail = $stmt->fetch();
    
    if ($facture_detail) {
        $stmt = $pdo->prepare("SELECT * FROM ventes WHERE facture_id = ?");
        $stmt->execute([$facture_id]);
        $facture_articles = $stmt->fetchAll();
        $show_modal = true;
    }
}

// Traite la création d'une facture à partir du panier envoyé par le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_creer_facture'])) {
    $items = json_decode($_POST['items'], true);
    $client_nom = securiser($_POST['client_nom'] ?: 'Client');
    $client_telephone = securiser($_POST['client_telephone'] ?? '');
    $client_adresse = securiser($_POST['client_adresse'] ?? '');
    $vendeur_id = $_SESSION['user_id'];
    $vendeur_nom = $_SESSION['user_nom'];
    
    if (empty($items)) {
        $_SESSION['error'] = "Panier vide";
        header('Location: utilisateur.php?section=ventes');
        exit;
    }
    
    // Démarre une transaction pour garantir que la facture et les mises à jour de stock sont cohérentes.
    $pdo->beginTransaction();
    // Génère un numéro unique pour la facture.
    $numero_facture = generer_numero_facture();
    $total_facture = 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO factures (numero_facture, client_nom, client_telephone, client_adresse, total_ht, total_ttc, vendeur_id, vendeur_nom) VALUES (?, ?, ?, ?, 0, 0, ?, ?)");
        $stmt->execute([$numero_facture, $client_nom, $client_telephone, $client_adresse, $vendeur_id, $vendeur_nom]);
        $facture_id = $pdo->lastInsertId();
        
        // Traite chaque article du panier : vérifie le stock, met à jour les quantités et crée la ligne de vente.
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
            
            $stmt = $pdo->prepare("INSERT INTO ventes (facture_id, medicament_id, medicament_nom, quantite, prix_unitaire, total, vendeur_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$facture_id, $item['id'], $medicament['nom'], $item['quantite'], $medicament['prix_vente'], $total, $vendeur_id]);
        }
        
        // Met à jour le montant total de la facture après traitement des articles.
        $stmt = $pdo->prepare("UPDATE factures SET total_ht = ?, total_ttc = ? WHERE id = ?");
        $stmt->execute([$total_facture, $total_facture, $facture_id]);
        
        // Valide la transaction si tout s'est bien passé.
        $pdo->commit();
        $_SESSION['success'] = "💰 Facture créée ! N° $numero_facture";
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    header('Location: utilisateur.php?section=factures');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendeur - PharmaCare Lubumbashi</title>
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
        .badge { background: #059669; color: white; padding: 3px 10px; border-radius: 20px; font-size: 10px; display: inline-block; }
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
        .nav-links a:hover, .nav-links a.active { background: #eff6ff; color: #2563eb; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 25px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
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
        
        .factures-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }
        .facture-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.2s;
        }
        .facture-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .facture-card-header {
            background: #f8fafc;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .facture-numero {
            font-weight: 700;
            font-size: 16px;
            color: #2563eb;
        }
        .facture-date {
            font-size: 12px;
            color: #64748b;
        }
        .facture-card-body {
            padding: 16px 20px;
        }
        .facture-client {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .client-avatar {
            width: 40px;
            height: 40px;
            background: #eff6ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
        }
        .client-info h4 { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .client-info p { font-size: 12px; color: #64748b; }
        .facture-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .stat-item {
            text-align: center;
            flex: 1;
        }
        .stat-label { font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .stat-value { font-weight: 700; font-size: 18px; color: #0f172a; }
        .facture-total {
            background: #f0fdf4;
            padding: 12px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .total-label { font-size: 13px; font-weight: 500; color: #166534; }
        .total-amount { font-size: 20px; font-weight: 700; color: #059669; }
        .facture-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
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
            max-width: 700px;
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
        
        .facture-print {
            max-width: 100%;
            margin: 0 auto;
            background: white;
        }
        .facture-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        .facture-logo h2 { font-size: 22px; color: #0f172a; margin-bottom: 5px; }
        .facture-logo p { font-size: 12px; color: #64748b; margin: 3px 0; }
        .facture-info { text-align: right; }
        .facture-info .numero { font-size: 18px; font-weight: 700; color: #2563eb; }
        .facture-client-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .facture-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .facture-table th, .facture-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .facture-table th { background: #f8fafc; font-weight: 600; }
        .facture-table td.text-right { text-align: right; }
        .facture-table td.text-center { text-align: center; }
        .facture-total-big {
            text-align: right;
            padding-top: 15px;
            border-top: 2px solid #f1f5f9;
        }
        .facture-total-big .montant { font-size: 24px; font-weight: 700; color: #059669; }
        
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
        
        .vente-container { display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; }
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
        
        .catalogue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .catalogue-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .catalogue-item:hover { background: #f1f5f9; }
        
        .client-search {
            position: relative;
        }
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
        .client-suggestions div:hover {
            background: #eff6ff;
        }
        
        .panier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-weight: 600;
            color: #64748b;
            font-size: 13px;
            padding: 0 5px;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; }
            .vente-container, .factures-grid { grid-template-columns: 1fr; }
        }
        
        @media print {
            .sidebar, .header, .facture-actions, .btn, .modal, .nav-links, .close-btn, .modal-header .close-btn { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
            .modal-content { box-shadow: none; padding: 0; background: white; }
            .modal { position: relative; background: white; padding: 0; }
            .facture-print { padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- Barre latérale de navigation du vendeur avec accès rapide aux sections principales. -->
    <div class="sidebar">
        <div class="logo"><h2>🏥 <span>Pharma soraya</span></h2><p style="font-size:12px; color:#64748b;">Lubumbashi - RDC</p></div>
        <div class="user-info">
            <h4><i class="fas fa-user"></i> <?= securiser($_SESSION['user_nom']) ?></h4>
            <p><?= securiser($_SESSION['user_email']) ?></p>
            <span class="badge">Vendeur</span>
        </div>
        <?php if ($alertes_stock > 0): ?>
        <div style="background:#fef2f2; padding:10px; border-radius:8px; margin-bottom:20px;">
            <span style="color:#b91c1c;"><i class="fas fa-exclamation-triangle"></i> <?= $alertes_stock ?> alerte(s) stock</span>
        </div>
        <?php endif; ?>
        <ul class="nav-links">
            <li><a href="?section=ventes" class="<?= $page_active == 'ventes' ? 'active' : '' ?>"><i class="fas fa-cash-register"></i> Ventes</a></li>
            <li><a href="?section=factures" class="<?= $page_active == 'factures' ? 'active' : '' ?>"><i class="fas fa-file-invoice"></i> Mes factures</a></li>
            <li><a href="?section=medicaments" class="<?= $page_active == 'medicaments' ? 'active' : '' ?>"><i class="fas fa-pills"></i> Catalogue</a></li>
        </ul>
        <div style="margin-top: 30px;"><a href="logout.php" class="btn btn-outline" style="width:100%; justify-content:center;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></div>
    </div>
    
    <!-- Contenu principal de l'application selon la section active. -->
    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- ==================== SECTION VENTES ==================== -->
        <!-- Interface de création d'une vente avec panier, formulaire client et validation. -->
        <?php if ($page_active == 'ventes'): ?>
            <div class="header">
                <h1><i class="fas fa-cash-register" style="color:#2563eb;"></i> Nouvelle vente</h1>
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
                            <?php foreach ($medicaments as $med): ?>
                            <option value="<?= $med['id'] ?>" data-nom="<?= securiser($med['nom']) ?>" data-prix="<?= $med['prix_vente'] ?>" data-stock="<?= $med['quantite_stock'] ?>"><?= securiser($med['nom']) ?> - <?= format_prix($med['prix_vente']) ?> (Stock: <?= $med['quantite_stock'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="quantiteInput" min="1" value="1" class="form-control" style="width: 80px;">
                        <button id="ajouterPanierBtn" class="btn btn-primary"><i class="fas fa-cart-plus"></i> Ajouter</button>
                    </div>
                    
                    <div class="panier-header">
                        <span>Produit</span>
                        <span>Quantité</span>
                        <span>Total</span>
                        <span></span>
                    </div>
                    
                    <div id="panierVide" style="text-align: center; padding: 40px;">
                        <i class="fas fa-shopping-cart" style="font-size:48px; color:#cbd5e1; margin-bottom:10px;"></i>
                        <p style="color:#64748b;">Votre panier est vide</p>
                        <p style="font-size:12px; color:#94a3b8;">Ajoutez des médicaments pour commencer</p>
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
                            <input type="text" name="client_nom" id="clientNom" class="form-control" required placeholder="Rechercher ou saisir un nom..." autocomplete="off">
                            <div id="clientSuggestions" class="client-suggestions"></div>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <div style="display:flex;">
                                <span style="background:#f1f5f9; padding:10px 12px; border:1px solid #e2e8f0; border-right:none; border-radius:8px 0 0 8px;">+243</span>
                                <input type="text" name="client_telephone" id="clientTelephone" class="form-control" placeholder="812345678" style="border-radius:0 8px 8px 0;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Adresse</label>
                            <input type="text" name="client_adresse" id="clientAdresse" class="form-control" placeholder="Lubumbashi, Golf, Q. Industriel">
                        </div>
                        <button type="submit" name="action_creer_facture" id="validerVenteBtn" class="btn btn-success" style="width:100%; padding: 14px;">
                            <i class="fas fa-check-circle"></i> Créer la facture
                        </button>
                    </form>
                </div>
            </div>
            
        <!-- ==================== SECTION MES FACTURES ==================== -->
        <!-- Affichage des factures déjà créées par le vendeur avec possibilité d'impression. -->
        <?php elseif ($page_active == 'factures'): ?>
            <div class="header">
                <h1><i class="fas fa-file-invoice" style="color:#2563eb;"></i> Mes factures</h1>
                <a href="?section=ventes" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nouvelle facture</a>
            </div>
            
            <?php if (count($factures) > 0): ?>
                <div class="factures-grid">
                    <?php foreach ($factures as $facture): ?>
                    <div class="facture-card">
                        <div class="facture-card-header">
                            <span class="facture-numero"><i class="fas fa-hashtag"></i> <?= securiser($facture['numero_facture']) ?></span>
                            <span class="facture-date"><i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($facture['date_facture'])) ?></span>
                        </div>
                        <div class="facture-card-body">
                            <div class="facture-client">
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="client-info">
                                    <h4><?= securiser($facture['client_nom']) ?></h4>
                                    <?php if ($facture['client_telephone']): ?>
                                        <p><i class="fas fa-phone"></i> <?= $facture['client_telephone'] ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="facture-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Articles</div>
                                    <div class="stat-value"><?= $facture['nb_articles'] ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Quantité totale</div>
                                    <div class="stat-value"><?= $facture['total_articles'] ?? 0 ?></div>
                                </div>
                            </div>
                            <div class="facture-total">
                                <span class="total-label"><i class="fas fa-money-bill-wave"></i> Montant total</span>
                                <span class="total-amount"><?= format_prix($facture['total_ttc']) ?></span>
                            </div>
                            <div class="facture-actions">
                                <a href="?section=factures&view_facture=<?= $facture['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-print"></i> Imprimer
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 50px;">
                    <i class="fas fa-file-invoice" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Aucune facture</h3>
                    <p style="color: #64748b; margin-bottom: 25px;">Vous n'avez pas encore créé de facture</p>
                    <a href="?section=ventes" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Créer une facture
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- MODAL IMPRESSION -->
            <?php if ($show_modal && $facture_detail): ?>
            <div class="modal" id="factureModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-print" style="color:#2563eb;"></i> Impression de la facture</h3>
                        <span class="close-btn" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="facture-print" id="factureContent">
                            <div class="facture-header">
                                <div class="facture-logo">
                                    <h2><?= NOM_PHARMACIE ?></h2>
                                    <p>📍 <?= ADRESSE_PHARMACIE ?></p>
                                    <p>📞 <?= TELEPHONE_PHARMACIE ?></p>
                                    <p>✉️ <?= EMAIL_PHARMACIE ?></p>
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
                                <?php if ($facture_detail['client_adresse']): ?>
                                <p><strong>Adresse :</strong> <?= securiser($facture_detail['client_adresse']) ?></p>
                                <?php endif; ?>
                                <p><strong>Vendeur :</strong> <?= securiser($facture_detail['vendeur_nom']) ?></p>
                            </div>
                            
                            <table class="facture-table">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th style="text-align:right">Prix unitaire</th>
                                        <th style="text-align:center">Quantité</th>
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
                            
                            <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #64748b;">
                                <p>Merci pour votre confiance !</p>
                                <p>Cette facture est officielle et fait office de justificatif d'achat.</p>
                                <p style="margin-top: 10px;"><?= NOM_PHARMACIE ?> - Votre santé, notre priorité</p>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                            <button onclick="imprimerFacture()" class="btn btn-primary">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                            <a href="?section=factures" class="btn btn-outline">Fermer</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        <!-- ==================== SECTION CATALOGUE ==================== -->
        <!-- Liste des médicaments disponibles avec recherche et accès rapide à la vente. -->
        <?php elseif ($page_active == 'medicaments'): ?>
            <div class="header">
                <h1><i class="fas fa-pills" style="color:#2563eb;"></i> Catalogue des médicaments</h1>
            </div>
            <div class="card">
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="flex:1; display: flex; align-items: center; gap: 10px; background:#f8fafc; padding: 0 15px; border-radius: 8px; border:1px solid #e2e8f0;">
                        <i class="fas fa-search" style="color:#94a3b8;"></i>
                        <input type="text" id="searchMedicament" placeholder="Rechercher un médicament..." style="flex:1; padding: 12px 0; border: none; background: transparent; outline: none;">
                    </div>
                </div>
                <div class="catalogue-grid">
                    <?php foreach ($medicaments as $med): ?>
                    <div class="catalogue-item">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                            <div style="width: 45px; height: 45px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-pills" style="color:#64748b;"></i>
                            </div>
                            <div>
                                <strong><?= securiser($med['nom']) ?></strong>
                                <div style="font-size: 11px; color: #64748b;"><?= securiser($med['code']) ?></div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <span style="font-weight: 700; color: #059669;"><?= format_prix($med['prix_vente']) ?></span>
                            <span style="font-size: 12px; padding: 3px 8px; border-radius: 20px; background: <?= $med['quantite_stock'] <= 10 ? '#fee2e2' : '#f1f5f9' ?>; color: <?= $med['quantite_stock'] <= 10 ? '#b91c1c' : '#334155' ?>;">
                                <i class="fas fa-box"></i> Stock: <?= $med['quantite_stock'] ?>
                            </span>
                        </div>
                        <a href="?section=ventes" class="btn btn-outline btn-sm" style="margin-top: 12px; width:100%; justify-content:center;">
                            <i class="fas fa-cart-plus"></i> Vendre
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // ==================== GESTION DU PANIER DE VENTE ====================
    // Ce script gère l'ajout, la modification et la suppression des produits avant la création de la facture.
    let panier = [];
    const panierVide = document.getElementById('panierVide');
    const panierItems = document.getElementById('panierItems');
    const panierTotal = document.getElementById('panierTotal');
    const totalMontant = document.getElementById('totalMontant');
    const itemsInput = document.getElementById('itemsInput');
    const viderPanierBtn = document.getElementById('viderPanierBtn');
    
    // Ajoute un article sélectionné au panier avec la quantité demandée.
    function ajouterAuPanier() {
        const select = document.getElementById('medicamentSelect');
        const quantite = parseInt(document.getElementById('quantiteInput').value) || 1;
        if (!select.value) { alert('Veuillez sélectionner un médicament'); return; }
        const option = select.options[select.selectedIndex];
        const id = parseInt(select.value);
        const nom = option.dataset.nom;
        const prix = parseFloat(option.dataset.prix);
        const stock = parseInt(option.dataset.stock);
        
        const existingItem = panier.find(item => item.id === id);
        const qtyInCart = existingItem ? existingItem.quantite : 0;
        
        if (qtyInCart + quantite > stock) { 
            alert(`Stock insuffisant ! Disponible: ${stock} unités`); 
            return; 
        }
        
        if (existingItem) {
            existingItem.quantite += quantite;
            existingItem.total = existingItem.quantite * existingItem.prix;
        } else {
            panier.push({ id: id, nom: nom, prix: prix, quantite: quantite, total: prix * quantite });
        }
        afficherPanier();
    }
    
    // Affiche le contenu du panier et met à jour le total général.
    function afficherPanier() {
        if (panier.length === 0) {
            panierVide.style.display = 'block';
            panierItems.style.display = 'none';
            panierTotal.style.display = 'none';
            itemsInput.value = '';
            if (viderPanierBtn) viderPanierBtn.style.display = 'none';
            return;
        }
        
        panierVide.style.display = 'none';
        panierItems.style.display = 'block';
        panierTotal.style.display = 'block';
        if (viderPanierBtn) viderPanierBtn.style.display = 'inline-flex';
        
        let html = '';
        let total = 0;
        
        panier.forEach((item, index) => {
            total += item.total;
            html += `
                <div class="panier-item">
                    <div style="flex:2;">
                        <strong>${item.nom}</strong>
                        <br><small style="color:#64748b;">${formatPrix(item.prix)} / unité</small>
                    </div>
                    <div style="flex:1; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <button onclick="modifierQuantite(${index}, -1)" class="btn-qty">-</button>
                        <span style="font-weight:600; min-width:30px; text-align:center;">${item.quantite}</span>
                        <button onclick="modifierQuantite(${index}, 1)" class="btn-qty">+</button>
                    </div>
                    <div style="flex:1; text-align: right; font-weight:600; color:#059669;">${formatPrix(item.total)}</div>
                    <div>
                        <button onclick="retirerDuPanier(${index})" class="btn-remove">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        panierItems.innerHTML = html;
        totalMontant.innerHTML = formatPrix(total);
        itemsInput.value = JSON.stringify(panier.map(item => ({ id: item.id, quantite: item.quantite })));
    }
    
    // Vide complètement le panier après confirmation.
    function viderPanier() {
        if (confirm('Voulez-vous vraiment vider tout le panier ?')) {
            panier = [];
            afficherPanier();
        }
    }
    
    // Modifie la quantité d'un article du panier.
    function modifierQuantite(index, delta) {
        const item = panier[index];
        const nouvelleQuantite = item.quantite + delta;
        if (nouvelleQuantite < 1) { retirerDuPanier(index); return; }
        if (nouvelleQuantite > 20) { alert('Quantité maximale par article: 20'); return; }
        
        // Vérifier le stock si on augmente
        if (delta > 0) {
            const select = document.getElementById('medicamentSelect');
            const option = Array.from(select.options).find(opt => parseInt(opt.value) === item.id);
            const stock = option ? parseInt(option.dataset.stock) : 999;
            if (nouvelleQuantite > stock) {
                alert(`Stock insuffisant ! Maximum: ${stock}`);
                return;
            }
        }
        
        item.quantite = nouvelleQuantite;
        item.total = item.quantite * item.prix;
        afficherPanier();
    }
    
    // Supprime un article du panier.
    function retirerDuPanier(index) { 
        panier.splice(index, 1); 
        afficherPanier(); 
    }
    
    // Formate un montant en monnaie locale avec séparateurs de milliers.
    function formatPrix(v) { 
        return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' FC'; 
    }
    
    // Lie le bouton d'ajout au panier à la fonction correspondante.
    document.getElementById('ajouterPanierBtn')?.addEventListener('click', ajouterAuPanier);
    if (viderPanierBtn) viderPanierBtn.addEventListener('click', viderPanier);
    
    // Empêche la soumission si le panier est vide avant la création de la facture.
    document.getElementById('validerVenteBtn')?.addEventListener('click', function(e) {
        if (panier.length === 0) { 
            e.preventDefault(); 
            alert('Votre panier est vide ! Ajoutez des produits.'); 
            return false; 
        }
    });
    
    // ==================== RECHERCHE DES CLIENTS EXISTANTS ====================
    const clientsList = <?= json_encode($clients) ?>;
    const clientNomInput = document.getElementById('clientNom');
    const clientTelephoneInput = document.getElementById('clientTelephone');
    const clientAdresseInput = document.getElementById('clientAdresse');
    const suggestionsDiv = document.getElementById('clientSuggestions');
    
    clientNomInput?.addEventListener('input', function() {
        const search = this.value.toLowerCase();
        if (search.length < 2) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        
        const matches = clientsList.filter(c => 
            c.client_nom && c.client_nom.toLowerCase().includes(search)
        ).slice(0, 5);
        
        if (matches.length > 0) {
            suggestionsDiv.innerHTML = matches.map(c => 
                `<div onclick="selectionnerClient('${c.client_nom.replace(/'/g, "\\'")}', '${c.client_telephone || ''}', '${c.client_adresse || ''}')">
                    <strong>${c.client_nom}</strong>
                    ${c.client_telephone ? `<br><small>📞 ${c.client_telephone}</small>` : ''}
                    ${c.client_adresse ? `<br><small>📍 ${c.client_adresse}</small>` : ''}
                </div>`
            ).join('');
            suggestionsDiv.style.display = 'block';
        } else {
            suggestionsDiv.style.display = 'none';
        }
    });
    
    // Remplit automatiquement les informations du client sélectionné.
    function selectionnerClient(nom, telephone, adresse) {
        clientNomInput.value = nom;
        if (telephone) clientTelephoneInput.value = telephone;
        if (adresse) clientAdresseInput.value = adresse;
        suggestionsDiv.style.display = 'none';
    }
    
    // Masque les suggestions si l'utilisateur clique ailleurs dans la page.
    document.addEventListener('click', function(e) {
        if (!clientNomInput?.contains(e.target) && !suggestionsDiv?.contains(e.target)) {
            suggestionsDiv.style.display = 'none';
        }
    });
    
    // ==================== IMPRESSION DE LA FACTURE ====================
    // Ouvre une nouvelle fenêtre pour imprimer la facture avec un rendu dédié.
    function imprimerFacture() {
        const contenu = document.getElementById('factureContent').cloneNode(true);
        const originalTitle = document.title;
        document.title = "Facture";
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Facture</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Inter', sans-serif; padding: 20px; }
                    .facture-header { display: flex; justify-content: space-between; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f1f5f9; }
                    .facture-logo h2 { font-size: 22px; color: #0f172a; }
                    .facture-logo p { font-size: 12px; color: #64748b; margin: 3px 0; }
                    .facture-info { text-align: right; }
                    .facture-info .numero { font-size: 18px; font-weight: 700; color: #2563eb; }
                    .facture-client-info { background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
                    .facture-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
                    .facture-table th, .facture-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
                    .facture-table th { background: #f8fafc; font-weight: 600; }
                    .facture-table td.text-right { text-align: right; }
                    .facture-table td.text-center { text-align: center; }
                    .facture-total-big { text-align: right; padding-top: 15px; border-top: 2px solid #f1f5f9; }
                    .facture-total-big .montant { font-size: 24px; font-weight: 700; color: #059669; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                ${contenu.outerHTML}
                <script>
                    window.print();
                    window.close();
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
        document.title = originalTitle;
    }
    
    // Ferme la vue de la facture et revient à la liste des factures.
    function closeModal() {
        window.location.href = '?section=factures';
    }
    
    // Filtre dynamiquement les médicaments affichés dans le catalogue selon la recherche.
    document.getElementById('searchMedicament')?.addEventListener('keyup', function() {
        let search = this.value.toLowerCase();
        document.querySelectorAll('.catalogue-item').forEach(item => {
            let nom = item.innerText.toLowerCase();
            item.style.display = nom.includes(search) ? 'block' : 'none';
        });
    });
    </script>
</body>
</html>