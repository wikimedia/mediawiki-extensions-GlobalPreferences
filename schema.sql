CREATE TABLE global_preferences (
  gp_user INT(11) NOT NULL,
  gp_property VARBINARY(255) NOT NULL,
  gp_value BLOB
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/global_preferences_user_property ON /*_*/global_preferences (gp_user,gp_property);
CREATE INDEX /*i*/global_preferences_property ON /*_*/global_preferences (gp_property);
