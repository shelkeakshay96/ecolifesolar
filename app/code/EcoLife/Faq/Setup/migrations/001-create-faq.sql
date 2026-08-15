-- EcoLife_Faq :: faq
--
-- The questions on /faq. Previously a PHP array in Cms/etc/faq.php, which the
-- family could not edit.
--
-- Unlike testimonial, this table does NOT ship empty. The eight questions below
-- are the ones that were already written and already live, so seeding them is
-- what makes this a move rather than a deletion -- without it, deploying this
-- migration would silently blank the FAQ page and throw away the content it was
-- ranking for.
--
-- sort_order goes up in tens so a new question can be slotted between two
-- existing ones without renumbering the rest.
--
-- {cost_per_kw} and {cost_3kw} in the answers are substituted at render time
-- from Calculator/etc/calculator.php by Faq\Model\Faq::getAnswer(). They are
-- stored as tokens on purpose: a price typed in as literal text here is a price
-- that will one day disagree with the one the calculator computes.

CREATE TABLE faq (
  faq_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question    VARCHAR(255) NOT NULL,
  answer      TEXT NOT NULL,
  link_url    VARCHAR(255) NULL COMMENT 'internal path only, e.g. /calculator',
  link_label  VARCHAR(120) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (faq_id),
  KEY idx_faq_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO faq (question, answer, link_url, link_label, sort_order) VALUES
('How much does a rooftop solar system cost in Satara?',
 'Around Rs {cost_per_kw} per kW of installed capacity before subsidy, so a 3 kW system for a typical Satara home works out near Rs {cost_3kw} before the PM Surya Ghar subsidy is applied. The final figure moves with your roof type, the mounting structure it needs and the inverter you end up on, which is why we survey before we quote rather than after.',
 '/calculator', 'Estimate your system and savings', 10),

('What subsidy can I get under PM Surya Ghar in Satara?',
 'Residential rooftop systems qualify for a central-government subsidy that is tiered by system size, with a cap once you go past a few kilowatts. The rates are set nationally and have been revised more than once, so we confirm what applies to your roof at the survey instead of publishing a number here that may be stale by the time you read it. We file the application, the net-metering paperwork and the DISCOM co-ordination as part of the job.',
 NULL, NULL, 20),

('How much roof space do I need for a 3 kW system?',
 'Roughly 300 square feet of unshaded roof, working on about 100 square feet per kW. Shading matters more than raw area: a smaller clear roof beats a larger one with a water tank or a neighbouring wall throwing shadow across it for part of the day, which is exactly what we check during the survey.',
 NULL, NULL, 30),

('Do you work on tiled and metal-sheet roofs, or only RCC?',
 'All three. RCC gives the best output for a given area and tiled the least, with metal sheet in between, because of how the mounting structure sits and the angle it can achieve. The difference is built into the estimate rather than discovered afterwards.',
 NULL, NULL, 40),

('Do you handle the MSEDCL net-metering paperwork?',
 'Yes, and it is not billed separately. Net metering is what lets your meter run backwards when the system generates more than the house is using, and it is the step that takes longest because it sits with the DISCOM rather than with us. We file it and follow it up.',
 NULL, NULL, 50),

('How much will my electricity bill actually drop?',
 'For most homes a correctly sized system covers the large majority of daytime consumption, but the honest answer is that it depends on your tariff, your shading and which way your roof faces. The calculator gives an illustrative figure from your current bill; a site visit gives one you can rely on.',
 '/calculator', 'Work out your savings', 60),

('Which parts of Satara district do you cover?',
 'Satara city is home ground, and we work regularly in Karad, Wai and Phaltan along with the villages in between. If your town is not one of those, ask anyway - most of the district is within reach, and we would rather say no on the phone than have you assume it.',
 '/contact', 'Ask about your area', 70),

('What maintenance does a rooftop system need?',
 'Panels need washing every few weeks in the dry season, more often if you are near a road that throws up dust, and an occasional check that the mounting and wiring are sound. There are no moving parts. We are based in Satara, so when something does need attention we are half an hour away rather than a scheduled trip from another city.',
 NULL, NULL, 80);
