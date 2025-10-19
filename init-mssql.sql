IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'test_db')
BEGIN
    CREATE DATABASE test_db;
END
GO