<div class="card card-primary card-outline">

    <div class="card-header">

        <h3 class="card-title">
            Product Management
        </h3>

        <div class="card-tools">

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="btnNew"
            >
                <i class="fas fa-plus"></i>
                New Product
            </button>

        </div>

    </div>

    <div class="card-body">

        <table
            id="products-table"
            class="table table-bordered table-striped table-hover w-100"
        >

            <thead>

                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th class="text-center">Option</th>
                </tr>

            </thead>

        </table>

    </div>

</div>


<div
    class="modal fade"
    id="product-modal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <form
            id="products-form"
            class="modal-content"
        >

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

                <input
                    type="number"
                    name="ingredient_quantity[]"
                    id="ingredient_quantity"
                    class="form-control"
                >

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