// Ask before destructive actions.
const confirmBeforeSubmit = (form) => {
  form.addEventListener('submit', (event) => {
    if (!confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
};
document.querySelectorAll('form[data-confirm]').forEach(confirmBeforeSubmit);

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
const stopDoubleSubmit = (form) => {
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
};
document.querySelectorAll('form:not([data-chat-form])').forEach(stopDoubleSubmit);

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

// Show or hide a password.
document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
  const input = toggle.parentElement.querySelector('input');
  toggle.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.querySelector('[data-eye-show]').hidden = show;
    toggle.querySelector('[data-eye-hide]').hidden = !show;
    input.focus();
  });
});

// Installing as a phone app.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {
    // Only the offline page depends on this, so the site works without it.
  });
}

const isInstalled = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const installMenuItem = document.querySelector('[data-install]');
const installSheet = document.querySelector('[data-install-sheet]');
const INSTALL_DISMISSED_KEY = 'installDismissedAt';
const INSTALL_ASK_AGAIN_MS = 30 * 24 * 60 * 60 * 1000;
const INSTALL_POPUP_DELAY_MS = 3000;
const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
let installPrompt = null;

const onPhone = () => window.matchMedia('(pointer: coarse) and (max-width: 767px)').matches;

const installWasDismissed = () => {
  try {
    return Date.now() - Number(localStorage.getItem(INSTALL_DISMISSED_KEY)) < INSTALL_ASK_AGAIN_MS;
  } catch {
    return false;
  }
};

const closeInstallSheet = (remember) => {
  if (installSheet) installSheet.hidden = true;
  if (!remember) return;
  try {
    localStorage.setItem(INSTALL_DISMISSED_KEY, String(Date.now()));
  } catch {
    // Without storage the popup just comes back on a later visit.
  }
};

const openInstallSheet = () => {
  if (!installSheet) return;
  // iPhones have no install prompt, so they get the steps instead of a button.
  if (isIOS) {
    installSheet.querySelector('[data-install-text]').textContent = 'Tap the Share button in your browser, then "Add to Home Screen".';
    installSheet.querySelector('[data-install-accept]').hidden = true;
  }
  installSheet.hidden = false;
};

const runInstallPrompt = async () => {
  closeInstallSheet(false);
  installPrompt.prompt();
  const { outcome } = await installPrompt.userChoice;
  installPrompt = null;
  if (installMenuItem) installMenuItem.hidden = true;
  if (outcome !== 'accepted') closeInstallSheet(true);
};

// Only on phones, and not while someone is typing in a chat. Browsers don't offer the prompt once it's installed.
const offerInstall = () => {
  if (installMenuItem) installMenuItem.hidden = false;
  if (onPhone() && !installWasDismissed() && !document.querySelector('[data-chat]')) {
    setTimeout(openInstallSheet, INSTALL_POPUP_DELAY_MS);
  }
};

window.addEventListener('beforeinstallprompt', (event) => {
  // Phones get our popup instead of the browser's banner. Computers have no banner, only an icon in the address bar.
  if (onPhone()) event.preventDefault();
  installPrompt = event;
  if (!isInstalled) offerInstall();
});

if (isIOS && !isInstalled) offerInstall();

installMenuItem?.addEventListener('click', () => {
  if (installPrompt) {
    runInstallPrompt();
  } else {
    openInstallSheet();
  }
});
installSheet?.querySelector('[data-install-accept]').addEventListener('click', () => {
  if (installPrompt) runInstallPrompt();
});
installSheet?.querySelector('[data-install-dismiss]').addEventListener('click', () => closeInstallSheet(true));

window.addEventListener('appinstalled', () => {
  closeInstallSheet(false);
  if (installMenuItem) installMenuItem.hidden = true;
});

// Blog posts open in a popup, with their own address so they can be shared. The links still work without this.
const postDialog = document.querySelector('[data-post-dialog]');
if (postDialog && document.querySelector('.post-card')) {
  const body = postDialog.querySelector('[data-post-dialog-body]');
  const listTitle = document.title;

  const showPost = async (url) => {
    body.textContent = 'Loading...';
    if (!postDialog.open) postDialog.showModal();
    postDialog.scrollTop = 0;
    const address = new URL(url, location.origin);
    address.searchParams.set('popup', '1');
    try {
      const response = await fetch(address);
      if (!response.ok) throw new Error(response.status);
      body.innerHTML = await response.text();
    } catch {
      location.href = url;
      return;
    }
    body.querySelectorAll('form[data-confirm]').forEach(confirmBeforeSubmit);
    body.querySelectorAll('form').forEach(stopDoubleSubmit);
    document.title = `${body.querySelector('h1')?.textContent ?? 'Blog'} - Bookworm Buddies`;
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.post-card a[href^="/post.php"]');
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    event.stopPropagation();
    history.pushState({ post: link.getAttribute('href') }, '', link.href);
    showPost(link.getAttribute('href'));
  }, true);

  const leavePost = () => {
    if (history.state?.post) {
      history.back();
    } else {
      postDialog.close();
    }
  };

  postDialog.querySelector('[data-post-dialog-close]').addEventListener('click', leavePost);
  // A click on the darkened area outside lands on the dialog itself.
  postDialog.addEventListener('click', (event) => {
    if (event.target === postDialog) leavePost();
  });
  // Escape closes it directly, so the address needs putting back too.
  postDialog.addEventListener('cancel', (event) => {
    event.preventDefault();
    leavePost();
  });
  postDialog.addEventListener('close', () => {
    document.title = listTitle;
  });
  window.addEventListener('popstate', (event) => {
    if (event.state?.post) {
      showPost(event.state.post);
    } else if (postDialog.open) {
      postDialog.close();
    }
  });
}

