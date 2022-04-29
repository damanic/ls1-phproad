ALTER TABLE `system_mail_settings`
ADD COLUMN `allow_recipient_blocking` TINYINT(4);

ALTER TABLE `system_email_templates`
ADD COLUMN `allow_recipient_block` TINYINT(4) NULL;

