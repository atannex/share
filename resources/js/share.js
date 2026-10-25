/*! atannex/share: share buttons and sheet. No dependencies; safe to load more than once. */
(() => {
    if (window.AtannexShare) return;

    const script = document.currentScript;
    const messages = readJson(script && script.dataset.shareMessages);
    const prepared = new WeakMap();
    const timers = new WeakMap();

    /*
     *   [data-share]               a share: its settings (beacon, title, text, url, files)
     *   [data-share-popup="WxH"]   web platform: centred popup on desktop, new tab elsewhere
     *   [data-share-beacon]        counted by the page (the server can't see it)
     *   [data-share-action]        copy, embed, native, print
     *   [data-share-open="id"]     opens <x-share::sheet id="id">
     *   [data-share-close]         closes it
     *   [data-share-select]        selects its text on focus
     *
     * Every share fires "share:shared" on the share's element (it bubbles):
     *   document.addEventListener('share:shared', (e) => console.log(e.detail.platform))
     */

    document.addEventListener('click', onClick);
    document.addEventListener('focusin', (event) => {
        if (event.target.matches && event.target.matches('[data-share-select]')) event.target.select();
    });
    document.addEventListener('toggle', onToggle, true);

    reveal(document);
    new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === 1) reveal(node);
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });

    function onClick(event) {
        const opener = event.target.closest('[data-share-open]');
        if (opener) return openSheet(opener.dataset.shareOpen, opener);

        const closer = event.target.closest('[data-share-close]');
        if (closer) return closer.closest('dialog')?.close();

        if (event.target.matches('dialog[data-share-sheet]')) return backdrop(event);

        const button = event.target.closest('[data-share-platform]');
        if (!button || button.matches('details')) return;

        const root = button.closest('[data-share]');
        const platform = button.dataset.sharePlatform;
        const action = button.dataset.shareAction;

        if (action === 'copy' || action === 'embed') return copy(root, button, platform);
        if (action === 'native') return native(root, button);
        if (action === 'print') {
            window.print();
            return shared(root, platform);
        }

        if (button.hasAttribute('data-share-popup')) popup(event, button);
        shared(root, platform, button.hasAttribute('data-share-beacon'));
    }

    /* Web platforms ------------------------------------------------------- */

    function popup(event, link) {
        if (!window.matchMedia('(min-width: 768px) and (pointer: fine)').matches) return;

        const [width, height] = (link.dataset.sharePopup || '620x680').split('x').map(Number);
        const left = Math.round(window.screenX + (window.outerWidth - width) / 2);
        const top = Math.round(window.screenY + (window.outerHeight - height) / 3);
        const opened = window.open(link.href, 'share', `popup,width=${width},height=${height},left=${left},top=${top}`);

        if (opened) {
            event.preventDefault();
            opened.opener = null;
            opened.focus();
        }
        // Blocked: the link opens its own tab.
    }

    /* Copy ---------------------------------------------------------------- */

    async function copy(root, button, platform) {
        const text = button.dataset.shareValue || '';

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                legacyCopy(text);
            }
        } catch {
            try {
                legacyCopy(text);
            } catch {
                return announce(root, messages.copyFailed);
            }
        }

        done(button);
        announce(root, messages.copied);
        shared(root, platform, true);
    }

    function legacyCopy(text) {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        (document.querySelector('dialog[open]') || document.body).append(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        if (!ok) throw new Error('copy failed');
    }

    function done(button) {
        button.classList.add('is-done');
        clearTimeout(timers.get(button));
        timers.set(button, setTimeout(() => button.classList.remove('is-done'), 1800));
    }

    /* The device's share sheet -------------------------------------------- */

    function reveal(scope) {
        if (typeof navigator.share !== 'function') return;

        const buttons = scope.matches && scope.matches('[data-share-action="native"]') ? [scope] : scope.querySelectorAll('[data-share-action="native"]');
        buttons.forEach((button) => {
            button.hidden = false;
        });
    }

    async function native(root, button) {
        const config = settings(root);
        const data = { title: config.title, text: config.text, url: config.url };

        // Files (a video for Instagram or TikTok…): fetch first, then share on a second tap,
        // because the browser only allows share() straight after a tap.
        if (config.files && config.files.length && canShareFiles(config.files)) {
            const files = prepared.get(button);

            if (!files) {
                return prepare(root, button, config.files);
            }

            prepared.delete(button);
            restore(button);
            return run(root, { ...data, files, url: undefined, text: [config.text, config.url].filter(Boolean).join('\n') }, button);
        }

        return run(root, data, button);
    }

    async function run(root, data, button) {
        try {
            await navigator.share(data);
            shared(root, button.dataset.sharePlatform || 'native', true);
        } catch (error) {
            // AbortError: the reader closed the sheet.
            if (error && error.name !== 'AbortError') console.warn('[share]', error);
        }
    }

    function canShareFiles(files) {
        if (typeof navigator.canShare !== 'function') return false;

        try {
            return navigator.canShare({ files: files.map((file) => new File([''], file.name || 'file', { type: file.mime || '' })) });
        } catch {
            return false;
        }
    }

    async function prepare(root, button, list) {
        const label = button.querySelector('span:last-child');
        button.dataset.shareLabel ??= label ? label.textContent : '';
        button.disabled = true;

        try {
            const files = [];
            for (const file of list) {
                files.push(await download(file, (percent) => {
                    if (label) label.textContent = `${messages.preparing || '…'} ${percent}%`;
                }));
            }
            prepared.set(button, files);
            if (label) label.textContent = messages.ready || button.dataset.shareLabel;
            button.classList.add('is-ready');
        } catch {
            restore(button);
            announce(root, messages.fileFailed);
            const config = settings(root);
            await run(root, { title: config.title, text: config.text, url: config.url }, button);
        } finally {
            button.disabled = false;
        }
    }

    function restore(button) {
        const label = button.querySelector('span:last-child');
        if (label && button.dataset.shareLabel !== undefined) label.textContent = button.dataset.shareLabel;
        button.classList.remove('is-ready');
    }

    async function download(file, progress) {
        const response = await fetch(file.url, { credentials: 'same-origin' });
        if (!response.ok || !response.body) throw new Error(`HTTP ${response.status}`);

        const total = Number(response.headers.get('Content-Length')) || file.size || 0;
        const reader = response.body.getReader();
        const chunks = [];
        let received = 0;

        for (;;) {
            const { done: finished, value } = await reader.read();
            if (finished) break;
            chunks.push(value);
            received += value.length;
            if (total) progress(Math.min(99, Math.round((received / total) * 100)));
        }

        return new File(chunks, file.name || 'file', { type: file.mime || response.headers.get('Content-Type') || '' });
    }

    /* Sheet --------------------------------------------------------------- */

    function openSheet(id, opener) {
        const sheet = document.getElementById(id);
        if (!sheet || typeof sheet.showModal !== 'function' || sheet.open) return;

        sheet.showModal();
        document.documentElement.classList.add('share-locked');
        sheet.addEventListener('close', () => {
            document.documentElement.classList.remove('share-locked');
            opener && opener.focus && opener.focus();
        }, { once: true });
    }

    function backdrop(event) {
        const sheet = event.target;
        const box = sheet.querySelector('.share-sheet__panel')?.getBoundingClientRect() || sheet.getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
        if (!inside) sheet.close();
    }

    function onToggle(event) {
        const details = event.target;
        if (!details.matches || !details.matches('details[data-share-platform]') || !details.open || details.dataset.shareCounted) return;

        details.dataset.shareCounted = '1';
        shared(details.closest('[data-share]'), details.dataset.sharePlatform, true);
    }

    /* Counting and feedback ------------------------------------------------ */

    function shared(root, platform, beacon = false) {
        if (!root || !platform) return;

        const config = settings(root);

        if (beacon && config.beacon) {
            const body = new FormData();
            body.append('platform', platform);

            let sent = false;
            try {
                sent = navigator.sendBeacon && navigator.sendBeacon(config.beacon, body);
            } catch {
                sent = false;
            }
            if (!sent) fetch(config.beacon, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(() => {});
        }

        root.dispatchEvent(new CustomEvent('share:shared', { bubbles: true, detail: { platform, url: config.url || null, title: config.title || null } }));
    }

    function announce(root, message) {
        if (!message) return;
        const status = root && root.querySelector('[data-share-status]');
        if (!status) return;
        status.textContent = '';
        // A fresh node change makes screen readers repeat the same message.
        requestAnimationFrame(() => {
            status.textContent = message;
        });
    }

    function settings(root) {
        if (!root) return {};
        if (!root._shareSettings) root._shareSettings = readJson(root.dataset.share);
        return root._shareSettings;
    }

    function readJson(value) {
        try {
            return JSON.parse(value || '{}') || {};
        } catch {
            return {};
        }
    }

    window.AtannexShare = { open: openSheet, share: native };
})();
