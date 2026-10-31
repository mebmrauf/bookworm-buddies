// Ask before destructive actions.
document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});

// Filters apply as soon as a choice is made.
document.querySelectorAll('form[data-autosubmit] select').forEach((select) => {
  select.addEventListener('change', () => select.form.submit());
});

// Show that something is happening the moment a page starts changing.
const startLoading = () => document.documentElement.classList.add('is-loading');

document.addEventListener('click', (event) => {
  const link = event.target.closest('a[href]');
  if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  const url = new URL(link.href);
  const samePage = url.pathname === location.pathname && url.search === location.search && url.hash;
  if (url.origin === location.origin && !samePage && !link.target) startLoading();
});

// Stop double submits, which matter most while an image uploads.
document.querySelectorAll('form').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    startLoading();
    if (form.method !== 'post') return;
    const button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    }
  });
});

// The back button can restore a page exactly as it was left, mid-submit included.
window.addEventListener('pageshow', () => {
  document.documentElement.classList.remove('is-loading');
  document.querySelectorAll('button[aria-busy="true"]').forEach((button) => {
    button.disabled = false;
    button.removeAttribute('aria-busy');
  });
});

// Photo picker: shrinks the photo to HD here first, so uploads are quick on mobile data. The server compresses again regardless.
const HD_LONG_EDGE = 1280;
const JPEG_QUALITY = 0.82;
const MAX_ORIGINAL_BYTES = 50 * 1024 * 1024;
const MAX_SERVER_BYTES = 10 * 1024 * 1024;

const formatSize = (bytes) => (bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

async function compressToHd(file) {
  const bitmap = await createImageBitmap(file); // Follows the camera's rotation.
  const scale = Math.min(1, HD_LONG_EDGE / Math.max(bitmap.width, bitmap.height));
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(bitmap.width * scale));
  canvas.height = Math.max(1, Math.round(bitmap.height * scale));
  const context = canvas.getContext('2d');
  context.fillStyle = '#fff'; // JPEG has no transparency.
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
  bitmap.close();
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
  return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
}

document.querySelectorAll('[data-upload]').forEach((box) => {
  const input = box.querySelector('input[type="file"]');
  const preview = box.querySelector('[data-upload-preview]');
  const current = box.querySelector('[data-upload-current]');
  const name = box.querySelector('[data-upload-name]');
  const hint = box.querySelector('[data-upload-hint]');
  const clear = box.querySelector('[data-upload-clear]');
  const submit = input.form.querySelector('button[type="submit"]');
  const defaultHint = hint.textContent;

  const show = (file, message = defaultHint) => {
    if (preview.src) URL.revokeObjectURL(preview.src);
    preview.hidden = !file;
    current.hidden = Boolean(file);
    clear.hidden = !file;
    name.textContent = file ? file.name : name.dataset.default;
    hint.textContent = message;
    if (file) preview.src = URL.createObjectURL(file);
  };

  const fail = (message) => {
    input.value = '';
    show(null, message);
    box.classList.add('upload--error');
  };

  input.addEventListener('change', async () => {
    const file = input.files[0];
    box.classList.remove('upload--error');
    if (!file) {
      show(null);
      return;
    }
    if (file.size > MAX_ORIGINAL_BYTES) {
      fail('That file is over 50 MB. Please choose a smaller one.');
      return;
    }

    hint.textContent = 'Preparing your photo...';
    submit.disabled = true;
    try {
      const hd = await compressToHd(file);
      const files = new DataTransfer();
      files.items.add(hd);
      input.files = files.files;
      show(hd, `Ready to upload: ${formatSize(hd.size)}${hd.size < file.size ? `, compressed from ${formatSize(file.size)}` : ''}.`);
    } catch (error) {
      // Not every browser can do this, so the server's compression takes over.
      if (file.size > MAX_SERVER_BYTES) {
        fail('That file is too large for this browser to compress. Please choose one under 10 MB.');
      } else {
        show(file);
      }
    } finally {
      submit.disabled = false;
    }
  });

  clear.addEventListener('click', () => {
    input.value = '';
    box.classList.remove('upload--error');
    show(null);
    input.focus();
  });

  input.addEventListener('dragenter', () => box.classList.add('upload--drag'));
  ['dragleave', 'drop'].forEach((type) => input.addEventListener(type, () => box.classList.remove('upload--drag')));
});

