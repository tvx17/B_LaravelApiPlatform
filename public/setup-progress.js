(() => {
    const panel = document.getElementById('setup-progress');
    if (!panel || !window.fetch || !window.ReadableStream) return;
    const translations = JSON.parse(panel.dataset.translations || '{}');
    const t = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`{${name}}`, value), translations[key] || key);
    let running = false;

    document.addEventListener('submit', async (event) => {
        if (event.submitter?.value !== 'run') return;
        event.preventDefault();
        if (running) return;
        const form = event.target;
        const data = new FormData(form);
        data.set('operation', 'run');
        running = true;
        const buttons = [...document.querySelectorAll('button')];
        buttons.forEach(button => button.disabled = true);
        panel.hidden = false;
        panel.querySelector('h2').textContent = t('Schritt wird ausgeführt');
        panel.querySelector('progress').hidden = false;
        panel.setAttribute('aria-busy', 'true');
        panel.querySelector('[data-message]').textContent = t('Verbindung zum Server wird aufgebaut …');
        panel.querySelector('[data-detail]').textContent = t('Bitte die Seite geöffnet lassen.');
        panel.scrollIntoView({block: 'center', behavior: 'smooth'});
        const started = Date.now();
        let lastUpdate = Date.now();
        const timer = setInterval(() => {
            const elapsed = Math.floor((Date.now() - started) / 1000);
            const silence = Math.floor((Date.now() - lastUpdate) / 1000);
            panel.querySelector('[data-time]').textContent = t('Laufzeit: {elapsed} s · letzte Statusmeldung vor {silence} s', {elapsed, silence});
            if (silence >= 15) {
                panel.querySelector('[data-detail]').textContent = t('Zurzeit keine neue Statusmeldung. Server oder Verbindung könnten warten; der Prozess kann weiterlaufen. Bitte nicht erneut starten.');
            }
        }, 1000);
        const render = html => {
            clearInterval(timer);
            document.open();
            document.write(html);
            document.close();
        };
        let completed = false;
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: {'Accept': 'application/x-ndjson'}
            });
            if (!response.headers.get('Content-Type')?.includes('application/x-ndjson')) {
                render(await response.text());
                return;
            }
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            const consume = line => {
                if (!line.trim()) return;
                const update = JSON.parse(line);
                lastUpdate = Date.now();
                if (update.type === 'progress') {
                    panel.querySelector('[data-message]').textContent = update.message;
                    panel.querySelector('[data-detail]').textContent = update.silent_seconds === null
                        ? t('Der Server bearbeitet diesen Schritt.')
                        : t('Prozess aktiv · keine neue Prozessausgabe seit {seconds} s. Das kann bei längeren Aufgaben normal sein.', {seconds: update.silent_seconds});
                } else if (update.type === 'result') {
                    completed = true;
                    render(update.html);
                }
            };
            while (true) {
                const {value, done} = await reader.read();
                buffer += decoder.decode(value, {stream: !done});
                const lines = buffer.split('\n');
                buffer = lines.pop();
                lines.forEach(consume);
                if (done) break;
            }
            if (buffer.trim()) consume(buffer);
            if (!completed) throw new Error('Statusübertragung ohne Abschluss beendet.');
        } catch {
            if (completed) return;
            clearInterval(timer);
            panel.setAttribute('aria-busy', 'false');
            panel.querySelector('[data-message]').textContent = t('Statusverbindung unterbrochen');
            panel.querySelector('[data-detail]').textContent = t('Der Schritt kann auf dem Server noch laufen. Nicht erneut absenden. Seite später neu laden; die Serversperre verhindert parallele Ausführung.');
            // Do not offer an immediate retry with the now consumed form token.
        }
    });
})();
