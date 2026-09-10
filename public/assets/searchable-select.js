'use strict';

document.querySelectorAll('[data-searchable-select]').forEach((select, index) => {
    const normalize = (text) => text.normalize('NFKC').toLocaleLowerCase('ja')
        .replace(/[\u30a1-\u30f6]/g, (c) => String.fromCharCode(c.charCodeAt(0) - 0x60))
        .replace(/\s+/gu, '');
    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'searchable-select-trigger';
    button.disabled = select.disabled;
    button.setAttribute('aria-expanded', 'false');
    const panel = document.createElement('div');
    panel.className = 'searchable-select-panel';
    panel.id = `searchable-select-${index}`;
    panel.hidden = true;
    button.setAttribute('aria-controls', panel.id);
    const search = document.createElement('input');
    search.type = 'search';
    search.placeholder = 'ID・名前で検索';
    search.setAttribute('aria-label', '候補を検索（学生はふりがなでも検索できます）');
    const results = document.createElement('div');
    results.className = 'searchable-select-results';
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    panel.append(search, results, status);
    wrapper.append(button, panel);
    select.after(wrapper);
    select.hidden = true;

    const label = select.closest('label');
    const labelText = label ? Array.from(label.childNodes)
        .filter((node) => node !== select && node !== wrapper)
        .map((node) => node.textContent).join('').trim() : '選択';
    const sync = () => {
        const option = select.selectedOptions[0];
        button.textContent = option ? option.textContent : '選択してください';
        button.setAttribute('aria-label', `${labelText}：${button.textContent}`);
    };
    const close = () => {
        panel.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    };
    const render = () => {
        results.replaceChildren();
        const query = normalize(search.value);
        let count = 0;
        Array.from(select.options).forEach((option) => {
            if (option.disabled || (query && !normalize(`${option.value} ${option.textContent} ${option.dataset.search || ''}`).includes(query))) return;
            const choice = document.createElement('button');
            choice.type = 'button';
            choice.textContent = option.value && !option.textContent.trim().startsWith(`${option.value}：`)
                ? `${option.value}：${option.textContent}` : option.textContent;
            choice.className = option.selected ? 'is-selected' : '';
            choice.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                button.focus();
            });
            results.append(choice);
            count++;
        });
        status.textContent = count ? `${count}件の候補` : '該当する候補がありません。';
    };
    const open = () => {
        panel.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        search.value = '';
        render();
        search.focus();
    };
    button.addEventListener('click', () => panel.hidden ? open() : close());
    search.addEventListener('input', render);
    select.addEventListener('change', sync);
    select.addEventListener('invalid', (event) => {
        event.preventDefault();
        open();
        status.textContent = '候補を選択してください。';
    });
    wrapper.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { close(); button.focus(); event.preventDefault(); }
        if (event.key === 'Enter' && event.target === search) event.preventDefault();
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (panel.hidden) { open(); return; }
            const choices = Array.from(results.children);
            const current = choices.indexOf(document.activeElement);
            const next = current + (event.key === 'ArrowDown' ? 1 : -1);
            if (choices.length) choices[Math.max(0, Math.min(choices.length - 1, next))].focus();
        }
    });
    document.addEventListener('click', (event) => { if (!wrapper.contains(event.target)) close(); });
    wrapper.addEventListener('focusout', (event) => { if (!wrapper.contains(event.relatedTarget)) close(); });
    sync();
});
