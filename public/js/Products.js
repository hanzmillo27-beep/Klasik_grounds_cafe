$(document).ready(function () {

    let table;
    let inventory = [];

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

            destroy: true,

            processing: true,

            ajax: {
                url: `${base_url}/api/Products/all`,
                dataSrc: 'data'
            },

            columns: [

                {
                    data: 'product_name'
                },

                {
                    data: 'category_name',
                    defaultContent: ''
                },

                {
                    data: 'product_price',

                    render: function (data) {

                        return parseFloat(data || 0).toFixed(2);
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
                                data-id="${data.product_id}"
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-danger btn-delete"
                                data-id="${data.product_id}"
                            >
                                <i class="fas fa-trash"></i>
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
                            min="0.01"
                            step="0.01"
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
                    `${base_url}/api/Products/delete/${id}`,

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

                        $('#products-table')
                            .DataTable()
                            .ajax.reload(
                                null,
                                false
                            );

                    } else {

                        msgBox(
                            response.message ||
                            'Unable to delete product.',
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

                msgBox(
                    'Please select an inventory item from the search results and enter a valid quantity.',
                    'error'
                );

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

                url: `${base_url}/api/Products/add`,

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

                        msgBox(
                            response.message,
                            'success'
                        );

                        $('#product-modal')
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

                    let message =
                        'Request failed.';

                    if (xhr.responseText) {
                        message =
                            xhr.responseText;
                    }

                    msgBox(
                        message,
                        'error'
                    );
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

});