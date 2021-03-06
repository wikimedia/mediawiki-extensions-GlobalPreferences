-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/GlobalPreferences/sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE global_preferences (
  gp_user INT NOT NULL,
  gp_property TEXT NOT NULL,
  gp_value TEXT DEFAULT NULL,
  PRIMARY KEY(gp_user, gp_property)
);

CREATE INDEX global_preferences_property ON global_preferences (gp_property);
