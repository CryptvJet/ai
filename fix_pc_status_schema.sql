-- Fix PC Status Table Schema
-- Add missing 'status' column that some code expects

USE `vemite5_pulse-core-ai`;

-- Add the missing status column if it doesn't exist
ALTER TABLE pc_status 
ADD COLUMN IF NOT EXISTS status ENUM('online', 'offline', 'error') DEFAULT 'offline' 
AFTER id;

-- Update existing records to have a status value
UPDATE pc_status SET status = 'offline' WHERE status IS NULL;

-- Show the corrected table structure
DESCRIBE pc_status;