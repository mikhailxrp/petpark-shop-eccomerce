// Пакетный ИИ-разбор Характеристик: повторяет POST порциями, пока очередь не
// опустеет, страница открыта и разбор не остановлен. Прогресс и итог — только
// счётчиками с сервера; ничего в каталоге не меняется (черновики).

const NETWORK_ERROR_MESSAGE = 'Не удалось выполнить запрос. Необработанные Товары остались в очереди.';
const DONE_MESSAGE = 'Разбор завершён. Проверьте черновики.';
const STOPPED_MESSAGE = 'Разбор остановлен. Необработанные Товары остались в очереди.';

const COUNTER_IDS = {
    queue: 'ai-batch-queue',
    processed: 'ai-batch-done',
    pending: 'ai-batch-pending',
    needs_decision: 'ai-batch-decision',
};

const showCounts = (counts) => {
    Object.entries(COUNTER_IDS).forEach(([key, id]) => {
        const element = document.getElementById(id);
        if (element && key in counts) {
            element.textContent = String(counts[key]);
        }
    });
};

const postBatch = async (form, afterId) => {
    const body = new FormData(form);
    body.set('after_id', String(afterId));

    const response = await fetch(form.dataset.runUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
};

const init = () => {
    const form = document.getElementById('ai-batch-form');
    const startButton = document.getElementById('ai-batch-start');
    const stopButton = document.getElementById('ai-batch-stop');
    const status = document.getElementById('ai-batch-status');
    if (!form || !startButton || !stopButton || !status) {
        return;
    }

    let stopRequested = false;

    const setRunning = (running) => {
        startButton.disabled = running;
        stopButton.hidden = !running;
    };

    const run = async () => {
        stopRequested = false;
        setRunning(true);
        status.textContent = 'Идёт разбор…';

        let afterId = 0;
        try {
            while (!stopRequested) {
                const result = await postBatch(form, afterId);
                afterId = result.last_id;
                showCounts(result.counts);

                if (result.stop) {
                    status.textContent = result.message;
                    return;
                }
                if (result.done) {
                    status.textContent = DONE_MESSAGE;
                    return;
                }
                status.textContent = `Идёт разбор… осталось в очереди: ${result.counts.queue}`;
            }
            status.textContent = STOPPED_MESSAGE;
        } catch {
            status.textContent = NETWORK_ERROR_MESSAGE;
        } finally {
            setRunning(false);
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        run();
    });
    stopButton.addEventListener('click', () => {
        stopRequested = true;
    });
};

init();
