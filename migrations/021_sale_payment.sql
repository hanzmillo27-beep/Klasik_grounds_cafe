ALTER TABLE sales ADD COLUMN IF NOT EXISTS amount_paid NUMERIC(10,2);
ALTER TABLE sales ADD CONSTRAINT sales_payment_amount_check
    CHECK (amount_paid IS NULL OR amount_paid >= total_amount);
