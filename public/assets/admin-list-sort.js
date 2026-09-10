'use strict';

document.querySelectorAll('[data-admin-sort-list]').forEach((list) => {
    const select = list.querySelector('[data-admin-sort]');
    const items = Array.from(list.querySelectorAll('[data-sort-id]'));
    const storageKey = `admin-list-sort:${list.dataset.adminSortList}`;
    const idCollator = new Intl.Collator('ja', { numeric: true });
    const nameCollator = new Intl.Collator('ja');
    const search = list.querySelector('[data-admin-search]');
    const empty = list.querySelector('[data-admin-search-empty]');
    const normalize = (value) => value.normalize('NFKC').toLocaleLowerCase('ja')
        .replace(/[\u30a1-\u30f6]/g, (char) => String.fromCharCode(char.charCodeAt(0) - 0x60))
        .replace(/\s+/gu, '');
    const searchText = new Map(items.map((item) => [item,
        normalize(`${item.dataset.sortId} ${item.dataset.sortName} ${item.textContent}`),
    ]));

    search.addEventListener('input', () => {
        const query = normalize(search.value);
        let visibleCount = 0;
        items.forEach((item) => {
            const matches = searchText.get(item).includes(query);
            item.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });
        empty.hidden = visibleCount !== 0;
    });

    try {
        select.value = localStorage.getItem(storageKey) === 'name' ? 'name' : 'id';
    } catch (_) {
        select.value = 'id';
    }

    const sortItems = () => {
        items.sort((a, b) => {
            const byId = idCollator.compare(a.dataset.sortId, b.dataset.sortId);
            return select.value === 'name'
                ? nameCollator.compare(a.dataset.sortName, b.dataset.sortName) || byId
                : byId;
        });
        items.forEach((item) => list.appendChild(item));
    };

    select.addEventListener('change', () => {
        sortItems();
        try {
            localStorage.setItem(storageKey, select.value);
        } catch (_) {
            // Sorting remains available when browser storage is disabled.
        }
    });
    sortItems();
});
