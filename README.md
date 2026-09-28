# Pharmacie Sorayah - Système de Gestion de Pharmacie

Application web complète de gestion pharmaceutique développée en **PHP / MySQL**, adaptée pour le marché de Lubumbashi (RDC).

---

## 🌟 Fonctionnalités

- **Gestion des Médicaments & Stocks** :
  - Enregistrement, modification, suppression de médicaments avec images d'illustration.
  - Alertes de stock faible et de péremption.
  - Catégorisation des produits et gestion des prix en Francs Congolais (FC).
- **Interface Caisse & Ventes** :
  - Enregistrement des ventes au comptoir.
  - Impression et génération de factures numérotées (`FACT-AAAAMMJJ-XXXX`).
- **Gestion des Rôles & Accès** :
  - Espace **Administrateur** (`admin.php`) avec rapports complets et gestion des utilisateurs.
  - Espace **Utilisateur / Vendeur** (`utilisateur.php`).
- **Notifications & Facturation** :
  - Support de contact WhatsApp intégré.

---

## 🛠️ Prérequis & Technologies

- **Serveur local** : WampServer, XAMPP ou Laragon (PHP 7.4+ ou 8.x)
- **Base de données** : MySQL / MariaDB
- **Frontend** : HTML5, CSS3 personnalisé, JavaScript / Ajax
- **Backend** : PHP avec PDO

---

## 🚀 Installation & Démarrage

1. **Cloner le projet** ou le placer dans le dossier web de WAMP :
   ```bash
   cd c:\wamp64\www\
   git clone https://github.com/kalambaycharles4/Pharmacie_Gestion.git
   ```

2. **Importer la base de données** :
   - Ouvrez phpMyAdmin (`http://localhost/phpmyadmin`).
   - Créez une base de données nommée `pharmacie_db`.
   - Importez le fichier `pharmacie_db.sql` inclus dans le projet.

3. **Configurer la connexion** :
   - Vérifiez et adaptez le fichier `config.php` si nécessaire :
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'pharmacie_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Lancer l'application** :
   - Ouvrez votre navigateur sur `http://localhost/Pharmacie_Gestion/login.php`.

---

## 👤 Auteur

- **Charles KAL** ([@kalambaycharles4](https://github.com/kalambaycharles4))
