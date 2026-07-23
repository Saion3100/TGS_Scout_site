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
  const cards = [...document.querySelectorAll('[data-student]')];
  let role = 'all';
  const update = () => {
    const query = search.value.trim().toLocaleLowerCase('ja');
    let count = 0;
    cards.forEach(card => {
      const visible = (role === 'all' || card.dataset.role === role) &&
        (!query || card.dataset.keywords.toLocaleLowerCase('ja').includes(query));
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
}

const contactForm = document.querySelector('[data-contact-form]');
const dialog = document.querySelector('[data-demo-dialog]');
contactForm?.addEventListener('submit', event => {
  event.preventDefault();
  if (contactForm.reportValidity()) dialog?.showModal();
});
document.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
