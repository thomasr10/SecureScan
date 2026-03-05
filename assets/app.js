import './stimulus_bootstrap.js';
import './styles/app.css';
import './styles/securescan.css';

/* ============================================================
   SecureScan — JS global (landing + auth modal)
   ============================================================ */

/* ========= MODALE AUTH (100% global) ========= */
function getModal() {
  return document.getElementById('auth-modal');
}

window.openAuthModal = function (tab = 'login') {
  const modal = getModal();
  if (!modal) return;
  window.switchAuthTab(tab);
  modal.classList.add('open');
};

window.closeAuthModal = function () {
  const modal = getModal();
  if (!modal) return;
  modal.classList.remove('open');
};

window.switchAuthTab = function (tab) {
  const tabsWrap = document.getElementById('auth-tabs');

  ['login', 'register', 'forgot'].forEach((s) => {
    const el = document.getElementById('screen-' + s);
    if (el) el.classList.toggle('active', s === tab);
  });

  const b1 = document.getElementById('tab-login-btn');
  const b2 = document.getElementById('tab-register-btn');
  if (b1) b1.classList.toggle('active', tab === 'login');
  if (b2) b2.classList.toggle('active', tab === 'register');

  if (tabsWrap) tabsWrap.style.display = tab === 'forgot' ? 'none' : '';
};

window.showForgotScreen = function (e) {
  e.preventDefault();
  window.switchAuthTab('forgot');
  const success = document.getElementById('forgot-success');
  const block = document.getElementById('forgot-form-block');
  if (success) success.classList.remove('visible');
  if (block) block.style.display = '';
};

window.hideForgotScreen = function (e) {
  e.preventDefault();
  window.switchAuthTab('login');
};

window.handleForgot = function (e) {
  e.preventDefault();
  const email = document.getElementById('forgot-email')?.value?.trim();
  if (!email) return;
  const block = document.getElementById('forgot-form-block');
  const success = document.getElementById('forgot-success');
  if (block) block.style.display = 'none';
  if (success) success.classList.add('visible');
};

window.togglePw = function (id) {
  const input = document.getElementById(id);
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
};

/* Fermer la modale si clic sur overlay */
document.addEventListener('click', (e) => {
  const modal = getModal();
  if (!modal) return;
  if (e.target === modal) window.closeAuthModal();
});

/* ========= LANDING (tabs + submit) ========= */
let currentInputType = 'url';

window.switchTab = function (type) {
  currentInputType = type;

  const tabUrl = document.getElementById('tab-url');
  const tabFile = document.getElementById('tab-file');
  const inputUrl = document.getElementById('input-url');
  const inputFile = document.getElementById('input-file');

  if (tabUrl) tabUrl.classList.toggle('active', type === 'url');
  if (tabFile) tabFile.classList.toggle('active', type === 'file');
  if (inputUrl) inputUrl.style.display = type === 'url' ? '' : 'none';
  if (inputFile) inputFile.style.display = type === 'file' ? '' : 'none';

  window.updateSubmitBtn();
};

window.handleFile = function (e) {
  const f = e.target.files?.[0];
  const label = document.getElementById('file-label');
  if (f && label) label.textContent = f.name;
  window.updateSubmitBtn();
};

window.updateSubmitBtn = function () {
  const repoUrl = document.getElementById('repo-url');
  const fileInput = document.getElementById('file-input');
  const submitBtn = document.getElementById('submit-btn');
  if (!submitBtn) return;

  const urlOk = currentInputType === 'url' && repoUrl && repoUrl.value.trim() !== '';
  const fileOk = currentInputType === 'file' && fileInput && fileInput.files.length > 0;

  submitBtn.disabled = !(urlOk || fileOk);
};

/* IMPORTANT :
   On ne fait PAS de "fake login" ici.
   La vraie auth = Symfony.
   Donc sur submit : si pas connecté => on ouvre la modale.
   Si connecté => on laisse le form submit.
*/
window.handleSubmit = function (e) {
  const isLoggedIn = document.body.dataset.loggedin === '1';
  if (!isLoggedIn) {
    e.preventDefault();
    window.openAuthModal('login');
    return false;
  }
  return true;
};

document.addEventListener('DOMContentLoaded', () => {
  window.updateSubmitBtn();
});