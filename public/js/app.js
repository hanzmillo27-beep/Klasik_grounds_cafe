const base_url = new URL('../', document.currentScript.src).href.replace(/\/$/, '');

function msgBox(result, message) {
    var Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
    Toast.fire({
        icon: result,
        title: message
    });
}

function isDelete(callback) {
    Swal.fire({
        title: 'Are you sure you want to delete this record?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#243e32',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((confirmed) => {
        callback(confirmed && confirmed.value == true);
    });
}

function handleAjaxError(xhr) {
    const message = xhr.responseJSON?.message;
    switch (xhr.status) {
        case 500:
            msgBox('error', message || 'Internal server error.');
            break;
        case 403:
            msgBox('error', message || 'You are not authorized to perform this action.');
            break;
        default:
            msgBox('error', message || 'Request failed.');
    }
}