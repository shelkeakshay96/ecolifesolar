-- EcoLife_Testimonial :: customer photograph and business name
--
-- photo_path is relative to pub/media/, like every other stored image path, so
-- the media root can move without a data fix. There is no thumbnail column:
-- the photo is only ever rendered as a small round avatar, so the uploader is
-- told not to write a second file nothing would read.
--
-- business is the customer's firm, where there is one. It carries more weight
-- than the town does for a commercial job -- "Santkrupa Dairy" is checkable in
-- a way that "Karad" is not -- so the template gives it its own line rather
-- than folding it into the meta run. Nullable, because most residential
-- customers are just a person with a roof.

ALTER TABLE testimonial
  ADD COLUMN business   VARCHAR(150) NULL AFTER location,
  ADD COLUMN photo_path VARCHAR(255) NULL COMMENT 'relative to pub/media/' AFTER business;
