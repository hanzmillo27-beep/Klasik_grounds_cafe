$(document).ready(function () {

    let table;


    function formatMoney(value) {

        return '₱' +
            parseFloat(value || 0)
                .toLocaleString(
                    undefined,
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );
    }


    function loadExpenses() {

        table = $('#expense-table').DataTable({

            responsive: true,

            autoWidth: false,

            destroy: true,

            processing: true,

            ajax: {
                url: `${base_url}/api/DailyExpenses/all`,
                dataSrc: 'data'
            },

            columns: [

                {
                    data: 'expense_name'
                },

                {
                    data: 'amount',

                    render: function (data) {

                        return formatMoney(data);
                    }
                },

                {
                    data: 'expense_date',

                    render: function (data) {

                        if (!data) {
                            return '';
                        }

                        let date = new Date(
                            data + 'T00:00:00'
                        );

                        return date.toLocaleDateString(
                            undefined,
                            {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            }
                        );
                    }
                },

                {
                    data: null,

                    className: 'text-center',

                    orderable: false,

                    searchable: false,

                    render: function (data) {

                        return `
                            <button
                                type="button"
                                class="btn btn-sm btn-primary btn-edit"
                                data-id="${data.expense_id}"
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-danger btn-delete"
                                data-id="${data.expense_id}"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                    }
                }

            ]
        });
    }


    function loadTotals() {

        let today = new Date();

        let year = today.getFullYear();

        let month = String(
            today.getMonth() + 1
        ).padStart(2, '0');

        let day = String(
            today.getDate()
        ).padStart(2, '0');

        let todayString =
            `${year}-${month}-${day}`;


        $.ajax({

            url:
                `${base_url}/api/DailyExpenses/daily/${todayString}`,

            type: 'GET',

            dataType: 'json',

            success: function (response) {

                $('#today-total')
                    .text(
                        formatMoney(
                            response.data.total
                        )
                    );
            }
        });


        let dayOfWeek = today.getDay();

        let mondayOffset =
            dayOfWeek === 0
                ? 6
                : dayOfWeek - 1;


        let startDate = new Date(today);

        startDate.setDate(
            today.getDate() - mondayOffset
        );


        let endDate = new Date(startDate);

        endDate.setDate(
            startDate.getDate() + 6
        );


        let startYear =
            startDate.getFullYear();

        let startMonth =
            String(
                startDate.getMonth() + 1
            ).padStart(2, '0');

        let startDay =
            String(
                startDate.getDate()
            ).padStart(2, '0');


        let endYear =
            endDate.getFullYear();

        let endMonth =
            String(
                endDate.getMonth() + 1
            ).padStart(2, '0');

        let endDay =
            String(
                endDate.getDate()
            ).padStart(2, '0');


        let startString =
            `${startYear}-${startMonth}-${startDay}`;

        let endString =
            `${endYear}-${endMonth}-${endDay}`;


        $.ajax({

            url:
                `${base_url}/api/DailyExpenses/weekly/${startString}/${endString}`,

            type: 'GET',

            dataType: 'json',

            success: function (response) {

                $('#week-total')
                    .text(
                        formatMoney(
                            response.data.total
                        )
                    );
            }
        });


        $.ajax({

            url:
                `${base_url}/api/DailyExpenses/monthly/${year}/${month}`,

            type: 'GET',

            dataType: 'json',

            success: function (response) {

                $('#month-total')
                    .text(
                        formatMoney(
                            response.data.total
                        )
                    );
            }
        });
    }


    function resetForm() {

        $('#expense-form')[0].reset();

        $('#id').val('');

        let today = new Date();

        let year =
            today.getFullYear();

        let month =
            String(
                today.getMonth() + 1
            ).padStart(2, '0');

        let day =
            String(
                today.getDate()
            ).padStart(2, '0');

        $('#expense_date')
            .val(
                `${year}-${month}-${day}`
            );

        $('#modal-title')
            .text('Add Expense');

        $('#btnSave')
            .text('Add');
    }


    $('#btnNew').on(
        'click',
        function () {

            resetForm();

            $('#expense-modal')
                .modal('show');
        }
    );


    $(document).on(
        'click',
        '.btn-edit',
        function () {

            let id =
                $(this).data('id');

            $.ajax({

                url:
                    `${base_url}/api/DailyExpenses/edit/${id}`,

                type: 'GET',

                dataType: 'json',

                success: function (expense) {

                    $('#id')
                        .val(expense.expense_id);

                    $('#expense_name')
                        .val(expense.expense_name);

                    $('#amount')
                        .val(expense.amount);

                    $('#expense_date')
                        .val(expense.expense_date);

                    $('#modal-title')
                        .text('Edit Expense');

                    $('#btnSave')
                        .text('Update');

                    $('#expense-modal')
                        .modal('show');
                },

                error: function (xhr) {

                    console.log(
                        'EDIT ERROR:',
                        xhr.responseText
                    );

                    handleAjaxError(xhr);
                }
            });
        }
    );


    $(document).on(
    'click',
    '.btn-delete',
    function () {

        let id = $(this).data('id');

        isDelete(function (confirmed) {

            if (!confirmed) {
                return;
            }

            $.ajax({

                url:
                    `${base_url}/api/DailyExpenses/delete/${id}`,

                type: 'POST',

                dataType: 'json',

                success: function (response) {

                    if (
                        response.status === 'success'
                    ) {

                        msgBox(
                            response.message,
                            'success'
                        );

                        $('#daily-expenses-table')
                            .DataTable()
                            .ajax.reload(
                                null,
                                false
                            );

                        loadTotals();

                    } else {

                        msgBox(
                            response.message ||
                            'Unable to delete expense.',
                            'error'
                        );
                    }
                },

                error: function (xhr) {

                    console.log(
                        'DELETE ERROR:',
                        xhr.responseText
                    );

                    handleAjaxError(xhr);
                }
            });
        });
    }
);


    $('#expense-form').on(
        'submit',
        function (e) {

            e.preventDefault();

            let expenseName =
                $('#expense_name')
                    .val()
                    .trim();

            let amount =
                parseFloat(
                    $('#amount').val()
                );

            let expenseDate =
                $('#expense_date').val();


            if (!expenseName) {

                msgBox(
                    'Expense name is required.',
                    'error'
                );

                return;
            }


            if (
                isNaN(amount) ||
                amount <= 0
            ) {

                msgBox(
                    'Expense amount must be greater than zero.',
                    'error'
                );

                return;
            }


            if (!expenseDate) {

                msgBox(
                    'Expense date is required.',
                    'error'
                );

                return;
            }


            let formData =
                new FormData(this);


            $('#btnSave')
                .prop('disabled', true);


            $.ajax({

                url:
                    `${base_url}/api/DailyExpenses/add`,

                type: 'POST',

                data: formData,

                processData: false,

                contentType: false,

                dataType: 'json',

                success: function (response) {

                    if (
                        response.status === 'success'
                    ) {

                        msgBox(
                            response.message,
                            'success'
                        );

                        $('#expense-modal')
                            .modal('hide');

                        table.ajax.reload(
                            null,
                            false
                        );

                        loadTotals();

                    } else {

                        msgBox(
                            response.message ||
                            'Request failed.',
                            'error'
                        );
                    }
                },

                error: function (
                    xhr,
                    status,
                    error
                ) {

                    console.log(
                        'AJAX STATUS:',
                        status
                    );

                    console.log(
                        'AJAX ERROR:',
                        error
                    );

                    console.log(
                        'HTTP STATUS:',
                        xhr.status
                    );

                    console.log(
                        'SERVER RESPONSE:',
                        xhr.responseText
                    );

                    handleAjaxError(xhr);
                },

                complete: function () {

                    $('#btnSave')
                        .prop('disabled', false);
                }
            });
        }
    );


    loadExpenses();

    loadTotals();

});
