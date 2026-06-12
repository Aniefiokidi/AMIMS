-- ============================================================
-- AMIMS Database Schema
-- Oil and Gas Asset, Maintenance & Inventory Management System
-- ============================================================

CREATE DATABASE IF NOT EXISTS amims_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE amims_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS maintenance_history;
DROP TABLE IF EXISTS maintenance_schedule;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS assets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. departments
CREATE TABLE departments (
  dept_id     INT AUTO_INCREMENT PRIMARY KEY,
  dept_name   VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO departments (dept_name, description) VALUES
  ('Terminal Operations', 'Handles crude oil and petroleum product terminal activities'),
  ('IT',                  'Information technology infrastructure and support'),
  ('Audit',               'Internal audit and compliance'),
  ('HSE',                 'Health, Safety and Environment'),
  ('Operations',          'Core oil and gas field operations'),
  ('Finance',             'Financial management and accounting'),
  ('Maintenance',         'Facility and equipment maintenance');

-- 2. categories
CREATE TABLE categories (
  category_id   INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description   TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (category_name, description) VALUES
  ('Chemicals and Additives',   'Corrosion inhibitors, demulsifiers, and other process chemicals'),
  ('Safety Equipment',          'PPE, fire suppression, and safety systems'),
  ('Mechanical Equipment',      'Pumps, compressors, valves, and mechanical machinery'),
  ('Electrical Equipment',      'Panels, transformers, cables, and electrical systems'),
  ('Instrumentation',           'Gauges, sensors, flow meters, and control systems'),
  ('Vehicles',                  'Field vehicles, trucks, and mobile equipment'),
  ('IT Equipment',              'Computers, servers, networking equipment');

-- 3. users
CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','maintenance_officer','manager') NOT NULL DEFAULT 'maintenance_officer',
  dept_id       INT,
  is_active     TINYINT(1) DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: admin@amims.ng / Admin@1234
-- Hash generated with: password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO users (full_name, email, password_hash, role, dept_id) VALUES
  ('System Administrator',
   'admin@amims.ng',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'admin',
   NULL);
-- NOTE: The hash above is a placeholder. Run this query after setup to set the real hash:
-- UPDATE users SET password_hash = (SELECT password_hash FROM (SELECT PASSWORD('Admin@1234') AS password_hash) AS t) WHERE email = 'admin@amims.ng';
-- Correct way: run PHP CLI: php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);"
-- Then: UPDATE users SET password_hash='<output>' WHERE email='admin@amims.ng';

-- Seed maintenance officers
INSERT INTO users (full_name, email, password_hash, role, dept_id) VALUES
  ('David Adeyemi',
   'david@amims.ng',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'maintenance_officer',
   7),
  ('Fatima Aliyu',
   'fatima@amims.ng',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'maintenance_officer',
   7),
  ('Chukwuemeka Obi',
   'emeka@amims.ng',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'manager',
   5);

-- 4. assets
CREATE TABLE assets (
  asset_id      INT AUTO_INCREMENT PRIMARY KEY,
  asset_name    VARCHAR(200) NOT NULL,
  asset_tag     VARCHAR(50)  NOT NULL UNIQUE,
  category_id   INT,
  dept_id       INT,
  `condition`   ENUM('Good','Fair','Bad','Needs Replacement','In Use','Inactive') NOT NULL DEFAULT 'Good',
  purchase_date DATE,
  purchase_cost DECIMAL(15,2),
  assigned_to   INT,
  notes         TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
  FOREIGN KEY (dept_id)     REFERENCES departments(dept_id)     ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(user_id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO assets (asset_name, asset_tag, category_id, dept_id, `condition`, purchase_date, purchase_cost, assigned_to) VALUES
  ('Centrifugal Pump Unit A',     'ASSET-001', 3, 1, 'Good',              '2021-03-15', 4500000.00, 2),
  ('Transfer Pipeline Valve Set', 'ASSET-002', 3, 1, 'Fair',              '2020-06-01', 1200000.00, 2),
  ('CCTV Surveillance System',    'ASSET-003', 4, 1, 'Good',              '2022-01-10', 3800000.00, 3),
  ('Fire Suppression System',     'ASSET-004', 2, 4, 'Good',              '2019-11-20', 9500000.00, 3),
  ('Pressure Gauge Array',        'ASSET-005', 5, 1, 'Needs Replacement', '2018-05-12', 750000.00,  2),
  ('IT Server Rack',              'ASSET-006', 7, 2, 'Good',              '2023-02-28', 6200000.00, NULL),
  ('Field Operations Vehicle #1', 'ASSET-007', 6, 5, 'In Use',            '2020-08-05', 18000000.00, NULL),
  ('Emergency Generator Set',     'ASSET-008', 4, 1, 'Fair',              '2017-09-30', 12500000.00, 2),
  ('Flow Meter Station B',        'ASSET-009', 5, 1, 'Bad',               '2016-03-14', 2100000.00, 2),
  ('Safety Shower Unit',          'ASSET-010', 2, 4, 'Good',              '2022-07-19', 350000.00,  3);

-- 5. maintenance_schedule
CREATE TABLE maintenance_schedule (
  schedule_id   INT AUTO_INCREMENT PRIMARY KEY,
  asset_id      INT NOT NULL,
  schedule_type ENUM('Preventive','Corrective') NOT NULL DEFAULT 'Preventive',
  frequency     ENUM('Daily','Weekly','Monthly','Quarterly','Annually') NOT NULL,
  description   TEXT,
  next_due_date DATE NOT NULL,
  assigned_to   INT,
  notify_emails TEXT,
  status        ENUM('Scheduled','Overdue','Completed','Cancelled') DEFAULT 'Scheduled',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id)    REFERENCES assets(asset_id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(user_id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO maintenance_schedule (asset_id, schedule_type, frequency, description, next_due_date, assigned_to, notify_emails, status) VALUES
  (1, 'Preventive', 'Monthly',    'Monthly lubrication and seal check',          DATE_ADD(CURDATE(), INTERVAL 5  DAY),  2, '["david@amims.ng"]',  'Scheduled'),
  (2, 'Preventive', 'Quarterly',  'Valve operation and leakage test',            DATE_ADD(CURDATE(), INTERVAL 10 DAY),  2, '["david@amims.ng"]',  'Scheduled'),
  (3, 'Preventive', 'Monthly',    'Camera lens cleaning and recording check',    DATE_ADD(CURDATE(), INTERVAL 3  DAY),  3, '["emeka@amims.ng"]',  'Scheduled'),
  (4, 'Preventive', 'Annually',   'Full system test and agent refill check',     DATE_ADD(CURDATE(), INTERVAL 90 DAY),  3, '["emeka@amims.ng"]',  'Scheduled'),
  (5, 'Corrective', 'Monthly',    'Replace faulty gauge units',                  DATE_ADD(CURDATE(), INTERVAL -5 DAY),  2, '["david@amims.ng","emeka@amims.ng"]', 'Overdue'),
  (8, 'Preventive', 'Quarterly',  'Load test and fuel system inspection',        DATE_ADD(CURDATE(), INTERVAL 2  DAY),  2, '["david@amims.ng"]',  'Scheduled'),
  (9, 'Corrective', 'Weekly',     'Calibration and sensor replacement',          DATE_ADD(CURDATE(), INTERVAL -2 DAY),  2, '["david@amims.ng","fatima@amims.ng"]','Overdue');

-- 6. maintenance_history
CREATE TABLE maintenance_history (
  history_id     INT AUTO_INCREMENT PRIMARY KEY,
  asset_id       INT NOT NULL,
  schedule_id    INT,
  performed_by   INT,
  performed_date DATE NOT NULL,
  description    TEXT NOT NULL,
  cost           DECIMAL(15,2),
  outcome        VARCHAR(200),
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id)    REFERENCES assets(asset_id)              ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES maintenance_schedule(schedule_id) ON DELETE SET NULL,
  FOREIGN KEY (performed_by)REFERENCES users(user_id)                ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO maintenance_history (asset_id, performed_by, performed_date, description, cost, outcome) VALUES
  (1, 2, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Monthly lubrication and bearing inspection completed.', 45000.00, 'Resolved — bearings greased, seals OK'),
  (4, 3, DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Annual fire suppression system test and CO2 refill.', 380000.00, 'Passed — all cylinders recharged'),
  (8, 2, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Generator load bank test at 80% capacity.', 120000.00, 'Generator performing within spec'),
  (6, 3, DATE_SUB(CURDATE(), INTERVAL 5  DAY), 'Server rack thermal management and cable management.', 35000.00, 'Temperature reduced by 4°C'),
  (2, 2, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'Valve greasing and seat inspection.', 28000.00, 'Two valves showing wear — flagged for replacement');

-- 7. inventory_items
CREATE TABLE inventory_items (
  item_id       INT AUTO_INCREMENT PRIMARY KEY,
  item_name     VARCHAR(200) NOT NULL,
  category_id   INT,
  dept_id       INT,
  quantity      INT NOT NULL DEFAULT 0,
  unit          VARCHAR(50),
  reorder_level INT NOT NULL DEFAULT 10,
  last_updated  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
  FOREIGN KEY (dept_id)     REFERENCES departments(dept_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inventory_items (item_name, category_id, dept_id, quantity, unit, reorder_level) VALUES
  ('Corrosion Inhibitor CI-201',     1, 1, 120, 'litres', 30),
  ('Pipe Thread Sealant',            1, 7, 8,   'tubes',  10),
  ('Safety Helmets (Yellow)',        2, 4, 45,  'pcs',    20),
  ('Fire-Resistant Gloves',          2, 4, 5,   'pairs',  15),
  ('Centrifugal Pump Seal Kit',      3, 7, 3,   'sets',   5),
  ('Hydraulic Oil 46 Grade',         3, 7, 200, 'litres', 50),
  ('Cable Ties (200mm)',             4, 2, 500, 'pcs',    100),
  ('Circuit Breaker 32A',            4, 2, 7,   'pcs',    10),
  ('Pressure Gauge 0-100 PSI',       5, 1, 2,   'pcs',    5),
  ('Signal Cable 2-core (100m)',     5, 1, 15,  'rolls',  5),
  ('Engine Oil 15W-40 (Bulk)',       3, 5, 80,  'litres', 40),
  ('CO2 Fire Extinguisher (9kg)',    2, 4, 12,  'pcs',    8),
  ('Laptop Charger Universal',       7, 2, 4,   'pcs',    5),
  ('Network Switch 24-Port',         7, 2, 2,   'pcs',    2),
  ('Welding Rods E6013',             3, 7, 0,   'boxes',  10);

-- 8. notifications
CREATE TABLE notifications (
  notif_id    INT AUTO_INCREMENT PRIMARY KEY,
  type        ENUM('maintenance','inventory','system','report') NOT NULL,
  title       VARCHAR(200) NOT NULL,
  message     TEXT NOT NULL,
  asset_id    INT,
  schedule_id INT,
  item_id     INT,
  sent_to     TEXT,
  is_read     TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id)    REFERENCES assets(asset_id)              ON DELETE SET NULL,
  FOREIGN KEY (schedule_id) REFERENCES maintenance_schedule(schedule_id) ON DELETE SET NULL,
  FOREIGN KEY (item_id)     REFERENCES inventory_items(item_id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed some notifications
INSERT INTO notifications (type, title, message, asset_id, schedule_id, item_id, is_read) VALUES
  ('maintenance', 'Maintenance Overdue: Pressure Gauge Array',
   'Maintenance for asset ''Pressure Gauge Array'' [ASSET-005] was due 5 days ago and is now overdue.',
   5, 5, NULL, 0),
  ('maintenance', 'Maintenance Overdue: Flow Meter Station B',
   'Maintenance for asset ''Flow Meter Station B'' [ASSET-009] was due 2 days ago and is now overdue.',
   9, 7, NULL, 0),
  ('inventory', 'Low Stock Alert: Pipe Thread Sealant',
   'Inventory item ''Pipe Thread Sealant'' is low on stock. Current quantity: 8, reorder level: 10.',
   NULL, NULL, 2, 0),
  ('inventory', 'Low Stock Alert: Fire-Resistant Gloves',
   'Inventory item ''Fire-Resistant Gloves'' is low on stock. Current quantity: 5, reorder level: 15.',
   NULL, NULL, 4, 0),
  ('inventory', 'OUT OF STOCK: Welding Rods E6013',
   'Inventory item ''Welding Rods E6013'' is OUT OF STOCK. Reorder level: 10.',
   NULL, NULL, 15, 0),
  ('system', 'AMIMS System Initialized',
   'The AMIMS system has been set up and is ready for use. Please update the admin password.',
   NULL, NULL, NULL, 1);

-- 9. reports
CREATE TABLE reports (
  report_id      INT AUTO_INCREMENT PRIMARY KEY,
  report_type    ENUM('inventory','maintenance_history','asset_condition','scheduled_maintenance') NOT NULL,
  generated_by   INT,
  generated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  file_path      VARCHAR(500),
  sent_to_emails TEXT,
  FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPORTANT: After importing, update the admin password hash
-- ============================================================
-- Step 1: Generate hash via PHP CLI:
--   php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);"
-- Step 2: Run this UPDATE in phpMyAdmin:
--   UPDATE users SET password_hash='<paste_hash_here>' WHERE email='admin@amims.ng';
-- Same for other seed users if you want to test their logins.
-- ============================================================
