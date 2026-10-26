import './bootstrap';
// Tombol mata (lihat/sembunyikan) di kolom kata sandi
(() => {
    const EYE = '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>';
    const EYE_OFF = '<path d="M9.9 4.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-2.6 3.6"/><path d="M6.6 6.6A16.6 16.6 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.2-.9"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/><path d="m2 2 20 20"/>';
    const svg = (p) => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
  
    document.querySelectorAll('input[type="password"]').forEach((input) => {
      const wrap = input.parentElement;
      if (getComputedStyle(wrap).position === 'static') wrap.style.position = 'relative';
      input.style.paddingRight = '2.75rem';
  
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.innerHTML = svg(EYE);
      btn.setAttribute('aria-label', 'Tampilkan kata sandi');
      Object.assign(btn.style, {
        position: 'absolute', right: '0.5rem',
        top: (input.offsetTop + input.offsetHeight / 2) + 'px', transform: 'translateY(-50%)',
        padding: '0.375rem', border: 'none', background: 'transparent',
        color: 'var(--mute)', cursor: 'pointer', borderRadius: '0.5rem', lineHeight: '0',
      });
  
      btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = svg(show ? EYE_OFF : EYE);
        btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        input.focus();
      });
  
      wrap.appendChild(btn);
    });
  })();