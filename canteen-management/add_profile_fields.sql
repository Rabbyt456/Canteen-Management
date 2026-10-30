-- Run this once in phpMyAdmin (or your MySQL console) on each teammate's local DB,
-- same as the other add_*.sql migrations - not tracked/auto-applied via Git.

ALTER TABLE users
    ADD COLUMN department VARCHAR(100) DEFAULT NULL,
    ADD COLUMN phone VARCHAR(20) DEFAULT NULL,
    ADD COLUMN profile_picture_url VARCHAR(255) DEFAULT NULL;

-- Note: department is nullable at the DB level so existing accounts aren't broken by
-- this migration, but the profile page requires it to be filled in before saving.