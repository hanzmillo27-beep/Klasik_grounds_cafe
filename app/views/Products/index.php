<div class="card card-primary card-outline">

    <div class="card-header d-flex flex-wrap align-items-center">

        <h3 class="card-title">
            Product Management
        </h3>

        <div class="card-tools ml-auto mt-2 mt-sm-0">

            <?php if (\Core\Auth::canAccess('Products', 'add', 'Api')): ?>
            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="btnNew"
            >
                <i class="fas fa-plus"></i>
                New Product
            </button>
            <?php endif; ?>

        </div>

    </div>

    <div class="card-body">
        <p class="small text-muted">Products become unavailable when ingredients are insufficient. You can also mark a product unavailable to pause orders.</p>

        <div class="table-responsive">
            <table
                id="products-table"
                class="table table-bordered table-striped table-hover w-100"
            >

            <thead>

                <tr>
                    <th>Photo</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Availability</th>
                    <th class="text-center">Option</th>
                </tr>

            </thead>

            </table>
        </div>

    </div>

</div>


<div
    class="modal fade"
    id="product-modal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <form
            id="products-form"
            class="modal-content"
            enctype="multipart/form-data"
        >

            <input type="hidden" name="product_id" id="id" value="">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="modal-title"
                >
                    Add Product
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                >
                    <span>&times;</span>
                </button>

            </div>


            <div class="modal-body">

                <div class="form-group">

                    <label for="product_name">
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="product_name"
                        id="product_name"
                        class="form-control"
                        placeholder="e.g. Matcha Latte"
                        required
                    >

                </div>

                <div class="form-group">
                    <label for="product_image">Product Photo</label>
                    <input type="file" name="product_image" id="product_image"
                        class="form-control-file" accept="image/jpeg,image/png,image/webp">
                    <small class="form-text text-muted">JPG, PNG, or WebP; maximum 5 MB.</small>
                    <img id="product-image-preview" class="img-fluid mt-2 d-none"
                        alt="Product photo preview" style="max-width: 180px; max-height: 140px; object-fit: cover;">
                    <div id="product-image-current" class="small text-muted mt-2"></div>
                </div>


                <div class="form-group">

                    <label for="category_id">
                        Category
                    </label>

                    <select
                        name="category_id"
                        id="category_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Loading categories...
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="product_price">
                        Product Price
                    </label>

                    <input
                        type="number"
                        name="product_price"
                        id="product_price"
                        class="form-control"
                        min="0"
                        step="0.01"
                        placeholder="e.g. 150.00"
                        required
                    >

                </div>


                <hr>


                <div class="d-flex justify-content-between align-items-center mb-3">

                    <h5 class="mb-0">
                        Ingredients
                    </h5>

                    <button
                        type="button"
                        class="btn btn-success btn-sm"
                        id="btnAddIngredient"
                    >
                        <i class="fas fa-plus"></i>
                        Add Ingredient
                    </button>

                </div>


                <div id="ingredients-container"></div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="btnSave"
                >
                    Add
                </button>

            </div>

        </form>

    </div>

</div>
