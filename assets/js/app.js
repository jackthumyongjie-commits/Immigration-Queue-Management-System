document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
  }

  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // Auto dismiss alerts
  document.querySelectorAll('.alert-dismissible').forEach(alert => {
    setTimeout(() => {
      try {
        bootstrap.Alert.getOrCreateInstance(alert).close();
      } catch (_) {}
    }, 5000);
  });
});

async function postJSON(url, data = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_csrf_token"]')?.value
    || '';
  const body = { ...data, _csrf_token: csrf, ajax: 1 };
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf,
      'Accept': 'application/json',
    },
    body: new URLSearchParams(body),
  });
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (err) {
    console.error('Invalid JSON response', text);
    return { success: false, message: 'Server returned an invalid response.' };
  }
}

function refreshIn(ms, callback) {
  setInterval(callback, ms);
}
