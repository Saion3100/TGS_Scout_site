'use strict';
document.querySelectorAll('[data-member-student]').forEach((select) => {
    const warning = document.getElementById(select.getAttribute('aria-describedby'));
    const update = () => {
        const option = select.selectedOptions[0];
        warning.hidden = !option || !option.value || option.dataset.public !== '0';
    };
    select.addEventListener('change', update);
    update();
});