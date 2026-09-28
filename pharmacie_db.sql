-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 09 juin 2026 à 20:09
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `pharmacie_db`
--

DELIMITER $$
--
-- Procédures
--
DROP PROCEDURE IF EXISTS `reapprovisionnement_auto`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `reapprovisionnement_auto` ()   BEGIN
    SELECT 
        id, nom, quantite_stock, seuil_alerte,
        (seuil_alerte * 3) as quantite_recommandee
    FROM medicaments
    WHERE quantite_stock <= seuil_alerte;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `achats`
--

DROP TABLE IF EXISTS `achats`;
CREATE TABLE IF NOT EXISTS `achats` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
  `medicament_id` int NOT NULL COMMENT 'ID du médicament réapprovisionné',
  `quantite` int NOT NULL COMMENT 'Quantité achetée',
  `prix_achat_unitaire` decimal(10,2) NOT NULL COMMENT 'Prix d''achat unitaire',
  `total` decimal(10,2) NOT NULL COMMENT 'Total de l''achat',
  `date_achat` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de l''achat',
  `fournisseur` varchar(100) DEFAULT NULL COMMENT 'Nom du fournisseur',
  `created_by` int DEFAULT NULL COMMENT 'ID de l''utilisateur qui a enregistré l''achat',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_medicament` (`medicament_id`),
  KEY `idx_date` (`date_achat`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des achats (entrées de stock)';

--
-- Déchargement des données de la table `achats`
--

INSERT INTO `achats` (`id`, `medicament_id`, `quantite`, `prix_achat_unitaire`, `total`, `date_achat`, `fournisseur`, `created_by`) VALUES
(3, 3, 60, 3000.00, 180000.00, '2026-06-09 03:07:05', 'VITAPLUS', 1),
(4, 4, 40, 2000.00, 80000.00, '2026-06-09 03:07:05', 'LABOREX', 1),
(5, 5, 20, 6000.00, 120000.00, '2026-06-09 03:07:05', 'CINPHARMA', 1),
(6, 6, 25, 8000.00, 200000.00, '2026-06-09 03:07:05', 'PHARMA-DRC', 1),
(7, 7, 35, 3000.00, 105000.00, '2026-06-09 03:07:05', 'MEDICOM', 1),
(8, 8, 20, 4000.00, 80000.00, '2026-06-09 03:07:05', 'LABOREX', 1),
(9, 9, 30, 7000.00, 210000.00, '2026-06-09 03:07:05', 'CINPHARMA', 1),
(10, 10, 15, 3500.00, 52500.00, '2026-06-09 03:07:05', 'PHARMA-DRC', 1);


--
-- Structure de la table `factures`
--

DROP TABLE IF EXISTS `factures`;
CREATE TABLE IF NOT EXISTS `factures` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
  `numero_facture` varchar(50) NOT NULL COMMENT 'Numéro unique de la facture (ex: FACT-20260609-001)',
  `date_facture` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date et heure de la facture',
  `client_nom` varchar(100) NOT NULL COMMENT 'Nom du client',
  `client_telephone` varchar(50) DEFAULT NULL COMMENT 'Téléphone du client',
  `client_adresse` text COMMENT 'Adresse du client',
  `total_ht` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total hors taxes',
  `total_ttc` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total toutes taxes comprises',
  `vendeur_id` int DEFAULT NULL COMMENT 'ID du vendeur qui a fait la vente',
  `vendeur_nom` varchar(100) DEFAULT NULL COMMENT 'Nom du vendeur',
  `statut` enum('payée','impayée','annulée') DEFAULT 'payée' COMMENT 'Statut de la facture',
  `notes` text COMMENT 'Notes supplémentaires',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_facture` (`numero_facture`),
  KEY `idx_numero` (`numero_facture`),
  KEY `idx_date` (`date_facture`),
  KEY `idx_client` (`client_nom`),
  KEY `vendeur_id` (`vendeur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des factures';

--
-- Déchargement des données de la table `factures`
--

INSERT INTO `factures` (`id`, `numero_facture`, `date_facture`, `client_nom`, `client_telephone`, `client_adresse`, `total_ht`, `total_ttc`, `vendeur_id`, `vendeur_nom`, `statut`, `notes`) VALUES
(1, 'FACT-20260601-001', '2026-06-09 03:07:03', 'Jean-Pierre KABILA', '081234567', 'Kinshasa, Gombe', 0.00, 35000.00, 2, 'Jean Vendeur', 'payée', NULL),
(2, 'FACT-20260601-002', '2026-06-09 03:07:03', 'Marie-Claire NTUMBA', '099876543', 'Lubumbashi, Golf', 0.00, 22500.00, 2, 'Jean Vendeur', 'payée', NULL),
(3, 'FACT-20260602-003', '2026-06-09 03:07:03', 'Joseph KASONGO', '097654321', 'Kinshasa, Limete', 0.00, 48000.00, 3, 'Marie Vendeuse', 'payée', NULL),
(4, 'FACT-20260603-004', '2026-06-09 03:07:03', 'Pauline MUKENDI', '082345678', 'Lubumbashi, Katuba', 0.00, 15000.00, 4, 'Pierre Vendeur', 'payée', NULL),
(5, 'FACT-20260603-005', '2026-06-09 03:07:03', 'Albert KALONJI', '089876543', 'Kinshasa, Ngaliema', 0.00, 32000.00, 2, 'Jean Vendeur', 'payée', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `medicaments`
--

DROP TABLE IF EXISTS `medicaments`;
CREATE TABLE IF NOT EXISTS `medicaments` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
  `code` varchar(50) NOT NULL COMMENT 'Code unique du médicament',
  `nom` varchar(100) NOT NULL COMMENT 'Nom commercial du médicament',
  `description` text COMMENT 'Description détaillée',
  `prix_achat` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Prix d''achat en FC',
  `prix_vente` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Prix de vente en FC',
  `quantite_stock` int NOT NULL DEFAULT '0' COMMENT 'Quantité disponible en stock',
  `seuil_alerte` int DEFAULT '10' COMMENT 'Seuil minimum pour alerte de réapprovisionnement',
  `photo` varchar(255) DEFAULT NULL COMMENT 'Chemin de la photo dans le dossier uploads/',
  `date_expiration` date DEFAULT NULL COMMENT 'Date d''expiration du médicament',
  `laboratoire` varchar(100) DEFAULT NULL COMMENT 'Laboratoire fabricant',
  `categorie` varchar(100) DEFAULT NULL COMMENT 'Catégorie: Antalgique, Antibiotique, etc.',
  `created_by` int DEFAULT NULL COMMENT 'ID de l''utilisateur qui a créé le médicament',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de dernière modification',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `created_by` (`created_by`),
  KEY `idx_nom` (`nom`),
  KEY `idx_code` (`code`),
  KEY `idx_categorie` (`categorie`),
  KEY `idx_expiration` (`date_expiration`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des médicaments';

--
-- Déchargement des données de la table `medicaments`
--

INSERT INTO `medicaments` (`id`, `code`, `nom`, `description`, `prix_achat`, `prix_vente`, `quantite_stock`, `seuil_alerte`, `photo`, `date_expiration`, `laboratoire`, `categorie`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 'VITC-003', 'Vitamine C 1000mg', 'Complément alimentaire - Boîte de 30 comprimés effervescents', 3000.00, 4500.00, 197, 30, NULL, NULL, 'Pharmaplus', 'Vitamines', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(4, 'IBUP-004', 'Ibuprofène 400mg', 'Anti-inflammatoire - Boîte de 20 comprimés', 2000.00, 3000.00, 117, 20, NULL, NULL, 'Labopharm', 'Anti-inflammatoire', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(5, 'ARTE-005', 'Artémisinine 120mg', 'Traitement antipaludique - Boîte de 12 comprimés', 6000.00, 9000.00, 59, 10, NULL, NULL, 'Cinpharma', 'Antipaludique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(6, 'AZIT-006', 'Azithromycine 250mg', 'Antibiotique - Boîte de 6 comprimés', 8000.00, 12000.00, 43, 8, NULL, NULL, 'Pharmaquin', 'Antibiotique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(7, 'DOLO-007', 'Doliprane 1000mg', 'Antalgique - Boîte de 8 comprimés', 3000.00, 4500.00, 88, 15, NULL, NULL, 'Medicamenta', 'Antalgique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(8, 'SPAS-008', 'Spasfon 80mg', 'Antispasmodique - Boîte de 30 comprimés', 4000.00, 6000.00, 69, 10, NULL, NULL, 'Labopharm', 'Antispasmodique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(9, 'OMEP-009', 'Oméprazole 20mg', 'Inhibiteur pompe à protons - Boîte de 28 gélules', 7000.00, 10500.00, 54, 10, NULL, NULL, 'Cinpharma', 'Antiacide', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:04'),
(10, 'DIAZ-010', 'Diazepam 10mg', 'Anxiolytique - Boîte de 30 comprimés', 3500.00, 5000.00, 38, 8, NULL, NULL, 'Pharmaplus', 'Antidépresseur', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:05'),
(11, 'LORA-011', 'Loratadine 10mg', 'Antihistaminique - Boîte de 30 comprimés', 2500.00, 4000.00, 98, 15, NULL, NULL, 'Pharmaquin', 'Antihistaminique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:05'),
(12, 'MET-012', 'Metformine 500mg', 'Antidiabétique - Boîte de 60 comprimés', 4500.00, 7000.00, 83, 12, NULL, NULL, 'Medicamenta', 'Antidiabétique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:05'),
(13, 'LIS-013', 'Lisinopril 10mg', 'Antihypertenseur - Boîte de 30 comprimés', 5500.00, 8500.00, 64, 10, NULL, NULL, 'Labopharm', 'Antihypertenseur', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:05'),
(14, 'CLOT-014', 'Clotrimazole 1%', 'Antifongique - Tube de 20g', 2000.00, 3500.00, 118, 20, NULL, NULL, 'Cinpharma', 'Antifongique', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:05'),
(15, 'ACIC-015', 'Aciclovir 5%', 'Antiviral - Tube de 10g', 4500.00, 7000.00, 50, 8, NULL, NULL, 'Pharmaplus', 'Antiviral', 1, '2026-06-09 03:07:03', '2026-06-09 03:07:03');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
  `nom` varchar(100) NOT NULL COMMENT 'Nom complet de l''utilisateur',
  `email` varchar(100) NOT NULL COMMENT 'Email (utilisé pour la connexion)',
  `mot_de_passe` varchar(255) NOT NULL COMMENT 'Mot de passe hashé en SHA256',
  `telephone` varchar(50) DEFAULT NULL COMMENT 'Numéro de téléphone',
  `role` enum('admin','vendeur') DEFAULT 'vendeur' COMMENT 'Rôle: admin ou vendeur',
  `statut` enum('actif','inactif') DEFAULT 'actif' COMMENT 'Statut du compte',
  `dernier_acces` timestamp NULL DEFAULT NULL COMMENT 'Date et heure de dernière connexion',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création du compte',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des utilisateurs';

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `email`, `mot_de_passe`, `telephone`, `role`, `statut`, `dernier_acces`, `created_at`) VALUES
(1, 'Administrateur', 'admin@pharmacie.cd', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', '+243901726290', 'admin', 'actif', '2026-06-09 19:32:27', '2026-06-09 03:07:03'),
(2, 'Jean Vendeur', 'vendeur@pharmacie.cd', '569dba69daa0e283c3a5498adc2011f7830fab26074ca7eebc7cc47c5e4b9493', '+243812345678', 'vendeur', 'actif', NULL, '2026-06-09 03:07:03'),
(3, 'Marie Vendeuse', 'marie@pharmacie.cd', '569dba69daa0e283c3a5498adc2011f7830fab26074ca7eebc7cc47c5e4b9493', '+243822345679', 'vendeur', 'actif', NULL, '2026-06-09 03:07:03'),
(4, 'Pierre Vendeur', 'pierre@pharmacie.cd', '569dba69daa0e283c3a5498adc2011f7830fab26074ca7eebc7cc47c5e4b9493', '+243832345680', 'vendeur', 'actif', NULL, '2026-06-09 03:07:03'),
(5, 'Test User', 'test@pharmacie.cd', 'ecd71870d1963316a97e3ac3408c9835ad8cf0f3c1bc703527c30265534f75ae', '+243899999999', 'vendeur', 'actif', NULL, '2026-06-09 03:07:05');

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

DROP TABLE IF EXISTS `fournisseurs`;
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
  `nom` varchar(150) NOT NULL COMMENT 'Nom du fournisseur',
  `email` varchar(150) DEFAULT NULL COMMENT 'Email du fournisseur',
  `telephone` varchar(50) DEFAULT NULL COMMENT 'Téléphone du fournisseur',
  `adresse` text DEFAULT NULL COMMENT 'Adresse du fournisseur',
  `notes` text DEFAULT NULL COMMENT 'Informations complémentaires',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  PRIMARY KEY (`id`),
  KEY `idx_nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des fournisseurs';

-- --------------------------------------------------------

--
-- Structure de la table `ventes`
--

DROP TABLE IF EXISTS `ventes`;
CREATE TABLE IF NOT EXISTS `ventes` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique',
  `facture_id` int NOT NULL COMMENT 'ID de la facture associée',
  `medicament_id` int NOT NULL COMMENT 'ID du médicament vendu',
  `medicament_nom` varchar(100) NOT NULL COMMENT 'Nom du médicament (pour archivage)',
  `quantite` int NOT NULL COMMENT 'Quantité vendue',
  `prix_unitaire` decimal(10,2) NOT NULL COMMENT 'Prix unitaire au moment de la vente',
  `total` decimal(10,2) NOT NULL COMMENT 'Total de la ligne (quantité * prix)',
  `date_vente` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de la vente',
  `vendeur_id` int DEFAULT NULL COMMENT 'ID du vendeur',
  PRIMARY KEY (`id`),
  KEY `vendeur_id` (`vendeur_id`),
  KEY `idx_facture` (`facture_id`),
  KEY `idx_medicament` (`medicament_id`),
  KEY `idx_date` (`date_vente`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table des ventes détaillées';

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `facture_id`, `medicament_id`, `medicament_nom`, `quantite`, `prix_unitaire`, `total`, `date_vente`, `vendeur_id`) VALUES
(2, 1, 3, 'Vitamine C 1000mg', 3, 4500.00, 13500.00, '2026-06-09 03:07:03', 2),
(3, 1, 5, 'Artémisinine 120mg', 1, 9000.00, 9000.00, '2026-06-09 03:07:03', 2),
(4, 1, 8, 'Spasfon 80mg', 1, 6000.00, 6000.00, '2026-06-09 03:07:03', 2),
(6, 2, 7, 'Doliprane 1000mg', 2, 4500.00, 9000.00, '2026-06-09 03:07:03', 2),
(7, 3, 4, 'Ibuprofène 400mg', 3, 3000.00, 9000.00, '2026-06-09 03:07:03', 3),
(8, 3, 6, 'Azithromycine 250mg', 2, 12000.00, 24000.00, '2026-06-09 03:07:03', 3),
(9, 3, 9, 'Oméprazole 20mg', 1, 10500.00, 10500.00, '2026-06-09 03:07:03', 3),
(10, 4, 11, 'Loratadine 10mg', 2, 4000.00, 8000.00, '2026-06-09 03:07:04', 4),
(11, 4, 14, 'Clotrimazole 1%', 2, 3500.00, 7000.00, '2026-06-09 03:07:04', 4),
(12, 5, 10, 'Diazepam 10mg', 2, 5000.00, 10000.00, '2026-06-09 03:07:04', 2),
(13, 5, 12, 'Metformine 500mg', 2, 7000.00, 14000.00, '2026-06-09 03:07:04', 2),
(14, 5, 13, 'Lisinopril 10mg', 1, 8500.00, 8500.00, '2026-06-09 03:07:04', 2);

--
-- Déclencheurs `ventes`
--
DROP TRIGGER IF EXISTS `after_vente_insert`;
DELIMITER $$
CREATE TRIGGER `after_vente_insert` AFTER INSERT ON `ventes` FOR EACH ROW BEGIN
    UPDATE medicaments 
    SET quantite_stock = quantite_stock - NEW.quantite
    WHERE id = NEW.medicament_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_top_medicaments_mois`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_top_medicaments_mois`;
CREATE TABLE IF NOT EXISTS `vue_top_medicaments_mois` (
`nom` varchar(100)
,`quantite_vendue` decimal(32,0)
,`total_ca` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_ventes_jour`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_ventes_jour`;
CREATE TABLE IF NOT EXISTS `vue_ventes_jour` (
`ca` decimal(32,2)
,`date_vente` date
,`nb_articles` decimal(32,0)
,`nb_factures` bigint
,`vendeur` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure de la vue `vue_top_medicaments_mois`
--
DROP TABLE IF EXISTS `vue_top_medicaments_mois`;

DROP VIEW IF EXISTS `vue_top_medicaments_mois`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_top_medicaments_mois`  AS SELECT `m`.`nom` AS `nom`, sum(`v`.`quantite`) AS `quantite_vendue`, sum(`v`.`total`) AS `total_ca` FROM (`ventes` `v` join `medicaments` `m` on((`v`.`medicament_id` = `m`.`id`))) WHERE (month(`v`.`date_vente`) = month(curdate())) GROUP BY `m`.`id` ORDER BY `quantite_vendue` DESC LIMIT 0, 10 ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_ventes_jour`
--
DROP TABLE IF EXISTS `vue_ventes_jour`;

DROP VIEW IF EXISTS `vue_ventes_jour`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_ventes_jour`  AS SELECT cast(`v`.`date_vente` as date) AS `date_vente`, `u`.`nom` AS `vendeur`, count(distinct `v`.`facture_id`) AS `nb_factures`, sum(`v`.`quantite`) AS `nb_articles`, sum(`v`.`total`) AS `ca` FROM (`ventes` `v` join `utilisateurs` `u` on((`v`.`vendeur_id` = `u`.`id`))) WHERE (cast(`v`.`date_vente` as date) = curdate()) GROUP BY cast(`v`.`date_vente` as date), `u`.`id` ;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `achats`
--
ALTER TABLE `achats`
  ADD CONSTRAINT `achats_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `achats_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `factures_ibfk_1` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `medicaments`
--
ALTER TABLE `medicaments`
  ADD CONSTRAINT `medicaments_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ventes_ibfk_2` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ventes_ibfk_3` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
