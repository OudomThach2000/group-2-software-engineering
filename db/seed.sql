-- Sample data for local development. Import AFTER schema.sql.
-- Categories (bilingual — English + Khmer, NFR2)
INSERT INTO categories (name_en, name_km, sort_order) VALUES
  ('Streetlight',      'ភ្លើងបំភ្លឺផ្លូវ', 1),
  ('Road / Pothole',   'ផ្លូវ / រណ្ដៅ',      2),
  ('Water / Drainage', 'ទឹក / លូ',           3),
  ('Waste',            'សំរាម',              4),
  ('Other',            'ផ្សេងៗ',             5);

-- Sample anonymous issues (no account needed to submit)
INSERT INTO issues (tracking_ref, category_id, description, location_text, status) VALUES
  ('CIR-2026-0001', 1, 'Streetlight out on the corner near the market, very dark at night.', 'Market corner, Ward 3', 'New'),
  ('CIR-2026-0002', 2, 'Large pothole on the main road, dangerous for motorbikes.',           'Main road near school', 'Assigned'),
  ('CIR-2026-0003', 3, 'Drain overflowing after rain, water across the street.',              'Street 12',             'Resolved');

-- Demo accounts: register through the app, then promote roles, e.g.:
--   UPDATE users SET role='supervisor' WHERE email='supervisor@example.com';
--   UPDATE users SET role='staff'      WHERE email='staff@example.com';
