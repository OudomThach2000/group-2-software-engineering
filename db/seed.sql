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
INSERT INTO issues (tracking_ref, category_id, description, location_text, status, created_at, resolved_at) VALUES
  ('CIR-2026-0001', 1, 'Streetlight out on the corner near the market, very dark at night.', 'Market corner, Ward 3', 'New',      '2026-09-20 18:30:00', NULL),
  ('CIR-2026-0002', 2, 'Large pothole on the main road, dangerous for motorbikes.',           'Main road near school', 'Assigned', '2026-09-18 09:15:00', NULL),
  ('CIR-2026-0003', 3, 'Drain overflowing after rain, water across the street.',              'Street 12',             'Resolved', '2026-09-10 07:00:00', '2026-09-12 13:30:00'),
  ('CIR-2026-0004', 4, 'Rubbish bins overflowing at the park entrance.',                      'Riverside park',        'Closed',   '2026-09-05 10:00:00', '2026-09-06 10:00:00');

-- Demo accounts: register through the app, then promote roles, e.g.:
--   UPDATE users SET role='supervisor' WHERE email='supervisor@example.com';
--   UPDATE users SET role='staff'      WHERE email='staff@example.com';
