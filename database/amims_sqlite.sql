-- AMIMS SQLite Schema + Seed Data
-- Column names match the original MySQL schema exactly

PRAGMA foreign_keys = OFF;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS maintenance_history;
DROP TABLE IF EXISTS maintenance_schedule;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS assets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS departments;

-- 1. departments
CREATE TABLE departments (
    dept_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    dept_name  TEXT    NOT NULL UNIQUE,
    description TEXT,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- 2. categories
CREATE TABLE categories (
    category_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    category_name TEXT    NOT NULL UNIQUE,
    description   TEXT,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- 3. users
CREATE TABLE users (
    user_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name     TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'maintenance_officer' CHECK (role IN ('admin','maintenance_officer','manager')),
    dept_id       INTEGER,
    is_active     INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);

-- 4. assets
CREATE TABLE assets (
    asset_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_name    TEXT    NOT NULL,
    asset_tag     TEXT    NOT NULL UNIQUE,
    category_id   INTEGER,
    dept_id       INTEGER,
    condition     TEXT    NOT NULL DEFAULT 'Good' CHECK (condition IN ('Good','Fair','Bad','Needs Replacement','In Use','Inactive')),
    purchase_date TEXT,
    purchase_cost REAL,
    assigned_to   INTEGER,
    notes         TEXT,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (dept_id)     REFERENCES departments(dept_id),
    FOREIGN KEY (assigned_to) REFERENCES users(user_id)
);

-- 5. maintenance_schedule
CREATE TABLE maintenance_schedule (
    schedule_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id      INTEGER NOT NULL,
    schedule_type TEXT    NOT NULL DEFAULT 'Preventive' CHECK (schedule_type IN ('Preventive','Corrective')),
    frequency     TEXT    NOT NULL CHECK (frequency IN ('Daily','Weekly','Monthly','Quarterly','Annually')),
    description   TEXT,
    next_due_date TEXT    NOT NULL,
    assigned_to   INTEGER,
    notify_emails TEXT,
    status        TEXT    NOT NULL DEFAULT 'Scheduled' CHECK (status IN ('Scheduled','Overdue','Completed','Cancelled')),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (asset_id)    REFERENCES assets(asset_id),
    FOREIGN KEY (assigned_to) REFERENCES users(user_id)
);

-- 6. maintenance_history
CREATE TABLE maintenance_history (
    history_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id       INTEGER NOT NULL,
    schedule_id    INTEGER,
    performed_by   INTEGER,
    performed_date TEXT    NOT NULL,
    description    TEXT    NOT NULL,
    cost           REAL,
    outcome        TEXT,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (asset_id)    REFERENCES assets(asset_id),
    FOREIGN KEY (schedule_id) REFERENCES maintenance_schedule(schedule_id),
    FOREIGN KEY (performed_by) REFERENCES users(user_id)
);

-- 7. inventory_items
CREATE TABLE inventory_items (
    item_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    item_name     TEXT    NOT NULL,
    category_id   INTEGER,
    dept_id       INTEGER,
    quantity      INTEGER NOT NULL DEFAULT 0,
    unit          TEXT,
    reorder_level INTEGER NOT NULL DEFAULT 10,
    last_updated  TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (dept_id)     REFERENCES departments(dept_id)
);

-- 8. notifications
CREATE TABLE notifications (
    notif_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    type        TEXT    NOT NULL CHECK (type IN ('maintenance','inventory','system','report')),
    title       TEXT    NOT NULL,
    message     TEXT    NOT NULL,
    asset_id    INTEGER,
    schedule_id INTEGER,
    item_id     INTEGER,
    sent_to     TEXT,
    is_read     INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (asset_id)    REFERENCES assets(asset_id),
    FOREIGN KEY (schedule_id) REFERENCES maintenance_schedule(schedule_id),
    FOREIGN KEY (item_id)     REFERENCES inventory_items(item_id)
);

-- 9. reports
CREATE TABLE reports (
    report_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    report_type    TEXT    NOT NULL CHECK (report_type IN ('inventory','maintenance_history','asset_condition','scheduled_maintenance')),
    generated_by   INTEGER,
    generated_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    file_path      TEXT,
    sent_to_emails TEXT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id)
);

PRAGMA foreign_keys = ON;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (dept_name, description, created_at) VALUES
('Inventory and Warehouse', 'Storage and inventory control', '2026-06-10 21:37:57'),
('Production Operations', 'Daily Production and processing activities', '2026-06-10 21:37:57'),
('Exploration and Production (E&P)', 'Oil and gas exploration and extraction', '2026-06-10 21:37:57'),
('Reservoir Engineering', 'Reservoir analysis and prduction optimization', '2026-06-10 21:37:57'),
('Supply Chain and Procurement', 'Purchasing and supplier management', '2026-06-10 21:37:57'),
('Drilling Operations', 'Well drilling and field development', '2026-06-10 21:37:57'),
('Engineering and Maintenance', 'Equipment maintenance and technical support', '2026-06-10 21:37:57'),
('Health, Safety and Environment (HSE)', 'Workplace safety and environmental compliance', '2026-06-10 22:23:37'),
('Finance and Accounting', 'Financial management and reporting', '2026-06-10 22:58:28'),
('Internal Audit and Compliance', 'Risk management and regulatory compliance', '2026-06-10 22:59:21'),
('Human Resources (HR)', 'Employee management and development', '2026-06-10 23:00:08'),
('Information Technology (IT)', 'Technology Infrastructure and Support', '2026-06-10 23:02:04'),
('Sales and Marketing', 'Product sales and customer relations', '2026-06-10 23:02:51'),
('Dispatch and Distirbution', 'Product delivery and logistics coordination', '2026-06-10 23:03:37'),
('Laboratory and Quality Control', 'Product yesting and quality assurance', '2026-06-10 23:04:11'),
('Legal and Corporate Affairs', 'Legal services and contract management', '2026-06-10 23:05:07'),
('Gas Operation', 'Natural gas processing, storage and distribution', '2026-06-10 23:07:43'),
('Asset Management', 'Asset tracking and lifecycle management', '2026-06-10 23:09:10'),
('Rig Operations', 'Drilling and well development operations', '2026-06-10 23:09:53'),
('Geology', 'Geological Analysis and resource  evaluation', '2026-06-10 23:10:58'),
('Waste Management', 'Waste handling and disposal assets', '2026-06-11 09:52:20');

-- Categories
INSERT INTO categories (category_name, description, created_at) VALUES
('Chemicals and Additives', 'Production and treatment chemicals', '2026-06-10 21:37:57'),
('Safety Equipment', 'PPE and emergency safety gear', '2026-06-10 21:37:57'),
('Mechanical Equipment', 'Pumps, valves, and machinery', '2026-06-10 21:37:57'),
('Electrical Equipment', 'Electrical systems and components', '2026-06-10 21:37:57'),
('Tools and Instruments', 'Hand tools and measuring devices', '2026-06-10 21:37:57'),
('Vehicles', 'Transport and operational vehicles', '2026-06-10 21:37:57'),
('IT Equipment', 'Computers, servers, networking devices', '2026-06-10 21:37:57'),
('Drilling Equipment', 'Drilling tools and rig equipment', '2026-06-11 09:36:40'),
('Laboratory Equipment', 'Testing and analysis instruments', '2026-06-11 09:37:20'),
('Pipes and Fittings', 'Pipelines, fittings and connectors', '2026-06-11 09:39:06'),
('Gas Processing  Equipments', 'Gas treatment and processing assets', '2026-06-11 09:39:57'),
('Storage Tanks', 'Crude oil tanks, fuel tanks, LPG tanks, water tanks', '2026-06-11 09:41:27'),
('Spare Parts', 'Replacement parts for equipment', '2026-06-11 09:42:16'),
('Marine Equipment', 'Offshore and marine operation assets', '2026-06-11 09:42:56'),
('Office Equipment', 'Furniture and office devices', '2026-06-11 09:43:32'),
('Communication', 'Radios and Communication systems', '2026-06-11 09:44:03'),
('Consumables', 'Frequently used operational supplies', '2026-06-11 09:44:39'),
('Generators and Power Systems', 'Backup and primary power units', '2026-06-11 09:45:25'),
('Construction', 'Heavy-duty construction machinery', '2026-06-11 09:46:04'),
('Waste Management', 'Waste handling and disposal assets', '2026-06-11 09:46:38'),
('Lubricants and Fuels', 'Equipment fuels and lubricants', '2026-06-11 09:47:18'),
('Pumps and Compressors', 'Fluid and gas movement equipment', '2026-06-11 09:48:09');

-- Users
INSERT INTO users (full_name, email, password_hash, role, dept_id, is_active, created_at) VALUES
('System Administrator', 'adike.2200191@stu.cu.edu.ng', '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'admin', NULL, '1', '2026-06-10 21:37:57'),
('Ebele Dike', 'ebele.dike@yahoo.com', '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'manager', '9', '1', '2026-06-10 21:37:57'),
('Chioma Ebeh', 'chioma@amims.ng', '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'manager', '20', '1', '2026-06-10 21:37:57'),
('Lotanna Tyrese', 'lotanna@amims.ng', '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'maintenance_officer', '7', '1', '2026-06-10 21:37:57'),
('Nkechi Chizea', 'nkechi@amims.ng', '$2y$12$6d4z9gJ5.piVF/r6Bb5FMON7IchxxU52/3IYAVYzfO/sglOvFkGqy', 'manager', '3', '1', '2026-06-10 23:19:38'),
('Samuel Omondeagbon', 'omondeagbon.samuel@gmail.com', '$2y$12$U7zQw1MWLiNIm5Wq.xpsRO3yAgAjpuLesE1hmY0s4jGPcEn3Jx3A6', 'maintenance_officer', '12', '1', '2026-06-10 23:21:04'),
('Gbemi Obilanade', 'gbemesola56@gmail.com', '$2y$12$aGIfhhU7JcVl7s24Un4t8uDvhVAluWqIL0P3IZdb1/2ml9UVyultW', 'manager', '16', '1', '2026-06-11 13:36:49'),
('Mabel Baker', 'mabel@amims.ng', '$2y$12$RoK..oIG2HsJSJdDuuCqLO0krBSusknzFXgjl8o6wu.07TX7r4Q0C', 'manager', '11', '1', '2026-06-11 17:40:21'),
('Esther Osakuade', 'esther@amims.ng', '$2y$12$GW2y8jXEiCRM0JwvLLKmde0BISktB0tKut49bz7sQb.0KPLfrZMxC', 'manager', '12', '1', '2026-06-12 12:10:39'),
('Amarachi Dike', 'amara.dike14@gmail.com', '$2y$12$0lcUVvACB4R5dNIPQKRCzudVqvWp6sh3ABYn9Du6hXNF2ZiWCe6Tu', 'manager', '4', '1', '2026-06-12 17:12:30');

-- Assets
INSERT INTO assets (asset_name, asset_tag, category_id, dept_id, condition, purchase_date, purchase_cost, assigned_to) VALUES
('Centrifugal Pump Unit A',     'ASSET-001', 3, 1, 'Good',             '2021-03-15', 4500000.00, 2),
('Transfer Pipeline Valve Set', 'ASSET-002', 3, 1, 'Fair',             '2020-06-01', 1200000.00, 2),
('CCTV Surveillance System',    'ASSET-003', 4, 1, 'Good',             '2022-01-10', 3800000.00, 3),
('Fire Suppression System',     'ASSET-004', 2, 4, 'Good',             '2019-11-20', 9500000.00, 3),
('Pressure Gauge Array',        'ASSET-005', 5, 1, 'Needs Replacement','2018-05-12',  750000.00, 2),
('IT Server Rack',              'ASSET-006', 7, 2, 'Good',             '2023-02-28', 6200000.00, NULL),
('Field Operations Vehicle #1', 'ASSET-007', 6, 5, 'In Use',           '2020-08-05',18000000.00, NULL),
('Emergency Generator Set',     'ASSET-008', 4, 1, 'Fair',             '2017-09-30',12500000.00, 2),
('Flow Meter Station B',        'ASSET-009', 5, 1, 'Bad',              '2016-03-14', 2100000.00, 2),
('Safety Shower Unit',          'ASSET-010', 2, 4, 'Good',             '2022-07-19',  350000.00, 3);

-- Maintenance Schedules (relative dates computed as text)
INSERT INTO maintenance_schedule (asset_id, schedule_type, frequency, description, next_due_date, assigned_to, notify_emails, status) VALUES
(1, 'Preventive', 'Monthly',    'Monthly lubrication and seal check',         date('now', '+5 days'),  2, '["david@amims.ng"]',                       'Scheduled'),
(2, 'Preventive', 'Quarterly',  'Valve operation and leakage test',           date('now', '+10 days'), 2, '["david@amims.ng"]',                       'Scheduled'),
(3, 'Preventive', 'Monthly',    'Camera lens cleaning and recording check',   date('now', '+3 days'),  3, '["emeka@amims.ng"]',                       'Scheduled'),
(4, 'Preventive', 'Annually',   'Full system test and agent refill check',    date('now', '+90 days'), 3, '["emeka@amims.ng"]',                       'Scheduled'),
(5, 'Corrective', 'Monthly',    'Replace faulty gauge units',                 date('now', '-5 days'),  2, '["david@amims.ng","emeka@amims.ng"]',       'Overdue'),
(8, 'Preventive', 'Quarterly',  'Load test and fuel system inspection',       date('now', '+2 days'),  2, '["david@amims.ng"]',                       'Scheduled'),
(9, 'Corrective', 'Weekly',     'Calibration and sensor replacement',         date('now', '-2 days'),  2, '["david@amims.ng","fatima@amims.ng"]',      'Overdue');

-- Maintenance History
INSERT INTO maintenance_history (asset_id, performed_by, performed_date, description, cost, outcome) VALUES
(1, 2, date('now', '-30 days'), 'Monthly lubrication and bearing inspection completed.',  45000.00, 'Resolved — bearings greased, seals OK'),
(4, 3, date('now', '-60 days'), 'Annual fire suppression system test and CO2 refill.',   380000.00,'Passed — all cylinders recharged'),
(8, 2, date('now', '-15 days'), 'Generator load bank test at 80%% capacity.',            120000.00,'Generator performing within spec'),
(6, 3, date('now', '-5 days'),  'Server rack thermal management and cable management.',   35000.00, 'Temperature reduced by 4 degrees C'),
(2, 2, date('now', '-45 days'), 'Valve greasing and seat inspection.',                   28000.00, 'Two valves showing wear — flagged for replacement');

-- Inventory Items
INSERT INTO inventory_items (item_name, category_id, dept_id, quantity, unit, reorder_level) VALUES
('Corrosion Inhibitor CI-201',     1, 1, 120, 'litres', 30),
('Pipe Thread Sealant',            1, 7,   8, 'tubes',  10),
('Safety Helmets (Yellow)',        2, 4,  45, 'pcs',    20),
('Fire-Resistant Gloves',          2, 4,   5, 'pairs',  15),
('Centrifugal Pump Seal Kit',      3, 7,   3, 'sets',    5),
('Hydraulic Oil 46 Grade',         3, 7, 200, 'litres', 50),
('Cable Ties (200mm)',             4, 2, 500, 'pcs',   100),
('Circuit Breaker 32A',            4, 2,   7, 'pcs',    10),
('Pressure Gauge 0-100 PSI',       5, 1,   2, 'pcs',     5),
('Signal Cable 2-core (100m)',     5, 1,  15, 'rolls',   5),
('Engine Oil 15W-40 (Bulk)',       3, 5,  80, 'litres', 40),
('CO2 Fire Extinguisher (9kg)',    2, 4,  12, 'pcs',     8),
('Laptop Charger Universal',       7, 2,   4, 'pcs',     5),
('Network Switch 24-Port',         7, 2,   2, 'pcs',     2),
('Welding Rods E6013',             3, 7,   0, 'boxes',  10);

-- Notifications
INSERT INTO notifications (type, title, message, asset_id, schedule_id, item_id, is_read) VALUES
('maintenance', 'Maintenance Overdue: Pressure Gauge Array',
 'Maintenance for asset "Pressure Gauge Array" [ASSET-005] was due 5 days ago and is now overdue.',
 5, 5, NULL, 0),
('maintenance', 'Maintenance Overdue: Flow Meter Station B',
 'Maintenance for asset "Flow Meter Station B" [ASSET-009] was due 2 days ago and is now overdue.',
 9, 7, NULL, 0),
('inventory', 'Low Stock Alert: Pipe Thread Sealant',
 'Inventory item "Pipe Thread Sealant" is low on stock. Current quantity: 8, reorder level: 10.',
 NULL, NULL, 2, 0),
('inventory', 'Low Stock Alert: Fire-Resistant Gloves',
 'Inventory item "Fire-Resistant Gloves" is low on stock. Current quantity: 5, reorder level: 15.',
 NULL, NULL, 4, 0),
('inventory', 'OUT OF STOCK: Welding Rods E6013',
 'Inventory item "Welding Rods E6013" is OUT OF STOCK. Reorder level: 10.',
 NULL, NULL, 15, 0),
('system', 'AMIMS System Initialized',
 'The AMIMS system has been set up and is ready for use.',
 NULL, NULL, NULL, 1);
