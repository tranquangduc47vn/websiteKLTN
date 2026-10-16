<script>window.CI_CSRF={!! $payload !!};
window.appendCsrf=function(d){d=d||{};if(window.CI_CSRF){d[window.CI_CSRF.name]=window.CI_CSRF.hash;}return d;};
window.applyCsrfResponse=function(r){if(r&&r.csrf_hash&&window.CI_CSRF){window.CI_CSRF.hash=r.csrf_hash;document.querySelectorAll('input[name="'+window.CI_CSRF.name+'"]').forEach(function(el){el.value=r.csrf_hash;});}};</script>
