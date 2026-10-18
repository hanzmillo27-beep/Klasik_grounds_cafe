$(function () {
    var $dialog = $('#login-dialog');
    if (!$dialog.length) return;
    $('[data-login-open]').on('click', function () { $dialog.modal('show'); });
    $dialog.on('shown.bs.modal', function () { $('#username').trigger('focus'); });
    $dialog.on('hidden.bs.modal', function () { $('#password').val(''); });
    if ($dialog.data('auto-open') || window.location.hash === '#login') {
        $dialog.modal('show');
    }
});
