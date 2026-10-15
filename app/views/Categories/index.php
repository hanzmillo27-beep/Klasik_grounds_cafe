<div class="card card-primary card-outline">
    <div class="card-header d-flex flex-wrap align-items-center">
        <h3 class="card-title">Category Management</h3>
        <div class="card-tools ml-auto mt-2 mt-sm-0">
            <button type="button" class="btn btn-primary btn-sm" id="btnNew">
                <i class="fas fa-plus"></i> New Category
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table id="categories-table" class="table table-bordered table-striped table-hover w-100">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th class="text-center">Option</th>
                </tr>
            </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="category-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <form id="categories-form" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="category_name">Category Name</label>
                    <input
                        type="text"
                        name="category_name"
                        id="category_name"
                        class="form-control"
                        placeholder="e.g. Coffee"
                        required
                    >
                </div>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="category_id" id="id">

                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Cancel
                </button>

                <button type="submit" class="btn btn-primary" id="btnSave">
                    Add
                </button>
            </div>
        </form>
    </div>
</div>
