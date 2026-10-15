-- ============================================================================
-- Migration: 20261013_001_faculty_sort_order.sql
-- Description: Add sort_order column to faculties table for custom faculty
--              ordering per department by Principal/Admin.
-- ============================================================================

-- 1. Add sort_order column to faculties table if not exists
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties' AND COLUMN_NAME = 'sort_order');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `faculties` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `dept_id`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add composite index for department and sort_order lookups if not exists
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties' AND INDEX_NAME = 'idx_faculties_dept_sort');
SET @sql_idx = IF(@idx_exists = 0, 'CREATE INDEX `idx_faculties_dept_sort` ON `faculties` (`dept_id`, `sort_order`)', 'SELECT 1');
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 3. One-time data initialization:
--    Seed sort_order sequentially for each department preserving existing designation ranking
UPDATE `faculties` f
JOIN (
    SELECT f2.id,
           ROW_NUMBER() OVER (
               PARTITION BY f2.dept_id 
               ORDER BY 
                   u.status DESC,
                   FIELD(f2.designation, 
                       'Professor', 
                       'Assoc. Professor', 
                       'Asst. Professor', 
                       'Asst. Professor (Adhoc)',
                       'Guest Faculty'
                   ) ASC,
                   f2.id ASC
           ) AS initial_order
    FROM `faculties` f2
    JOIN `users` u ON f2.username = u.username
) ordered ON f.id = ordered.id
SET f.sort_order = ordered.initial_order
WHERE f.sort_order = 0;
