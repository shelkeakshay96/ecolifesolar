/**
 * EcoLifeSolar front-end behaviour.
 *
 * Vanilla JS on purpose. jQuery was dropped from the original site: everything
 * these pages need is fetch() and querySelectorAll(), and dropping it removes a
 * third-party origin from the CSP and one more dependency to patch.
 *
 * The site sends "script-src 'self'", so nothing here may rely on an inline
 * handler. The original markup used onclick="openLightbox(...)" attributes;
 * every equivalent below is wired by delegation from a data-* attribute.
 *
 * Structure:
 *   motion.*     decorative behaviour, all of it opt-out under reduced motion
 *   lightbox     accessible image viewer
 *   leadForm     the reason the site exists
 *   calculator   live savings estimate
 *
 * Loaded with `defer`, so the DOM is ready by the time this runs.
 */
(function () {
    'use strict';

    /* ====================================================================== */
    /* Shared helpers                                                         */
    /* ====================================================================== */

    var reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    var finePointerQuery  = window.matchMedia('(pointer: fine)');

    /**
     * Read live rather than cached: a visitor can turn reduced motion on while
     * the page is open, and the decorative loops below check on every tick.
     */
    function prefersReducedMotion() {
        return reduceMotionQuery.matches;
    }

    function finePointer() {
        return finePointerQuery.matches;
    }

    /** Coalesce scroll/mousemove bursts down to one callback per frame. */
    function onFrame(callback) {
        var queued = false;
        var lastArgs;

        return function () {
            lastArgs = arguments;
            if (queued) { return; }
            queued = true;
            window.requestAnimationFrame(function () {
                queued = false;
                callback.apply(null, lastArgs);
            });
        };
    }

    function each(selector, fn, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
    }

    /* ====================================================================== */
    /* Motion                                                                 */
    /* ====================================================================== */

    var motion = {};

    /**
     * Scroll reveal.
     *
     * Two changes from the original: elements are unobserved once they have
     * appeared -- the old version kept every section under observation for the
     * life of the page -- and a container marked data-reveal-stagger hands its
     * children an increasing delay, so a grid arrives as a sequence rather than
     * as a block.
     */
    motion.reveal = function () {
        var targets = document.querySelectorAll('.reveal');
        if (!targets.length) { return; }

        each('[data-reveal-stagger]', function (container) {
            var step = parseInt(container.getAttribute('data-reveal-stagger'), 10) || 90;
            Array.prototype.forEach.call(container.querySelectorAll('.reveal'), function (el, i) {
                el.style.setProperty('--reveal-delay', (i * step) + 'ms');
            });
        });

        if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
            Array.prototype.forEach.call(targets, function (el) { el.classList.add('is-in'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        Array.prototype.forEach.call(targets, function (el) { observer.observe(el); });
    };

    /**
     * Headline word reveal.
     *
     * The original built this with innerHTML from a JavaScript string literal,
     * which meant the headline lived in two places and could only ever be
     * changed by editing a script. Here the words come out of the DOM the
     * server rendered, and each is wrapped with createElement/textContent --
     * markup in, markup out, nothing interpolated.
     */
    motion.headline = function () {
        each('[data-word-reveal]', function (host) {
            var index = 0;

            Array.prototype.slice.call(host.childNodes).forEach(function (node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    wrapWords(node, host, node.textContent);
                    return;
                }

                if (node.nodeType !== Node.ELEMENT_NODE) { return; }

                /* An inline element such as <em> keeps its own tag; its words
                   are wrapped inside it so the emphasis survives. */
                var inner = node.textContent;
                node.textContent = '';
                wrapWords(null, node, inner);
            });

            host.classList.add('word-reveal');

            function wrapWords(replacing, parent, text) {
                var fragment = document.createDocumentFragment();

                text.split(/(\s+)/).forEach(function (chunk) {
                    if (chunk.trim() === '') {
                        fragment.appendChild(document.createTextNode(chunk));
                        return;
                    }
                    var span = document.createElement('span');
                    span.textContent = chunk;
                    span.style.setProperty('--word-index', String(index++));
                    fragment.appendChild(span);
                });

                if (replacing) {
                    parent.replaceChild(fragment, replacing);
                } else {
                    parent.appendChild(fragment);
                }
            }
        });
    };

    /**
     * Sticky header state and the scroll progress hairline.
     *
     * The progress bar is driven with transform: scaleX() rather than width, so
     * it stays on the compositor and never triggers layout.
     */
    motion.scrollChrome = function () {
        var header = document.querySelector('[data-site-header]');
        var progress = document.querySelector('[data-scroll-progress]');
        if (!header && !progress) { return; }

        var update = onFrame(function () {
            var y = window.scrollY || document.documentElement.scrollTop;

            if (header) {
                header.classList.toggle('is-stuck', y > 24);
            }

            if (progress) {
                var scrollable = document.documentElement.scrollHeight - window.innerHeight;
                var ratio = scrollable > 0 ? Math.min(1, y / scrollable) : 0;
                progress.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';
            }
        });

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    };

    /**
     * Magnetic buttons.
     *
     * The original wrote a transform straight from the mousemove handler, which
     * fires far more often than the screen refreshes and fought the CSS
     * transition on the same property. This batches to one write per frame and
     * releases on blur as well as mouseleave, so a keyboard user tabbing away
     * from a button that was previously hovered does not leave it displaced.
     */
    motion.magnetic = function () {
        if (!finePointer()) { return; }

        each('[data-magnetic]', function (el) {
            var strength = parseFloat(el.getAttribute('data-magnetic')) || 0.2;

            var move = onFrame(function (x, y) {
                if (prefersReducedMotion()) { return; }
                el.style.transform = 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)';
            });

            el.addEventListener('mousemove', function (event) {
                var rect = el.getBoundingClientRect();
                move((event.clientX - rect.left - rect.width / 2) * strength,
                     (event.clientY - rect.top - rect.height / 2) * strength * 1.6);
            });

            function release() { el.style.transform = ''; }
            el.addEventListener('mouseleave', release);
            el.addEventListener('blur', release);
        });
    };

    /**
     * The streetlight scene lights up when it is on screen and goes dark again
     * when it is not, so scrolling back gives you the moment a second time.
     */
    motion.streetlight = function () {
        var scene = document.querySelector('[data-scene]');
        if (!scene) { return; }

        if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
            scene.classList.add('is-lit');
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                scene.classList.toggle('is-lit', entry.isIntersecting);
            });
        }, { threshold: 0.4 });

        observer.observe(scene);
    };

    /**
     * Falling leaves.
     *
     * Kept from the original because it is a genuine piece of the brand, but
     * rebuilt around its two failure modes: leaves used to spawn anywhere on
     * the page including over the lead form, and the counter could drift if an
     * animation never reported finished, permanently starving the effect.
     *
     * Now: leaves only fall while a marked section is on screen, the count is
     * derived from what is actually in the DOM rather than a running tally,
     * spawning stops when the tab is hidden, and coarse pointers and reduced
     * motion get none at all.
     */
    motion.leaves = function () {
        var zone = document.querySelector('[data-leaf-zone]');

        /* No finePointer() gate here, deliberately. magnetic and pointerHalo
           need one because they follow a cursor and mean nothing without it;
           leaves are driven by scrolling, which every device does. Gating them
           on pointer type was a copy-paste from those two, and it switched the
           effect off for every phone -- which is most of this site's traffic. */
        if (!zone || !('IntersectionObserver' in window)) { return; }

        var SHAPES = [
            'M12 0C4 4 0 12 4 22c3 7 11 9 17 6-2-10-2-19-9-28Z',
            'M0 12C4 4 12 0 22 4c7 3 9 11 6 17-10-2-19-2-28-9Z'
        ];
        var COLOURS = ['#8BC34A', '#2E7D32', '#F0A500'];

        /* Read per spawn rather than once, so rotating a phone does not leave a
           stale figure behind. Half as many on a small screen: twelve elements
           animating over a 360px viewport is both busier to look at and more
           work for the low-end Androids a lot of this audience is on. */
        function maxLeaves() {
            return window.innerWidth < 640 ? 6 : 12;
        }

        var inZone = false;
        var lastY = window.scrollY;
        var cooling = false;

        new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) { inZone = entry.isIntersecting; });
        }, { threshold: 0.05 }).observe(zone);

        function spawn() {
            if (document.hidden || prefersReducedMotion()) { return; }
            if (document.querySelectorAll('.leaf-fall').length >= maxLeaves()) { return; }

            var size = 14 + Math.random() * 12;
            var spin = Math.random() * 360;
            var drift = Math.random() * 150 - 75;
            var seconds = 5 + Math.random() * 4;

            var leaf = document.createElement('div');
            leaf.className = 'leaf-fall';
            leaf.style.left = (Math.random() * 100) + 'vw';
            leaf.setAttribute('aria-hidden', 'true');

            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', String(size));
            svg.setAttribute('height', String(size));
            svg.setAttribute('viewBox', '0 0 24 24');

            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', SHAPES[Math.floor(Math.random() * SHAPES.length)]);
            path.setAttribute('fill', COLOURS[Math.floor(Math.random() * COLOURS.length)]);

            svg.appendChild(path);
            leaf.appendChild(svg);
            document.body.appendChild(leaf);

            var animation = leaf.animate([
                { transform: 'translate(0,0) rotate(' + spin + 'deg)', opacity: 0.9 },
                { transform: 'translate(' + drift + 'px, 105vh) rotate(' + (spin + 260) + 'deg)', opacity: 0.15 }
            ], { duration: seconds * 1000, easing: 'cubic-bezier(.4,0,.6,1)' });

            /* finish and cancel both settle the promise, so a leaf cannot be
               orphaned by a tab switch mid-flight. */
            animation.finished.catch(function () {}).then(function () { leaf.remove(); });
        }

        window.addEventListener('scroll', function () {
            var delta = Math.abs(window.scrollY - lastY);
            lastY = window.scrollY;

            if (!inZone || cooling || delta < 25) { return; }

            spawn();
            cooling = true;
            window.setTimeout(function () { cooling = false; }, 350);
        }, { passive: true });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { return; }
            each('.leaf-fall', function (leaf) { leaf.remove(); });
        });
    };

    /**
     * Pointer halo.
     *
     * The original replaced the system cursor with a custom dot, hiding the
     * real one across the whole document. That is a genuine usability loss --
     * text carets, resize handles and the browser's own affordances all
     * disappear with it. This adds a soft ring that trails the real cursor and
     * swells over anything interactive, and never hides it.
     */
    motion.pointerHalo = function () {
        if (!finePointer() || prefersReducedMotion()) { return; }

        var halo = document.createElement('div');
        halo.className = 'cursor-halo';
        halo.setAttribute('aria-hidden', 'true');
        document.body.appendChild(halo);

        var haloX = window.innerWidth / 2;
        var haloY = window.innerHeight / 2;
        var targetX = haloX;
        var targetY = haloY;
        var running = false;

        function follow() {
            haloX += (targetX - haloX) * 0.18;
            haloY += (targetY - haloY) * 0.18;
            halo.style.transform = 'translate(' + haloX.toFixed(1) + 'px,' + haloY.toFixed(1) + 'px)';

            if (Math.abs(targetX - haloX) < 0.1 && Math.abs(targetY - haloY) < 0.1) {
                running = false;
                return;
            }
            window.requestAnimationFrame(follow);
        }

        window.addEventListener('mousemove', function (event) {
            targetX = event.clientX;
            targetY = event.clientY;
            halo.classList.add('is-active');

            if (!running) {
                running = true;
                window.requestAnimationFrame(follow);
            }
        }, { passive: true });

        var INTERACTIVE = 'a, button, input, select, textarea, summary, [role="button"], .chip, [data-lightbox]';

        document.addEventListener('mouseover', function (event) {
            if (event.target.closest(INTERACTIVE)) { halo.classList.add('is-hover'); }
        });
        document.addEventListener('mouseout', function (event) {
            if (event.target.closest(INTERACTIVE)) { halo.classList.remove('is-hover'); }
        });
        document.addEventListener('mouseleave', function () { halo.classList.remove('is-active'); });
    };

    /**
     * Count a number up to its final value when it scrolls into view. Used for
     * the calculator headline figure and any [data-count-to] element.
     */
    motion.counters = function () {
        var targets = document.querySelectorAll('[data-count-to]');
        if (!targets.length || !('IntersectionObserver' in window)) { return; }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                observer.unobserve(entry.target);
                run(entry.target);
            });
        }, { threshold: 0.4 });

        Array.prototype.forEach.call(targets, function (el) { observer.observe(el); });

        function run(el) {
            var to = parseFloat(el.getAttribute('data-count-to')) || 0;
            var prefix = el.getAttribute('data-count-prefix') || '';
            var suffix = el.getAttribute('data-count-suffix') || '';
            var decimals = parseInt(el.getAttribute('data-count-decimals'), 10) || 0;

            if (prefersReducedMotion()) {
                el.textContent = prefix + to.toFixed(decimals) + suffix;
                return;
            }

            var started = null;
            var duration = 1100;
            el.classList.add('is-counting');

            window.requestAnimationFrame(function step(now) {
                if (started === null) { started = now; }
                var t = Math.min(1, (now - started) / duration);
                var eased = 1 - Math.pow(1 - t, 3);
                var value = to * eased;

                el.textContent = prefix
                    + (decimals ? value.toFixed(decimals)
                                : Math.round(value).toLocaleString('en-IN'))
                    + suffix;

                if (t < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    el.classList.remove('is-counting');
                }
            });
        }
    };

    /** Gentle parallax on anything marked data-parallax="<factor>". */
    motion.parallax = function () {
        var layers = document.querySelectorAll('[data-parallax]');
        if (!layers.length || prefersReducedMotion()) { return; }

        var update = onFrame(function () {
            var y = window.scrollY || 0;
            Array.prototype.forEach.call(layers, function (layer) {
                var factor = parseFloat(layer.getAttribute('data-parallax')) || 0.12;
                layer.style.transform = 'translate3d(0,' + (y * factor).toFixed(1) + 'px,0) scale(1.08)';
            });
        });

        window.addEventListener('scroll', update, { passive: true });
        update();
    };

    /* ====================================================================== */
    /* Toast                                                                  */
    /* ====================================================================== */

    var toast = (function () {
        var node = null;
        var timer = null;

        function ensure() {
            if (node) { return node; }
            node = document.createElement('div');
            node.className = 'toast rounded-full border border-gold-500 bg-pine-800 px-5 py-2.5 '
                           + 'font-mono text-[.78rem] text-pearl';
            node.setAttribute('role', 'status');
            node.setAttribute('aria-live', 'polite');
            document.body.appendChild(node);
            return node;
        }

        return function (message) {
            var el = ensure();
            el.textContent = message;
            el.classList.add('is-visible');
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { el.classList.remove('is-visible'); }, 2600);
        };
    }());

    /* "Coming soon" links. Delegated, because the CSP forbids the inline
       onclick the original used. */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-soon]');
        if (!trigger) { return; }
        event.preventDefault();
        toast(trigger.getAttribute('data-soon') + ' — coming soon');
    });

    /* ====================================================================== */
    /* Lightbox                                                               */
    /* ====================================================================== */

    /**
     * The original lightbox was a div with an onclick, no keyboard handling
     * beyond Escape, and no way to move between photographs. This one is a
     * modal dialog: focus moves into it, is trapped while it is open, and
     * returns to the thumbnail that opened it. Left and right arrows step
     * through the gallery.
     */
    (function lightbox() {
        var triggers = [];
        var openIndex = -1;
        var lastFocused = null;
        var root = null;
        var image = null;
        var caption = null;

        function build() {
            if (root) { return; }

            root = document.createElement('div');
            root.className = 'lightbox';
            root.setAttribute('role', 'dialog');
            root.setAttribute('aria-modal', 'true');
            root.setAttribute('aria-label', 'Photograph viewer');

            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'absolute right-6 top-6 flex h-11 w-11 items-center justify-center rounded-full '
                            + 'border border-gold-300/50 text-pearl transition hover:bg-gold-500 hover:text-pine-950';
            close.innerHTML = '<span class="sr-only">Close</span>'
                            + '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">'
                            + '<path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>';
            close.addEventListener('click', hide);

            var prev = navButton('left', 'Previous photograph', -1);
            var next = navButton('right', 'Next photograph', 1);

            var figure = document.createElement('figure');
            figure.className = 'flex flex-col items-center gap-4';

            image = document.createElement('img');
            image.alt = '';

            caption = document.createElement('figcaption');
            caption.className = 'font-mono text-[.72rem] tracking-wide text-pine-100';

            figure.appendChild(image);
            figure.appendChild(caption);
            root.appendChild(close);
            root.appendChild(prev);
            root.appendChild(next);
            root.appendChild(figure);
            document.body.appendChild(root);

            root.addEventListener('click', function (event) {
                if (event.target === root) { hide(); }
            });
        }

        function navButton(side, label, step) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'absolute ' + (side === 'left' ? 'left-4' : 'right-4')
                             + ' top-1/2 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full '
                             + 'border border-gold-300/40 text-pearl transition hover:bg-gold-500 '
                             + 'hover:text-pine-950 sm:flex';
            button.innerHTML = '<span class="sr-only">' + label + '</span>'
                             + '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">'
                             + '<path stroke-linecap="round" stroke-linejoin="round" d="'
                             + (side === 'left' ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7') + '"/></svg>';
            button.addEventListener('click', function () { step_(step); });
            return button;
        }

        function collect() {
            triggers = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
        }

        function show(index) {
            collect();
            if (!triggers.length) { return; }

            build();
            openIndex = (index + triggers.length) % triggers.length;

            var trigger = triggers[openIndex];
            var img = trigger.matches('img') ? trigger : trigger.querySelector('img');

            image.src = trigger.getAttribute('data-lightbox-src') || (img ? img.src : '');
            image.alt = trigger.getAttribute('data-lightbox-alt') || (img ? img.alt : '');
            caption.textContent = trigger.getAttribute('data-lightbox-caption') || '';

            lastFocused = document.activeElement;
            root.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            root.querySelector('button').focus();
        }

        function step_(by) {
            if (openIndex < 0) { return; }
            show(openIndex + by);
        }

        function hide() {
            if (!root) { return; }
            root.classList.remove('is-open');
            document.body.style.overflow = '';
            openIndex = -1;
            if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-lightbox]');
            if (!trigger) { return; }
            event.preventDefault();
            collect();
            show(triggers.indexOf(trigger));
        });

        document.addEventListener('keydown', function (event) {
            if (openIndex < 0) { return; }

            if (event.key === 'Escape') { hide(); return; }
            if (event.key === 'ArrowRight') { step_(1); return; }
            if (event.key === 'ArrowLeft') { step_(-1); return; }

            /* Focus trap. Only the three buttons inside are focusable, so
               cycling them is enough. */
            if (event.key !== 'Tab') { return; }

            var focusable = root.querySelectorAll('button');
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }());

    /* ====================================================================== */
    /* Navigation                                                             */
    /* ====================================================================== */

    (function mobileNav() {
        var toggle = document.querySelector('[data-mobile-nav-toggle]');
        var nav = document.getElementById('mobile-nav');
        if (!toggle || !nav) { return; }

        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        /* Tapping a link should close the panel, or the destination arrives
           underneath an open menu. */
        nav.addEventListener('click', function (event) {
            if (!event.target.closest('a')) { return; }
            nav.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }());

    /* ====================================================================== */
    /* Destructive-action confirmation                                        */
    /* ====================================================================== */

    /**
     * Confirms any form carrying data-confirm before it submits.
     *
     * The admin screens previously did this with onsubmit="return confirm(...)"
     * on the element. That is dead under our Content-Security-Policy -- an
     * inline handler is script, script-src is 'self', and the browser drops it
     * silently. The delete went through unconfirmed and nothing anywhere said
     * so. Delegated from the document, so it also covers markup added later.
     */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.matches || !form.matches('[data-confirm]')) { return; }

        if (!window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    /* ====================================================================== */
    /* Lead form                                                              */
    /* ====================================================================== */

    /**
     * The CSRF token. Read from the form the server rendered, so a script can
     * POST without the page having to contain its own copy.
     */
    function formKey() {
        var input = document.querySelector('input[name="form_key"]');
        return input ? input.value : '';
    }

    each('[data-lead-form]', function (form) {
        var banner = form.querySelector('[data-lead-messages]');
        var submit = form.querySelector('[data-lead-submit]');
        var labels = {
            residential: 'Book my free site visit',
            society: 'Get a society proposal',
            commercial: 'Request a commercial quote'
        };

        /* Which extra fields are visible is decided in CSS, from :has() on the
           checked radio -- that way the form still adapts with JavaScript off.
           All this does is relabel the button. The server validates the same
           rules regardless of what is on screen. */
        function applyType() {
            var checked = form.querySelector('[data-lead-type]:checked');
            var type = checked ? checked.value : 'residential';

            if (submit && labels[type]) {
                submit.textContent = labels[type];
            }
        }

        each('[data-lead-type]', function (radio) {
            radio.addEventListener('change', applyType);
        }, form);
        applyType();

        function clearErrors() {
            each('[data-error-for]', function (el) {
                el.textContent = '';
                el.classList.add('hidden');
            }, form);
        }

        function showBanner(message, ok) {
            if (!banner) { return; }
            banner.textContent = message;
            banner.className = 'mt-4 rounded-xl border px-4 py-3 text-sm ' + (ok
                ? 'border-pine-300 bg-pine-50 text-pine-700'
                : 'border-red-200 bg-red-50 text-red-800');
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();

            submit.disabled = true;
            var original = submit.textContent;
            submit.textContent = 'Sending…';

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            }).then(function (response) {
                return response.json().then(function (body) { return { ok: response.ok, body: body }; });
            }).then(function (result) {
                if (result.ok && result.body.success) {
                    form.reset();
                    applyType();
                    showBanner(result.body.message, true);
                    return;
                }

                showBanner(result.body.message || 'Something went wrong. Please try again.', false);

                var errors = result.body.errors || {};
                Object.keys(errors).forEach(function (field) {
                    var el = form.querySelector('[data-error-for="' + field + '"]');
                    if (el) {
                        el.textContent = errors[field];
                        el.classList.remove('hidden');
                    }
                });
            }).catch(function () {
                showBanner('We could not reach the server. Please check your connection and try again.', false);
            }).finally(function () {
                submit.disabled = false;
                submit.textContent = original;
            });
        });
    });

    /* ====================================================================== */
    /* Calculator                                                             */
    /* ====================================================================== */

    (function calculator() {
        var form = document.querySelector('[data-calculator]');
        if (!form) { return; }

        var out = {
            monthly: document.querySelector('[data-calc-monthly]'),
            annual:  document.querySelector('[data-calc-annual]'),
            size:    document.querySelector('[data-calc-size]'),
            payback: document.querySelector('[data-calc-payback]'),
            cost:    document.querySelector('[data-calc-cost]')
        };
        var billLabel = document.querySelector('[data-calc-bill-label]');

        /* Indian digit grouping: 1,23,456 rather than 123,456. */
        function rupees(value) {
            return '₹' + Math.round(value).toLocaleString('en-IN');
        }

        /** Retint a figure briefly so a changed number is noticed. */
        function put(el, text) {
            if (!el || el.textContent === text) { return; }
            el.textContent = text;
            el.classList.remove('is-counting');
            void el.offsetWidth;               /* restart the animation */
            el.classList.add('is-counting');
            window.setTimeout(function () { el.classList.remove('is-counting'); }, 900);
        }

        var pending = null;

        function refresh() {
            var bill = form.querySelector('[data-calc-bill]');
            var params = new URLSearchParams({
                monthly_bill: bill.value,
                roof_type:    form.querySelector('[data-calc-roof]').value,
                system_type:  form.querySelector('[data-calc-system]').value
            });

            if (billLabel) { billLabel.textContent = rupees(parseFloat(bill.value) || 0); }

            fetch(form.action + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                return r.json();
            }).then(function (data) {
                if (!data.success) { return; }
                var e = data.estimate;
                put(out.monthly, rupees(e.monthly_saving));
                put(out.annual,  rupees(e.annual_saving));
                put(out.size,    e.system_size_kw + ' kW');
                put(out.payback, e.payback_years + ' years');
                put(out.cost,    rupees(e.system_cost));
            }).catch(function () { /* leave the server-rendered figures in place */ });
        }

        function scheduleRefresh() {
            window.clearTimeout(pending);
            pending = window.setTimeout(refresh, 250);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            refresh();
        });

        each('input, select', function (field) {
            field.addEventListener('input', scheduleRefresh);
            field.addEventListener('change', scheduleRefresh);
        }, form);

        var initialBill = form.querySelector('[data-calc-bill]');
        if (billLabel && initialBill) {
            billLabel.textContent = rupees(parseFloat(initialBill.value) || 0);
        }
    }());

    /* ====================================================================== */
    /* Boot                                                                   */
    /* ====================================================================== */

    motion.reveal();
    motion.headline();
    motion.scrollChrome();
    motion.magnetic();
    motion.streetlight();
    motion.leaves();
    motion.pointerHalo();
    motion.counters();
    motion.parallax();

    window.EcoLife = {
        formKey: formKey,
        toast: toast,

        /** POST as JSON with the form key attached, returning the parsed body. */
        post: function (url, data) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Form-Key': formKey()
                },
                body: data
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, status: response.status, body: body };
                });
            });
        }
    };
}());
