CREATE TABLE global_preferences (
  gp_user INT(11) NOT NULL,
  gp_property VARBINARY(255) NOT NULL,
  gp_value BLOB,
  PRIMARY KEY ( gp_user, gp_property ),
  KEY ( gp_property )
) /*$wgDBTableOptions*/;
