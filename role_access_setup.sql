-- Adds the requested customer role and uses it as the default account role.
ALTER TABLE users
    MODIFY role ENUM('owner', 'admin', 'cashier', 'staff', 'user', 'loyal_customer')
    NOT NULL DEFAULT 'user';
