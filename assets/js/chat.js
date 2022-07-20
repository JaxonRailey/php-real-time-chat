const wsUrl     = await fetch('./ip.json');
const wsResp    = await wsUrl.json();
const ip        = wsResp.ip;
const port      = wsResp.port;
const name      = sessionStorage.getItem('name');
const avatar    = sessionStorage.getItem('avatar');
const id        = Math.random().toString(36).slice(2);
const websocket = new WebSocket('ws://' + ip + ':' + port);

let sent = false;
let open = true;

websocket.onopen = function() {

    const object = {
        id    : id,
        name  : name,
        avatar: avatar,
        open  : true
    };

    websocket.send(JSON.stringify(object));
}

websocket.onmessage = function(event) {

    const data = JSON.parse(event.data);

    if (data.users) {
        $('aside ul').innerHTML = '';
        data.users.forEach(user => {
            const date = new Date(user.date);
            const time = date.toLocaleTimeString('it-IT');
            const html = `
            <li>
                <img src="${user.avatar}" alt="${user.name}" />
                <div class="user">
                    <div>${user.name}</div>
                    <div>${time}</div>
                </div>
            </li>`;

            $('aside ul').innerHTML += html;
        });

        if (open) {
            $('header .status').innerText = 'Connesso';
            $('header img').src = avatar;
            $('header img').alt = data.last;
            $('.chat header .profile .name').innerText = data.last;
            open = false;
        } else if (data.last) {
            const now = Date.now();
            $('.notification').innerHTML += `
            <div class="toast entry" data-number="${now}">
                <p>L'utente <strong>${data.last}</strong> si &egrave; unito alla chat!</p>
            </div>`;

            new Audio('assets/audio/entry.wav').play();

            setTimeout(() => {
                $('[data-number="' + now + '"]').remove();
            }, 5000);
        }
    }

    if (data.leave) {
        const now = Date.now();
        $('.notification').innerHTML += `
        <div class="toast leave" data-number="${now}">
            <p>L'utente <strong>${data.leave.name}</strong> ha lasciato la chat!</p>
        </div>`;

        new Audio('assets/audio/leave.wav').play();

        setTimeout(() => {
            $('[data-number="' + now + '"]').remove();
        }, 5000);
    }

    if (!data.text) return;

    new Audio('assets/audio/message.wav').play();

    const date = new Date(data.date);
    const time = date.toLocaleTimeString('it-IT');
    const html = `
    <li class="${sent ? 'received' : 'sent'}">
        <div class="avatar" style="background-image: url('${data.avatar}')"></div>
        <div class="message">
            <div class="box">
                <div class="name">${data.name}</div>
                <div class="time">${time}</div>
            </div>
            <div>${data.text}</div>
        </div>
    </li>`

    $('main ul').innerHTML += html;
    $('main ul').scrollIntoView(false);

    $('footer input').value = '';
    sent = false;
};

websocket.onerror = function () {
    const now = Date.now();
    $('.notification').innerHTML += `
    <div class="toast error" data-number="${now}">
        <p>Si è verificato qualche problema...</p>
    </div>`;

    setTimeout(() => {
        $('[data-number="' + now + '"]').remove();
    }, 5000);
};

websocket.onclose = function () {
    $('main').classList.add('close');
    $('main').innerHTML = 'Connessione chiusa';
    $('footer input').value = '';
    $('header .status').innerText = 'Disconnesso';
    $('footer input').setAttribute('disabled', true);
    $('footer input').setAttribute('placeholder', '');
};

$('footer input').addEventListener('keypress', function (event) {
    if (event.which == 13) {
        event.preventDefault();
        const text = $('footer input').value.trim();
        if (!text) return;

        const json = {
            id  : id,
            text: text
        };

        sent = true;
        websocket.send(JSON.stringify(json));
    }
});

$('aside input').addEventListener('input', function (event) {
    const input  = $('aside input');
    const filter = input.value.toUpperCase();
    const items  = document.querySelectorAll('aside ul li');

    for (let i = 0; i < items.length; i++) {
        const text = items[i].querySelector('.user div:first-child').innerText;
        if (text.toUpperCase().indexOf(filter) > -1) {
            items[i].style.display = '';
        } else {
            items[i].style.display = 'none';
        }
    }
});