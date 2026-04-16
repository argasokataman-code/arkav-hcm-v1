(function (window, document) {
    "use strict";

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function initSwiper() {
        var el = qs("[data-landing-swiper]");
        if (!el) return;
        if (!window.Swiper) return;

        // eslint-disable-next-line no-new
        new window.Swiper(el, {
            loop: true,
            speed: 650,
            spaceBetween: 16,
            autoplay: { delay: 3500, disableOnInteraction: false },
            pagination: {
                el: el.querySelector(".swiper-pagination"),
                clickable: true,
            },
        });
    }

    function initCountUp() {
        var nodes = qsa("[data-countup]");
        if (!nodes.length) return;
        var CountUp = window.CountUp && window.CountUp.CountUp ? window.CountUp.CountUp : null;
        if (!CountUp) return;

        var reduceMotion = false;
        try {
            reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        } catch (_e) {}

        function run(node) {
            var endRaw = node.getAttribute("data-countup-end");
            var end = endRaw ? Number(endRaw) : NaN;
            if (!isFinite(end)) return;
            var suffix = node.getAttribute("data-countup-suffix") || "";

            if (reduceMotion) {
                node.textContent = String(Math.round(end)) + suffix;
                return;
            }

            var counter = new CountUp(node, end, {
                duration: 1.3,
                separator: ".",
                decimal: ",",
                suffix: suffix,
            });
            if (!counter.error) counter.start();
            else node.textContent = String(Math.round(end)) + suffix;
        }

        nodes.forEach(function (node) {
            if ("IntersectionObserver" in window && !reduceMotion) {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            run(node);
                            io.disconnect();
                        }
                    });
                }, { threshold: 0.35 });
                io.observe(node);
            } else {
                run(node);
            }
        });
    }

    function init() {
        initSwiper();
        initCountUp();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);

