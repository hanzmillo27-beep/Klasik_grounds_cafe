ALTER TABLE loyalty_redemptions
    ADD COLUMN IF NOT EXISTS used_sale_id INTEGER REFERENCES sales(sale_id);
ALTER TABLE loyalty_redemptions
    ADD COLUMN IF NOT EXISTS used_at TIMESTAMP;
