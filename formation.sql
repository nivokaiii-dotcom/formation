-- phpMyAdmin SQL Dump
-- version 4.9.6
-- https://www.phpmyadmin.net/
--
-- Hôte : 3f2b5f.myd.infomaniak.com
-- Généré le :  mer. 29 avr. 2026 à 11:07
-- Version du serveur :  10.11.14-MariaDB-deb11-log
-- Version de PHP :  7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `3f2b5f_formation`
--
CREATE DATABASE IF NOT EXISTS `3f2b5f_formation` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
USE `3f2b5f_formation`;

-- --------------------------------------------------------

--
-- Structure de la table `discord_logs`
--

CREATE TABLE `discord_logs` (
  `id` int(11) NOT NULL DEFAULT 1,
  `message_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formateurs`
--

CREATE TABLE `formateurs` (
  `id` int(11) NOT NULL,
  `discord_id` varchar(50) NOT NULL,
  `pseudo` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formations`
--

CREATE TABLE `formations` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `referent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `doc_link_2025` varchar(255) DEFAULT NULL,
  `doc_link_2026` varchar(255) DEFAULT NULL,
  `qst_link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formation_custom_fields`
--

CREATE TABLE `formation_custom_fields` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `field_type` varchar(50) NOT NULL,
  `label` varchar(255) NOT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `required` tinyint(1) DEFAULT 0,
  `order_index` int(11) DEFAULT 0,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formation_custom_field_types`
--

CREATE TABLE `formation_custom_field_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formation_field_values`
--

CREATE TABLE `formation_field_values` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `custom_field_id` int(11) NOT NULL,
  `value` longtext DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formation_staff`
--

CREATE TABLE `formation_staff` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `formateur_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL,
  `action` text DEFAULT NULL,
  `date_action` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `membres_formes`
--

CREATE TABLE `membres_formes` (
  `id` int(11) NOT NULL,
  `discord_id` varchar(255) NOT NULL,
  `pseudo` varchar(255) NOT NULL,
  `role_obtenu` enum('Modérateur','Support') NOT NULL,
  `formation_id` int(11) DEFAULT NULL,
  `date_reussite` date NOT NULL,
  `formateur_nom` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `planning`
--

CREATE TABLE `planning` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `heure` time NOT NULL,
  `formateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `discord_id` varchar(50) NOT NULL,
  `username` varchar(100) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `discord_logs`
--
ALTER TABLE `discord_logs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `formateurs`
--
ALTER TABLE `formateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `discord_id` (`discord_id`);

--
-- Index pour la table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `formation_custom_fields`
--
ALTER TABLE `formation_custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `formation_idx` (`formation_id`),
  ADD KEY `order_idx` (`formation_id`,`order_index`),
  ADD KEY `field_type` (`field_type`);

--
-- Index pour la table `formation_custom_field_types`
--
ALTER TABLE `formation_custom_field_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `formation_field_values`
--
ALTER TABLE `formation_field_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_field_value` (`formation_id`,`custom_field_id`),
  ADD KEY `formation_idx` (`formation_id`),
  ADD KEY `custom_field_id` (`custom_field_id`);

--
-- Index pour la table `formation_staff`
--
ALTER TABLE `formation_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `formation_id` (`formation_id`),
  ADD KEY `formateur_id` (`formateur_id`);

--
-- Index pour la table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `membres_formes`
--
ALTER TABLE `membres_formes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `formation_id` (`formation_id`);

--
-- Index pour la table `planning`
--
ALTER TABLE `planning`
  ADD PRIMARY KEY (`id`),
  ADD KEY `formation_id` (`formation_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `discord_id` (`discord_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `formateurs`
--
ALTER TABLE `formateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `formation_custom_fields`
--
ALTER TABLE `formation_custom_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `formation_custom_field_types`
--
ALTER TABLE `formation_custom_field_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `formation_field_values`
--
ALTER TABLE `formation_field_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `formation_staff`
--
ALTER TABLE `formation_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `membres_formes`
--
ALTER TABLE `membres_formes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `planning`
--
ALTER TABLE `planning`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `formation_custom_fields`
--
ALTER TABLE `formation_custom_fields`
  ADD CONSTRAINT `formation_custom_fields_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `formation_custom_fields_ibfk_2` FOREIGN KEY (`field_type`) REFERENCES `formation_custom_field_types` (`name`);

--
-- Contraintes pour la table `formation_field_values`
--
ALTER TABLE `formation_field_values`
  ADD CONSTRAINT `formation_field_values_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `formation_field_values_ibfk_2` FOREIGN KEY (`custom_field_id`) REFERENCES `formation_custom_fields` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `formation_staff`
--
ALTER TABLE `formation_staff`
  ADD CONSTRAINT `formation_staff_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `formation_staff_ibfk_2` FOREIGN KEY (`formateur_id`) REFERENCES `formateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `membres_formes`
--
ALTER TABLE `membres_formes`
  ADD CONSTRAINT `membres_formes_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `planning`
--
ALTER TABLE `planning`
  ADD CONSTRAINT `planning_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
