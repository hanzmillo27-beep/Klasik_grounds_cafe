-- Run this once in the Supabase SQL Editor before deploying the image-enabled app.
ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS product_image TEXT;

-- Create the public bucket used by the application. The service-role key is
-- used by the server for uploads, so no client upload policy is required.
INSERT INTO storage.buckets (id, name, public)
VALUES ('product-images', 'product-images', true)
ON CONFLICT (id) DO UPDATE
SET public = EXCLUDED.public;
