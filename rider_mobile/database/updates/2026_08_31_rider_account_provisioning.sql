-- ============================================================
-- Invoiz E-Commerce - Rider Account Provisioning (ADDITIVE ONLY)
-- Date: 2026-08-31
-- Author: Logistics / Rider Teams
-- Database: invoizdb (SHARED - same as Logistics Web)
-- Principle: DO NOT delete/rename/reset existing tables/columns.
--            Only ADDITIVE changes below (new columns + FKs).
-- See: database/updates/CHANGELOG.md for team visibility
-- ============================================================

-- ------------------------------------------------------------
-- rider_applications: columns needed for admin review flow that
-- provisions a working rider login on approval.
--   * submitted_via    -> 'web' or 'mobile' (from the apply flow)
--   * center_id        -> logistics center the rider is assigned to
--   * service_area_id  -> service area the rider covers
--   * approved_by      -> admin (users.id) who approved the app
--   * provisioned_at   -> when the rider login was provisioned
-- All nullable/defaulted, so existing rows are unaffected.
-- FK columns mirror the pre-existing riders.center_id / service_area_id.
-- ------------------------------------------------------------
ALTER TABLE `rider_applications`
  ADD COLUMN `submitted_via` ENUM('web','mobile') NULL DEFAULT 'web' AFTER `vehicle_registration`,
  ADD COLUMN `center_id` BIGINT UNSIGNED NULL AFTER `submitted_via`,
  ADD COLUMN `service_area_id` BIGINT UNSIGNED NULL AFTER `center_id`,
  ADD COLUMN `approved_by` BIGINT UNSIGNED NULL AFTER `service_area_id`,
  ADD COLUMN `provisioned_at` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`;

ALTER TABLE `rider_applications`
  ADD CONSTRAINT `rider_applications_center_id_foreign`
    FOREIGN KEY (`center_id`) REFERENCES `logistics_centers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_applications_service_area_id_foreign`
    FOREIGN KEY (`service_area_id`) REFERENCES `service_areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_applications_approved_by_foreign`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
