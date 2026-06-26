SET @waha_template_constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.CHECK_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'ck_whatsapp_template'
);

SET @waha_template_drop_sql = IF(
    @waha_template_constraint_exists > 0,
    'ALTER TABLE whatsapp_gateway_settings DROP CHECK ck_whatsapp_template',
    'SELECT 1'
);

PREPARE waha_template_drop_stmt FROM @waha_template_drop_sql;
EXECUTE waha_template_drop_stmt;
DEALLOCATE PREPARE waha_template_drop_stmt;

ALTER TABLE whatsapp_gateway_settings
    ADD CONSTRAINT ck_whatsapp_template CHECK (template IN ('default','custom','fonnte','waha'));
