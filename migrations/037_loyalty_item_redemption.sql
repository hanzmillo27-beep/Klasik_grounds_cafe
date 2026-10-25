-- Item-bound rewards extend existing coffee vouchers without changing usage.
-- Snapshot columns intentionally have no product FK so history survives edits.
ALTER TABLE loyalty_redemptions
    ADD COLUMN IF NOT EXISTS product_id INTEGER,
    ADD COLUMN IF NOT EXISTS product_name VARCHAR(150);
