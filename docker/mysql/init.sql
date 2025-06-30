-- docker/mysql/init.sql

-- Create the database if it doesn't exist with optimized character set
CREATE DATABASE IF NOT EXISTS `ad_platform_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `ad_platform_db`;

-- Create a user and grant privileges
CREATE USER IF NOT EXISTS 'user'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON `ad_platform_db`.* TO 'user'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;

-- Optional: Insert initial tenant data
-- This data will be used by the Laravel Seeder and TraefikConfigController
INSERT IGNORE INTO tenants (id, name, domain, created_at, updated_at) VALUES
(1, 'Tenant A', 'tenant-a.localhost', NOW(), NOW()),
(2, 'Tenant B', 'tenant-b.localhost', NOW(), NOW()),
(3, 'Tenant C', 'tenant-c.localhost', NOW(), NOW()),
(4, 'Tenant D', 'tenant-d.localhost', NOW(), NOW()),
(5, 'Tenant E', 'tenant-e.localhost', NOW(), NOW());

-- More initial data for Ads (associated with Tenant A and B)
-- Generate 500 ads
DELIMITER //
CREATE PROCEDURE InsertDummyAds(IN num_ads INT)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE tenant_id_val INT;
    WHILE i < num_ads DO
        SET tenant_id_val = (SELECT id FROM tenants ORDER BY RAND() LIMIT 1);
        INSERT IGNORE INTO ads (tenant_id, name, content, start_time, end_time, target_audience, created_at, updated_at) VALUES
        (tenant_id_val, CONCAT('Ad_', i, '_', UUID_SHORT()), CONCAT('Content for Ad ', i), DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*30) DAY), DATE_ADD(NOW(), INTERVAL FLOOR(RAND()*60) DAY), JSON_OBJECT('gender', CASE WHEN RAND() > 0.5 THEN 'male' ELSE 'female' END, 'age_min', FLOOR(RAND()*40)+18, 'interests', JSON_ARRAY('tech', 'fashion', 'sports', 'travel')), NOW(), NOW());
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL InsertDummyAds(500); -- Changed from 100 to 500
DROP PROCEDURE InsertDummyAds;

-- More initial data for Events (impressions and clicks)
-- Generate 5000 events
DELIMITER //
CREATE PROCEDURE InsertDummyEvents(IN num_events INT)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE tenant_id_val INT;
    DECLARE ad_id_val INT;
    DECLARE user_id_val INT;
    DECLARE event_type_val VARCHAR(20);
    DECLARE occurred_at_val DATETIME;

    WHILE i < num_events DO
        SET tenant_id_val = (SELECT id FROM tenants ORDER BY RAND() LIMIT 1);
        SET ad_id_val = (SELECT id FROM ads WHERE tenant_id = tenant_id_val ORDER BY RAND() LIMIT 1);
        IF ad_id_val IS NULL THEN
            SET ad_id_val = (SELECT id FROM ads ORDER BY RAND() LIMIT 1); -- Fallback if no ads for tenant
        END IF;
        SET user_id_val = FLOOR(RAND() * 500) + 1; -- Simulate 500 unique users
        SET event_type_val = CASE WHEN RAND() > 0.7 THEN 'click' ELSE 'impression' END;
        SET occurred_at_val = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*90) DAY); -- Events within last 90 days

        INSERT IGNORE INTO events (tenant_id, ad_id, user_id, event_type, data, occurred_at, created_at, updated_at) VALUES
        (tenant_id_val, ad_id_val, user_id_val, event_type_val, '{}', occurred_at_val, NOW(), NOW());
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL InsertDummyEvents(5000); -- Changed from 1000 to 5000
DROP PROCEDURE InsertDummyEvents;

-- Consider partitioning for large tables in production based on tenant_id or occurred_at
-- ALTER TABLE events PARTITION BY RANGE (YEAR(occurred_at)) (
--     PARTITION p2024 VALUES LESS THAN (2025),
--     PARTITION p2025 VALUES LESS THAN (2026),
--     PARTITION pMax VALUES LESS THAN MAXVALUE
-- );
