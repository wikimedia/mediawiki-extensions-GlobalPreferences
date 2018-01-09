ALTER TABLE /*_*/global_preferences
  DROP INDEX /*i*/global_preferences_user_property,
  ADD PRIMARY KEY (gp_user, gp_property);