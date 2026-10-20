<script nonce="{{ Vite::cspNonce() }}">
  // Tombol x pada kotak pesan (komponen alert) menutup pesannya.
  document.querySelectorAll('[data-alert] [data-dismiss]').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('[data-alert]').remove());
  });
</script>
