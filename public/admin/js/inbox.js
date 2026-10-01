// Единый инбокс: обновление без перезагрузки опросом сервера (FR-CHANNELS-001/002).
// Список (`#inbox-list`) и открытая переписка (`#chat-thread`) читают
// /admin/inbox/poll?since=<id сообщения>; разметка строится через textContent,
// текст сообщений в HTML не попадает.

const SIMULATE_ERROR_MESSAGE = 'Не удалось сымитировать входящее. Обновите страницу и попробуйте снова.';

const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    if (text !== undefined) {
        node.textContent = text;
    }
    return node;
};

const fetchJson = async (url) => {
    const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        cache: 'no-store',
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
};

// ─── Список Обращений ───────────────────────────────────────────────────

const buildListItem = (row) => {
    const item = element('li');
    item.dataset.conversationId = String(row.id);

    const link = element('a', row.unread ? 'inbox-item inbox-item--unread' : 'inbox-item');
    link.href = `/admin/inbox/${row.id}`;

    const sender = element('span', 'inbox-item__sender', row.sender);
    if (row.identified) {
        sender.append(' ', element('span', 'badge bg-success-transparent', 'Покупатель'));
    }
    if (row.unread) {
        sender.append(' ', element('span', 'badge bg-primary', 'Непрочитано'));
    }

    const body = element('span', 'inbox-item__body');
    body.append(sender, element('span', 'inbox-item__preview', row.preview));

    link.append(
        element('span', 'badge bg-light text-dark inbox-item__channel', row.channel),
        body,
        element('span', 'inbox-item__time', row.time),
    );
    item.append(link);
    return item;
};

const renderRows = (list, rows, perPage) => {
    // rows приходят «новые сверху»; вставляем с конца, чтобы порядок сохранился.
    [...rows].reverse().forEach((row) => {
        list.querySelector(`[data-conversation-id="${row.id}"]`)?.remove();
        list.prepend(buildListItem(row));
    });
    while (list.children.length > perPage) {
        list.lastElementChild.remove();
    }
};

const startListPolling = (root) => {
    const list = document.getElementById('inbox-list');
    const total = document.getElementById('inbox-total');
    const empty = document.getElementById('inbox-empty');
    const perPage = Number(root.dataset.perPage);

    return async (since) => {
        const data = await fetchJson(`${root.dataset.pollUrl}?since=${since}`);
        if (data.rows.length > 0) {
            renderRows(list, data.rows, perPage);
        }
        total.textContent = String(data.total);
        empty.hidden = list.children.length > 0;
        return data.latest;
    };
};

// ─── Открытая переписка ─────────────────────────────────────────────────

const buildBubble = (message) => {
    const bubble = element('div', `chat-bubble ${message.incoming ? 'chat-bubble--in' : 'chat-bubble--out'}`);
    bubble.append(element('span', 'visually-hidden', message.incoming ? 'Входящее:' : 'Исходящее:'));

    message.body.split('\n').forEach((line, index) => {
        if (index > 0) {
            bubble.append(document.createElement('br'));
        }
        bubble.append(line);
    });

    bubble.append(element('span', 'chat-bubble__time', message.time));
    return bubble;
};

const startThreadPolling = (root) => async (since) => {
    const url = `${root.dataset.pollUrl}?conversation=${root.dataset.conversation}&since=${since}`;
    const data = await fetchJson(url);
    data.messages.forEach((message) => root.append(buildBubble(message)));
    return data.latest;
};

// ─── Опрос ──────────────────────────────────────────────────────────────

const startPolling = (root, fetchUpdates) => {
    let since = Number(root.dataset.since);
    let running = false;

    const tick = async () => {
        if (running || document.hidden) {
            return;
        }
        running = true;
        try {
            since = await fetchUpdates(since);
        } catch {
            // Сеть/сервер недоступны — тихо пробуем на следующем тике.
        } finally {
            running = false;
        }
    };

    window.setInterval(tick, Number(root.dataset.interval));
    return tick;
};

// «Сымитировать входящее» без перезагрузки: POST, затем внеочередной опрос.
const initSimulate = (form, tick) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                body: new FormData(form),
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            await tick();
        } catch {
            window.alert(SIMULATE_ERROR_MESSAGE);
        } finally {
            button.disabled = false;
        }
    });
};

const init = () => {
    const root = document.querySelector('[data-inbox-poll]');
    if (!root) {
        return;
    }

    const isThread = root.dataset.conversation !== undefined;
    const tick = startPolling(root, isThread ? startThreadPolling(root) : startListPolling(root));

    const simulateForm = document.getElementById('inbox-simulate');
    if (simulateForm && !isThread) {
        initSimulate(simulateForm, tick);
    }
};

init();
