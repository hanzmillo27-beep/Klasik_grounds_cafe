<?php
require_once __DIR__ . '/pos_fixture.php';

$db->exec('ALTER TABLE loyalty_earnings ADD COLUMN earned_at TIMESTAMP');
$db->exec('CREATE TABLE loyalty_redemptions (
    loyalty_redemption_id ' . $identity . ', user_id INTEGER NOT NULL REFERENCES users(id),
    reward_code VARCHAR(20) NOT NULL UNIQUE, points_spent INTEGER NOT NULL DEFAULT 10,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_sale_id INTEGER REFERENCES sales(sale_id), used_at TIMESTAMP,
    product_id INTEGER, product_name VARCHAR(150)
)');
