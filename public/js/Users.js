$(function () {
    UsersModule.init();
});

const UsersModule = (function () {
    const s = {
        table: '#users-table',
        form: '#users-form',
        modal: '#user-modal',
        newBtn: '#btnNew',
        saveBtn: '#btnSave',
        modalTitle: '#modal-title',
        username: '#username',
        password: '#password',
        role: '#role',
        id: '#id',
        csrf: '[name="_csrfToken"]'
    };

    function init() {
        bindEvents();
        loadUsers();
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
        $(s.modalTitle).text('Add User');
        $(s.saveBtn).text('Add');
        $(s.modal).modal('show');
    }

    function handleEdit(e) {
        e.preventDefault();

        const id = $(this).data('id');

        $.ajax({
            url: `${base_url}/api/Users/edit/${id}`,
            type: 'GET',
            dataType: 'json'
        })
            .done((res) => {
                if (!res) return;

                $(s.username).val(res.username);
                $(s.password).val('');
                $(s.role).val(res.role);
                $(s.id).val(res.id);

                $(s.modalTitle).text('Edit User');
                $(s.saveBtn).text('Update');
                $(s.modal).modal('show');
                $(s.name).trigger('focus');
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
            ? `${base_url}/api/Users/edit/${id}`
            : `${base_url}/api/Users/add`;

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
                    loadUsers();
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
                url: `${base_url}/api/Users/delete/${id}`,
                type: 'DELETE',
                dataType: 'json'
            })
                .done((res) => {
                    const status = res.status || res.result;

                    msgBox(status, res.message);

                    if (status === 'success') {
                        loadUsers();
                    }
                })
                .fail((xhr) => {
                    handleAjaxError(xhr);
                });
        });
    }

    function loadUsers() {
        $(s.table).DataTable({
            responsive: true,
            autoWidth: true,
            destroy: true,
            order: [[0, 'asc']],
            ajax: {
                url: `${base_url}/api/Users/all`,
                type: 'GET'
            },
            columns: [
                {
                    data: 'username'
                },
                {
                    data: 'role',
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data) => `
                        <div class="text-center">
                            <a href="#" class="text-success edit"
                               data-id="${data.id}" title="Edit User">
                                <i class="fa fa-pen"></i>
                            </a> |
                            <a href="#" class="text-danger delete"
                               data-id="${data.id}" title="Delete User">
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
        $(s.role).val('user');
        $(s.status).val('active');
        $(s.saveBtn).text('Add');
        $(s.modalTitle).text('Add User');
    }

    return {
        init
    };
})();

