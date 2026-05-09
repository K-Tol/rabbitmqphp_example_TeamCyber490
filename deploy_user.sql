CREATE USER 'db_user'@'localhost' IDENTIFIED BY 'passwd123';
GRANT SELECT, INSERT, UPDATE, DELETE ON deploy.* TO 'db_user'@'localhost';
FLUSH PRIVILEGES;
