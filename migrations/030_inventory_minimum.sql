ALTER TABLE inventory ADD COLUMN IF NOT EXISTS min_stock NUMERIC(12,3) NOT NULL DEFAULT 10;
ALTER TABLE inventory ADD CONSTRAINT inventory_minimum_nonnegative CHECK (min_stock >= 0 AND min_stock <= 999999999.999);
