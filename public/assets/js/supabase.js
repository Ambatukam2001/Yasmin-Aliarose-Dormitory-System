/**
 * Supabase client (CDN via esm.sh — no npm).
 * Load after supabase-config.js. Exposes window.dormSupabase and window.supabaseReady.
 */
(function () {
  if (window.supabaseReady) return;

  window.supabaseReady = import('https://esm.sh/@supabase/supabase-js@2')
    .then(function (mod) {
      var url = window.__SUPABASE_URL__;
      var key = window.__SUPABASE_ANON_KEY__;
      if (!url || !key || /YOUR_PROJECT_REF|YOUR_ANON_PUBLIC_KEY/.test(url + key)) {
        console.error(
          '[Supabase] Set window.__SUPABASE_URL__ and window.__SUPABASE_ANON_KEY__ in supabase-config.js'
        );
      }
      var createClient = mod.createClient || mod.default && mod.default.createClient;
      if (typeof createClient !== 'function') {
        throw new Error('Supabase createClient not found in module');
      }
      window.dormSupabase = createClient(url, key, {
        auth: { persistSession: false, autoRefreshToken: false, detectSessionInUrl: false },
      });
      return window.dormSupabase;
    })
    .catch(function (err) {
      console.error('[Supabase] Failed to load client:', err);
      throw err;
    });
})();
