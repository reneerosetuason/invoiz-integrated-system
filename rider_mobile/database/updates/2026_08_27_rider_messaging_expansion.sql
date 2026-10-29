-- ============================================================
-- Rider Messaging Expansion: Rider↔Seller/Buyer (ADDITIVE ONLY)
-- Date: 2026-08-27
-- Database: invoizdb (SHARED)
-- Purpose: Allow Rider to message Seller/Buyer per delivery,
--          with delivery-based authorization, reusing
--          logistics_conversations / logistics_messages.
-- All changes are ADD COLUMN, no DROP.
-- ============================================================

-- Add rider_id to logistics_conversations to tie seller/buyer
-- conversations to the specific rider (for logistics, rider_id = participant_id)
ALTER TABLE `logistics_conversations`
  ADD COLUMN `rider_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `participant_id`,
  ADD INDEX `idx_logistics_conversations_rider_id` (`rider_id`);

-- Backfill existing rider-logistics conversations: rider_id = participant_id where type=rider
UPDATE `logistics_conversations` SET `rider_id` = `participant_id` WHERE `participant_type` = 'rider' AND `rider_id` IS NULL;

-- Add index for faster delivery-based lookup
CREATE INDEX `idx_logistics_conversations_order_rider` ON `logistics_conversations` (`order_id`, `rider_id`);
