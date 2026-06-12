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
INSERT INTO departments (dept_name, description) VALUES
('Terminal Operations', 'Handles crude oil and petroleum product terminal activities'),
('IT',                  'Information technology infrastructure and support'),
('Audit',               'Internal audit and compliance'),
('HSE',                 'Health, Safety and Environment'),
('Operations',          'Core oil and gas field operations'),
('Finance',             'Financial management and accounting'),
('Maintenance',         'Facility and equipment maintenance');

-- Categories
INSERT INTO categories (category_name, description) VALUES
('Chemicals and Additives',  'Corrosion inhibitors, demulsifiers, and other process chemicals'),
('Safety Equipment',         'PPE, fire suppression, and safety systems'),
('Mechanical Equipment',     'Pumps, compressors, valves, and mechanical machinery'),
('Electrical Equipment',     'Panels, transformers, cables, and electrical systems'),
('Instrumentation',          'Gauges, sensors, flow meters, and control systems'),
('Vehicles',                 'Field vehicles, trucks, and mobile equipment'),
('IT Equipment',             'Computers, servers, networking equipment');

-- Users (password hash for 'Admin@1234')
INSERT INTO users (full_name, email, password_hash, role, dept_id) VALUES
('System Administrator', 'admin@amims.ng',  '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'admin',                NULL),
('David Adeyemi',        'david@amims.ng',  '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'maintenance_officer',  7),
('Fatima Aliyu',         'fatima@amims.ng', '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'maintenance_officer',  7),
('Chukwuemeka Obi',      'emeka@amims.ng',  '$2y$12$RTgQsx345BTTsGpduCWROOJdfsWJ5EBZK327mKA.oh6d9SOyfLP4.', 'manager',             5);

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
