(function () {
    "use strict";

    const config = window.surveyjsAddonPdfRespondent;
    if (!config) return;

    const i18n = config.i18n;
    let libraries = null;

    function format(template, ...values) {
        return values.reduce((text, value, index) => text.split(`%${index + 1}$s`).join(String(value)), template);
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = src;
            script.onload = resolve;
            script.onerror = () => {
                script.remove();
                reject(new Error(i18n.loadError));
            };
            document.head.appendChild(script);
        });
    }

    function loadLibraries() {
        if (window.SurveyJSAddonPdf) return Promise.resolve(window.SurveyJSAddonPdf);
        window.surveyjsAddonPdf = window.surveyjsAddonPdf || config.pdf;
        libraries ||= (window.jspdf ? Promise.resolve() : loadScript(config.scripts.jspdf))
            .then(() => window.SurveyPDF || loadScript(config.scripts.pdf))
            .then(() => (window.DocLayout && window.DocLayout.Spacious) || loadScript(config.scripts.spacious))
            .then(() => loadScript(config.scripts.pdfJs))
            .then(() => {
                if (!window.SurveyJSAddonPdf) throw new Error(i18n.loadError);
                return window.SurveyJSAddonPdf;
            })
            .catch((error) => {
                libraries = null;
                throw error;
            });
        return libraries;
    }

    function buildPdf(form, entry) {
        return loadLibraries().then((api) => {
            const settings = Object.assign({}, form.settings);
            if (!entry.id) settings.addonPdfEntryHeader = false;
            return api.createPdf({ json: form.json, theme: form.theme, logoUrl: form.logoUrl }, settings, entry);
        });
    }

    function fileName(api, name, entry) {
        return `${api.fileBase(name)}${entry.id ? `-entry-${entry.id}` : ""}.pdf`;
    }

    function formOf(detail) {
        return { json: detail.config.json, theme: detail.config.theme, settings: detail.config.addonPdf.settings, logoUrl: detail.config.addonPdf.logoUrl };
    }

    function withTimeout(promise, ms) {
        return Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error("timeout")), ms))]);
    }

    document.addEventListener("surveyjs:before-submit", (event) => {
        const detail = event.detail;
        const options = detail.config.addonPdf;
        if (!options || !options.attach) return;
        const answersSize = new Blob([JSON.stringify(detail.data)]).size;
        detail.waitUntil(withTimeout(buildPdf(formOf(detail), { id: "", date: "", data: detail.data }), config.timeout)
            .then((blob) => {
                // A request over post_max_size reaches PHP without any fields, so the entry itself would be lost.
                const tooLarge = blob.size > config.maxSize || (config.postMaxSize > 0 && blob.size + answersSize + 65536 > config.postMaxSize);
                return tooLarge ? null : { [config.field]: blob };
            })
            .catch((error) => {
                console.error("Error: ", error);
                return null;
            }));
    });

    function addDownloadButton(detail, entry) {
        const wrapper = document.createElement("div");
        wrapper.className = "wp-sjs-addon-pdf-download";
        wrapper.style.cssText = "margin: 16px 0; text-align: center;";
        const button = document.createElement("button");
        button.type = "button";
        button.className = "wp-element-button";
        button.textContent = i18n.download;
        const status = document.createElement("p");
        status.setAttribute("role", "status");
        status.style.margin = "8px 0 0";
        let pdf = null;

        button.addEventListener("click", () => {
            button.disabled = true;
            status.textContent = i18n.generating;
            (pdf ? Promise.resolve(pdf) : buildPdf(formOf(detail), entry))
                .then((blob) => {
                    pdf = blob;
                    window.SurveyJSAddonPdf.download(blob, fileName(window.SurveyJSAddonPdf, detail.survey.title, entry));
                    status.textContent = "";
                })
                .catch((error) => {
                    console.error("Error: ", error);
                    status.textContent = i18n.failed;
                })
                .finally(() => {
                    button.disabled = false;
                });
        });

        wrapper.append(button, status);
        detail.container.after(wrapper);
    }

    document.addEventListener("surveyjs:submitted", (event) => {
        const detail = event.detail;
        const options = detail.config.addonPdf;
        if (!options || !options.download) return;
        const saved = (detail.response && detail.response.addonPdf) || {};
        const entry = { id: saved.id || "", date: saved.date || "", data: detail.data };
        let shown = false;
        const show = () => {
            if (shown) return;
            shown = true;
            addDownloadButton(detail, entry);
        };
        // With CAPTCHA the entry is saved before the survey completes, so the button waits for the completed page.
        if (detail.survey.state === "completed") {
            show();
        } else {
            detail.survey.onComplete.add(show);
        }
    });

    function initEntryPage(page) {
        const status = document.getElementById("wp-sjs-addon-pdf-status");
        const link = document.getElementById("wp-sjs-addon-pdf-download");
        const frame = document.getElementById("wp-sjs-addon-pdf-frame");
        if (!status || !link || !frame) return;
        buildPdf(page, page.entry).then(
            (blob) => {
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.download = fileName(window.SurveyJSAddonPdf, page.formName, page.entry);
                link.hidden = false;
                frame.src = url;
                frame.hidden = false;
                status.textContent = "";
            },
            (error) => {
                status.textContent = format(i18n.error, error && error.message ? error.message : String(error));
            }
        );
    }

    if (config.entryPage) initEntryPage(config.entryPage);
})();