// Chat: checks for new messages every few seconds, only while the chat is on screen.
const chat = document.querySelector('[data-chat]');
if (chat) {
  const POLL_MS = 3000;
  const IDLE_POLL_MS = 10000;
  const IDLE_AFTER_MS = 120000;
  const log = chat.querySelector('[data-chat-log]');
  const form = chat.querySelector('[data-chat-form]');
  const status = chat.querySelector('[data-chat-status]');
  const empty = chat.querySelector('[data-chat-empty]');
  const swapID = chat.dataset.chat;
  const reportTemplate = chat.querySelector('[data-report-template]');
  let lastID = Number(chat.dataset.last) || 0;
  let lastActivity = Date.now();
  let timer;
  let polling = false;

  const isNearBottom = () => log.scrollHeight - log.scrollTop - log.clientHeight < 80;
  const scrollDown = () => { log.scrollTop = log.scrollHeight; };

  // A send and a check can return out of order, so messages go in by ID and duplicates are skipped.
  const addMessage = (message) => {
    if (log.querySelector(`[data-id="${message.id}"]`)) return;
    empty?.remove();
    const bubble = document.createElement('div');
    bubble.className = `bubble${message.mine ? ' bubble--mine' : ''}`;
    bubble.dataset.id = message.id;
    bubble.append(message.body);
    const meta = document.createElement('div');
    meta.className = 'bubble__meta';
    const time = document.createElement('time');
    time.textContent = message.time;
    meta.append(time);
    if (!message.mine && reportTemplate) {
      const report = reportTemplate.content.cloneNode(true);
      report.querySelector('input[name="id"]').value = message.id;
      meta.append(report);
    }
    bubble.append(meta);
    const next = [...log.querySelectorAll('[data-id]')].find((other) => Number(other.dataset.id) > message.id);
    log.insertBefore(bubble, next || null);
  };

  const schedule = () => {
    clearTimeout(timer);
    if (document.hidden || chat.dataset.open !== '1' && !polling) return;
    timer = setTimeout(poll, Date.now() - lastActivity > IDLE_AFTER_MS ? IDLE_POLL_MS : POLL_MS);
  };

  async function poll() {
    if (polling || document.hidden) return;
    polling = true;
    try {
      const response = await fetch(`/chat-messages.php?swap=${swapID}&after=${lastID}`);
      if (response.ok) {
        const data = await response.json();
        const stick = isNearBottom();
        if (data.messages.length) {
          lastActivity = Date.now();
          data.messages.forEach(addMessage);
          lastID = Math.max(lastID, ...data.messages.map((message) => message.id));
          if (stick) scrollDown();
        }
        if (!data.open && form) {
          form.remove();
          status.textContent = 'This chat has ended, so no more messages can be sent.';
          chat.dataset.open = '0';
        }
      }
    } catch (error) {
      // A brief connection problem. The next check picks up where this one stopped.
    } finally {
      polling = false;
      schedule();
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      lastActivity = Date.now();
      poll();
    } else {
      clearTimeout(timer);
    }
  });

  scrollDown();
  poll();

  if (form) {
    const input = form.querySelector('textarea');
    const send = form.querySelector('button[type="submit"]');
    const grow = () => {
      input.style.height = 'auto';
      input.style.height = `${input.scrollHeight + 2}px`;
    };
    input.addEventListener('input', grow);
    input.addEventListener('keydown', (event) => {
      // Enter sends on a computer. Phones keep it as a new line, with the Send button beside it.
      if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && window.matchMedia('(pointer: fine)').matches) {
        event.preventDefault();
        form.requestSubmit();
      }
    });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const text = input.value.trim();
      if (!text || send.disabled) return;
      send.disabled = true;
      status.textContent = '';
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: new FormData(form),
        });
        const data = await response.json();
        if (!response.ok) {
          status.textContent = data.error || 'That message could not be sent. Please try again.';
        } else {
          input.value = '';
          grow();
          lastActivity = Date.now();
          addMessage(data.message);
          scrollDown();
        }
      } catch (error) {
        status.textContent = 'That message could not be sent. Check your connection and try again.';
      } finally {
        send.disabled = false;
        input.focus();
      }
    });
  }
}
