// assets/securescan.js

window.openAuthModal = function(tab) {
  window.switchAuthTab(tab || 'login');
  document.getElementById('auth-modal')?.classList.add('open');
};

window.closeAuthModal = function() {
  document.getElementById('auth-modal')?.classList.remove('open');
};

window.switchAuthTab = function(tab) {
  const tabs = document.getElementById('auth-tabs');
  if (tabs) tabs.style.display = (tab === 'forgot') ? 'none' : '';

  ['login','register','forgot'].forEach(s => {
    const el = document.getElementById('screen-' + s);
    if (el) el.classList.toggle('active', s === tab);
  });

  document.getElementById('tab-login-btn')?.classList.toggle('active', tab === 'login');
  document.getElementById('tab-register-btn')?.classList.toggle('active', tab === 'register');
};

document.addEventListener('click', (e) => {
  const modal = document.getElementById('auth-modal');
  if (modal && e.target === modal) window.closeAuthModal();
});