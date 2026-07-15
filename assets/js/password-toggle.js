/**
 * Password visibility toggle.
 *
 * Adds a self-contained "eye" button to every password field so users can
 * reveal/hide what they type. No external dependency (icons are inline SVG,
 * styles are injected once), so it works on the admin auth pages that don't
 * load Font Awesome as well as inside the admin area.
 */
(function () {
    'use strict';

    var EYE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    var EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    function injectStyles() {
        if (document.getElementById('pw-toggle-styles')) {
            return;
        }
        var css = [
            '.pw-toggle-wrap{position:relative;display:block;}',
            '.pw-toggle-wrap>input{padding-right:40px !important;}',
            'html[dir="rtl"] .pw-toggle-wrap>input{padding-right:12px !important;padding-left:40px !important;}',
            '.pw-toggle-btn{position:absolute;top:0;right:0;bottom:0;width:38px;display:flex;align-items:center;justify-content:center;padding:0;margin:0;background:transparent;border:0;cursor:pointer;color:#94a3b8;line-height:0;}',
            'html[dir="rtl"] .pw-toggle-btn{right:auto;left:0;}',
            '.pw-toggle-btn:hover{color:#475569;}',
            '.pw-toggle-btn:focus{outline:0;}',
            '.pw-toggle-btn svg{width:18px;height:18px;display:block;}'
        ].join('');
        var style = document.createElement('style');
        style.id = 'pw-toggle-styles';
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

    function enhance(input) {
        if (!input || input.getAttribute('type') !== 'password') {
            return;
        }
        if (input.getAttribute('data-pw-toggle')) {
            return;
        }
        // Ignore the hidden anti-autofill decoy fields used across the app.
        if (input.classList.contains('fake-autofill-field') || input.tabIndex === -1) {
            return;
        }

        input.setAttribute('data-pw-toggle', '1');

        var wrap = document.createElement('span');
        wrap.className = 'pw-toggle-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle-btn';
        btn.tabIndex = -1;
        btn.setAttribute('aria-label', 'Show password');
        btn.innerHTML = EYE;
        wrap.appendChild(btn);

        btn.addEventListener('click', function () {
            var reveal = input.getAttribute('type') === 'password';
            input.setAttribute('type', reveal ? 'text' : 'password');
            btn.innerHTML = reveal ? EYE_OFF : EYE;
            btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        });
    }

    function run() {
        injectStyles();
        var inputs = document.querySelectorAll('input[type="password"]');
        for (var i = 0; i < inputs.length; i++) {
            enhance(inputs[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
