-- ============================================================
-- Invoiz E-Commerce - Rider App Integration (ADDITIVE ONLY)
-- Date: 2026-08-27
-- Author: Rider Mobile Team
-- Database: invoizdb (SHARED - same as Logistics Web)
-- Principle: DO NOT delete/rename/reset existing tables.
--            Only ADDITIVE changes below (new column + indexes).
--            Safe to run once; re-running will error on duplicate
--            column/index - check CHANGELOG.md before re-applying.
-- See: database/updates/CHANGELOG.md for team visibility
-- ============================================================

-- ------------------------------------------------------------
-- 1. rider_applications.submitted_via
-- Purpose: Track whether application was submitted via
--          Logistics Web (web) or Rider Mobile App (mobile).
--          Required for audit & to show "Applied via Mobile"
--          in Logistics Web admin view.
-- Type: Additive column, nullable-safe, defaults to 'web' for
--       existing rows (no data loss).
-- ------------------------------------------------------------
ALTER TABLE `rider_applications`
  ADD COLUMN `submitted_via` ENUM('web','mobile') NOT NULL DEFAULT 'web'
  AFTER `documents`;

-- ------------------------------------------------------------
-- 2. Performance index for rider login / application lookup
-- Purpose: Rider App frequently looks up by email (login,
--          application status). Existing `riders.email` is
--          UNIQUE, but `rider_applications.email` had no index.
-- ------------------------------------------------------------
CREATE INDEX `idx_rider_applications_email` ON `rider_applications` (`email`);
CREATE INDEX `idx_rider_applications_status` ON `rider_applications` (`status`);

-- ------------------------------------------------------------
-- 3. Ensure rider messaging can be queried efficiently via API
-- Tables already exist (logistics_conversations, logistics_messages,
-- logistics_message_attachments) - reused, no new tables.
-- Add composite index for mobile polling (rider_id + updated_at)
-- if not already present - check before running:
-- ------------------------------------------------------------
-- No new tables created. Verification:
--   SHOW TABLES LIKE 'logistics_conversations'; -- exists since 2026_01_01_000007
--   SHOW TABLES LIKE 'rider_notifications';     -- exists since 2026_08_19_000008
-- All Rider App features reuse these + deliveries/riders/etc.

-- ------------------------------------------------------------
-- 4. Optional: Add index for delivery assignment polling
-- Rider App polls GET /rider/deliveries?status=assigned
-- ------------------------------------------------------------
CREATE INDEX `idx_deliveries_rider_status` ON `deliveries` (`rider_id`, `status`);

-- End of additive migration. No DROP, no RENAME, no DELETE.
