const navToggle = document.querySelector('.nav-toggle');
document.querySelectorAll('.student-photo, .team-thumbnail').forEach(image => {
  const hideFailedImage = () => { image.hidden = true; };
  image.addEventListener('error', hideFailedImage);
  if (image.complete && image.naturalWidth === 0) hideFailedImage();
});
const nav = document.querySelector('.global-nav');
navToggle?.addEventListener('click', () => {
  const open = navToggle.getAttribute('aria-expanded') === 'true';
  navToggle.setAttribute('aria-expanded', String(!open));
  nav?.classList.toggle('is-open', !open);
});

const heroCarousel = document.querySelector('[data-hero-carousel]');
if (heroCarousel) {
  const slides = [...heroCarousel.querySelectorAll('[data-hero-slide]')];
  const dots = [...heroCarousel.querySelectorAll('[data-hero-dot]')];
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let current = 0;
  let timer;
  let touchStartX = 0;
  const showSlide = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => { const active = i === current; slide.classList.toggle('is-active', active); slide.setAttribute('aria-hidden', String(!active)); slide.tabIndex = active ? 0 : -1; });
    dots.forEach((dot, i) => { const active = i === current; dot.classList.toggle('is-active', active); dot.setAttribute('aria-current', String(active)); });
  };
  const stop = () => window.clearInterval(timer);
  const start = () => { stop(); if (!reduceMotion && slides.length > 1) timer = window.setInterval(() => showSlide(current + 1), 5000); };
  heroCarousel.querySelector('[data-hero-prev]')?.addEventListener('click', () => { showSlide(current - 1); start(); });
  heroCarousel.querySelector('[data-hero-next]')?.addEventListener('click', () => { showSlide(current + 1); start(); });
  dots.forEach(dot => dot.addEventListener('click', () => { showSlide(Number(dot.dataset.heroDot)); start(); }));
  heroCarousel.addEventListener('mouseenter', stop);
  heroCarousel.addEventListener('mouseleave', start);
  heroCarousel.addEventListener('focusin', stop);
  heroCarousel.addEventListener('focusout', start);
  heroCarousel.addEventListener('touchstart', event => { touchStartX = event.changedTouches[0].clientX; }, { passive: true });
  heroCarousel.addEventListener('touchend', event => { const distance = event.changedTouches[0].clientX - touchStartX; if (Math.abs(distance) > 45) showSlide(current + (distance < 0 ? 1 : -1)); start(); }, { passive: true });
  start();
}

const featuredSlider = document.querySelector('[data-featured-slider]');
if (featuredSlider) {
  const track = featuredSlider.querySelector('[data-featured-track]');
  const cards = [...track.children];
  const currentLabel = featuredSlider.querySelector('[data-featured-current]');
  const updateFeaturedSlider = () => {
    const overflowing = track.scrollWidth > track.clientWidth + 2;
    featuredSlider.classList.toggle('is-overflowing', overflowing);
    if (!overflowing || !cards.length) return;
    const positions = cards.map(card => card.offsetLeft - track.offsetLeft);
    const current = positions.reduce((nearest, position, index) => Math.abs(position - track.scrollLeft) < Math.abs(positions[nearest] - track.scrollLeft) ? index : nearest, 0);
    if (currentLabel) currentLabel.textContent = String(current + 1);
  };
  const moveFeaturedSlider = direction => {
    const card = cards[0];
    if (!card) return;
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    track.scrollBy({ left: direction * (card.getBoundingClientRect().width + gap), behavior: 'smooth' });
  };
  featuredSlider.querySelector('[data-featured-prev]')?.addEventListener('click', () => moveFeaturedSlider(-1));
  featuredSlider.querySelector('[data-featured-next]')?.addEventListener('click', () => moveFeaturedSlider(1));
  track.addEventListener('scroll', updateFeaturedSlider, { passive: true });
  window.addEventListener('resize', updateFeaturedSlider);
  updateFeaturedSlider();
}

const workSlider = document.querySelector('[data-work-slider]');
if (workSlider) {
  const track = workSlider.querySelector('[data-work-track]');
  const currentLabel = workSlider.querySelector('[data-work-current]');
  const totalLabel = workSlider.querySelector('[data-work-total]');
  const updateWorkSlider = () => {
    const overflowing = track.scrollWidth > track.clientWidth + 2;
    const pages = Math.max(1, Math.ceil(track.scrollWidth / track.clientWidth));
    const current = Math.min(pages, Math.round(track.scrollLeft / track.clientWidth) + 1);
    workSlider.classList.toggle('is-overflowing', overflowing);
    if (currentLabel) currentLabel.textContent = String(current);
    if (totalLabel) totalLabel.textContent = String(pages);
  };
  const moveWorkSlider = direction => track.scrollBy({ left: direction * track.clientWidth, behavior: 'smooth' });
  workSlider.querySelector('[data-work-prev]')?.addEventListener('click', () => moveWorkSlider(-1));
  workSlider.querySelector('[data-work-next]')?.addEventListener('click', () => moveWorkSlider(1));
  track.addEventListener('scroll', updateWorkSlider, { passive: true });
  window.addEventListener('resize', updateWorkSlider);
  updateWorkSlider();
}

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
        selects.every(select => !select.value || JSON.parse(card.dataset[select.dataset.teamField] || '[]').includes(select.value));
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
