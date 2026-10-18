ALTER TABLE sales ADD COLUMN IF NOT EXISTS checkout_key VARCHAR(80);
CREATE UNIQUE INDEX IF NOT EXISTS sales_checkout_key_unique ON sales (checkout_key);
