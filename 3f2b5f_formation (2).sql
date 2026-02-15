-- phpMyAdmin SQL Dump
-- version 4.9.6
-- https://www.phpmyadmin.net/
--
-- Hôte : 3f2b5f.myd.infomaniak.com
-- Généré le :  Dim 15 fév. 2026 à 20:51
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

-- --------------------------------------------------------

--
-- Structure de la table `discord_logs`
--

CREATE TABLE `discord_logs` (
  `id` int(11) NOT NULL DEFAULT 1,
  `message_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Déchargement des données de la table `discord_logs`
--

INSERT INTO `discord_logs` (`id`, `message_id`) VALUES
(1, '1471801562610274345');

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

--
-- Déchargement des données de la table `formateurs`
--

INSERT INTO `formateurs` (`id`, `discord_id`, `pseudo`, `created_at`) VALUES
(1, '882717172575653928', 'Nivokai', '2026-02-12 08:00:48'),
(4, '506093210318274570', 'Sharingan', '2026-02-12 08:17:59'),
(5, '821483895282204732', 'Random', '2026-02-13 08:03:50'),
(6, '454318011696807957', 'Maybe Ange', '2026-02-13 08:04:12'),
(7, '469647594793205780', 'QLF', '2026-02-13 08:04:24'),
(8, '199889993273966592', 'Astyzia', '2026-02-13 08:37:08'),
(9, '599648426568712213', 'jules_gpr', '2026-02-13 08:37:16'),
(10, '593489056797556751', 'maalty', '2026-02-13 08:37:25'),
(11, '992500077794955285', 'ManFol', '2026-02-13 09:08:56'),
(12, '581601020392505374', 'MURDER', '2026-02-13 09:09:06'),
(13, '304980625868455942', 'Nano', '2026-02-13 09:09:13'),
(14, '568157851810267137', 'PRINCESSE', '2026-02-13 14:37:16'),
(15, '539929252641112080', 'Pucc', '2026-02-13 14:37:29'),
(16, '462716512252329996', 'Rayane', '2026-02-13 14:37:57'),
(17, '239518191380987905', 'stillfresh', '2026-02-13 14:38:08'),
(18, '682572056201658372', 'ZeKirito', '2026-02-13 14:38:21');

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

--
-- Déchargement des données de la table `formations`
--

INSERT INTO `formations` (`id`, `titre`, `referent_id`, `created_at`, `doc_link_2025`, `doc_link_2026`, `qst_link`) VALUES
(5, 'Modérateur', NULL, '2026-02-12 07:52:33', NULL, 'https://www.canva.com/design/DAG_owyc4ts/8Tk4WYoo6apDYElv-JLukA/edit', 'https://docs.google.com/forms/d/1RQY0Jzu3VARt7psVSjvrmWFNyj-WPIuAWy3USDirBAo/viewform?edit_requested=true'),
(6, 'Support', NULL, '2026-02-12 08:26:10', NULL, 'https://www.canva.com/design/DAG_m-rc22s/dwZXW1vWgiqVXCfGVvbT2Q/edit', 'https://forms.gle/1aE5S7rq7fdmRLJL6'),
(7, 'FreeKill', NULL, '2026-02-12 10:33:39', NULL, 'https://www.canva.com/design/DAG9TckUNQI/0UApTVXv9vHI7dIUBhHMBA/edit?utm_content=DAG9TckUNQI&utm_campaign=designshare&utm_medium=link2&utm_source=sharebutton', 'https://docs.google.com/forms/d/1kNd6La9J1fFQ9Yf96rOQvfwpCRg5rMIPyCNqh28_glk/edit'),
(8, 'Remboursement', 7, '2026-02-12 18:13:39', NULL, 'https://www.canva.com/design/DAG_1b8EHGE/hmP1ruw6IGzgCZpGH_vl1Q/edit', 'https://forms.gle/y2BwSTnR1BGBffWx6'),
(9, 'Wipe', NULL, '2026-02-13 08:07:35', NULL, 'https://www.canva.com/design/DAG-_HSkGl0/CaiJAKC9ZLzE92w0Jpw77A/edit', 'https://forms.gle/i2CNCwEEJre8RriN6'),
(10, 'Ticket', NULL, '2026-02-13 08:08:28', NULL, 'https://www.canva.com/design/DAHASqP1Il0/S8NdnS2GaC8M2H-9bAJj2w/edit', ''),
(11, 'Légal', 8, '2026-02-13 08:13:07', NULL, 'https://www.canva.com/design/DAG-mFOfb7Y/9KjHZvX0OUSvp-FBaIrYNg/edit', 'https://forms.gle/UBV7Xq9asW3V2PhJ7'),
(12, 'Management', 1, '2026-02-13 08:14:52', NULL, 'https://www.canva.com/design/DAG_tQvg6kg/hev0acYuCWbA53viPCrJUg/edit', ''),
(13, 'Ilegal', NULL, '2026-02-13 21:37:19', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `formation_staff`
--

CREATE TABLE `formation_staff` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `formateur_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `formation_staff`
--

INSERT INTO `formation_staff` (`id`, `formation_id`, `formateur_id`) VALUES
(14, 7, 1),
(16, 11, 1),
(17, 7, 17),
(18, 11, 16),
(19, 12, 4),
(20, 12, 5),
(22, 12, 12),
(23, 5, 4),
(24, 5, 17),
(25, 5, 13),
(26, 5, 12),
(27, 6, 5),
(28, 8, 8),
(29, 8, 7),
(30, 8, 10);

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

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id`, `utilisateur`, `action`, `date_action`) VALUES
(350, 'nivokai', 'A vidé l\'historique des logs', '2026-02-14 20:49:04'),
(351, 'nivokai', 'A consulté le tableau de bord', '2026-02-14 20:49:06'),
(352, 'astyzia', 'A consulté le tableau de bord', '2026-02-14 21:28:18'),
(353, 'astyzia', 'A consulté le tableau de bord', '2026-02-14 21:34:21'),
(354, 'nivokai', 'A consulté le tableau de bord', '2026-02-15 16:23:48'),
(355, 'adchat', 'A consulté le tableau de bord', '2026-02-15 19:47:49'),
(356, 'adchat', 'A consulté le tableau de bord', '2026-02-15 19:48:40'),
(357, 'adchat', 'A consulté le tableau de bord', '2026-02-15 19:49:30'),
(358, 'adchat', 'A consulté le tableau de bord', '2026-02-15 19:51:11'),
(359, 'adchat', 'A consulté le tableau de bord', '2026-02-15 19:51:15');

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

--
-- Déchargement des données de la table `membres_formes`
--

INSERT INTO `membres_formes` (`id`, `discord_id`, `pseudo`, `role_obtenu`, `formation_id`, `date_reussite`, `formateur_nom`) VALUES
(153, '547968713694117905', 'Louna', 'Modérateur', NULL, '2026-02-14', NULL),
(154, '665663319071260714§', 'Stylix', 'Modérateur', NULL, '2026-02-14', NULL),
(155, '653667050908155934', 'nyro', 'Modérateur', NULL, '2026-02-14', NULL),
(156, '795268751015542804', 'rs', 'Modérateur', NULL, '2026-02-14', NULL),
(157, '1135651032072978522', 'Agentfire', 'Modérateur', NULL, '2026-02-14', NULL),
(158, '469647594793205780', 'QLF', 'Modérateur', NULL, '2026-02-14', NULL),
(159, '1033767191109185566', 'Wes', 'Modérateur', NULL, '2026-02-14', NULL),
(160, '1156052740842197044', 'Antho', 'Modérateur', NULL, '2026-02-14', NULL),
(161, '199889993273966592', 'Astyzia', 'Modérateur', NULL, '2026-02-14', NULL),
(162, '1293633098495557652', 'Bianca', 'Modérateur', NULL, '2026-02-14', NULL),
(163, '611919979398299658', 'Daimao', 'Modérateur', NULL, '2026-02-14', NULL),
(164, '676684303031206067', 'Fianso', 'Modérateur', NULL, '2026-02-14', NULL),
(165, '250607073107247104', 'Fox', 'Modérateur', NULL, '2026-02-14', NULL),
(166, '631944896567312405', 'Félix', 'Modérateur', NULL, '2026-02-14', NULL),
(167, '263631289272369164', 'Ismayah', 'Modérateur', NULL, '2026-02-14', NULL),
(168, '1037044901579333722', 'Cuchillo', 'Modérateur', NULL, '2026-02-14', NULL),
(169, '261596866779676673', 'Keyfreez', 'Modérateur', NULL, '2026-02-14', NULL),
(170, '547570384883417088', 'KleEwi', 'Modérateur', NULL, '2026-02-14', NULL),
(171, '1215290514262462494', 'Law', 'Modérateur', NULL, '2026-02-14', NULL),
(172, '363388112107470848', 'Liaména', 'Modérateur', NULL, '2026-02-14', NULL),
(173, '992500077794955285', 'ManFol', 'Modérateur', NULL, '2026-02-14', NULL),
(174, '581601020392505374', 'Murder', 'Modérateur', NULL, '2026-02-14', NULL),
(175, '572188910835204097', 'Nero', 'Modérateur', NULL, '2026-02-14', NULL),
(176, '304980625868455942', 'Nano', 'Modérateur', NULL, '2026-02-14', NULL),
(177, '714656494402011177', 'Neversyy', 'Modérateur', NULL, '2026-02-14', NULL),
(178, '882717172575653928', 'Nivokai', 'Modérateur', NULL, '2026-02-14', NULL),
(179, '1409545653968703538', 'NyxFall', 'Modérateur', NULL, '2026-02-14', NULL),
(180, '999416966450258032', 'Nz', 'Modérateur', NULL, '2026-02-14', NULL),
(181, '463004960867745792', 'Pistouille', 'Modérateur', NULL, '2026-02-14', NULL),
(182, '539929252641112080', 'Pucc', 'Modérateur', NULL, '2026-02-14', NULL),
(183, '621271441681416192', 'Pyt', 'Modérateur', NULL, '2026-02-14', NULL),
(184, '821483895282204732', 'Random', 'Modérateur', NULL, '2026-02-14', NULL),
(185, '709197190429540476', 'Rayan13k14', 'Modérateur', NULL, '2026-02-14', NULL),
(186, '812379666962055168', 'Redbull', 'Modérateur', NULL, '2026-02-14', NULL),
(187, '309370022898171905', 'Sashoof', 'Modérateur', NULL, '2026-02-14', NULL),
(188, '492427161765150720', 'Shadow', 'Modérateur', NULL, '2026-02-14', NULL),
(189, '506093210318274570', 'Sharingan141', 'Modérateur', NULL, '2026-02-14', NULL),
(190, '1106359474198949928', 'Sora', 'Modérateur', NULL, '2026-02-14', NULL),
(191, '1059567293681651782', 'Strictop', 'Modérateur', NULL, '2026-02-14', NULL),
(192, '1105576606124212396', 'Sunshine', 'Modérateur', NULL, '2026-02-14', NULL),
(193, '212636665879986178', 'Soya', 'Modérateur', NULL, '2026-02-14', NULL),
(194, '595226484449345547', 'Uzw3y', 'Modérateur', NULL, '2026-02-14', NULL),
(195, '443887453812686858', 'Zak', 'Modérateur', NULL, '2026-02-14', NULL),
(196, '682572056201658372', 'ZeKirito', 'Modérateur', NULL, '2026-02-14', NULL),
(197, '820245989230247990', 'Zerotwo', 'Modérateur', NULL, '2026-02-14', NULL),
(198, '599648426568712213', 'Jules_gpr', 'Modérateur', NULL, '2026-02-14', NULL),
(199, '1370749639124455568', 'Laciteyyy', 'Modérateur', NULL, '2026-02-14', NULL),
(200, '736169071187591168', 'Lau', 'Modérateur', NULL, '2026-02-14', NULL),
(201, '593489056797556751', 'Maalty', 'Modérateur', NULL, '2026-02-14', NULL),
(202, '959452260587409418', 'Skryzz', 'Modérateur', NULL, '2026-02-14', NULL),
(203, '1187384153549832213', 'Theia', 'Modérateur', NULL, '2026-02-14', NULL),
(204, '1045026154656038972', 'STLS', 'Modérateur', NULL, '2026-02-14', NULL),
(205, '413294822460489738', 'Eldryss', 'Modérateur', NULL, '2026-02-14', NULL),
(206, '260026373307891712', 'Misssmouflette', 'Modérateur', NULL, '2026-02-14', NULL),
(207, '458289003373002763', 'Taak', 'Modérateur', NULL, '2026-02-14', NULL),
(208, '873292741218566204', 'Shaketonbody13', 'Modérateur', NULL, '2026-02-14', NULL),
(209, '568157851810267137', 'Princesse', 'Modérateur', NULL, '2026-02-14', NULL),
(210, '472168580214161438', 'Abysse', 'Support', NULL, '2026-02-14', NULL),
(216, '1392723858427084960', 'Nazko', 'Support', NULL, '2026-02-14', NULL),
(226, '1135651032072978522', 'Agentfire', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(227, '1156052740842197044', 'Antho', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(228, '199889993273966592', 'Astyzia', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(229, '1293633098495557652', 'Bianca', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(230, '1037044901579333722', 'Cuchillo', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(231, '611919979398299658', 'Daimao', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(232, '413294822460489738', 'Eldryss', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(233, '631944896567312405', 'Félix', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(234, '676684303031206067', 'Fianso', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(235, '250607073107247104', 'Fox', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(236, '263631289272369164', 'Ismayah', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(237, '599648426568712213', 'Jules_gpr', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(238, '261596866779676673', 'Keyfreez', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(239, '547570384883417088', 'KleEwi', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(240, '1370749639124455568', 'Laciteyyy', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(241, '736169071187591168', 'Lau', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(242, '1215290514262462494', 'Law', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(243, '363388112107470848', 'Liaména', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(244, '547968713694117905', 'Louna', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(245, '593489056797556751', 'Maalty', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(246, '992500077794955285', 'ManFol', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(247, '260026373307891712', 'Misssmouflette', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(248, '581601020392505374', 'Murder', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(249, '304980625868455942', 'Nano', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(250, '572188910835204097', 'Nero', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(251, '714656494402011177', 'Neversyy', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(252, '882717172575653928', 'Nivokai', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(253, '653667050908155934', 'nyro', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(254, '1409545653968703538', 'NyxFall', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(255, '999416966450258032', 'Nz', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(256, '463004960867745792', 'Pistouille', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(257, '568157851810267137', 'Princesse', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(258, '539929252641112080', 'Pucc', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(259, '621271441681416192', 'Pyt', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(260, '469647594793205780', 'QLF', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(261, '821483895282204732', 'Random', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(262, '709197190429540476', 'Rayan13k14', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(263, '812379666962055168', 'Redbull', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(264, '795268751015542804', 'rs', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(265, '309370022898171905', 'Sashoof', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(266, '492427161765150720', 'Shadow', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(267, '873292741218566204', 'Shaketonbody13', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(268, '506093210318274570', 'Sharingan141', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(269, '959452260587409418', 'Skryzz', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(270, '1106359474198949928', 'Sora', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(271, '212636665879986178', 'Soya', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(272, '1045026154656038972', 'STLS', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(273, '1059567293681651782', 'Strictop', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(274, '665663319071260714§', 'Stylix', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(275, '1105576606124212396', 'Sunshine', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(276, '458289003373002763', 'Taak', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(277, '1187384153549832213', 'Theia', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(278, '595226484449345547', 'Uzw3y', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(279, '1033767191109185566', 'Wes', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(280, '443887453812686858', 'Zak', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(281, '682572056201658372', 'ZeKirito', 'Modérateur', 6, '2026-02-14', 'nivokai'),
(282, '820245989230247990', 'Zerotwo', 'Modérateur', 6, '2026-02-14', 'nivokai');

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

--
-- Déchargement des données de la table `planning`
--

INSERT INTO `planning` (`id`, `formation_id`, `date`, `heure`, `formateur`) VALUES
(8, 11, '2026-02-06', '12:00:00', 'Astzia'),
(11, 7, '2026-01-13', '10:12:00', 'Astzia'),
(14, 7, '2026-02-02', '12:00:00', 'Astyzia'),
(15, 7, '2026-02-20', '12:00:00', 'Astyzia');

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
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `discord_id`, `username`, `avatar`, `role`, `created_at`) VALUES
(4, '506093210318274570', 'sharingan141', 'https://cdn.discordapp.com/avatars/506093210318274570/ad02a006ecb531129afb70dce77fe641.png', 'admin', '2026-02-12 20:23:40'),
(5, '211579197242605568', 'folkruw', 'https://cdn.discordapp.com/avatars/211579197242605568/7d8e3b96c894c5af7563bd4a968fdfc7.png', 'admin', '2026-02-13 18:44:26'),
(6, '734796394069753856', 'adchat', 'https://cdn.discordapp.com/avatars/734796394069753856/5ba319622fc8063410aa86b6abbedeb4.png', 'user', '2026-02-13 19:05:48'),
(7, '199889993273966592', 'astyzia', 'https://cdn.discordapp.com/avatars/199889993273966592/57028f205a49fcdc7bf03d3a1a7969b0.png', 'user', '2026-02-13 19:14:23'),
(8, '411241324482920460', '_.picasso._', 'https://cdn.discordapp.com/avatars/411241324482920460/5053a9adc3a522f3b5e269b795ddde85.png', 'user', '2026-02-13 21:11:35'),
(9, '462716512252329996', 'rayane83', 'https://cdn.discordapp.com/avatars/462716512252329996/e68a9bb3cd1982b5323fc510f3aeb78c.png', 'admin', '2026-02-13 22:06:34'),
(10, '821483895282204732', 'lqwm', 'https://cdn.discordapp.com/avatars/821483895282204732/129cfcd31c961d0b52dd3d1df7b61210.png', 'admin', '2026-02-13 23:31:26'),
(11, '882717172575653928', 'nivokai', 'https://cdn.discordapp.com/avatars/882717172575653928/bdc7448ffdb743f2fac29b1c0155c6b9.png', 'admin', '2026-02-14 12:49:17');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `formation_staff`
--
ALTER TABLE `formation_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=359;

--
-- AUTO_INCREMENT pour la table `membres_formes`
--
ALTER TABLE `membres_formes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT pour la table `planning`
--
ALTER TABLE `planning`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Contraintes pour les tables déchargées
--

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
