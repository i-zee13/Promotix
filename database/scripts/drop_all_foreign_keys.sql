-- =============================================================================
-- Remove ALL foreign keys from the current database (phpMyAdmin / MySQL)
-- Run in SQL tab BEFORE importing a full .sql dump.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Optional: generate DROP statements for your database (run SELECT only first):
-- SELECT CONCAT('ALTER TABLE `', TABLE_NAME, '` DROP FOREIGN KEY `', CONSTRAINT_NAME, '`;')
-- FROM information_schema.TABLE_CONSTRAINTS
-- WHERE CONSTRAINT_SCHEMA = DATABASE()
--   AND CONSTRAINT_TYPE = 'FOREIGN KEY';

-- Known PromoTix / ClickGuard foreign keys (from migrations):
-- users
-- ALTER TABLE `users` DROP FOREIGN KEY `users_role_id_foreign`;
-- domains
-- ALTER TABLE `domains` DROP FOREIGN KEY `domains_user_id_foreign`;
-- ALTER TABLE `domains` DROP FOREIGN KEY `domains_google_ads_account_id_foreign`;
-- google_ads
-- ALTER TABLE `google_ads_accounts` DROP FOREIGN KEY `google_ads_accounts_google_connection_id_foreign`;
-- ALTER TABLE `google_connections` DROP FOREIGN KEY `google_connections_user_id_foreign`;
-- ALTER TABLE `google_ads_campaign_daily_metrics` DROP FOREIGN KEY `google_ads_campaign_daily_metrics_domain_id_foreign`;
-- ALTER TABLE `google_ads_campaign_daily_metrics` DROP FOREIGN KEY `google_ads_campaign_daily_metrics_google_ads_account_id_foreign`;
-- ALTER TABLE `google_ads_advertised_hosts` DROP FOREIGN KEY `google_ads_advertised_hosts_google_ads_account_id_foreign`;
-- ALTER TABLE `domain_google_ads_mappings` DROP FOREIGN KEY `domain_google_ads_mappings_domain_id_foreign`;
-- ALTER TABLE `domain_google_ads_mappings` DROP FOREIGN KEY `domain_google_ads_mappings_google_ads_account_id_foreign`;
-- domain / tracking
-- ALTER TABLE `domain_detection_settings` DROP FOREIGN KEY `domain_detection_settings_domain_id_foreign`;
-- ALTER TABLE `tracking_scripts` DROP FOREIGN KEY `tracking_scripts_domain_id_foreign`;
-- ALTER TABLE `domain_settings` DROP FOREIGN KEY `domain_settings_domain_id_foreign`;
-- ALTER TABLE `visits` DROP FOREIGN KEY `visits_domain_id_foreign`;
-- ALTER TABLE `ip_sessions` DROP FOREIGN KEY `ip_sessions_domain_id_foreign`;
-- ALTER TABLE `analytics_hourly` DROP FOREIGN KEY `analytics_hourly_domain_id_foreign`;
-- ALTER TABLE `detection_logs` DROP FOREIGN KEY `detection_logs_domain_id_foreign`;
-- paid marketing
-- ALTER TABLE `paid_marketing_visits` DROP FOREIGN KEY `paid_marketing_visits_domain_id_foreign`;
-- ALTER TABLE `paid_marketing_clicks` DROP FOREIGN KEY `paid_marketing_clicks_paid_marketing_visit_id_foreign`;
-- permissions
-- ALTER TABLE `permission_role` DROP FOREIGN KEY `permission_role_permission_id_foreign`;
-- ALTER TABLE `permission_role` DROP FOREIGN KEY `permission_role_role_id_foreign`;
-- billing / plans
-- ALTER TABLE `payments` DROP FOREIGN KEY `payments_plan_id_foreign`;
-- ALTER TABLE `payments` DROP FOREIGN KEY `payments_verified_by_id_foreign`;
-- ALTER TABLE `subscriptions` DROP FOREIGN KEY `subscriptions_last_payment_id_foreign`;
-- ALTER TABLE `subscriptions` DROP FOREIGN KEY `subscriptions_user_id_foreign`;
-- ALTER TABLE `subscriptions` DROP FOREIGN KEY `subscriptions_plan_id_foreign`;
-- admin ops
-- ALTER TABLE `admin_automation_jobs` DROP FOREIGN KEY `admin_automation_jobs_user_id_foreign`;
-- ALTER TABLE `admin_job_runs` DROP FOREIGN KEY `admin_job_runs_admin_automation_job_id_foreign`;
-- ALTER TABLE `admin_integration_settings` DROP FOREIGN KEY `admin_integration_settings_user_id_foreign`;
-- ALTER TABLE `admin_webhooks` DROP FOREIGN KEY `admin_webhooks_user_id_foreign`;
-- ALTER TABLE `support_tickets` DROP FOREIGN KEY `support_tickets_user_id_foreign`;
-- ALTER TABLE `support_ticket_messages` DROP FOREIGN KEY `support_ticket_messages_support_ticket_id_foreign`;
-- integrations
-- ALTER TABLE `direct_ads_integrations` DROP FOREIGN KEY `direct_ads_integrations_user_id_foreign`;
-- ALTER TABLE `user_invites` DROP FOREIGN KEY `user_invites_invited_by_id_foreign`;
-- ALTER TABLE `login_histories` DROP FOREIGN KEY `login_histories_user_id_foreign`;
-- ALTER TABLE `payment_methods` DROP FOREIGN KEY `payment_methods_user_id_foreign`;
-- ALTER TABLE `role_changes` DROP FOREIGN KEY `role_changes_user_id_foreign`;

-- Easiest: auto-drop every FK in the current database:
DROP PROCEDURE IF EXISTS promotix_drop_all_foreign_keys;
DELIMITER $$
CREATE PROCEDURE promotix_drop_all_foreign_keys()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_table VARCHAR(255);
  DECLARE v_constraint VARCHAR(255);
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME, CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_TYPE = 'FOREIGN KEY';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_table, v_constraint;
    IF done THEN
      LEAVE read_loop;
    END IF;
    SET @sql = CONCAT('ALTER TABLE `', v_table, '` DROP FOREIGN KEY `', v_constraint, '`');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;

CALL promotix_drop_all_foreign_keys();
DROP PROCEDURE IF EXISTS promotix_drop_all_foreign_keys;

SET FOREIGN_KEY_CHECKS = 1;
