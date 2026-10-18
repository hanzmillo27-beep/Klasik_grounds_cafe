ALTER TABLE inventory ADD CONSTRAINT inventory_amount_nonnegative
    CHECK (amount >= 0 AND amount <= 999999999.999) NOT VALID;
ALTER TABLE product_ingredients ADD CONSTRAINT recipe_quantity_positive
    CHECK (quantity_required > 0) NOT VALID;
