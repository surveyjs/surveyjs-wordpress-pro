(function () {
    "use strict";

    const config = window.surveyjsProPdf;
    if (!config || !window.SurveyPDF) return;

    const i18n = config.i18n;
    const MM_TO_PT = 72 / 25.4;
    let fontsPromise = null;
    let busy = false;

    function format(template, ...values) {
        return values.reduce((text, value, index) => text.split(`%${index + 1}$s`).join(String(value)), template);
    }

    function fileBase(name) {
        const slug = String(name || "").trim().replace(/[^\p{L}\p{N}_-]+/gu, "-").replace(/^-+|-+$/g, "");
        return slug || "form";
    }

    function clamp(value, min, max, fallback) {
        const number = parseInt(value, 10);
        return Number.isNaN(number) ? fallback : Math.min(max, Math.max(min, number));
    }

    function loadFonts() {
        if (SurveyPDF.DocOptions.SEGOE_NORMAL) return Promise.resolve();
        fontsPromise ||= new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = config.fontsUrl;
            script.onload = resolve;
            script.onerror = () => {
                fontsPromise = null;
                script.remove();
                reject(new Error(i18n.fontError));
            };
            document.head.appendChild(script);
        });
        return fontsPromise;
    }

    // The site renders [text](url) in form texts as links; the PDF has no Markdown renderer, so keep the text only.
    function withPlainLinks(value) {
        if (typeof value === "string") return value.replace(/\[([^\]]+)\]\((https?:\/\/[^\s()]+)\)/g, "$1");
        if (Array.isArray(value)) return value.map(withPlainLinks);
        if (value && typeof value === "object") {
            return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, withPlainLinks(item)]));
        }
        return value;
    }

    function needsUnicodeFont(...values) {
        return /[^\u0000-ÿ]/.test(JSON.stringify(values));
    }

    function layoutWithoutPagePadding(layout) {
        return Object.fromEntries(Object.entries(layout).filter(([key]) => !key.startsWith("--sjs2-pdf-layout-page-padding-")));
    }

    async function createPdf(source, settings, entry) {
        const json = withPlainLinks(source.json || {});
        const header = entry && settings.proPdfEntryHeader ? format(i18n.entryHeader, entry.id, entry.date) : "";
        let font = settings.proPdfFont;
        if (font !== "helvetica" && font !== "segoe") {
            font = needsUnicodeFont(json, entry ? entry.data : null, header) ? "segoe" : "helvetica";
        }
        if (font === "segoe") await loadFonts();

        const readOnly = Boolean(entry) || !settings.proPdfFillable;
        const margin = clamp(settings.proPdfMargin, 0, 50, 10);
        const pdf = new SurveyPDF.SurveyPDF(json, {
            format: settings.proPdfFormat || "a4",
            orientation: settings.proPdfOrientation === "l" ? "l" : "p",
            fontSize: clamp(settings.proPdfFontSize, 8, 24, 14),
            margins: { left: margin, right: margin, top: margin, bot: margin },
            fontName: font,
            readonlyRenderAs: "text",
            // jsPDF drops the AcroForm fields of a compressed document, so a fillable form stays uncompressed.
            compress: readOnly
        });
        if (settings.proPdfLayout === "spacious" && window.DocLayout && window.DocLayout.Spacious) {
            pdf.applyLayout(layoutWithoutPagePadding(window.DocLayout.Spacious));
        }
        if (settings.proPdfThemeColors && settings.themeMode === "surveyjs" && source.theme && source.theme.colorPalette !== "dark") {
            pdf.applyTheme(source.theme);
        }
        if (entry) pdf.data = entry.data;
        pdf.readOnly = readOnly;

        const textMargins = { left: margin * MM_TO_PT, right: margin * MM_TO_PT };
        if (header) {
            pdf.onRenderHeader.add((_, canvas) => {
                canvas.drawText({ text: header, fontSize: 9, horizontalAlign: "left", margins: textMargins });
            });
        }
        if (settings.proPdfPageNumbers) {
            pdf.onRenderFooter.add((_, canvas) => {
                canvas.drawText({ text: format(i18n.page, canvas.pageNumber, canvas.pageCount), fontSize: 9, horizontalAlign: "right", margins: textMargins });
            });
        }
        return pdf.raw("blob");
    }

    const CRC_TABLE = (() => {
        const table = new Uint32Array(256);
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
            table[n] = c >>> 0;
        }
        return table;
    })();

    function crc32(bytes) {
        let crc = 0xffffffff;
        for (let i = 0; i < bytes.length; i++) crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
        return (crc ^ 0xffffffff) >>> 0;
    }

    function zipRecord(size, fields) {
        const view = new DataView(new ArrayBuffer(size));
        fields.forEach(([offset, bytes, value]) => {
            if (bytes === 4) view.setUint32(offset, value, true);
            else view.setUint16(offset, value, true);
        });
        return view;
    }

    async function zip(files) {
        const encoder = new TextEncoder();
        const now = new Date();
        const time = (now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1);
        const date = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();
        const UTF8_NAMES = 0x0800;
        const local = [];
        const central = [];
        let offset = 0;
        for (const file of files) {
            const name = encoder.encode(file.name);
            const data = new Uint8Array(await file.blob.arrayBuffer());
            const crc = crc32(data);
            local.push(zipRecord(30, [
                [0, 4, 0x04034b50], [4, 2, 20], [6, 2, UTF8_NAMES], [10, 2, time], [12, 2, date],
                [14, 4, crc], [18, 4, data.length], [22, 4, data.length], [26, 2, name.length]
            ]), name, data);
            central.push(zipRecord(46, [
                [0, 4, 0x02014b50], [4, 2, 20], [6, 2, 20], [8, 2, UTF8_NAMES], [12, 2, time], [14, 2, date],
                [16, 4, crc], [20, 4, data.length], [24, 4, data.length], [28, 2, name.length], [42, 4, offset]
            ]), name);
            offset += 30 + name.length + data.length;
        }
        const centralSize = central.reduce((sum, part) => sum + part.byteLength, 0);
        const end = zipRecord(22, [
            [0, 4, 0x06054b50], [8, 2, files.length], [10, 2, files.length], [12, 4, centralSize], [16, 4, offset]
        ]);
        return new Blob([...local, ...central, end], { type: "application/zip" });
    }

    function download(blob, fileName) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    }

    async function run(task, setStatus, onError) {
        if (busy) return;
        busy = true;
        setStatus(i18n.generating);
        try {
            await task(setStatus);
            setStatus(i18n.done);
        } catch (error) {
            if (onError) onError();
            setStatus(format(i18n.error, error && error.message ? error.message : String(error)), true);
        } finally {
            busy = false;
        }
    }

    function initEditor() {
        const api = window.surveyjsEditor;
        const previewButton = document.getElementById("wp-sjs-pro-pdf-preview");
        const blankButton = document.getElementById("wp-sjs-pro-pdf-blank");
        const status = document.getElementById("wp-sjs-pro-pdf-status");
        if (!api || !previewButton || !blankButton || !status) return;

        function setStatus(text, isError) {
            status.textContent = text;
            status.className = "description";
            status.style.color = isError ? "#d63638" : "";
        }

        function currentBlankPdf() {
            const json = api.getSurvey();
            if (!json) throw new Error(i18n.invalidJson);
            return createPdf({ json, theme: api.getTheme() }, api.getSettings());
        }

        blankButton.addEventListener("click", () => run(async () => {
            download(await currentBlankPdf(), `${fileBase(api.config.name || config.formName)}.pdf`);
        }, setStatus));

        previewButton.addEventListener("click", () => {
            if (busy) return;
            const preview = window.open("", "_blank");
            if (!preview) {
                setStatus(format(i18n.error, i18n.popupBlocked), true);
                return;
            }
            run(async () => {
                preview.location.href = URL.createObjectURL(await currentBlankPdf());
            }, setStatus, () => preview.close());
        });
    }

    function initResults() {
        const notice = document.createElement("div");
        notice.className = "notice inline";
        notice.setAttribute("role", "status");
        notice.appendChild(document.createElement("p"));

        function setStatus(text, isError) {
            if (!notice.isConnected) {
                const headerEnd = document.querySelector(".wrap .wp-header-end");
                if (headerEnd) headerEnd.after(notice);
            }
            notice.classList.toggle("notice-error", Boolean(isError));
            notice.classList.toggle("notice-info", !isError);
            notice.firstChild.textContent = text;
        }

        const source = { json: config.json, theme: config.theme };
        const baseName = fileBase(config.formName);

        async function fetchEntries(ids) {
            const body = new URLSearchParams([["action", config.action], ["form", config.formId], ["_ajax_nonce", config.nonce]]);
            ids.forEach((id) => body.append("entries[]", id));
            const response = await fetch(config.ajaxUrl, { method: "POST", credentials: "same-origin", body });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result || !result.success) throw new Error(i18n.loadError);
            if (!result.data.length) throw new Error(i18n.noEntries);
            return result.data;
        }

        function exportEntries(ids) {
            return run(async (progress) => {
                const entries = await fetchEntries(ids);
                const files = [];
                for (const [index, entry] of entries.entries()) {
                    if (entries.length > 1) progress(format(i18n.progress, index + 1, entries.length));
                    files.push({ name: `${baseName}-entry-${entry.id}.pdf`, blob: await createPdf(source, config.settings, entry) });
                }
                if (files.length === 1) {
                    download(files[0].blob, files[0].name);
                } else {
                    download(await zip(files), `${baseName}-entries.zip`);
                }
            }, setStatus);
        }

        document.addEventListener("click", (event) => {
            const entryLink = event.target.closest(".wp-sjs-pro-pdf-entry");
            if (entryLink) {
                event.preventDefault();
                exportEntries([Number(entryLink.dataset.entry)]);
                return;
            }
            if (event.target.closest(".wp-sjs-pro-pdf-blank")) {
                event.preventDefault();
                run(async () => {
                    download(await createPdf(source, config.settings), `${baseName}.pdf`);
                }, setStatus);
            }
        });

        const list = document.getElementById("wp-sjs-results-list");
        if (list) {
            list.addEventListener("submit", (event) => {
                const button = event.submitter;
                if (!button || !/^doaction2?$/.test(button.id)) return;
                const select = document.getElementById(button.id === "doaction2" ? "bulk-action-selector-bottom" : "bulk-action-selector-top");
                const ids = Array.from(list.querySelectorAll('input[name="result[]"]:checked'), (input) => Number(input.value));
                if (!select || select.value !== "pro_pdf" || !ids.length) return;
                event.preventDefault();
                exportEntries(ids);
            });
        }

        if (config.autoExport && config.autoExport.length) {
            const url = new URL(window.location.href);
            url.searchParams.delete("pro_pdf");
            window.history.replaceState(null, "", url);
            exportEntries(config.autoExport);
        }
    }

    if (config.page === "editor") {
        initEditor();
    } else {
        initResults();
    }
})();
