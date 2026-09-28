<?php
session_start();
require_once 'config.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = securiser($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND statut = 'actif'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && hash('sha256', $mot_de_passe) === $user['mot_de_passe']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        // Mettre à jour dernier accès
        $stmt = $pdo->prepare("UPDATE utilisateurs SET dernier_acces = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
       if ($user['role'] === 'admin') {
            header('Location: admin.php');
        } else  {
            header('Location: utilisateur.php');
        }
        exit;
    } else {
        $erreur = "Email ou mot de passe incorrect";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Pharmacie RDC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .login-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #334155;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
        }
    
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-login:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .login-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            margin-top: 20px;
            color: #64748b;
            font-size: 13px;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .info-card p {
            font-size: 12px;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .info-card code {
            background: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-hospital" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h1>Pharmacie</h1>
            <p>Gestion de pharmacie professionnelle</p>
        </div>
        
        <div class="login-body">
            <?php if ($erreur): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $erreur ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" placeholder="admin@pharmacie.cd" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Mot de passe</label>
                    <input type="password" name="mot_de_passe" placeholder="0000" required>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
            
            
            
            <div class="login-footer">
                <p>© 2026 - Tous droits réservés</p>
                <p style="margin-top: 5px;">
                    <i class="fab fa-whatsapp" style="color: #25D366;"></i> +243 990 662 898
                </p>
            </div>
        </div>
    </div>
</body>
</html>