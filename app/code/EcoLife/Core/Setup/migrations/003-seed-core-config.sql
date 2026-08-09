-- EcoLife_Core :: seed core_config
--
-- Every path the frontend reads is seeded here so that a missing row is a bug
-- rather than an expected state. Values marked PLACEHOLDER must be replaced
-- with the family's real details before launch -- see build step 11.
--
-- The original static site carried no real phone number, email or postal
-- address, so there was nothing to carry across.

INSERT INTO core_config (path, value) VALUES
  ('general/contact/phone',        'PLACEHOLDER +91 00000 00000'),
  ('general/contact/whatsapp',     'PLACEHOLDER +91 00000 00000'),
  ('general/contact/email',        'PLACEHOLDER info@example.com'),
  ('general/contact/address',      'PLACEHOLDER Satara, Maharashtra 415001'),
  ('general/contact/hours',        'Mon-Sat, 9:00 AM - 7:00 PM'),
  ('general/business/name',        'Eco Life'),
  ('general/business/gstin',       NULL),
  ('general/social/facebook',      NULL),
  ('general/social/instagram',     NULL),
  ('lead/notification/recipients', 'PLACEHOLDER info@example.com'),
  ('lead/notification/enabled',    '1');
