(function () {
    var config = window.surveyjsProAdminThemeConfig || {};
    var DARK_CLASS = "wp-sjs-pro-dark";
    var EVENT = "surveyjs-pro:admin-theme";
    var systemDark = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
    var listeners = [];
    var dark = null;
    var probe = null;
    var pixel = null;

    var SURVEYJS_VARIABLES = {
        "--sjs2-color-project-brand-600": "var(--wp-sjs-pro-accent)",
        "--sjs2-color-project-brand-700": "var(--wp-sjs-pro-accent-dim)",
        "--sjs2-color-bg-brand-primary-dim": "var(--wp-sjs-pro-accent-dim)",
        "--sjs2-color-fg-brand-primary": "var(--wp-sjs-pro-accent)",
        "--sjs2-color-fg-brand-primary-dim": "var(--wp-sjs-pro-accent-dim)",
        "--sjs2-color-fg-brand-on-primary": "var(--wp-sjs-pro-on-accent)",
        "--sjs2-color-bg-basic-primary": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-bg-basic-primary-dim": "var(--wp-sjs-pro-surface-2)",
        "--sjs2-color-bg-basic-primary-disabled": "var(--wp-sjs-pro-surface-2)",
        "--sjs2-color-bg-basic-secondary": "var(--wp-sjs-pro-surface-2)",
        "--sjs2-color-bg-basic-secondary-dim": "var(--wp-sjs-pro-surface-3)",
        "--sjs2-color-bg-basic-tertiary": "var(--wp-sjs-pro-surface-3)",
        "--sjs2-color-bg-basic-tertiary-dim": "var(--wp-sjs-pro-border-2)",
        "--sjs2-color-bg-neutral-primary": "var(--wp-sjs-pro-text)",
        "--sjs2-color-bg-neutral-primary-dim": "var(--wp-sjs-pro-text-2)",
        "--sjs2-color-bg-neutral-secondary": "var(--wp-sjs-pro-surface-2)",
        "--sjs2-color-bg-neutral-secondary-dim": "var(--wp-sjs-pro-surface-3)",
        "--sjs2-color-fg-basic-primary": "var(--wp-sjs-pro-text)",
        "--sjs2-color-fg-basic-primary-dim": "var(--wp-sjs-pro-text-2)",
        "--sjs2-color-fg-basic-primary-muted": "var(--wp-sjs-pro-text-2)",
        "--sjs2-color-fg-neutral-on-primary": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-border-basic-primary": "var(--wp-sjs-pro-text)",
        "--sjs2-color-border-basic-secondary": "var(--wp-sjs-pro-border-2)",
        "--sjs2-color-border-basic-secondary-disabled": "var(--wp-sjs-pro-surface-3)",
        "--sjs2-color-border-neutral-primary": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-utility-body": "var(--wp-sjs-pro-page)",
        "--sjs2-color-utility-sheet": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-utility-toolbox": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-utility-property-grid": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-utility-tabs": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-utility-surface-survey": "var(--wp-sjs-pro-canvas)",
        "--sjs2-color-utility-surface-designer": "var(--wp-sjs-pro-canvas)",
        "--sjs2-color-utility-surface-presets-manager": "var(--wp-sjs-pro-canvas)",
        "--sjs2-color-utility-surface-json-editor": "var(--wp-sjs-pro-surface)",
        "--sjs2-color-utility-scrollbar": "rgba(from var(--wp-sjs-pro-text) r g b / 0.2)",
        "--sjs2-color-utility-shadow-surface-default": "var(--wp-sjs-pro-border-2)",
        "--sjs2-color-utility-shadow-floating-default": "var(--wp-sjs-pro-shadow-floating)",
        "--sjs2-typography-font-family-text": "var(--wp-sjs-pro-font)",
        "--sjs2-color-data-chart-bg-color-0": "var(--wp-sjs-pro-chart-1)",
        "--sjs2-color-data-chart-fg-on-color-0": "var(--wp-sjs-pro-chart-1-text)"
    };
    for (var i = 1; i <= 10; i++) {
        SURVEYJS_VARIABLES["--sjs2-color-data-chart-bg-color-" + i] = "var(--wp-sjs-pro-chart-" + i + ")";
        SURVEYJS_VARIABLES["--sjs2-color-data-chart-fg-on-color-" + i] = "var(--wp-sjs-pro-chart-" + i + "-text, var(--wp-sjs-pro-chart-text))";
    }

    function luminance(color) {
        var rgb = (color.match(/[\d.]+/g) || []).map(Number);
        if (rgb.length < 3 || rgb[3] === 0) {
            return null;
        }
        var linear = rgb.slice(0, 3).map(function (value) {
            value /= 255;
            return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
    }

    // WordPress core has no dark admin; dark-mode plugins repaint its background, so the first opaque
    // background behind the page content tells whether the admin is dark, whatever plugin did it.
    function adminIsDark() {
        var selectors = config.darkSelectors || [];
        for (var i = 0; i < selectors.length; i++) {
            if (document.querySelector(selectors[i])) {
                return true;
            }
        }
        var element = document.getElementById("wpbody-content") || document.body;
        for (; element; element = element.parentElement) {
            var value = luminance(resolve(window.getComputedStyle(element).backgroundColor));
            if (value !== null) {
                return value < 0.2;
            }
        }
        return false;
    }

    function detect() {
        switch (config.mode) {
            case "light":
                return false;
            case "dark":
                return true;
            case "system":
                return Boolean(systemDark && systemDark.matches);
            default:
                return document.body ? adminIsDark() : false;
        }
    }

    function update() {
        var next = detect();
        var changed = dark !== null && next !== dark;
        dark = next;
        document.documentElement.classList.toggle(DARK_CLASS, dark);
        if (changed) {
            listeners.slice().forEach(function (listener) {
                listener(dark);
            });
            document.dispatchEvent(new CustomEvent(EVENT, { detail: { dark: dark } }));
        }
        return dark;
    }

    // Tokens may use color-mix() or relative colors that Chart.js cannot parse, so each one is resolved to rgba.
    function resolve(value) {
        if (!probe) {
            probe = document.createElement("span");
            probe.hidden = true;
            pixel = document.createElement("canvas").getContext("2d", { willReadFrequently: true });
        }
        probe.style.color = value;
        document.body.appendChild(probe);
        var value = window.getComputedStyle(probe).color;
        probe.remove();
        pixel.clearRect(0, 0, 1, 1);
        pixel.fillStyle = value;
        pixel.fillRect(0, 0, 1, 1);
        var rgba = pixel.getImageData(0, 0, 1, 1).data;
        return "rgba(" + rgba[0] + ", " + rgba[1] + ", " + rgba[2] + ", " + Math.round(rgba[3] / 2.55) / 100 + ")";
    }

    function color(name) {
        return resolve("var(--wp-sjs-pro-" + name + ")");
    }

    // Charts are drawn on canvas from colors read once, so chart colors are handed over resolved.
    function surveyjsTheme() {
        var variables = Object.assign({}, SURVEYJS_VARIABLES);
        Object.keys(variables).forEach(function (name) {
            if (name.indexOf("--sjs2-color-data-chart-") === 0) {
                variables[name] = resolve(variables[name]);
            }
        });
        return { themeName: "wordpress", colorPalette: dark ? "dark" : "light", isLight: !dark, cssVariables: variables };
    }

    function watch() {
        var scheduled = false;
        var schedule = function () {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(function () {
                    scheduled = false;
                    update();
                });
            }
        };
        if (systemDark && systemDark.addEventListener) {
            systemDark.addEventListener("change", schedule);
        }
        if (config.mode === "auto" || !config.mode) {
            var observer = new MutationObserver(schedule);
            var options = { attributes: true, attributeFilter: ["class", "style", "data-theme"] };
            observer.observe(document.documentElement, options);
            observer.observe(document.body, options);
        }
    }

    window.surveyjsProAdminTheme = {
        mode: config.mode || "auto",
        isDark: update,
        color: color,
        surveyjsTheme: surveyjsTheme,
        onChange: function (listener) {
            listeners.push(listener);
        }
    };

    update();
    document.addEventListener("DOMContentLoaded", function () {
        update();
        watch();
    });
})();
