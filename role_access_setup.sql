-- Adds the requested customer role and uses it as the default account role.
ALTER TABLE users
    MODIFY role ENUM('owner', 'admin', 'cashier', 'user', 'loyal_customer')
    NOT NULL DEFAULT 'user';
