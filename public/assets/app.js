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

// Stop double submits, which matter most while an image uploads.
document.querySelectorAll('form[method="post"]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    }
  });
});

// Preview the chosen image and catch oversized files before uploading.
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
document.querySelectorAll('[data-image-input]').forEach((input) => {
  const preview = document.querySelector(`[data-preview="${input.dataset.imageInput}"]`);
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (file && file.size > MAX_IMAGE_BYTES) {
      input.value = '';
      preview.hidden = true;
      alert('Image must be 5 MB or smaller.');
      return;
    }
    preview.hidden = !file;
    if (file) {
      preview.src = URL.createObjectURL(file);
    }
  });
});

// Close the account menu when clicking elsewhere.
document.addEventListener('click', (event) => {
  document.querySelectorAll('details.menu[open]').forEach((menu) => {
    if (!menu.contains(event.target)) {
      menu.open = false;
    }
  });
});
