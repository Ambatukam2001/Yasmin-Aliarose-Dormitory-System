/**
 * Browser-side Supabase client via ES module CDN — no npm install.
 * Load from a page with: <script type="module" src="assets/js/supabase-cdn.js"></script>
 * Or inline a small module that imports createClient from esm.sh / jsdelivr.
 *
 * Exposes: window.__yaSupabaseReady — Promise<import('@supabase/supabase-js').SupabaseClient>
 */
(function () {
  if (typeof window === 'undefined') return;

  const URL_KEY = 'data-supabase-url';
  const ANON_KEY = 'data-supabase-anon-key';

  window.__yaSupabaseReady = (async function () {
    const el = document.querySelector('[' + URL_KEY + ']');
    const url = el && el.getAttribute(URL_KEY);
    const anon = el && el.getAttribute(ANON_KEY);
    if (!url || !anon) {
      console.warn('[YA Dorm] Supabase: add data-supabase-url and data-supabase-anon-key on a script or body tag.');
      return null;
    }
    const { createClient } = await import('https://esm.sh/@supabase/supabase-js@2.49.1');
    return createClient(url, anon, {
      auth: { persistSession: true, autoRefreshToken: true },
    });
  })();
})();
