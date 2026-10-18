(function () {
    const dialog = document.getElementById('login-dialog');
    if (!dialog) return;

    function openLogin() {
        if (!dialog.open) {
            dialog.showModal();
            document.body.classList.add('kg-dialog-open');
        }
    }

    document.querySelectorAll('[data-login-open]').forEach(function (button) {
        button.addEventListener('click', openLogin);
    });

    dialog.querySelector('[data-login-close]').addEventListener('click', function () {
        dialog.close();
    });

    dialog.addEventListener('click', function (event) {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right ||
            event.clientY < bounds.top || event.clientY > bounds.bottom) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', function () {
        document.body.classList.remove('kg-dialog-open');
        dialog.querySelector('#password').value = '';
    });

    if (dialog.dataset.autoOpen === 'true' || window.location.hash === '#login') {
        openLogin();
    }
})();
