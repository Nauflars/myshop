-- Initialize myshop database
CREATE DATABASE IF NOT EXISTS myshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant all privileges to root from any host for Docker networking
ALTER USER 'root'@'%' IDENTIFIED WITH mysql_native_password BY 'rootpassword';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

USE myshop;

-- Grant privileges to application user
GRANT ALL PRIVILEGES ON myshop.* TO 'myshop_user'@'%';
FLUSH PRIVILEGES;
