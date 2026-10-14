<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title">User Management</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" id="btnNew">
                <i class="fas fa-plus"></i> New User
            </button>
        </div>
    </div>

    <div class="card-body">
        <table id="users-table" class="table table-bordered table-striped table-hover w-100">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th class="text-center">Option</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="user-modal" tabindex="-1">
    <div class="modal-dialog">
        <form id="users-form" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Add User</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password"
                           class="form-control" minlength="4">
                    <small class="text-muted">Required for new users. Leave blank when updating to keep the current password.</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="role">Role</label>
                        <select name="role" id="role" class="form-control">
                            <option value="user">User</option>
                            <option value="owner">Owner</option>
                            <option value="admin">Administrator</option>
                            <option value="cashier">Cashier</option>
                            <option value="staff">Staff</option>
                            <option value="loyal_customer">Loyal Customer</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <input type="hidden" name="id" id="id">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSave">Add</button>
            </div>
        </form>
    </div>
</div>
