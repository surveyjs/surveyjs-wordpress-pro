(function () {
    var config = window.surveyjsAddonAdminThemeConfig || {};
    var DARK_CLASS = "wp-sjs-addon-dark";
    var EVENT = "surveyjs-forms-addon:admin-theme";
    var systemDark = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
    var listeners = [];
    var dark = null;
    var probe = null;
    var pixel = null;

    var SURVEYJS_VARIABLES = {
        "--sjs2-color-project-brand-600": "var(--wp-sjs-addon-accent)",
        "--sjs2-color-project-brand-700": "var(--wp-sjs-addon-accent-dim)",
        "--sjs2-color-bg-brand-primary-dim": "var(--wp-sjs-addon-accent-dim)",
        "--sjs2-color-fg-brand-primary": "var(--wp-sjs-addon-accent)",
        "--sjs2-color-fg-brand-primary-dim": "var(--wp-sjs-addon-accent-dim)",
        "--sjs2-color-fg-brand-on-primary": "var(--wp-sjs-addon-on-accent)",
        "--sjs2-color-bg-basic-primary": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-bg-basic-primary-dim": "var(--wp-sjs-addon-surface-2)",
        "--sjs2-color-bg-basic-primary-disabled": "var(--wp-sjs-addon-surface-2)",
        "--sjs2-color-bg-basic-secondary": "var(--wp-sjs-addon-surface-2)",
        "--sjs2-color-bg-basic-secondary-dim": "var(--wp-sjs-addon-surface-3)",
        "--sjs2-color-bg-basic-tertiary": "var(--wp-sjs-addon-surface-3)",
        "--sjs2-color-bg-basic-tertiary-dim": "var(--wp-sjs-addon-border-2)",
        "--sjs2-color-bg-neutral-primary": "var(--wp-sjs-addon-text)",
        "--sjs2-color-bg-neutral-primary-dim": "var(--wp-sjs-addon-text-2)",
        "--sjs2-color-bg-neutral-secondary": "var(--wp-sjs-addon-surface-2)",
        "--sjs2-color-bg-neutral-secondary-dim": "var(--wp-sjs-addon-surface-3)",
        "--sjs2-color-fg-basic-primary": "var(--wp-sjs-addon-text)",
        "--sjs2-color-fg-basic-primary-dim": "var(--wp-sjs-addon-text-2)",
        "--sjs2-color-fg-basic-primary-muted": "var(--wp-sjs-addon-text-2)",
        "--sjs2-color-fg-neutral-on-primary": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-border-basic-primary": "var(--wp-sjs-addon-text)",
        "--sjs2-color-border-basic-secondary": "var(--wp-sjs-addon-border-2)",
        "--sjs2-color-border-basic-secondary-disabled": "var(--wp-sjs-addon-surface-3)",
        "--sjs2-color-border-neutral-primary": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-utility-body": "var(--wp-sjs-addon-page)",
        "--sjs2-color-utility-sheet": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-utility-toolbox": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-utility-property-grid": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-utility-tabs": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-utility-surface-survey": "var(--wp-sjs-addon-canvas)",
        "--sjs2-color-utility-surface-designer": "var(--wp-sjs-addon-canvas)",
        "--sjs2-color-utility-surface-presets-manager": "var(--wp-sjs-addon-canvas)",
        "--sjs2-color-utility-surface-json-editor": "var(--wp-sjs-addon-surface)",
        "--sjs2-color-utility-scrollbar": "rgba(from var(--wp-sjs-addon-text) r g b / 0.2)",
        "--sjs2-color-utility-shadow-surface-default": "var(--wp-sjs-addon-border-2)",
        "--sjs2-color-utility-shadow-floating-default": "var(--wp-sjs-addon-shadow-floating)",
        "--sjs2-typography-font-family-text": "var(--wp-sjs-addon-font)",
        "--sjs2-color-data-chart-bg-color-0": "var(--wp-sjs-addon-chart-1)",
        "--sjs2-color-data-chart-fg-on-color-0": "var(--wp-sjs-addon-chart-1-text)"
    };
    for (var i = 1; i <= 10; i++) {
        SURVEYJS_VARIABLES["--sjs2-color-data-chart-bg-color-" + i] = "var(--wp-sjs-addon-chart-" + i + ")";
        SURVEYJS_VARIABLES["--sjs2-color-data-chart-fg-on-color-" + i] = "var(--wp-sjs-addon-chart-" + i + "-text, var(--wp-sjs-addon-chart-text))";
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

    function oklabLightness(color) {
        var rgb = (color.match(/[\d.]+/g) || []).slice(0, 3).map(function (value) {
            value /= 255;
            return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        });
        var l = 0.4122214708 * rgb[0] + 0.5363325363 * rgb[1] + 0.0514459929 * rgb[2];
        var m = 0.2119034982 * rgb[0] + 0.6806995451 * rgb[1] + 0.1073969566 * rgb[2];
        var s = 0.0883024619 * rgb[0] + 0.2817188376 * rgb[1] + 0.6299787005 * rgb[2];
        return 0.2104542553 * Math.cbrt(l) + 0.793617785 * Math.cbrt(m) - 0.0040720342 * Math.cbrt(s);
    }

    function setLightness(name, value) {
        var style = document.documentElement.style;
        value = value.toFixed(4);
        if (style.getPropertyValue(name) !== value) {
            style.setProperty(name, value);
        }
    }

    // Dark accents keep the admin color's hue and chroma but are lightened to stay readable on dark surfaces.
    function setDarkLightness() {
        var admin = oklabLightness(resolve("var(--wp-admin-theme-color, #2271b1)"));
        setLightness("--wp-sjs-addon-dark-accent-l", Math.max(admin, 0.72));
        setLightness("--wp-sjs-addon-dark-chart-1-l", Math.min(Math.max(admin, 0.55), 0.62));
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
        if (dark && document.body) {
            setDarkLightness();
        }
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
        return resolve("var(--wp-sjs-addon-" + name + ")");
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

    window.surveyjsAddonAdminTheme = {
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
