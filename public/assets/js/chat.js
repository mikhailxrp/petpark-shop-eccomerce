const CHAT_URL = '/chat';
const NETWORK_TIMEOUT_MS = 15000;
const NETWORK_ERROR_TEXT = 'Не удалось отправить вопрос. Проверьте соединение и попробуйте снова.';

const widget = document.getElementById('chat-widget');

if (widget) {
    const toggle = document.getElementById('chat-toggle');
    const closeButton = document.getElementById('chat-close');
    const panel = document.getElementById('chat-panel');
    const messages = document.getElementById('chat-messages');
    const fallback = document.getElementById('chat-fallback');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const send = document.getElementById('chat-send');
    const csrf = widget.dataset.csrf ?? '';

    const scrollToEnd = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const addMessage = (text, role) => {
        const item = document.createElement('p');
        item.className = `chat-widget__message chat-widget__message--${role}`;
        item.textContent = text;
        messages.append(item);
        scrollToEnd();
        return item;
    };

    const addCards = (cards) => {
        if (!cards.length) {
            return;
        }
        const list = document.createElement('ul');
        list.className = 'chat-widget__cards';

        cards.forEach((card) => {
            const item = document.createElement('li');
            item.className = 'chat-widget__card';

            const link = document.createElement('a');
            link.className = 'chat-widget__card-link';
            link.href = card.url;
            link.textContent = card.title;

            const meta = document.createElement('span');
            meta.className = 'chat-widget__card-meta';
            meta.textContent = `${card.price} · ${card.note}`;

            item.append(link, meta);
            list.append(item);
        });

        messages.append(list);
        scrollToEnd();
    };

    const setBusy = (busy) => {
        input.disabled = busy;
        send.disabled = busy;
    };

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) {
            input.focus();
        }
    };

    const requestAnswer = async (text) => {
        const body = new URLSearchParams({ message: text, _csrf: csrf });
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), NETWORK_TIMEOUT_MS);

        try {
            const response = await fetch(CHAT_URL, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
                signal: controller.signal,
            });
            return await response.json();
        } finally {
            clearTimeout(timer);
        }
    };

    const showFallback = () => {
        fallback.hidden = false;
        scrollToEnd();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const text = input.value.trim();
        if (!text) {
            return;
        }

        addMessage(text, 'user');
        input.value = '';
        setBusy(true);
        const typing = addMessage('Консультант печатает…', 'typing');

        try {
            const data = await requestAnswer(text);
            typing.remove();

            if (data.status === 'fallback') {
                showFallback();
            } else {
                addMessage(data.text, data.status === 'ok' ? 'bot' : 'error');
                addCards(data.cards ?? []);
            }
        } catch {
            typing.remove();
            addMessage(NETWORK_ERROR_TEXT, 'error');
        } finally {
            setBusy(false);
            input.focus();
        }
    });

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    closeButton.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });

    widget.hidden = false;
}
