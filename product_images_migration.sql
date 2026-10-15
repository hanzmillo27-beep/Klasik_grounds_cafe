-- Run this once in the Supabase SQL Editor before deploying the image-enabled app.
ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS product_image TEXT;
