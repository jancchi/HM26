SET NAMES utf8mb4;

-- ============================================================
-- HALMAKE26 SUPER SQL (FULL RESET + CLEAN ENGLISH SCHEMA)
-- WARNING: This script DROPS existing app tables and data.
-- ============================================================

DROP TABLE IF EXISTS worker_categories;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS workers;

CREATE TABLE workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_plain VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  organization VARCHAR(100),
  role ENUM('Startup', 'Investor', 'Service Provider', 'Community Member') NOT NULL,
  category ENUM(
    'Employee Search',
    'Investor Search',
    'Event Speaking',
    'Marketing Materials Sharing',
    'Sales Support',
    'Client Search',
    'Other'
  ) NOT NULL,
  title VARCHAR(180) NOT NULL,
  city VARCHAR(120) NOT NULL,
  phone VARCHAR(60),
  description TEXT NOT NULL,
  urgency ENUM('low', 'medium', 'high') DEFAULT 'medium',
  deadline DATE NULL,
  budget DECIMAL(12,2) NULL,
  help_type ENUM('volunteer', 'financial', 'material', 'other') DEFAULT 'volunteer',
  tags_text TEXT NULL,
  is_vip TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('New', 'Assigned', 'In Progress', 'Done (Waiting Approval)', 'Resolved') DEFAULT 'New',
  assigned_worker_id INT NULL,
  admin_note TEXT NULL,
  rejection_note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  assigned_at TIMESTAMP NULL,
  CONSTRAINT fk_requests_worker FOREIGN KEY (assigned_worker_id) REFERENCES workers(id) ON DELETE SET NULL
);

CREATE TABLE worker_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  category ENUM(
    'Employee Search',
    'Investor Search',
    'Event Speaking',
    'Marketing Materials Sharing',
    'Sales Support',
    'Client Search',
    'Other'
  ) NOT NULL,
  CONSTRAINT fk_worker_categories_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_worker_category (worker_id, category)
);

INSERT INTO workers (full_name, username, password_plain, active) VALUES
('Juraj Novak', 'juraj_novak', 'worker0100', 1),
('Peter Kovac', 'peter_kovac', 'worker0100', 1),
('Marek Horvath', 'marek_horvath', 'worker0100', 1),
('Lucia Vargova', 'lucia_vargova', 'worker0100', 1),
('Monika Bielikova', 'monika_bielikova', 'worker0100', 1),
('Zuzana Krizanova', 'zuzana_krizanova', 'worker0100', 1),
('Tomas Mikula', 'tomas_mikula', 'worker0100', 1),
('Adam Slany', 'adam_slany', 'worker0100', 1),
('Martin Vasko', 'martin_vasko', 'worker0100', 1),
('Ema Kralova', 'ema_kralova', 'worker0100', 1),
('Nina Benkova', 'nina_benkova', 'worker0100', 1),
('Dominika Ruzickova', 'dominika_ruzickova', 'worker0100', 1),
('Filip Urban', 'filip_urban', 'worker0100', 1),
('Milan Fabry', 'milan_fabry', 'worker0100', 1),
('Samuel Kristof', 'samuel_kristof', 'worker0100', 1),
('Barbora Cibulova', 'barbora_cibulova', 'worker0100', 1),
('Veronika Halova', 'veronika_halova', 'worker0100', 1),
('Kristina Lehotska', 'kristina_lehotska', 'worker0100', 1),
('Andrej Cerny', 'andrej_cerny', 'worker0100', 1),
('Michal Poliak', 'michal_poliak', 'worker0100', 1),
('Denis Grendel', 'denis_grendel', 'worker0100', 1);

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Employee Search' FROM workers WHERE username IN ('juraj_novak', 'peter_kovac', 'marek_horvath');

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Investor Search' FROM workers WHERE username IN ('lucia_vargova', 'monika_bielikova', 'zuzana_krizanova');

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Event Speaking' FROM workers WHERE username IN ('tomas_mikula', 'adam_slany', 'martin_vasko');

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Marketing Materials Sharing' FROM workers WHERE username IN ('ema_kralova', 'nina_benkova', 'dominika_ruzickova');

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Sales Support' FROM workers WHERE username IN ('filip_urban', 'milan_fabry', 'samuel_kristof');

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Client Search' FROM workers WHERE username IN ('barbora_cibulova', 'veronika_halova', 'kristina_lehotska');

INSERT INTO worker_categories (worker_id, category)
SELECT id, 'Other' FROM workers WHERE username IN ('andrej_cerny', 'michal_poliak', 'denis_grendel');

-- Default worker password for all seeded workers: worker0100
