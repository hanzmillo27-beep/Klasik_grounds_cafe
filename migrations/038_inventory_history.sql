CREATE TABLE IF NOT EXISTS inventory_history (
    history_id SERIAL PRIMARY KEY,
    inv_id INTEGER NOT NULL,
    inv_name VARCHAR(150) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    movement_type VARCHAR(20) NOT NULL CHECK (movement_type IN ('created', 'restock', 'adjustment', 'sale', 'deleted')),
    amount_before NUMERIC(12,3) NOT NULL CHECK (amount_before >= 0),
    quantity_change NUMERIC(12,3) NOT NULL,
    amount_after NUMERIC(12,3) NOT NULL CHECK (amount_after >= 0),
    min_stock NUMERIC(12,3) NOT NULL,
    previous_inv_name VARCHAR(150),
    previous_unit VARCHAR(30),
    previous_min_stock NUMERIC(12,3),
    actor_id INTEGER,
    actor_name VARCHAR(100),
    sale_id INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (ROUND(amount_before + quantity_change, 3) = amount_after)
);
CREATE INDEX IF NOT EXISTS inventory_history_created_idx ON inventory_history (created_at DESC, history_id DESC);
CREATE INDEX IF NOT EXISTS inventory_history_item_idx ON inventory_history (inv_id, created_at DESC, history_id DESC);
CREATE INDEX IF NOT EXISTS inventory_history_sale_idx ON inventory_history (sale_id) WHERE sale_id IS NOT NULL;
