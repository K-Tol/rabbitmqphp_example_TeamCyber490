/*
RUN THIS FILE ALONG WITH "setup.sql" IF YOU WANT TO TEST OUT THE DATABASE ON YOUR VM
*/

-- creating a local database user so it can access our database locally and grants it with permissions to do everything
CREATE USER 'db_user'@'localhost' IDENTIFIED BY 'passwd123';
GRANT SELECT, INSERT, UPDATE, DELETE ON auth_db.* TO 'db_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON movie_db.* TO 'db_user'@'localhost';
FLUSH PRIVILEGES;