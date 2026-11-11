-- Sample data for local development. Import AFTER schema.sql.
-- Categories (bilingual — English + Khmer, NFR2)
INSERT INTO categories (name_en, name_km, sort_order) VALUES
  ('Streetlight',      'ភ្លើងបំភ្លឺផ្លូវ', 1),
  ('Road / Pothole',   'ផ្លូវ / រណ្ដៅ',      2),
  ('Water / Drainage', 'ទឹក / លូ',           3),
  ('Waste',            'សំរាម',              4),
  ('Other',            'ផ្សេងៗ',             5);

-- Sample anonymous issues (no account needed to submit).
-- Fixed dates keep the dashboard's average resolution time predictable (UC5, FR6):
--   CIR-2026-0003 took 54.5 h and CIR-2026-0004 took 24 h  ->  average 39.25 h.
INSERT INTO issues (tracking_ref, category_id, description, location_text, priority, status, created_at, resolved_at) VALUES
  ('CIR-2026-0001', 1, 'Streetlight out on the corner near the market, very dark at night.', 'Market corner, Ward 3', NULL,     'New',      '2026-09-20 18:30:00', NULL),
  ('CIR-2026-0002', 2, 'Large pothole on the main road, dangerous for motorbikes.',           'Main road near school', 'High',   'Assigned', '2026-09-18 09:15:00', NULL),
  ('CIR-2026-0003', 3, 'Drain overflowing after rain, water across the street.',              'Street 12',             'Medium', 'Resolved', '2026-09-10 07:00:00', '2026-09-12 13:30:00'),
  ('CIR-2026-0004', 4, 'Rubbish bins overflowing at the park entrance.',                      'Riverside park',        'Low',    'Closed',   '2026-09-05 10:00:00', '2026-09-06 10:00:00');

-- Demo accounts for the live demo (UC6). Passwords are shared in the group chat, never here.
-- More accounts can register through the app; a supervisor then promotes their role.
INSERT INTO users (name, email, password_hash, role) VALUES
  ('Demo Supervisor',  'supervisor@example.com', '$2y$10$lf1sFt4q8pWYAIULFbUSyOChpDto5NE1WXaG4wAOu0epXYCTKxGGe', 'supervisor'),
  ('Demo Field Staff', 'staff@example.com',      '$2y$10$QGVGxbD9LDf.AkSc.O2JKehDF7DfRFMR.9RYRK1s1jkuhYL1ZwL0K', 'staff');

-- Triage, work and history of the sample issues, so that the sample data follows the issue
-- lifecycle like data made through the app (Figure 7.10, docs/models.md). Without these rows the
-- Assigned issue CIR-2026-0002 was in no worker's queue (FR13), and tracking showed no history
-- for the sample issues (FR2). tests/model_test.php checks these rules for every issue.
SET @supervisor = (SELECT id FROM users WHERE email = 'supervisor@example.com');
SET @staff      = (SELECT id FROM users WHERE email = 'staff@example.com');
SET @issue1 = (SELECT id FROM issues WHERE tracking_ref = 'CIR-2026-0001');
SET @issue2 = (SELECT id FROM issues WHERE tracking_ref = 'CIR-2026-0002');
SET @issue3 = (SELECT id FROM issues WHERE tracking_ref = 'CIR-2026-0003');
SET @issue4 = (SELECT id FROM issues WHERE tracking_ref = 'CIR-2026-0004');

INSERT INTO assignments (issue_id, assigned_to_user_id, assigned_by_user_id, assigned_at) VALUES
  (@issue2, @staff, @supervisor, '2026-09-18 11:00:00'),
  (@issue3, @staff, @supervisor, '2026-09-10 09:00:00'),
  (@issue4, @staff, @supervisor, '2026-09-05 11:00:00');

INSERT INTO status_history (issue_id, old_status, new_status, changed_by_user_id, note, changed_at) VALUES
  (@issue1, NULL,          'New',         NULL,        'Issue submitted',        '2026-09-20 18:30:00'),
  (@issue2, NULL,          'New',         NULL,        'Issue submitted',        '2026-09-18 09:15:00'),
  (@issue2, 'New',         'Assigned',    @supervisor, 'Assigned by supervisor', '2026-09-18 11:00:00'),
  (@issue3, NULL,          'New',         NULL,        'Issue submitted',        '2026-09-10 07:00:00'),
  (@issue3, 'New',         'Assigned',    @supervisor, 'Assigned by supervisor', '2026-09-10 09:00:00'),
  (@issue3, 'Assigned',    'In-progress', @staff,      NULL,                     '2026-09-11 08:00:00'),
  (@issue3, 'In-progress', 'Resolved',    @staff,      'Drain cleared.',         '2026-09-12 13:30:00'),
  (@issue4, NULL,          'New',         NULL,        'Issue submitted',        '2026-09-05 10:00:00'),
  (@issue4, 'New',         'Assigned',    @supervisor, 'Assigned by supervisor', '2026-09-05 11:00:00'),
  (@issue4, 'Assigned',    'In-progress', @staff,      NULL,                     '2026-09-05 14:00:00'),
  (@issue4, 'In-progress', 'Resolved',    @staff,      'Bins emptied.',          '2026-09-06 10:00:00'),
  (@issue4, 'Resolved',    'Closed',      @supervisor, 'Closed by supervisor',   '2026-09-07 09:00:00');
