let state = {
  filters: {},
  cache: {},
  loading: false
};

let debounceTimer;
let isFirstLoad = true;

// INIT
document.addEventListener('DOMContentLoaded', initFilter);
document.addEventListener('yootheme:load', initFilter);

function initFilter() {

  if (window.__filterInitialized) return;
  window.__filterInitialized = true;

  state.filters = getFiltersFromURL();
  syncUIWithState();
  bindEvents();

  // не делаем AJAX если уже есть фильтр в URL
  if (!window.location.search) {
    runFilter();
  }

}


// EVENTS
function bindEvents() {

  document.querySelectorAll('#catalog-filter .filter').forEach(filter => {

    const type = filter.dataset.filter;
    const button = filter.querySelector('.filter-btn');

    filter.querySelectorAll('[data-value]').forEach(option => {

      option.addEventListener('click', () => {

        const value = option.dataset.value;
        const text = option.textContent;

        // если клик по уже активному — снимаем фильтр
        if (state.filters[type] === value) {
          delete state.filters[type];
          button.textContent = button.dataset.default || 'Все';
          option.classList.remove('uk-active');
        } else {

          state.filters[type] = value;

          filter.querySelectorAll('[data-value]').forEach(el => el.classList.remove('uk-active'));
          option.classList.add('uk-active');

          button.textContent = text;
        }

        updateURL();
        debounce(runFilter, 250);

      });

    });

  });

}


// FILTER
function runFilter() {
    if (isFirstLoad) {
      isFirstLoad = false;
      return;
    }
  const container = document.getElementById('products-container');

  const params = new URLSearchParams(window.location.search);
  const cacheKey = params.toString();

  // CACHE
  if (state.cache[cacheKey]) {
    animateSwap(container, state.cache[cacheKey]);
    return;
  }

  setLoading(true);

  const data = new FormData();
  data.append('action', 'filter_products');

  // URL = источник правды
  params.forEach((value, key) => {
    data.append(key, value);
  });

  fetch(mospal.ajaxurl, {
    method: 'POST',
    body: data
  })
  .then(res => {
    if (!res.ok) throw new Error('Network error');
    return res.text();
  })
  .then(html => {

    state.cache[cacheKey] = html;
    animateSwap(container, html);

  })
  .catch(err => {
    console.error(err);
    showError(container);
  })
  .finally(() => {
    setLoading(false);
  });

}


// ANIMATION
function animateSwap(container, html) {
  container.innerHTML = html;
  // обновляем UIkit
  if (window.UIkit) {
    UIkit.update();
  }

}


// 🔗 URL
function updateURL() {

  const params = new URLSearchParams();

  Object.keys(state.filters).forEach(key => {
    if (state.filters[key]) {
      params.set(key, state.filters[key]);
    }
  });

  const query = params.toString();
  const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;

  history.pushState({ ...state.filters }, '', newUrl);
}


// BACK / FORWARD
window.addEventListener('popstate', function (e) {

  state.filters = e.state || getFiltersFromURL();
  syncUIWithState();
  runFilter();

});


// SYNC UI
function syncUIWithState() {

  document.querySelectorAll('#catalog-filter .filter').forEach(filter => {

    const type = filter.dataset.filter;
    const button = filter.querySelector('.filter-btn');
    const value = state.filters[type] || '';

    let active = null;

    filter.querySelectorAll('[data-value]').forEach(option => {

      option.classList.remove('uk-active');

      if (option.dataset.value === value) {
        active = option;
      }

    });

    if (active) {
      active.classList.add('uk-active');
      button.textContent = active.textContent;
    } else {
      button.textContent = button.dataset.default || 'Все';
    }

  });

}


// URL → STATE
function getFiltersFromURL() {

  const params = new URLSearchParams(window.location.search);
  const obj = {};

  params.forEach((value, key) => {
    obj[key] = value;
  });

  return obj;
}


// DEBOUNCE
function debounce(fn, delay) {

  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(fn, delay);

}


// LOADER
function setLoading(flag) {

  state.loading = flag;

  const container = document.getElementById('products-container');

  if (flag) {
    container.classList.add('is-loading');
  } else {
    container.classList.remove('is-loading');
  }

}


// ERROR
function showError(container) {

  container.innerHTML = '<p class="uk-text-center">Ошибка загрузки 😢</p>';

}