(function () {
    "use strict";

    const config = window.surveyjsProPdf;
    if (!config || !window.SurveyPDF) return;

    const i18n = config.i18n;
    const MM_TO_PT = 72 / 25.4;
    const MARGIN = 10;
    const BRANDED_TOP_MARGIN = 24;
    const LOGO_MAX_WIDTH = 50;
    const LOGO_MAX_HEIGHT = 12;
    const LOGO_GAP = 4;
    // survey-pdf draws an image at no more than 0.75 pt per pixel, so an image smaller than 1.34 px per pt would not fill its rect.
    const LOGO_PX_PER_PT = 3;
    const WATERMARK_PX_PER_PT = 1.5;
    const logoCache = new Map();
    const watermarkCache = new Map();
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

    function createCanvas(width, height) {
        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(width));
        canvas.height = Math.max(1, Math.round(height));
        return canvas;
    }

    function loadLogo(url) {
        if (!logoCache.has(url)) {
            const promise = new Promise((resolve, reject) => {
                const image = new Image();
                image.crossOrigin = "anonymous";
                image.onload = () => resolve(image);
                image.onerror = () => reject(new Error(i18n.logoError));
                image.src = url;
            }).then((image) => {
                if (!image.naturalWidth || !image.naturalHeight) throw new Error(i18n.logoError);
                const scale = Math.min(LOGO_MAX_WIDTH * MM_TO_PT / image.naturalWidth, LOGO_MAX_HEIGHT * MM_TO_PT / image.naturalHeight);
                const width = image.naturalWidth * scale;
                const height = image.naturalHeight * scale;
                const canvas = createCanvas(width * LOGO_PX_PER_PT, height * LOGO_PX_PER_PT);
                canvas.getContext("2d").drawImage(image, 0, 0, canvas.width, canvas.height);
                return { base64: canvas.toDataURL("image/png"), width, height };
            });
            promise.catch(() => logoCache.delete(url));
            logoCache.set(url, promise);
        }
        return logoCache.get(url);
    }

    function watermarkImage(text, width, height) {
        const key = `${width}x${height}:${text}`;
        if (!watermarkCache.has(key)) {
            const canvas = createCanvas(width * WATERMARK_PX_PER_PT, height * WATERMARK_PX_PER_PT);
            const context = canvas.getContext("2d");
            const font = (size) => `bold ${size}px Helvetica, Arial, sans-serif`;
            context.font = font(100);
            const size = Math.min(70 * Math.hypot(canvas.width, canvas.height) / context.measureText(text).width, canvas.width * 0.3);
            context.font = font(size);
            context.fillStyle = "rgba(128, 128, 128, 0.18)";
            context.textAlign = "center";
            context.textBaseline = "middle";
            context.translate(canvas.width / 2, canvas.height / 2);
            context.rotate(-Math.atan2(canvas.height, canvas.width));
            context.fillText(text, 0, 0);
            watermarkCache.set(key, canvas.toDataURL("image/png"));
        }
        return watermarkCache.get(key);
    }

    function hideUnansweredQuestions(survey) {
        survey.getAllQuestions().forEach((question) => {
            if (question.hasInput && question.isEmpty() && !question.comment) question.visible = false;
        });
    }

    function addPageHeader(pdf, logo, title) {
        const margin = MARGIN * MM_TO_PT;
        pdf.onRenderHeader.add(async (_, canvas) => {
            let left = margin;
            if (logo) {
                await canvas.drawImage({ base64: logo.base64, width: logo.width, height: logo.height, horizontalAlign: "left", margins: { left } });
                left += logo.width + LOGO_GAP * MM_TO_PT;
            }
            if (title) {
                canvas.drawText({ text: title, fontSize: 12, isBold: true, horizontalAlign: "left", margins: { left, right: margin } });
            }
        });
    }

    function addWatermark(pdf, text) {
        const margin = MARGIN * MM_TO_PT;
        pdf.onRenderFooter.add(async (_, canvas) => {
            const rect = { xLeft: margin, xRight: canvas.rect.xRight - margin, yTop: margin, yBot: canvas.rect.yBot - margin };
            await canvas.drawImage({ base64: watermarkImage(text, rect.xRight - rect.xLeft, rect.yBot - rect.yTop), rect });
        });
    }

    async function createPdf(source, settings, entry) {
        const json = withPlainLinks(source.json || {});
        const header = entry && settings.proPdfEntryHeader ? format(i18n.entryHeader, entry.id, entry.date) : "";
        const title = String(settings.proPdfHeaderText || "").trim();
        const watermark = String(settings.proPdfWatermark || "").trim();
        const font = needsUnicodeFont(json, entry ? entry.data : null, header, title) ? "segoe" : "helvetica";
        if (font === "segoe") await loadFonts();
        const logo = source.logoUrl ? await loadLogo(source.logoUrl) : null;

        const readOnly = Boolean(entry) || !settings.proPdfFillable;
        const blankShowsAll = entry ? {} : {
            choiceComments: true,
            choiceNestedContent: true,
            conditionalChoices: true,
            conditionalElements: true,
            conditionalMatrixColumns: true,
            conditionalMatrixRows: true
        };
        const pdf = new SurveyPDF.SurveyPDF(json, {
            format: settings.proPdfFormat && settings.proPdfFormat !== "auto" ? settings.proPdfFormat : config.format || "a4",
            fontSize: clamp(settings.proPdfFontSize, 8, 24, 14),
            margins: { left: MARGIN, right: MARGIN, top: logo || title ? BRANDED_TOP_MARGIN : MARGIN, bot: MARGIN },
            fontName: font,
            readonlyRenderAs: "text",
            dynamicContent: blankShowsAll,
            // jsPDF drops the AcroForm fields of a compressed document, so a fillable form stays uncompressed.
            compress: readOnly
        });
        if (settings.proPdfLayout === "spacious" && window.DocLayout && window.DocLayout.Spacious) {
            pdf.applyLayout(layoutWithoutPagePadding(window.DocLayout.Spacious));
        }
        if (settings.proPdfThemeColors && settings.themeMode === "surveyjs" && source.theme && source.theme.colorPalette !== "dark") {
            pdf.applyTheme(source.theme);
        }
        if (entry) {
            pdf.data = entry.data;
            if (settings.proPdfHideEmpty) hideUnansweredQuestions(pdf);
        }
        pdf.readOnly = readOnly;

        const textMargins = { left: MARGIN * MM_TO_PT, right: MARGIN * MM_TO_PT };
        if (logo || title) addPageHeader(pdf, logo, title);
        if (header) {
            pdf.onRenderFooter.add((_, canvas) => {
                canvas.drawText({ text: header, fontSize: 9, horizontalAlign: "left", margins: textMargins });
            });
        }
        if (watermark) addWatermark(pdf, watermark);
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

    function initLogoPicker(logoUrls) {
        const input = document.getElementById("wp-sjs-pro-pdf-logo");
        const preview = document.getElementById("wp-sjs-pro-pdf-logo-preview");
        const chooseButton = document.getElementById("wp-sjs-pro-pdf-logo-choose");
        const removeButton = document.getElementById("wp-sjs-pro-pdf-logo-remove");
        if (!input || !preview || !chooseButton || !removeButton) return;

        function render() {
            const url = logoUrls.get(input.value) || "";
            if (url) preview.src = url;
            preview.hidden = !url;
            removeButton.hidden = !url;
        }

        function setLogo(id, url) {
            logoUrls.set(String(id), url);
            input.value = String(id);
            input.dispatchEvent(new Event("input", { bubbles: true }));
            render();
        }

        chooseButton.disabled = !(window.wp && window.wp.media);
        chooseButton.addEventListener("click", () => {
            const frame = window.wp.media({
                title: chooseButton.dataset.frameTitle,
                button: { text: chooseButton.dataset.frameButton },
                library: { type: "image" },
                multiple: false
            });
            frame.on("select", () => {
                const attachment = frame.state().get("selection").first().toJSON();
                const url = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;
                if (url) setLogo(attachment.id, url);
            });
            frame.open();
        });
        removeButton.addEventListener("click", () => setLogo(0, ""));
        render();
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

        const logoUrls = new Map([[String(config.logo.id), config.logo.url]]);
        initLogoPicker(logoUrls);

        let pdfQueue = Promise.resolve();

        // survey-pdf keeps render state in shared singletons, so two documents must not render at the same time.
        function serial(task) {
            const result = pdfQueue.then(task);
            pdfQueue = result.catch(() => {});
            return result;
        }

        function currentBlankPdf(isStale) {
            return serial(() => {
                if (isStale && isStale()) return null;
                const json = api.getSurvey();
                if (!json) throw new Error(i18n.invalidJson);
                const settings = api.getSettings();
                return createPdf({ json, theme: api.getTheme(), logoUrl: logoUrls.get(String(settings.proPdfLogo)) }, settings);
            });
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

        initLivePreview(currentBlankPdf, setStatus);
    }

    function initLivePreview(buildPdf, setStatus) {
        const frame = document.getElementById("wp-sjs-pro-pdf-live");
        const panel = frame && frame.closest("[data-panel]");
        if (!panel) return;
        let frameUrl = null;
        let timer = null;
        let latest = 0;
        let showsError = false;

        async function refresh() {
            clearTimeout(timer);
            const request = ++latest;
            try {
                const blob = await buildPdf(() => request !== latest);
                if (!blob || request !== latest || panel.hidden) return;
                if (frameUrl) URL.revokeObjectURL(frameUrl);
                frameUrl = URL.createObjectURL(blob);
                frame.src = frameUrl;
                if (showsError) setStatus("");
                showsError = false;
            } catch (error) {
                if (request !== latest) return;
                setStatus(format(i18n.error, error && error.message ? error.message : String(error)), true);
                showsError = true;
            }
        }

        function schedule() {
            clearTimeout(timer);
            if (!panel.hidden) timer = setTimeout(refresh, 500);
        }

        ["input", "change"].forEach((type) => panel.addEventListener(type, schedule));
        document.addEventListener("surveyjs:editor-tab", (event) => {
            if (event.detail && event.detail.tab === panel.dataset.panel) refresh();
            else {
                clearTimeout(timer);
                setFullScreen(false);
            }
        });
        if (!panel.hidden) refresh();

        const previewPanel = document.getElementById("wp-sjs-pro-pdf-preview-panel");
        const openButton = document.getElementById("wp-sjs-pro-pdf-full");
        const closeButton = document.getElementById("wp-sjs-pro-pdf-full-close");
        let fullScreen = false;

        function setFullScreen(open) {
            if (!previewPanel || open === fullScreen) return;
            fullScreen = open;
            previewPanel.classList.toggle("wp-sjs-pro-pdf__preview--full", open);
            document.body.classList.toggle("wp-sjs-pro-pdf-full-open", open);
            openButton.setAttribute("aria-expanded", String(open));
            openButton.hidden = open;
            closeButton.hidden = !open;
            (open ? closeButton : openButton).focus();
        }

        if (previewPanel && openButton && closeButton) {
            openButton.addEventListener("click", () => setFullScreen(true));
            closeButton.addEventListener("click", () => setFullScreen(false));
            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape" && fullScreen) setFullScreen(false);
            });
        }
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

        const source = { json: config.json, theme: config.theme, logoUrl: config.logo.url };
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

    window.SurveyJSProPdf = { createPdf, download, fileBase };

    if (config.page === "editor") {
        initEditor();
    } else if (config.page === "results") {
        initResults();
    }
})();
