const avatar = sessionStorage.getItem('avatar');

document.querySelectorAll('aside img').forEach(item => {
    if (item.src == avatar) {
        item.classList.add('active');
    }
});

$('[name="name"]').value = sessionStorage.getItem('name');

$('[type="button"]').addEventListener('click', function(event) {

    const action = $('form').getAttribute('action');
    const name   = $('[name="name"]').value;

    if (!name) {
        alert('Scrivi il tuo nome'); return;
    }

    if (!sessionStorage.getItem('avatar')) {
        alert('Seleziona l\'avatar'); return;
    }

    sessionStorage.setItem('name', name);
    location.href = action;
});

$('input').addEventListener('keypress', function (event) {
    if (event.which == 13) {
        $('[type="button"]').click();
        event.preventDefault();
    }
});

document.querySelectorAll('aside img').forEach(element => {
    element.addEventListener('click', function () {
        document.querySelectorAll('aside img').forEach(item => {
            item.classList.remove('active');
        });
        element.classList.add('active');
        sessionStorage.setItem('avatar', element.src);
    });
});