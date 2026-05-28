ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','suporte','professor','certificador')
    NOT NULL DEFAULT 'suporte';
