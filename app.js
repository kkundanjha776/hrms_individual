const loginScreen = document.querySelector('#loginScreen');
const app = document.querySelector('#app');
const toast = document.querySelector('#toast');
const showToast = (message) => { toast.textContent = message; toast.classList.add('show'); window.setTimeout(() => toast.classList.remove('show'), 2600); };

document.querySelector('#loginForm').addEventListener('submit', (event) => {
  event.preventDefault();
  loginScreen.classList.add('hidden');
  app.classList.remove('hidden');
  lucide.createIcons();
  showToast('Welcome back, Arjun.');
});

document.querySelector('.password-toggle').addEventListener('click', () => {
  const input = document.querySelector('#password');
  input.type = input.type === 'password' ? 'text' : 'password';
});

document.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => {
  document.querySelectorAll('.nav-item').forEach((item) => item.classList.remove('active'));
  const nav = document.querySelector(`.nav-item[data-view="${button.dataset.view}"]`);
  if (nav) nav.classList.add('active');
  document.querySelector('#pageTitle').textContent = button.dataset.view;
  document.querySelector('.sidebar').classList.remove('open');
  showToast(`${button.dataset.view} module is ready for the next build phase.`);
}));

document.querySelector('#addEmployee').addEventListener('click', () => showToast('Employee onboarding form opened in the full HRMS build.'));
document.querySelectorAll('.approve').forEach((button) => button.addEventListener('click', () => { const request = button.closest('.request'); request.querySelector('.status').textContent = 'Approved'; request.querySelector('.status').className = 'status'; button.parentElement.querySelector('.reject').remove(); button.remove(); showToast('Leave request approved.'); }));
document.querySelectorAll('.reject').forEach((button) => button.addEventListener('click', () => { button.closest('.request').remove(); showToast('Leave request declined.'); }));
document.querySelector('.send-wish').addEventListener('click', () => showToast('Birthday wishes sent to Alex.'));
document.querySelector('#logout').addEventListener('click', () => { app.classList.add('hidden'); loginScreen.classList.remove('hidden'); showToast('You have been signed out.'); });
document.querySelector('#openNav').addEventListener('click', () => document.querySelector('.sidebar').classList.add('open'));
document.querySelector('#closeNav').addEventListener('click', () => document.querySelector('.sidebar').classList.remove('open'));
lucide.createIcons();
