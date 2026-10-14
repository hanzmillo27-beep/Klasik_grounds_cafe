<div class="card card-primary card-outline">

    <div class="card-header">

        <h3 class="card-title">
            Daily Expenses
        </h3>

        <div class="card-tools">

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="btnNew"
            >
                <i class="fas fa-plus"></i>
                New Expense
            </button>

        </div>

    </div>


    <div class="card-body">

        <div class="row mb-3">

            <div class="col-md-4">

                <div class="small-box bg-primary">

                    <div class="inner">

                        <h3 id="today-total">
                            ₱0.00
                        </h3>

                        <p>
                            Today's Expenses
                        </p>

                    </div>

                    <div class="icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="small-box bg-info">

                    <div class="inner">

                        <h3 id="week-total">
                            ₱0.00
                        </h3>

                        <p>
                            This Week
                        </p>

                    </div>

                    <div class="icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="small-box bg-success">

                    <div class="inner">

                        <h3 id="month-total">
                            ₱0.00
                        </h3>

                        <p>
                            This Month
                        </p>

                    </div>

                    <div class="icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>

                </div>

            </div>

        </div>


        <table
            id="expense-table"
            class="table table-bordered table-striped table-hover w-100"
        >

            <thead>

                <tr>
                    <th>Expense</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Option</th>
                </tr>

            </thead>

        </table>

    </div>

</div>


<div
    class="modal fade"
    id="expense-modal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <form
            id="expense-form"
            class="modal-content"
        >

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="modal-title"
                >
                    Add Expense
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

                    <label for="expense_name">
                        Expense
                    </label>

                    <input
                        type="text"
                        name="expense_name"
                        id="expense_name"
                        class="form-control"
                        placeholder="e.g. Transportation"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="amount">
                        Amount
                    </label>

                    <div class="input-group">

                        <div class="input-group-prepend">

                            <span class="input-group-text">
                                ₱
                            </span>

                        </div>

                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            placeholder="150.00"
                            required
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label for="expense_date">
                        Date
                    </label>

                    <input
                        type="date"
                        name="expense_date"
                        id="expense_date"
                        class="form-control"
                        required
                    >

                </div>

            </div>


            <div class="modal-footer">

                <input
                    type="hidden"
                    name="expense_id"
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