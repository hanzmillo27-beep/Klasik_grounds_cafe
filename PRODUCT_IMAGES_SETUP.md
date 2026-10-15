# Product image setup (Render + Supabase)

1. Run `product_images_migration.sql` in the Supabase SQL Editor before deploying the code. This adds the nullable image path to `products` and creates the public `product-images` bucket.
3. In Render, add these environment variables to the web service:
   - `SUPABASE_URL`: the project URL, such as `https://<project-ref>.supabase.co`
   - `SUPABASE_SERVICE_ROLE_KEY`: the project's service-role/secret key (keep it only in Render; never put it in GitHub or browser code)
   - `SUPABASE_STORAGE_BUCKET`: `product-images` (optional if using that bucket name)
4. Deploy from GitHub. Product photos upload directly from the server to Supabase Storage; only the object path is stored in the database.

The product form accepts JPG, PNG, and WebP images up to 5 MB. Updating a product without selecting a new photo keeps its current photo.
