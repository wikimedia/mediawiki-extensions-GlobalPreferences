-- Central table that stores global preferences
CREATE TABLE /*_*/global_preferences (
  -- Key to globaluser.gu_id
  gp_user INT UNSIGNED NOT NULL,
  -- Property name, same as user_properties.up_property
  gp_property VARBINARY(255) NOT NULL,
  -- Property value, same as user_properties.up_value
  gp_value BLOB,

  PRIMARY KEY (gp_user, gp_property)
) /*$wgDBTableOptions*/;

-- For batch lookup
CREATE INDEX /*i*/global_preferences_property ON /*_*/global_preferences (gp_property);
