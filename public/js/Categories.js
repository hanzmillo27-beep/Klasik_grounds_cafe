$(function () {
    CategoriesModule.init();
});

const CategoriesModule = (function () {
    const s = {
        table: '#categories-table',
        form: '#categories-form',
        modal: '#category-modal',
        newBtn: '#btnNew',
        saveBtn: '#btnSave',
        modalTitle: '#modal-title',
        categoryName: '#category_name',
        id: '#id'
    };

    function init() {
        bindEvents();
        loadCategories();
    }

    function bindEvents() {
        $(s.newBtn).on('click', showAddModal);
        $(s.table).on('click', '.edit', handleEdit);
        $(s.table).on('click', '.delete', handleDelete);
        $(s.form).on('submit', submitForm);
        $(s.modal).on('hidden.bs.modal', resetForm);
    }

    function showAddModal(e) {
        e.preventDefault();

        resetForm();

        $(s.modalTitle).text('Add Category');
        $(s.saveBtn).text('Add');
        $(s.modal).modal('show');
    }

    function handleEdit(e) {
        e.preventDefault();

        const id = $(this).data('id');

        $.ajax({
            url: `${base_url}/api/Categories/edit/${id}`,
            type: 'GET',
            dataType: 'json'
        })
            .done((res) => {
                if (!res) return;

                $(s.categoryName).val(res.category_name);
                $(s.id).val(res.category_id);

                $(s.modalTitle).text('Edit Category');
                $(s.saveBtn).text('Update');
                $(s.modal).modal('show');
                $(s.categoryName).trigger('focus');
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            });
    }

    function submitForm(e) {
        e.preventDefault();

        const id = $(s.id).val();
        const fd = new FormData(e.target);

        const url = id
            ? `${base_url}/api/Categories/edit/${id}`
            : `${base_url}/api/Categories/create`;

        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done((res) => {
                const status = res.status || res.result;

                msgBox(status, res.message);

                if (status === 'success') {
                    loadCategories();
                    $(s.modal).modal('hide');
                    resetForm();
                }
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            });
    }

    function handleDelete(e) {
        e.preventDefault();

        const id = $(this).data('id');

        isDelete((confirmed) => {
            if (!confirmed) return;

            $.ajax({
                url: `${base_url}/api/Categories/delete/${id}`,
                type: 'DELETE',
                dataType: 'json'
            })
                .done((res) => {
                    const status = res.status || res.result;

                    msgBox(status, res.message);

                    if (status === 'success') {
                        loadCategories();
                    }
                })
                .fail((xhr) => {
                    handleAjaxError(xhr);
                });
        });
    }

    function loadCategories() {
        $(s.table).DataTable({
            responsive: true,
            autoWidth: true,
            destroy: true,
            order: [[0, 'asc']],
            ajax: {
                url: `${base_url}/api/Categories/all`,
                type: 'GET'
            },
            columns: [
                {
                    data: 'category_name'
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: (data) => `
                        <div class="text-center">
                            <a href="#" class="text-success edit"
                               data-id="${data.category_id}"
                               title="Edit Category">
                                <i class="fa fa-pen"></i>
                            </a> |
                            <a href="#" class="text-danger delete"
                               data-id="${data.category_id}"
                               title="Delete Category">
                                <i class="fa fa-trash"></i>
                            </a>
                        </div>`
                }
            ]
        });
    }

    function resetForm(e) {
        if (e) {
            e.preventDefault();
        }

        $(s.form).trigger('reset');
        $(s.id).val('');
        $(s.saveBtn).text('Add');
        $(s.modalTitle).text('Add Category');
    }

    return {
        init
    };
})();