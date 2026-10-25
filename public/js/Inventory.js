$(document).ready(function () {

    let table;
    let historyTable = null;
    let historyInventoryId = null;
    let historyRequest = null;
    let historyRequestNumber = 0;
    let historyOpen = false;
    let historySearchTimer = null;
    let finishHistoryRequest = null;

    function escapeHtml(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
    function historyText(value, type) { return type === 'display' ? escapeHtml(value) : value; }
    function historyQuantity(value, type) {
        const quantity = Number(value || 0);
        return type === 'display' ? quantity.toLocaleString(undefined, {maximumFractionDigits: 3}) : quantity;
    }
    function historyStockQuantity(value, type, row) {
        if (row.entry_kind === 'sale_record' || value == null) return type === 'display' ? 'Not recorded' : '';
        return historyQuantity(value, type);
    }
    function historyAction(value, type, row) {
        if (row.entry_kind === 'sale_record') return type === 'display'
            ? 'Product sold<small class="d-block text-muted">Stock quantities not recorded</small>' : 'Product sold';
        const labels = {created: 'Created', restock: 'Restocked', adjustment: 'Stock adjusted', sale: 'Sale deduction', deleted: 'Deleted'};
        const details = [];
        if (value === 'adjustment') {
            if (row.previous_inv_name != null && row.previous_inv_name !== row.inv_name) details.push('Name: ' + row.previous_inv_name + ' → ' + row.inv_name);
            if (row.previous_unit != null && row.previous_unit !== row.unit) details.push('Unit: ' + row.previous_unit + ' → ' + row.unit);
            if (row.previous_min_stock != null && Number(row.previous_min_stock) !== Number(row.min_stock)) {
                details.push('Minimum stock: ' + historyQuantity(row.previous_min_stock, 'display') + ' → ' + historyQuantity(row.min_stock, 'display'));
            }
            if (!Number(row.quantity_change)) details.push('Stock unchanged');
        }
        const label = value === 'adjustment' && !Number(row.quantity_change) ? 'Details updated' : labels[value] || value;
        if (type !== 'display') return label;
        return escapeHtml(label) + (details.length ? '<small class="d-block text-muted">' + escapeHtml(details.join('; ')) + '</small>' : '');
    }
    function reloadVisibleHistory() {
        if (historyTable && historyOpen) historyTable.ajax.reload(null, false);
    }
    function openHistory(item) {
        historyInventoryId = item ? Number(item.inv_id) : null;
        $('#inventory-history-context').text(item ? 'Item: ' + item.inv_name : 'All inventory items');
        $('#history-movement-type').val('');
        $('#inventory-history-error').text('').prop('hidden', true);
        if (historyTable) historyTable.search('');
        $('#inventory-history-table_filter input').val('');
        $('#inventory-history-modal').modal('show');
    }
    $('#btnInventoryHistory').on('click', function () { openHistory(null); });
    $(document).on('click', '.btn-history', function () {
        const id = Number($(this).data('id'));
        const item = table.rows().data().toArray().find(row => Number(row.inv_id) === id);
        if (item) openHistory(item);
    });
    $('#inventory-history-modal').on('show.bs.modal', function () {
        historyOpen = true;
    }).on('shown.bs.modal', function () {
        if (historyTable) {
            historyTable.ajax.reload(null, true);
            historyTable.columns.adjust();
            return;
        }
        historyTable = $('#inventory-history-table').DataTable({
            serverSide: true, processing: true, autoWidth: false,
            pageLength: 10, lengthMenu: [10, 25, 50], ordering: false, order: [], searchDelay: 300,
            language: {emptyTable: 'No inventory history found.', zeroRecords: 'No matching history found.'},
            ajax: function (data, callback) {
                function emptyResult() { callback({draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: []}); }
                if (!historyOpen) { emptyResult(); return; }
                const number = ++historyRequestNumber;
                if (historyRequest && historyRequest.abort) historyRequest.abort();
                finishHistoryRequest = emptyResult;
                if (historyInventoryId !== null) data.inv_id = historyInventoryId;
                const movementType = $('#history-movement-type').val();
                if (movementType) data.movement_type = movementType;
                $('#inventory-history-error').text('').prop('hidden', true);
                function fail(message) {
                    $('#inventory-history-error').text(message).prop('hidden', false);
                    finishHistoryRequest = null;
                    emptyResult();
                }
                historyRequest = $.ajax({url: `${base_url}/api/Inventory/history`, type: 'GET', data: data, dataType: 'json'})
                    .done(function (response) {
                        if (number !== historyRequestNumber) return;
                        if (!response || !Array.isArray(response.data) || !Number.isFinite(Number(response.recordsTotal)) || !Number.isFinite(Number(response.recordsFiltered))) {
                            fail('Unable to load inventory history. Please refresh and try again.');
                            return;
                        }
                        finishHistoryRequest = null;
                        callback(response);
                    }).fail(function (xhr, status) {
                        if (number !== historyRequestNumber || status === 'abort') return;
                        fail(xhr.responseJSON && xhr.responseJSON.message || 'Unable to load inventory history. Please refresh and try again.');
                    }).always(function () { if (number === historyRequestNumber) historyRequest = null; });
            },
            columns: [
                {data: 'created_at', render: historyText},
                {data: 'inv_name', render: function (value, type) { return historyText(value || 'Product name not recorded', type); }},
                {data: 'movement_type', render: historyAction},
                {data: 'quantity_change', render: function (value, type, row) {
                    if (row.entry_kind === 'sale_record') return type === 'display' ? historyQuantity(row.sold_quantity, type) + ' sold' : Number(row.sold_quantity);
                    return (type === 'display' && Number(value) > 0 ? '+' : '') + historyQuantity(value, type);
                }},
                {data: 'amount_before', render: historyStockQuantity},
                {data: 'amount_after', render: historyStockQuantity},
                {data: 'unit', render: function (value, type, row) { return historyText(row.entry_kind === 'sale_record' ? 'items' : value, type); }},
                {data: 'actor_name', defaultContent: '', render: historyText},
                {data: 'sale_id', orderable: false, defaultContent: '', render: function (value, type) { return historyText(value == null ? '—' : 'Sale #' + value, type); }}
            ]
        });
        const searchInput = $('#inventory-history-table_filter input').attr('maxlength', 150);
        // Own this timer so closing the modal also cancels a queued search.
        searchInput.off('.DT').off('mouseup').on('input.inventoryHistory search.inventoryHistory', function () {
            clearTimeout(historySearchTimer);
            historySearchTimer = setTimeout(function () {
                historySearchTimer = null;
                if (historyOpen && historyTable.search() !== searchInput.val()) historyTable.search(searchInput.val()).draw();
            }, 300);
        }).on('keydown.inventoryHistory', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            clearTimeout(historySearchTimer);
            historySearchTimer = null;
            if (historyOpen && historyTable.search() !== searchInput.val()) historyTable.search(searchInput.val()).draw();
        });
    }).on('hide.bs.modal', function () {
        historyOpen = false;
        clearTimeout(historySearchTimer);
        historySearchTimer = null;
        ++historyRequestNumber;
        if (historyRequest && historyRequest.abort) historyRequest.abort();
        historyRequest = null;
        if (finishHistoryRequest) finishHistoryRequest();
        finishHistoryRequest = null;
    });
    $('#history-movement-type').on('change', function () { if (historyTable && historyOpen) historyTable.ajax.reload(null, true); });
    $('#btnRefreshInventoryHistory').on('click', reloadVisibleHistory);


    function loadInventory() {

        table = $('#inventory-table').DataTable({

            responsive: true,

            autoWidth: false,

            destroy: true,

            processing: true,

            ajax: {
                url: `${base_url}/api/Inventory/all`,
                dataSrc: 'data'
            },

            columns: [

                {
                    data: 'inv_name', render: $.fn.dataTable.render.text()
                },

                {
                    data: 'amount',

                    render: function (data) {

                        return parseFloat(data || 0)
                            .toLocaleString(
                                undefined,
                                {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 3
                                }
                            );
                    }
                },

                {
                    data: 'unit', render: $.fn.dataTable.render.text()
                },
                {data: 'min_stock', render: function (value) { return Number(value).toLocaleString(undefined, {maximumFractionDigits: 3}); }},
                {data: 'is_low', render: function (low) { return low ? '<span class="badge badge-warning">Low stock</span>' : '<span class="badge badge-success">In stock</span>'; }},

                {
                    data: null,

                    className: 'text-center',

                    orderable: false,

                    searchable: false,

                    render: function (data) {

                        var $actions = $('<div>').html(`
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-history" data-id="${Number(data.inv_id)}" title="Inventory history" aria-label="View inventory history"><i class="fas fa-history"></i></button>
                            <button
                                type="button"
                                class="btn btn-sm btn-success btn-restock"
                                data-id="${Number(data.inv_id)}"
                                title="Replenish stock"
                            >
                                <i class="fas fa-plus-circle"></i>
                                
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-primary btn-edit"
                                data-id="${Number(data.inv_id)}"
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-danger btn-delete"
                                data-id="${Number(data.inv_id)}"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        `);
                        if (!$('body').data('manage-inventory')) $actions.find('.btn-edit, .btn-delete').remove();
                        if (!$('body').data('restock-inventory')) $actions.find('.btn-restock').remove();
                        return $actions.html();
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
                    $('#min_stock').val(inventory.min_stock);

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
                msgBox('error', 'Unable to load this inventory item.');
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
                msgBox('error', 'Enter an amount of at least 0.001 to add.');
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
                        msgBox('success', response.message);
                        $('#restock-modal').modal('hide');
                        table.ajax.reload(null, false);
                        reloadVisibleHistory();
                    } else {
                        msgBox('error', response.message || 'Unable to replenish stock.');
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

                        msgBox('success', response.message);

                        $('#inventory-table')
                            .DataTable()
                            .ajax.reload(
                                null,
                                false
                            );
                        reloadVisibleHistory();

                    } else {

                        msgBox('error', response.message ||
                            'Unable to delete inventory item.');
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

                msgBox('error', 'Item name is required.');

                return;
            }


            if (!Number.isFinite(amount) || amount < 0) {

                msgBox('error', 'Enter a nonnegative quantity.');

                return;
            }


            if (!$('#unit').val().trim()) {

                msgBox('error', 'Unit is required.');

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

                        msgBox('success', response.message);

                        $('#inventory-modal')
                            .modal('hide');

                        table.ajax.reload(
                            null,
                            false
                        );
                        reloadVisibleHistory();

                    } else {

                        msgBox('error', response.message ||
                            'Request failed.');
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


    $('#btnRefreshInventory').on('click', function () { table.ajax.reload(null, false); });
    loadInventory();

});
