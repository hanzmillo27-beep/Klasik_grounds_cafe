$(document).ready(function () {

    let table;


    function loadInventory() {

        table = $('#inventory-table').DataTable({

            destroy: true,

            processing: true,

            ajax: {
                url: `${base_url}/api/Inventory/all`,
                dataSrc: 'data'
            },

            columns: [

                {
                    data: 'inv_name'
                },

                {
                    data: 'amount',

                    render: function (data) {

                        return parseFloat(data || 0)
                            .toLocaleString(
                                undefined,
                                {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 2
                                }
                            );
                    }
                },

                {
                    data: 'unit'
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
                                class="btn btn-sm btn-success btn-restock"
                                data-id="${data.inv_id}"
                                title="Replenish stock"
                            >
                                <i class="fas fa-plus-circle"></i>
                                
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-primary btn-edit"
                                data-id="${data.inv_id}"
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-danger btn-delete"
                                data-id="${data.inv_id}"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                    }
                }

            ]
        });
    }


    function resetForm() {

        $('#inventory-form')[0].reset();

        $('#id').val('');

        $('#modal-title')
            .text('Add Inventory');

        $('#btnSave')
            .text('Add');
    }


    $('#btnNew').on(
        'click',
        function () {

            resetForm();

            $('#inventory-modal')
                .modal('show');
        }
    );


    $(document).on(
        'click',
        '.btn-edit',
        function () {

            let id = $(this).data('id');

            $.ajax({

                url: `${base_url}/api/Inventory/edit/${id}`,

                type: 'GET',

                dataType: 'json',

                success: function (inventory) {

                    $('#id')
                        .val(inventory.inv_id);

                    $('#inv_name')
                        .val(inventory.inv_name);

                    $('#amount')
                        .val(inventory.amount);

                    $('#unit')
                        .val(inventory.unit);

                    $('#modal-title')
                        .text('Edit Inventory');

                    $('#btnSave')
                        .text('Update');

                    $('#inventory-modal')
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
        '.btn-restock',
        function () {
            let item = table
                .row($(this).closest('tr'))
                .data();

            if (!item) {
                msgBox('Unable to load this inventory item.', 'error');
                return;
            }

            let currentAmount = parseFloat(item.amount || 0)
                .toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 3
                });

            $('#restock-inv-id').val(item.inv_id);
            $('#restock-item-name').val(item.inv_name);
            $('#restock-current-stock').text(`${currentAmount} ${item.unit}`);
            $('#restock-amount').val('');
            $('#restock-modal').modal('show');
        }
    );


    $('#restock-form').on(
        'submit',
        function (e) {
            e.preventDefault();

            let itemId = $('#restock-inv-id').val();
            let amount = parseFloat($('#restock-amount').val());

            if (!itemId || !Number.isFinite(amount) || amount < 0.001) {
                msgBox('Enter an amount of at least 0.001 to add.', 'error');
                return;
            }

            $('#btnRestock').prop('disabled', true);

            $.ajax({
                url: `${base_url}/api/Inventory/restock/${itemId}`,
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        msgBox(response.message, 'success');
                        $('#restock-modal').modal('hide');
                        table.ajax.reload(null, false);
                    } else {
                        msgBox(response.message || 'Unable to replenish stock.', 'error');
                    }
                },
                error: function (xhr) {
                    console.log('RESTOCK ERROR:', xhr.responseText);
                    handleAjaxError(xhr);
                },
                complete: function () {
                    $('#btnRestock').prop('disabled', false);
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
                    `${base_url}/api/Inventory/delete/${id}`,

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

                        $('#inventory-table')
                            .DataTable()
                            .ajax.reload(
                                null,
                                false
                            );

                    } else {

                        msgBox(
                            response.message ||
                            'Unable to delete inventory item.',
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

    $('#inventory-form').on(
        'submit',
        function (e) {

            e.preventDefault();

            let amount = parseFloat(
                $('#amount').val()
            );


            if (!$('#inv_name').val().trim()) {

                msgBox(
                    'Item name is required.',
                    'error'
                );

                return;
            }


            if (!amount || amount <= 0) {

                msgBox(
                    'Amount must be greater than zero.',
                    'error'
                );

                return;
            }


            if (!$('#unit').val().trim()) {

                msgBox(
                    'Unit is required.',
                    'error'
                );

                return;
            }


            let formData =
                new FormData(this);


            $('#btnSave')
                .prop('disabled', true);


            $.ajax({

                url: `${base_url}/api/Inventory/add`,

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

                        $('#inventory-modal')
                            .modal('hide');

                        table.ajax.reload(
                            null,
                            false
                        );

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


    loadInventory();

});
