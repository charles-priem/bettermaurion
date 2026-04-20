-- ============================================
-- Junia Salles - Base de Données
-- ============================================

-- ============================================
-- Table USERS (Utilisateurs)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  firstname VARCHAR(100) NOT NULL,
  lastname VARCHAR(100) NOT NULL,
  photo_profil VARCHAR(255) DEFAULT 'default_profile.png',
  promotion VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table PROMOTIONS (Promotions/Années)
-- ============================================
CREATE TABLE IF NOT EXISTS promotions (
  promotion_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL,
  label VARCHAR(150),
  description TEXT,
  year INT,
  cycle VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table PLANNINGS (Emplois du Temps)
-- ============================================
CREATE TABLE IF NOT EXISTS plannings (
  planning_id INT PRIMARY KEY AUTO_INCREMENT,
  planning_name VARCHAR(255) UNIQUE NOT NULL,
  planning_label VARCHAR(255),
  promotion_id INT,
  json_file VARCHAR(255),
  event_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (promotion_id) REFERENCES promotions(promotion_id) ON DELETE SET NULL,
  INDEX idx_planning_name (planning_name),
  INDEX idx_promotion_id (promotion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table EVENTS (Événements d'emploi du temps)
-- ============================================
CREATE TABLE IF NOT EXISTS events (
  event_id INT PRIMARY KEY AUTO_INCREMENT,
  planning_id INT NOT NULL,
  event_title VARCHAR(500),
  salle VARCHAR(255),
  matiere VARCHAR(255),
  prof VARCHAR(255),
  type_event VARCHAR(50),
  start_time DATETIME,
  end_time DATETIME,
  all_day BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (planning_id) REFERENCES plannings(planning_id) ON DELETE CASCADE,
  INDEX idx_planning_id (planning_id),
  INDEX idx_start_time (start_time),
  INDEX idx_type_event (type_event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table USER_PREFERENCES (Préférences Utilisateur)
-- ============================================
CREATE TABLE IF NOT EXISTS user_preferences (
  preference_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  visible_types VARCHAR(255) DEFAULT 'cours,tp,td,projet,exam',
  last_promotion VARCHAR(100),
  last_planning VARCHAR(255),
  language VARCHAR(10) DEFAULT 'fr',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  UNIQUE KEY unique_user_prefs (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table BUILDINGS (Bâtiments)
-- ============================================
CREATE TABLE IF NOT EXISTS buildings (
  building_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL,
  code VARCHAR(50),
  location VARCHAR(255),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table ROOMS (Salles)
-- ============================================
CREATE TABLE IF NOT EXISTS rooms (
  room_id INT PRIMARY KEY AUTO_INCREMENT,
  room_name VARCHAR(100) UNIQUE NOT NULL,
  building_id INT,
  capacity INT,
  type VARCHAR(50),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE SET NULL,
  INDEX idx_room_name (room_name),
  INDEX idx_building_id (building_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Données de Test
-- ============================================

-- Promotions
INSERT IGNORE INTO promotions (name, label, year, cycle) VALUES
('ADIMAKER', 'ADIMAKER', 1, 'A1'),
('ADIMAKER', 'ADIMAKER', 1, 'A2'),
('HEI_Ingenieur', 'HEI Ingénieur', 3, 'A3'),
('CIR', 'Cycle Informatique Renforcé', 1, 'CIR1'),
('CSI', 'Cybersécurité et Systèmes d\'Information', 3, 'CSI3'),
('AP', 'Apprentissage', 3, 'AP3'),
('Master', 'Master', 1, 'M1');

-- Bâtiments
INSERT IGNORE INTO buildings (name, code, location, description) VALUES
('IC1', 'IC1', 'Lille', 'Bâtiment IC1'),
('IC2', 'IC2', 'Lille', 'Bâtiment IC2'),
('ALG', 'ALG', 'Lille', 'Bâtiment ALG'),
('MF', 'MF', 'Lille', 'Bâtiment MF');

-- Salles
INSERT IGNORE INTO rooms (room_name, building_id, capacity, type) VALUES
('BORDEAUX_148', 1, 30, 'Classroom'),
('BORDEAUX_149', 1, 30, 'Classroom'),
('TOULOUSE_201', 2, 50, 'Amphitheater'),
('LYON_101', 3, 20, 'Lab'),
('MARSEILLE_301', 4, 40, 'Classroom');
