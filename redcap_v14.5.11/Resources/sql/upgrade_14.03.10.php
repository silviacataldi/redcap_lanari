<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('mtb_experimental_enabled', '0');
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);