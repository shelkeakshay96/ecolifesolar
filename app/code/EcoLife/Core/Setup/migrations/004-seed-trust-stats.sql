-- EcoLife_Core :: seed the trust-band figures
--
-- Four numbers shown on the home page and the About page. They move -- another
-- rooftop is another installation -- so they are core_config rows for exactly
-- the same reason the phone number is: the family can edit a settings field,
-- and cannot edit a template.
--
-- These are NOT PLACEHOLDER values. They are the real figures the client
-- supplied, so Settings::isPlaceholder() must stay quiet and the amber warning
-- banner on /admin/settings must not flag them.
--
-- Stored as bare integers: no punctuation, no suffix. The "+" and the "KW+"
-- are typography and live in Cms\Block\Stats::FIGURES, because data-count-to
-- has to stay parseable by parseFloat() in pub/js/app.js.

INSERT INTO core_config (path, value) VALUES
  ('general/stats/installations',    '120'),
  ('general/stats/capacity_kw',      '600'),
  ('general/stats/water_heaters',    '500'),
  ('general/stats/experience_years', '20');