// Close the account menu when clicking elsewhere.
document.addEventListener('click', (event) => {
  document.querySelectorAll('details.menu[open]').forEach((menu) => {
    if (!menu.contains(event.target)) {
      menu.open = false;
    }
  });
});

// Search results appear as you type. Enter still opens the full results page.
const searchInput = document.getElementById('site-search');
const searchPanel = document.getElementById('search-results');
if (searchInput && searchPanel) {
  const SEARCH_DELAY_MS = 180;
  const cache = new Map();
  let timer;
  let controller;
  let options = [];
  let active = -1;

  const closePanel = () => {
    searchPanel.hidden = true;
    searchInput.setAttribute('aria-expanded', 'false');
    searchInput.removeAttribute('aria-activedescendant');
    active = -1;
  };

  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  const option = (href, picture, title, detail) => {
    const link = element('a', 'search__option');
    link.href = href;
    link.id = `search-option-${options.length}`;
    link.setAttribute('role', 'option');
    link.setAttribute('aria-selected', 'false');
    link.append(picture);
    const text = element('span', 'search__text');
    text.append(element('strong', '', title), element('span', '', detail));
    link.append(text);
    options.push(link);
    return link;
  };

  const render = (data, query) => {
    searchPanel.replaceChildren();
    options = [];
    active = -1;

    if (data.books.length) {
      searchPanel.append(element('div', 'search__group', 'Books'));
      data.books.forEach((book) => {
        let picture = element('span', 'search__thumb');
        if (book.cover) {
          picture = element('img');
          picture.src = book.cover;
          picture.alt = '';
          picture.width = 36;
          picture.height = 54;
        }
        searchPanel.append(option(`/book.php?id=${book.id}`, picture, book.title, [book.author, book.where].filter(Boolean).join(' · ')));
      });
    }

    if (data.people.length) {
      searchPanel.append(element('div', 'search__group', 'People'));
      data.people.forEach((person) => {
        const picture = element('span', 'avatar avatar--40', person.initials);
        searchPanel.append(option(`/profile.php?userID=${person.id}`, picture, person.name, [`@${person.username}`, person.where].filter(Boolean).join(' · ')));
      });
    }

    if (!options.length) {
      searchPanel.append(element('div', 'search__empty', `Nothing found for "${query}"`));
    } else {
      const all = element('a', 'search__option search__all', 'See all results');
      all.href = `/search.php?q=${encodeURIComponent(query)}`;
      all.id = `search-option-${options.length}`;
      all.setAttribute('role', 'option');
      all.setAttribute('aria-selected', 'false');
      options.push(all);
      searchPanel.append(all);
    }

    searchPanel.hidden = false;
    searchInput.setAttribute('aria-expanded', 'true');
  };

  const search = async (query) => {
    if (cache.has(query)) {
      render(cache.get(query), query);
      return;
    }
    controller?.abort();
    controller = new AbortController();
    try {
      const response = await fetch(`/search-suggest.php?q=${encodeURIComponent(query)}`, { signal: controller.signal });
      if (!response.ok) return;
      const data = await response.json();
      cache.set(query, data);
      if (searchInput.value.trim() === query) render(data, query);
    } catch (error) {
      // Superseded by newer typing, or offline. Enter still works.
    }
  };

  searchInput.addEventListener('input', () => {
    clearTimeout(timer);
    const query = searchInput.value.trim();
    if (query.length < 2) {
      controller?.abort();
      closePanel();
      return;
    }
    timer = setTimeout(() => search(query), SEARCH_DELAY_MS);
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closePanel();
      return;
    }
    if (searchPanel.hidden || !options.length) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      options[active]?.setAttribute('aria-selected', 'false');
      const step = event.key === 'ArrowDown' ? 1 : -1;
      active = (active + step + options.length) % options.length;
      options[active].setAttribute('aria-selected', 'true');
      options[active].scrollIntoView({ block: 'nearest' });
      searchInput.setAttribute('aria-activedescendant', options[active].id);
    } else if (event.key === 'Enter' && active >= 0) {
      event.preventDefault();
      startLoading();
      options[active].click();
    }
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.search')) closePanel();
  });
}
