CREATE TABLE /*_*/global_preferences_tmp (
  gp_user INTEGER UNSIGNED NOT NULL,
  gp_property BLOB NOT NULL,
  gp_value BLOB DEFAULT NULL,
  PRIMARY KEY(gp_user, gp_property)
);

INSERT INTO global_preferences_tmp (gp_user, gp_property, gp_value)
SELECT gp_user, gp_property, gp_value
FROM /*_*/global_preferences;

DROP TABLE /*_*/global_preferences;
ALTER TABLE /*_*/global_preferences_tmp RENAME TO /*_*/global_preferences;

CREATE INDEX global_preferences_property ON /*_*/global_preferences (gp_property);
