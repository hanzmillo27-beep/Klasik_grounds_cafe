$(document).ready(function () {

    let table;
    let inventory = [];
    const deletingProducts = new Set();

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function loadCategories() {

        $.ajax({
            url: `${base_url}/api/Products/categories`,
            type: 'GET',
            dataType: 'json',

            success: function (response) {

                let options = '<option value="">Select Category</option>';

                (response.data || []).forEach(function (category) {

                    options += `
                        <option value="${category.category_id}">
                            ${category.category_name}
                        </option>
                    `;
                });

                $('#category_id').html(options);
            },

            error: function (xhr) {

                console.log('CATEGORY ERROR:', xhr.responseText);

                $('#category_id').html(
                    '<option value="">Unable to load categories</option>'
                );

                handleAjaxError(xhr);
            }
        });
    }

    function loadInventory() {

        $.ajax({
            url: `${base_url}/api/Products/inventory`,
            type: 'GET',
            dataType: 'json',

            success: function (response) {

                console.log('INVENTORY RESPONSE:', response);

                inventory = response.data || [];
            },

            error: function (xhr) {

                console.log('INVENTORY ERROR:', xhr.responseText);

                inventory = [];

                handleAjaxError(xhr);
            }
        });
    }

    function loadProducts() {

        table = $('#products-table').DataTable({

            responsive: true,

            autoWidth: false,

            destroy: true,

            processing: true,

            ajax: {
                url: `${base_url}/api/Products/all`,
                dataSrc: 'data'
            },

            columns: [

                {
                    data: 'product_image_url',
                    orderable: false,
                    searchable: false,
                    render: function (url) {
                        return url
                            ? `<img src="${escapeHtml(url)}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">`
                            : '';
                    }
                },

                {
                    data: 'product_name', render: $.fn.dataTable.render.text()
                },

                {
                    data: 'category_name',
                    render: $.fn.dataTable.render.text(),
                    defaultContent: ''
                },

                {
                    data: 'product_price',

                    render: function (data) {

                        return parseFloat(data || 0).toFixed(2);
                    }
                },

                { data: 'is_active', render: function (active) { return active ? 'Active' : 'Inactive'; } },
                { data: 'can_order', defaultContent: '', render: function (available, type, product) {
                    if (available == null) available = !!product.is_active && !!product.is_available;
                    const label = available ? 'Available' : 'Unavailable';
                    const reason = !available ? product.availability_reason || '' : '';
                    if (type !== 'display') return label + (reason ? ': ' + reason : '');
                    return `<span class="badge ${available ? 'badge-success' : 'badge-warning'}">${label}</span>`
                        + (reason ? `<small class="d-block text-muted">${escapeHtml(reason)}</small>` : '');
                } },
                {
                    data: null,

                    className: 'text-center',

                    orderable: false,

                    searchable: false,

                    render: function (data) {
                        if (!$('body').data('manage-products')) return '';
                        const id = Number(data.product_id);
                        const disabled = deletingProducts.has(id) ? 'disabled' : '';
                        return `
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-availability"
                                title="Change the availability setting. Ingredient stock is checked automatically."
                                data-id="${id}" data-available="${data.is_available ? 0 : 1}" ${disabled}>
                                ${data.is_available ? 'Mark unavailable' : 'Mark available'}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-primary btn-edit"
                                data-id="${id}" ${disabled}
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-secondary btn-status"
                                data-id="${id}" ${disabled}
                                data-active="${data.is_active ? 0 : 1}"
                            >
                                ${data.is_active ? 'Deactivate' : 'Reactivate'}
                            </button>
                            <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="${id}" ${disabled}>
                                <i class="fas fa-trash" aria-hidden="true"></i> Delete
                            </button>
                        `;
                    }
                }

            ]
        });
    }

    function addIngredientRow(
        ingredientName = '',
        quantity = ''
    ) {

        let rowId =
            Date.now() +
            Math.floor(Math.random() * 1000);

        let row = `

            <div class="ingredient-row mb-3">

                <div class="row">

                    <div class="col-md-8">

                        <label for="ingredient_name_${rowId}">
                            Ingredient
                        </label>

                        <div class="position-relative">

                            <input
                                type="text"
                                name="ingredient_name[]"
                                id="ingredient_name_${rowId}"
                                class="form-control ingredient-search"
                                value="${ingredientName}"
                                placeholder="Search inventory..."
                                autocomplete="off"
                                required
                            >

                            <div
                                class="inventory-results list-group position-absolute w-100"
                                style="
                                    display:none;
                                    z-index:9999;
                                    max-height:200px;
                                    overflow-y:auto;
                                "
                            ></div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <label for="ingredient_quantity_${rowId}">
                            Quantity
                        </label>

                        <input
                            type="number"
                            name="ingredient_quantity[]"
                            id="ingredient_quantity_${rowId}"
                            class="form-control ingredient-quantity"
                            value="${quantity}"
                            min="0.001"
                            max="999999999.999"
                            step="0.001"
                            placeholder="Quantity"
                            required
                        >

                    </div>


                    <div class="col-md-1">

                        <label>
                            &nbsp;
                        </label>

                        <button
                            type="button"
                            class="btn btn-danger btn-remove-ingredient w-100"
                        >
                            <i class="fas fa-trash"></i>
                        </button>

                    </div>

                </div>

            </div>
        `;

        $('#ingredients-container').append(row);
    }

    function resetForm() {

        $('#products-form')[0].reset();

        $('#id').val('');
        $('#product-image-preview').addClass('d-none').attr('src', '');
        $('#product-image-current').text('');

        $('#modal-title').text('Add Product');

        $('#btnSave').text('Add');

        $('#ingredients-container').empty();

        addIngredientRow();
    }

    $('#btnNew').on('click', function () {

        resetForm();

        $('#product-modal').modal('show');
    });

    $('#btnAddIngredient').on('click', function () {

        addIngredientRow();
    });

    $(document).on(
        'click',
        '.btn-remove-ingredient',
        function () {

            $(this)
                .closest('.ingredient-row')
                .remove();
        }
    );

    $(document).on(
        'input',
        '.ingredient-search',
        function () {

            let input = $(this);

            let search = input
                .val()
                .trim()
                .toLowerCase();

            let results = input
                .siblings('.inventory-results');

            results.empty();

            if (search === '') {

                results.hide();

                return;
            }

            let matches = inventory.filter(function (item) {

                return item.inv_name
                    .toLowerCase()
                    .includes(search);
            });

            if (matches.length === 0) {

                results.html(`
                    <div class="list-group-item text-muted">
                        No inventory item found
                    </div>
                `);

                results.show();

                return;
            }

            matches.forEach(function (item) {

                let safeName = $('<div>')
                    .text(item.inv_name)
                    .html();

                let safeAmount = $('<div>')
                    .text(item.amount)
                    .html();

                let safeUnit = $('<div>')
                    .text(item.unit)
                    .html();

                results.append(`

                    <button
                        type="button"
                        class="list-group-item list-group-item-action inventory-option"
                        data-name="${safeName}"
                    >

                        ${safeName}

                        <small class="text-muted">
                            (${safeAmount} ${safeUnit})
                        </small>

                    </button>

                `);
            });

            results.show();
        }
    );

    $(document).on(
        'click',
        '.inventory-option',
        function () {

            let name = $(this).attr('data-name');

            let container = $(this)
                .closest('.position-relative');

            container
                .find('.ingredient-search')
                .val(name);

            container
                .find('.inventory-results')
                .hide();
        }
    );

    $(document).on(
        'focusout',
        '.ingredient-search',
        function () {

            let input = $(this);

            setTimeout(function () {

                input
                    .siblings('.inventory-results')
                    .hide();

            }, 200);
        }
    );

    $(document).on(
        'click',
        '.btn-edit',
        function () {

            let id = $(this).data('id');
            if (deletingProducts.has(Number(id))) return;

            $.ajax({

                url: `${base_url}/api/Products/edit/${id}`,

                type: 'GET',

                dataType: 'json',

                success: function (product) {

                    $('#id')
                        .val(product.product_id);

                    $('#product_name')
                        .val(product.product_name);

                    $('#category_id')
                        .val(product.category_id);

                    $('#product_price')
                        .val(product.product_price);

                    $('#product-image-preview').toggleClass('d-none', !product.product_image_url)
                        .attr('src', product.product_image_url || '');
                    $('#product-image-current').text(product.product_image_url ? 'Current photo (choose a file to replace it).' : '');

                    $('#ingredients-container')
                        .empty();

                    if (
                        product.ingredients &&
                        product.ingredients.length > 0
                    ) {

                        product.ingredients.forEach(
                            function (ingredient) {

                                addIngredientRow(
                                    ingredient.inv_name,
                                    ingredient.quantity_required
                                );
                            }
                        );

                    } else {

                        addIngredientRow();
                    }

                    $('#modal-title')
                        .text('Edit Product');

                    $('#btnSave')
                        .text('Update');

                    $('#product-modal')
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

    $('#product_image').on('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        $('#product-image-preview').attr('src', URL.createObjectURL(file)).removeClass('d-none');
        $('#product-image-current').text('New photo selected.');
    });


    $(document).on('click', '.btn-status', function () {
        const id = $(this).data('id');
        if (deletingProducts.has(Number(id))) return;
        const active = $(this).data('active');
        Swal.fire({title: active ? 'Reactivate product?' : 'Deactivate product?',
            text: 'Existing recipes and transaction records will be retained.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Confirm'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({url: `${base_url}/api/Products/status/${id}`, type: 'POST',
                data: {is_active: active}, dataType: 'json',
                success: function (response) {
                    msgBox(response.status, response.message);
                    if (response.status === 'success') table.ajax.reload(null, false);
                }, error: handleAjaxError
            });
        });
    });
    $(document).on('click', '.btn-availability', function () {
        if (deletingProducts.has(Number($(this).data('id')))) return;
        $.ajax({url: `${base_url}/api/Products/availability/${$(this).data('id')}`, type: 'POST',
            data: {is_available: $(this).data('available')}, dataType: 'json',
            success: function (response) {
                msgBox(response.status, response.message);
                if (response.status === 'success') table.ajax.reload(null, false);
            }, error: handleAjaxError
        });
    });

    function updateProductControls(id) {
        $('#products-table button[data-id]').filter(function () {
            return Number($(this).data('id')) === id;
        }).prop('disabled', deletingProducts.has(id));
    }
    $(document).on('click', '.btn-delete', async function () {
        if (!$('body').data('manage-products')) return;
        const id = Number($(this).data('id'));
        if (!Number.isInteger(id) || id <= 0 || deletingProducts.has(id)) return;
        const product = table.rows().data().toArray().find(item => Number(item.product_id) === id);
        if (!product) return;

        deletingProducts.add(id);
        updateProductControls(id);
        try {
            const result = await Swal.fire({
                title: 'Delete product?',
                text: 'Remove "' + product.product_name + '" from the catalog? Existing sales history will be kept.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete product',
                cancelButtonText: 'Keep product', confirmButtonColor: '#b42318', focusCancel: true
            });
            if (!result || !result.isConfirmed) return;
            const response = await $.ajax({url: `${base_url}/api/Products/delete/${id}`, type: 'GET', dataType: 'json'});
            if (response && response.status === 'success') msgBox('success', response.message || 'Product deleted.');
            else msgBox('error', response && response.message || 'Unable to delete product.');
            table.ajax.reload(null, false);
        } catch (error) {
            handleAjaxError(error);
            table.ajax.reload(null, false);
        } finally {
            deletingProducts.delete(id);
            updateProductControls(id);
        }
    });

    $('#products-form').on(
        'submit',
        function (e) {

            e.preventDefault();

            let valid = true;

            $('.ingredient-row').each(
                function () {

                    let name = $(this)
                        .find('.ingredient-search')
                        .val()
                        .trim();

                    let quantity = parseFloat(
                        $(this)
                            .find('.ingredient-quantity')
                            .val()
                    );

                    if (name === '') {

                        valid = false;

                        return false;
                    }

                    let exists = inventory.some(
                        function (item) {

                            return item.inv_name
                                .toLowerCase() ===
                                name.toLowerCase();
                        }
                    );

                    if (!exists) {

                        valid = false;

                        return false;
                    }

                    if (!quantity || quantity <= 0) {

                        valid = false;

                        return false;
                    }
                }
            );

            if (!valid) {

                msgBox('error', 'Please select an inventory item from the search results and enter a valid quantity.');

                return;
            }

            let formData = new FormData(this);

            console.log(
                'FORM DATA:'
            );

            for (let pair of formData.entries()) {

                console.log(
                    pair[0],
                    pair[1]
                );
            }

            $('#btnSave')
                .prop('disabled', true);

            $.ajax({

                url: $('#id').val()
                    ? `${base_url}/api/Products/edit/${encodeURIComponent($('#id').val())}`
                    : `${base_url}/api/Products/add`,

                type: 'POST',

                data: formData,

                processData: false,

                contentType: false,

                dataType: 'json',

                success: function (response) {

                    console.log(
                        'SERVER RESPONSE:',
                        response
                    );

                    if (
                        response.status === 'success'
                    ) {

                        msgBox('success', response.message);

                        $('#product-modal')
                            .modal('hide');

                        table.ajax.reload(
                            null,
                            false
                        );

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

                    let message =
                        'Request failed.';

                    if (xhr.responseText) {
                        message =
                            xhr.responseText;
                    }

                    msgBox('error', message);
                },

                complete: function () {

                    $('#btnSave')
                        .prop('disabled', false);
                }
            });
        }
    );

    loadCategories();

    loadInventory();

    loadProducts();
    $(window).on('focus.productsAvailability', function () { if (table) table.ajax.reload(null, false); });

});
