-- ============================================================
-- Rider Operational: Decline + Return-to-Seller (ADDITIVE ONLY)
-- Date: 2026-08-27
-- Database: invoizdb (SHARED with Logistics Web)
-- All changes are ADD COLUMN / MODIFY ENUM to add values, no DROP.
-- See database/updates/CHANGELOG.md
-- ============================================================

-- 1. Add return tracking columns to deliveries (nullable, safe)
ALTER TABLE `deliveries`
  ADD COLUMN `returned_at` TIMESTAMP NULL DEFAULT NULL AFTER `failed_at`,
  ADD COLUMN `return_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `returned_at`;

-- 2. Extend status ENUM to include declined + returned (keep existing values)
--    This is additive: adds 2 new enum values, preserves all old rows.
ALTER TABLE `deliveries`
  MODIFY COLUMN `status` ENUM('waiting_for_rider','assigned','accepted','going_to_pickup','arrived_at_shop','picked_up','out_for_delivery','arrived_at_customer','delivered','delivery_failed','cancelled','declined','returned') NOT NULL DEFAULT 'waiting_for_rider';

-- No new tables; reuse delivery_status_logs for history, rider_notifications for assignment.
