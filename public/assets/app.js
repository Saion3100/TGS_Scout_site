const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.global-nav');
navToggle?.addEventListener('click', () => {
  const open = navToggle.getAttribute('aria-expanded') === 'true';
  navToggle.setAttribute('aria-expanded', String(!open));
  nav?.classList.toggle('is-open', !open);
});

const filterRoot = document.querySelector('[data-filter-root]');
if (filterRoot) {
  const buttons = [...filterRoot.querySelectorAll('[data-role]')];
  const search = filterRoot.querySelector('[data-search]');
  const selects = [...filterRoot.querySelectorAll('[data-filter-field]')];
  const cards = [...document.querySelectorAll('[data-student]')];
  let role = 'all';
  const update = () => {
    const query = search.value.trim().toLocaleLowerCase('ja');
    let count = 0;
    cards.forEach(card => {
      const visible = (role === 'all' || card.dataset.role === role) &&
        (!query || card.dataset.keywords.toLocaleLowerCase('ja').includes(query)) &&
        selects.every(select => {
          if (!select.value) return true;
          if (select.dataset.filterField === 'availability') return card.dataset[select.value] === '1';
          return card.dataset[select.dataset.filterField] === select.value;
        });
      card.hidden = !visible;
      if (visible) count++;
    });
    document.querySelector('[data-result-count]').textContent = count;
    document.querySelector('[data-no-results]').hidden = count !== 0;
  };
  buttons.forEach(button => button.addEventListener('click', () => {
    role = button.dataset.role;
    buttons.forEach(item => item.classList.toggle('is-active', item === button));
    update();
  }));
  search.addEventListener('input', update);
  selects.forEach(select => select.addEventListener('change', update));
}

const teamFilterRoot = document.querySelector('[data-team-filter-root]');
if (teamFilterRoot) {
  const cards = [...document.querySelectorAll('[data-team]')];
  const search = teamFilterRoot.querySelector('[data-team-search]');
  const selects = [...teamFilterRoot.querySelectorAll('[data-team-field]')];
  const updateTeams = () => {
    const query = search.value.trim().toLocaleLowerCase('ja');
    let count = 0;
    cards.forEach(card => {
      const visible = (!query || card.dataset.keywords.toLocaleLowerCase('ja').includes(query)) &&
        selects.every(select => !select.value || card.dataset[select.dataset.teamField].includes(select.value));
      card.hidden = !visible;
      if (visible) count++;
    });
    document.querySelector('[data-team-no-results]').hidden = count !== 0;
  };
  search.addEventListener('input', updateTeams);
  selects.forEach(select => select.addEventListener('change', updateTeams));
}

const contactForm = document.querySelector('[data-contact-form]');
const dialog = document.querySelector('[data-demo-dialog]');
contactForm?.addEventListener('submit', event => {
  event.preventDefault();
  if (contactForm.reportValidity()) dialog?.showModal();
});
document.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
