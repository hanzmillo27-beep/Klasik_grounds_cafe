<div class="card card-primary card-outline">

    <div class="card-header d-flex flex-wrap align-items-center">

        <h3 class="card-title">
            Inventory Management
        </h3>

        <div class="card-tools ml-auto mt-2 mt-sm-0">

            <?php if (\Core\Auth::canAccess('Inventory', 'add', 'Api')): ?>
            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="btnNew"
            >
                <i class="fas fa-plus"></i>
                New Inventory
            </button>
            <?php endif; ?>

        </div>

    </div>

    <div class="card-body">

        <div class="table-responsive">
            <table
                id="inventory-table"
                class="table table-bordered table-striped table-hover w-100"
            >

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Amount</th>
                    <th>Unit</th>
                    <th class="text-center">Option</th>
                </tr>

            </thead>

            </table>
        </div>

    </div>

</div>

<div class="modal fade" id="restock-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <form id="restock-form" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Replenish Stock</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="restock-item-name">Inventory item</label>
                    <input type="text" id="restock-item-name" class="form-control" readonly>
                </div>

                <p class="text-muted">
                    Current stock: <strong id="restock-current-stock"></strong>
                </p>

                <div class="form-group">
                    <label for="restock-amount">Amount to add</label>
                    <input
                        type="number"
                        name="amount"
                        id="restock-amount"
                        class="form-control"
                        min="0.001"
                        max="999999999.999"
                        step="0.001"
                        placeholder="Enter resupplied quantity"
                        required
                    >
                </div>

                <input type="hidden" name="inv_id" id="restock-inv-id">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="btnRestock">
                    Add to Stock
                </button>
            </div>
        </form>
    </div>
</div>


<div
    class="modal fade"
    id="inventory-modal"
    tabindex="-1"
>

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            id="inventory-form"
            class="modal-content"
        >

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="modal-title"
                >
                    Add Inventory
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

                    <label for="inv_name">
                        Name
                    </label>

                    <input
                        type="text"
                        name="inv_name"
                        maxlength="150"
                        id="inv_name"
                        class="form-control"
                        placeholder="e.g. Matcha powder"
                        required
                    >

                </div>


                <div class="row">

                    <div class="col-md-7">

                        <div class="form-group">

                            <label for="amount">
                                Amount
                            </label>

                            <input
                                type="number"
                                name="amount"
                                id="amount"
                                class="form-control"
                                min="0"
                                max="999999999.999"
                                step="0.001"
                                placeholder="e.g. 300"
                                required
                            >

                        </div>

                    </div>


                    <div class="col-md-5">

                        <div class="form-group">

                            <label for="unit">
                                Unit
                            </label>

                            <input
                                type="text"
                                name="unit"
                                maxlength="30"
                                id="unit"
                                class="form-control"
                                placeholder="e.g. g"
                                required
                            >

                        </div>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <input
                    type="hidden"
                    name="inv_id"
                    id="id"
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
