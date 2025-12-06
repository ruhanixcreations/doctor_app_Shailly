-- Update clinic_logos table to support multiple logos with is_active flag
-- Run this script to migrate from the old single-logo structure to the new multi-logo structure

-- First, backup the old table (optional but recommended)
-- CREATE TABLE clinic_logos_backup AS SELECT * FROM clinic_logos;

-- Drop the unique constraint on client_id if it exists
ALTER TABLE `clinic_logos` DROP INDEX IF EXISTS `client_id`;

-- Add is_active column if it doesn't exist
ALTER TABLE `clinic_logos` 
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 0 AFTER `logo_path`;

-- Set existing logos as active (for migration purposes)
UPDATE `clinic_logos` SET `is_active` = 1 WHERE `is_active` = 0;

-- Add index for better query performance
ALTER TABLE `clinic_logos` 
ADD INDEX `idx_client_active` (`client_id`, `is_active`);

-- Verify the structure
DESCRIBE `clinic_logos`;
