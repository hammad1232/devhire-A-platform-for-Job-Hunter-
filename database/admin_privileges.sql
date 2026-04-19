-- Run as MySQL root/admin user.
-- This creates a dedicated DB login with full CRUD access on all DevHire tables.

CREATE USER IF NOT EXISTS 'devhire_admin'@'localhost' IDENTIFIED BY 'DevHireDb@123';
GRANT SELECT, INSERT, UPDATE, DELETE ON devhire.* TO 'devhire_admin'@'localhost';
FLUSH PRIVILEGES;
