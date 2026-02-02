-- MySQL schema for CiRCLE-YUMMY-PARTY Ticketing
-- Encoding: utf8mb4
CREATE TABLE
  IF NOT EXISTS tickets (
    ticket_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    sig_b64 VARCHAR(255) NOT NULL,
    checked TINYINT (1) NOT NULL DEFAULT 0,
    KEY idx_checked (checked),
    KEY idx_email (email)
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;