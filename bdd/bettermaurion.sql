-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : mer. 22 avr. 2026 à 15:05
-- Version du serveur : 5.7.24
-- Version de PHP : 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `bettermaurion`
--

-- --------------------------------------------------------

--
-- Structure de la table `buildings`
--

CREATE TABLE `buildings` (
  `building_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `buildings`
--

INSERT INTO `buildings` (`building_id`, `name`, `code`, `location`, `description`, `created_at`, `updated_at`) VALUES
(1, 'IC1', 'IC1', 'Lille', 'Bâtiment IC1', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(2, 'IC2', 'IC2', 'Lille', 'Bâtiment IC2', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(3, 'ALG', 'ALG', 'Lille', 'Bâtiment ALG', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(4, 'MF', 'MF', 'Lille', 'Bâtiment MF', '2026-04-20 20:54:44', '2026-04-20 20:54:44');

-- --------------------------------------------------------

--
-- Structure de la table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `planning_id` int(11) NOT NULL,
  `event_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matiere` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prof` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_event` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `all_day` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plannings`
--

CREATE TABLE `plannings` (
  `planning_id` int(11) NOT NULL,
  `planning_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `planning_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `json_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `promotions`
--

CREATE TABLE `promotions` (
  `promotion_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `year` int(11) DEFAULT NULL,
  `cycle` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `promotions`
--

INSERT INTO `promotions` (`promotion_id`, `name`, `label`, `description`, `year`, `cycle`, `created_at`, `updated_at`) VALUES
(1, 'ADIMAKER', 'ADIMAKER', NULL, 1, 'A1', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(2, 'HEI_Ingenieur', 'HEI Ingénieur', NULL, 3, 'A3', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(3, 'CIR', 'Cycle Informatique Renforcé', NULL, 1, 'CIR1', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(4, 'CSI', 'Cybersécurité et Systèmes d\'Information', NULL, 3, 'CSI3', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(5, 'AP', 'Apprentissage', NULL, 3, 'AP3', '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(6, 'Master', 'Master', NULL, 1, 'M1', '2026-04-20 20:54:44', '2026-04-20 20:54:44');

-- --------------------------------------------------------

--
-- Structure de la table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `building_id`, `capacity`, `type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'BORDEAUX_148', 1, 30, 'Classroom', NULL, '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(2, 'BORDEAUX_149', 1, 30, 'Classroom', NULL, '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(3, 'TOULOUSE_201', 2, 50, 'Amphitheater', NULL, '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(4, 'LYON_101', 3, 20, 'Lab', NULL, '2026-04-20 20:54:44', '2026-04-20 20:54:44'),
(5, 'MARSEILLE_301', 4, 40, 'Classroom', NULL, '2026-04-20 20:54:44', '2026-04-20 20:54:44');

-- --------------------------------------------------------

--
-- Structure de la table `room_reservations`
--

CREATE TABLE `room_reservations` (
  `reservation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `building_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT '120',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `room_reservations`
--

INSERT INTO `room_reservations` (`reservation_id`, `user_id`, `building_code`, `room_name`, `start_time`, `end_time`, `duration_minutes`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'IC1', 'IC1_017', '2026-04-21 08:00:00', '2026-04-21 10:00:00', 120, 'confirmed', '2026-04-21 16:01:38', '2026-04-21 16:01:38'),
(2, 1, 'IC1', 'IC1_017', '2026-04-21 10:00:00', '2026-04-21 12:00:00', 120, 'confirmed', '2026-04-21 16:01:38', '2026-04-21 16:01:38'),
(3, 1, 'IC1', 'IC1_102', '2026-04-21 18:00:00', '2026-04-21 20:00:00', 120, 'confirmed', '2026-04-21 16:01:45', '2026-04-21 16:01:45'),
(4, 1, 'IC1', 'IC1_427', '2026-04-22 08:00:00', '2026-04-22 10:00:00', 120, 'confirmed', '2026-04-22 14:35:17', '2026-04-22 14:35:17'),
(5, 1, 'IC1', 'IC1_427', '2026-04-22 10:00:00', '2026-04-22 12:00:00', 120, 'confirmed', '2026-04-22 14:35:17', '2026-04-22 14:35:17'),
(6, 1, 'IC1', 'IC1_427', '2026-04-22 13:00:00', '2026-04-22 15:00:00', 120, 'confirmed', '2026-04-22 14:35:24', '2026-04-22 14:35:24'),
(7, 1, 'IC1', 'IC1_427', '2026-04-22 15:00:00', '2026-04-22 17:00:00', 120, 'confirmed', '2026-04-22 14:35:24', '2026-04-22 14:35:24'),
(8, 1, 'IC1', 'IC1_427', '2026-04-22 18:00:00', '2026-04-22 20:00:00', 120, 'confirmed', '2026-04-22 14:35:24', '2026-04-22 14:35:24'),
(9, 1, 'IC1', 'IC1_419', '2026-04-22 08:00:00', '2026-04-22 10:00:00', 120, 'confirmed', '2026-04-22 14:35:28', '2026-04-22 14:35:28'),
(10, 1, 'IC1', 'IC1_419', '2026-04-22 10:00:00', '2026-04-22 12:00:00', 120, 'confirmed', '2026-04-22 14:35:28', '2026-04-22 14:35:28'),
(11, 1, 'IC1', 'IC1_419', '2026-04-22 13:00:00', '2026-04-22 15:00:00', 120, 'confirmed', '2026-04-22 14:35:28', '2026-04-22 14:35:28'),
(12, 1, 'IC1', 'IC1_419', '2026-04-22 15:00:00', '2026-04-22 17:00:00', 120, 'confirmed', '2026-04-22 14:35:28', '2026-04-22 14:35:28'),
(13, 1, 'IC1', 'IC1_419', '2026-04-22 18:00:00', '2026-04-22 20:00:00', 120, 'confirmed', '2026-04-22 14:35:28', '2026-04-22 14:35:28');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_profil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'default_profile.png',
  `promotion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_admin` tinyint(1) DEFAULT '0',
  `is_banned` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `firstname`, `lastname`, `photo_profil`, `promotion`, `created_at`, `updated_at`, `is_admin`, `is_banned`) VALUES
(1, 'fz@student.junia.com', '$2y$10$lF7uB41DLSbccSfalaiSo.r43.jIsvLzobDvhe903B8w.LajLqw.a', 'jaen', 'fz', 'profil_1.png', NULL, '2026-04-21 16:01:17', '2026-04-22 15:02:42', 1, 0);

-- --------------------------------------------------------

--
-- Structure de la table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `visible_types` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'cours,tp,td,projet,exam',
  `last_promotion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_planning` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'fr',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`building_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Index pour la table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_planning_id` (`planning_id`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_type_event` (`type_event`);

--
-- Index pour la table `plannings`
--
ALTER TABLE `plannings`
  ADD PRIMARY KEY (`planning_id`),
  ADD UNIQUE KEY `planning_name` (`planning_name`),
  ADD KEY `idx_planning_name` (`planning_name`),
  ADD KEY `idx_promotion_id` (`promotion_id`);

--
-- Index pour la table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`promotion_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Index pour la table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_name` (`room_name`),
  ADD KEY `idx_room_name` (`room_name`),
  ADD KEY `idx_building_id` (`building_id`);

--
-- Index pour la table `room_reservations`
--
ALTER TABLE `room_reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD UNIQUE KEY `uniq_room_slot` (`building_code`,`room_name`,`start_time`,`end_time`),
  ADD KEY `idx_building_room_time` (`building_code`,`room_name`,`start_time`,`end_time`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_start_time` (`start_time`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Index pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `unique_user_prefs` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `building_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `plannings`
--
ALTER TABLE `plannings`
  MODIFY `planning_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `promotion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `room_reservations`
--
ALTER TABLE `room_reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`planning_id`) REFERENCES `plannings` (`planning_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `plannings`
--
ALTER TABLE `plannings`
  ADD CONSTRAINT `plannings_ibfk_1` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`promotion_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`building_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
