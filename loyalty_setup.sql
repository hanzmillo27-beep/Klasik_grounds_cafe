-- Run once against klasik_grounds_cafe after kiosk_orders has been created.
-- The existing schema already contains the loyal (user_id, loyalty_points) table.

ALTER TABLE kiosk_orders
    ADD COLUMN customer_user_id INT UNSIGNED NULL AFTER customer_name,
    ADD KEY idx_kiosk_orders_customer_user_id (customer_user_id);

CREATE TABLE loyalty_earnings (
    loyalty_earning_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    sale_id INT NOT NULL,
    point_date DATE NOT NULL,
    earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (loyalty_earning_id),
    UNIQUE KEY uq_loyalty_earning_sale (sale_id),
    UNIQUE KEY uq_loyalty_earning_daily (user_id, point_date),
    KEY idx_loyalty_earning_user_date (user_id, earned_at),
    CONSTRAINT fk_loyalty_earnings_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_earnings_sale
        FOREIGN KEY (sale_id) REFERENCES sales (sale_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_redemptions (
    loyalty_redemption_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    reward_code VARCHAR(20) NOT NULL,
    points_spent TINYINT UNSIGNED NOT NULL DEFAULT 10,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (loyalty_redemption_id),
    UNIQUE KEY uq_loyalty_reward_code (reward_code),
    KEY idx_loyalty_redemption_user_date (user_id, created_at),
    CONSTRAINT fk_loyalty_redemptions_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
