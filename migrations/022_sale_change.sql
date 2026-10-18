ALTER TABLE sales ADD COLUMN IF NOT EXISTS change_amount NUMERIC(10,2);
ALTER TABLE sales ADD CONSTRAINT sales_change_amount_check
    CHECK (change_amount IS NULL OR (amount_paid IS NOT NULL AND change_amount = amount_paid - total_amount AND change_amount >= 0));
