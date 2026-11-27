(function () {
    const editor = window.surveyjsEditor;
    const options = window.surveyjsAddonCreator;
    const host = document.getElementById("wp-sjs-addon-creator");
    if (!editor || !options || !host || !window.SurveyCreator) return;

    const labels = options.labels;
    const rootElement = document.querySelector(".wp-sjs-editor");
    const saveButton = document.getElementById("wp-sjs-save");
    const GDPR_NAME = "gdpr_agreement";
    const MEDIA_URL = "surveyjsMediaUrl";
    const FULLSCREEN_KEY = "surveyjs-forms-addon-editor-fullscreen";
    const SITE_PREVIEW_TABS = ["preview"];
    const SITE_PREVIEW_CLASS = "wp-sjs-addon-site-preview";
    // Creator picks its JSON tab (Monaco or a plain text area) when it is created, so it waits for SurveyJS Forms' Monaco.
    const MONACO_TIMEOUT = 15000;
    const REVIEW_ATTEMPTS = 40;
    const REVIEW_RETRY_MS = 100;
    const DEFAULT_THEME = { themeName: "default", colorPalette: "light", isLight: true, cssVariables: {} };
    const CREATOR_LAYOUT = {
        "--sjs2-base-unit-size": "unit-size",
        "--sjs2-base-unit-spacing": "unit-spacing",
        "--sjs2-base-unit-font-size": "unit-font-size",
        "--sjs2-base-unit-line-height": "unit-line-height",
        "--sjs2-base-unit-radius": "unit-radius"
    };
    const CREATOR_VARIABLES = {
        "--sjs2-typography-font-size-small": "var(--wp-sjs-addon-creator-font-size-small)",
        "--sjs2-typography-line-height-small": "var(--wp-sjs-addon-creator-line-height-small)",
        "--sjs2-color-component-formbox-default-bg": "var(--wp-sjs-addon-input-bg)",
        "--sjs2-color-component-formbox-hovered-bg": "var(--wp-sjs-addon-input-bg)",
        "--sjs2-color-component-formbox-focused-bg": "var(--wp-sjs-addon-input-bg)",
        "--sjs2-color-component-formbox-highlighted-bg": "var(--wp-sjs-addon-input-bg)",
        "--sjs2-color-component-formbox-invalid-bg": "var(--wp-sjs-addon-input-bg)",
        "--sjs2-color-component-formbox-invalid-focused-bg": "var(--wp-sjs-addon-input-bg)",
        "--sjs2-color-component-formbox-default-border": "var(--wp-sjs-addon-input-border)",
        "--sjs2-color-component-formbox-hovered-border": "var(--wp-sjs-addon-input-border)",
        "--sjs2-color-component-formbox-highlighted-border": "var(--wp-sjs-addon-text-2)"
    };

    const columns = rootElement.querySelector(".wp-sjs-editor__columns");
    const sitePreview = rootElement.querySelector(".wp-sjs-editor__preview");

    let creator = null;
    let monacoWait = null;
    let monacoSettled = false;
    let sitePreviewFrame = 0;
    let sitePreviewCanvas = null;
    let fullPreviewOpen = Boolean(editor.isFullPreviewOpen && editor.isFullPreviewOpen());
    const sitePreviewResize = new ResizeObserver(() => scheduleSitePreview());
    let editorSurvey = null;
    let editorTheme = null;
    let creatorSurvey = null;
    let adaptiveNoticeShown = false;

    function isJsonObject(value) {
        return typeof value === "object" && value !== null && !Array.isArray(value);
    }

    function getSetting(key) {
        const input = rootElement.querySelector(`input[data-setting="${key}"]`);
        return Boolean(input && input.checked);
    }

    // The question is a plain boolean, so the form renders on the site without anything registered there.
    function addGdprToolboxItem(instance) {
        const booleanItem = instance.toolbox.getItemByName("boolean");
        instance.toolbox.addItem({
            name: GDPR_NAME,
            title: labels.gdprAgreement,
            tooltip: labels.gdprHelp,
            iconName: booleanItem ? booleanItem.iconName : undefined,
            json: {
                type: "boolean",
                name: GDPR_NAME,
                displayMode: "checkbox",
                title: editor.config.gdpr.text,
                isRequired: true,
                // A required boolean also accepts false (checked, then unchecked), so the validator demands true.
                validators: [{ type: "expression", expression: `{${GDPR_NAME}} = true`, text: editor.config.gdpr.error }]
            }
        });
    }

    function pushSurvey() {
        const json = creator.JSON;
        creatorSurvey = JSON.stringify(json);
        editor.setSurvey(json);
        editorSurvey = JSON.stringify(editor.getSurvey());
    }

    function pushTheme() {
        if (!isJsonObject(creator.theme)) return;
        editor.setTheme(creator.theme);
        editorTheme = JSON.stringify(editor.getTheme());
        if (getSetting("themeMode") && !adaptiveNoticeShown) {
            adaptiveNoticeShown = true;
            creator.notify(labels.adaptiveTheme, "info");
        }
    }

    // While the JSON Editor tab is open, Creator's JSON setter only hands the text to a callback that nothing sets, so
    // the tab and the survey behind it would keep the old JSON while the form editor (preview, Save) has the new one.
    // There the text goes into the tab and is applied the way typed text is.
    function loadIntoCreator(survey) {
        const jsonTab = creator.activeTab === "json" ? creator.getPlugin("json") : null;
        if (!jsonTab) {
            creator.JSON = survey;
            return;
        }
        const text = JSON.stringify(survey, null, 2);
        try {
            jsonTab.model.text = text;
            jsonTab.model.isJSONChanged = true;
            jsonTab.model.applyChanges(true);
        } catch (error) {
            creator.changeText(text, false, true);
        }
    }

    function pullFromEditor() {
        const survey = editor.getSurvey();
        if (survey && JSON.stringify(survey) !== editorSurvey) {
            loadIntoCreator(survey);
            editorSurvey = JSON.stringify(survey);
            creatorSurvey = JSON.stringify(creator.JSON);
        }
        const theme = editor.getTheme();
        if (theme && JSON.stringify(theme) !== editorTheme) {
            creator.theme = theme;
            editorTheme = JSON.stringify(theme);
        }
    }

    // Creator applies text typed in its JSON tab only when the tab is left, and SurveyJS Forms may read the survey before that.
    function flushToEditor() {
        if (!creator) return;
        const jsonTab = creator.activeTab === "json" ? creator.getPlugin("json") : null;
        if (jsonTab && jsonTab.model) jsonTab.model.applyChanges();
        if (JSON.stringify(creator.JSON) !== creatorSurvey) pushSurvey();
        else pullFromEditor();
    }

    function chooseMedia(chooser) {
        const accept = chooser.input && chooser.input.accept ? chooser.input.accept : "";
        const types = Array.from(new Set(accept.split(",").map((type) => type.trim().split("/")[0]).filter((type) => /^[a-z]+$/.test(type))));
        const frame = window.wp.media({
            title: labels.mediaTitle,
            button: { text: labels.mediaButton },
            library: types.length > 0 ? { type: types } : {},
            multiple: false
        });
        frame.$el.addClass("wp-sjs-media-frame");
        frame.on("select", () => {
            const attachment = frame.state().get("selection").first().toJSON();
            const file = new File([], attachment.filename || "file", { type: attachment.mime || "" });
            file[MEDIA_URL] = attachment.url;
            chooser.callback([file]);
        });
        frame.open();
    }

    function uploadFile(upload) {
        const file = upload.files[0];
        if (!file) {
            upload.callback("error");
        } else if (file[MEDIA_URL]) {
            upload.callback("success", file[MEDIA_URL]);
        } else {
            const body = new FormData();
            body.append("file", file, file.name);
            window.wp.apiFetch({ path: "/wp/v2/media", method: "POST", body: body })
                .then((media) => upload.callback("success", media.source_url))
                .catch((error) => {
                    creator.notify(labels.uploadFailed + (error && error.message ? " " + error.message : ""), "error");
                    upload.callback("error");
                });
        }
    }

    // Creator's preview saves nothing, so files answered there stay in the browser instead of being uploaded to the site.
    function keepFilesInBrowser(upload) {
        upload.callback(upload.files.map((file) => ({ file: file, content: URL.createObjectURL(file) })));
    }

    function createCreator() {
        const instance = new SurveyCreator.SurveyCreator({
            showLogicTab: true,
            showThemeTab: false,
            showTranslationTab: true,
            showJSONEditorTab: true,
            showPreviewTab: true,
            showCreatorThemeSettings: false,
            isAutoSave: true
        });
        if (options.locale) {
            instance.locale = options.locale;
        }
        // "Saving" only hands each change to the form editor at once: its Save button stores the form, and its unsaved-changes prompt must see every change.
        instance.autoSaveDelay = 0;
        instance.JSON = editor.getSurvey() || {};
        instance.theme = editor.getTheme() || {};
        // Loading the form editor's JSON into the JSON Editor tab also ends in this autosave; handing the same survey
        // back would count as a new edit there and mark the AI draft as edited by the person.
        instance.saveSurveyFunc = (saveNo, callback) => {
            if (JSON.stringify(instance.JSON) !== creatorSurvey) pushSurvey();
            callback(saveNo, true);
        };
        instance.saveThemeFunc = (saveNo, callback) => {
            pushTheme();
            callback(saveNo, true);
        };
        if (window.wp && window.wp.media) {
            instance.onOpenFileChooser.add((sender, chooser) => chooseMedia(chooser));
        }
        instance.onUploadFile.add((sender, upload) => uploadFile(upload));
        instance.onSurveyInstanceCreated.add((sender, created) => {
            if (created.area === "preview-tab") created.survey.onUploadFiles.add((survey, upload) => keepFilesInBrowser(upload));
        });
        addGdprToolboxItem(instance);
        applyAdminTheme(instance);
        // SurveyJS Forms counts the issues of a saved form itself; with Creator's lint options it counts what the JSON Editor tab shows.
        if (editor.setLintOptions && window.SurveyCreatorCore && SurveyCreatorCore.getCreatorLintOptions) {
            editor.setLintOptions(SurveyCreatorCore.getCreatorLintOptions(instance));
        }
        return instance;
    }

    function jsonErrors() {
        const plugin = creator && creator.activeTab === "json" ? creator.getPlugin("json") : null;
        const list = plugin && plugin.model ? plugin.model.errorList : null;
        return list ? list.actions.map((action) => action.data && action.data.error).filter(Boolean) : [];
    }

    // Creator renders its JSON Editor tab and lints the text only after the switch, so the error is looked up until it is there.
    function reviewIssues(path, attempt = 0) {
        if (!creator) {
            if (attempt < REVIEW_ATTEMPTS) setTimeout(() => reviewIssues(path, attempt + 1), REVIEW_RETRY_MS);
            return;
        }
        if (creator.activeTab !== "json") creator.switchTab("json");
        const errors = jsonErrors();
        const error = errors.find((item) => item.finding && item.finding.path === path) || errors[0];
        try {
            if (!error) throw new Error("no error yet");
            creator.getPlugin("json").model.gotoError(error.at, error.rowAt, error.columnAt);
        } catch (failure) {
            if (attempt < REVIEW_ATTEMPTS) setTimeout(() => reviewIssues(path, attempt + 1), REVIEW_RETRY_MS);
        }
    }

    // Creator reads its base units as plain lengths, so the creator.css tokens are resolved before they are handed over.
    function creatorLayout() {
        const style = window.getComputedStyle(document.body);
        const layout = {};
        Object.keys(CREATOR_LAYOUT).forEach((name) => {
            const value = style.getPropertyValue("--wp-sjs-addon-creator-" + CREATOR_LAYOUT[name]).trim();
            if (value) layout[name] = value;
        });
        return layout;
    }

    function applyAdminTheme(instance) {
        const adminTheme = window.surveyjsAddonAdminTheme;
        if (!adminTheme) return;
        const dark = adminTheme.isDark();
        instance.preferredColorPalette = dark ? "dark" : "light";
        if (window.monaco) window.monaco.editor.setTheme(dark ? "vs-dark" : "vs");
        if (!options.adminStyle) {
            instance.applyCreatorTheme(dark && options.darkTheme ? options.darkTheme : DEFAULT_THEME);
            return;
        }
        const theme = adminTheme.surveyjsTheme();
        Object.assign(theme.cssVariables, CREATOR_VARIABLES, creatorLayout());
        instance.applyCreatorTheme(theme);
    }

    function sitePreviewTarget() {
        const shown = creator && editor.getActiveTab() === options.tab && SITE_PREVIEW_TABS.includes(creator.activeTab);
        return shown ? host.querySelector(".svc-test-tab__content") : null;
    }

    // SurveyJS Forms' preview frame shows the form on a real page of the site and already follows every change Creator hands
    // to the form editor, so it is laid over Creator's own preview canvas instead of moving it, which would reload it.
    function placeSitePreview() {
        cancelAnimationFrame(sitePreviewFrame);
        if (fullPreviewOpen) return;
        const target = sitePreviewTarget();
        rootElement.classList.toggle(SITE_PREVIEW_CLASS, Boolean(target));
        // Creator animates its side bar when it switches between the mobile and full layouts, so the canvas resizes
        // after the DOM change that started it; watching the canvas itself catches the final size.
        if (target !== sitePreviewCanvas) {
            if (sitePreviewCanvas) sitePreviewResize.unobserve(sitePreviewCanvas);
            if (target) sitePreviewResize.observe(target);
            sitePreviewCanvas = target;
        }
        const model = target ? previewModel() : null;
        if (editor.setPreviewShowInvisibleElements) {
            editor.setPreviewShowInvisibleElements(Boolean(model && model.showInvisibleElements));
        }
        if (!target) {
            sitePreview.removeAttribute("style");
            return;
        }
        const canvas = target.getBoundingClientRect();
        const origin = columns.getBoundingClientRect();
        const frame = model && model.simulator ? model.simulator.simulatorFrame : null;
        const screen = frame ? target.querySelector(".svd-simulator-content") : null;
        if (!screen) {
            Object.assign(sitePreview.style, {
                top: canvas.top - origin.top + "px",
                left: canvas.left - origin.left + "px",
                width: canvas.width + "px",
                height: canvas.height + "px",
                transform: "",
                transformOrigin: "",
                clipPath: ""
            });
            return;
        }
        // The site page is laid out at the device's CSS size and scaled like Creator's simulator, so it shows the theme's real breakpoints.
        const box = screen.getBoundingClientRect();
        const scale = box.width / frame.deviceWidth || 1;
        const clip = [
            canvas.top - box.top,
            box.right - canvas.right,
            box.bottom - canvas.bottom,
            canvas.left - box.left
        ].map((overflow) => Math.max(0, overflow) / scale + "px");
        Object.assign(sitePreview.style, {
            top: box.top - origin.top + "px",
            left: box.left - origin.left + "px",
            width: frame.deviceWidth + "px",
            height: frame.deviceHeight + "px",
            transform: scale === 1 ? "" : `scale(${scale})`,
            transformOrigin: "0 0",
            clipPath: `inset(${clip.join(" ")})`
        });
    }

    function previewModel() {
        const plugin = creator && creator.getPlugin ? creator.getPlugin("preview") : null;
        return plugin ? plugin.model : null;
    }

    function scheduleSitePreview() {
        cancelAnimationFrame(sitePreviewFrame);
        sitePreviewFrame = requestAnimationFrame(placeSitePreview);
    }

    function watchSitePreview() {
        if (!columns || !sitePreview) return;
        creator.onActiveTabChanged.add(scheduleSitePreview);
        creator.registerFunctionOnPropertyValueChanged("isMobileView", scheduleSitePreview);
        sitePreviewResize.observe(host);
        new MutationObserver(scheduleSitePreview).observe(host, { childList: true, subtree: true, attributes: true, attributeFilter: ["class"] });
        host.addEventListener("transitionend", scheduleSitePreview);
        host.addEventListener("scroll", scheduleSitePreview, true);
        window.addEventListener("resize", scheduleSitePreview);
    }

    // SurveyJS Forms' full preview shows the same container, so the overlay placement must not stay on it meanwhile.
    function onFullPreview(event) {
        fullPreviewOpen = Boolean(event.detail && event.detail.open);
        if (fullPreviewOpen) {
            flushToEditor();
            cancelAnimationFrame(sitePreviewFrame);
            rootElement.classList.remove(SITE_PREVIEW_CLASS);
            if (sitePreview) sitePreview.removeAttribute("style");
        } else if (creator && sitePreview) {
            placeSitePreview();
        }
    }

    function showCreator() {
        if (creator) {
            pullFromEditor();
            scheduleSitePreview();
            return;
        }
        if (!window.monaco && editor.whenMonaco && !monacoSettled) {
            if (!monacoWait) {
                monacoWait = Promise.race([editor.whenMonaco(), new Promise((resolve) => setTimeout(resolve, MONACO_TIMEOUT))]).then(() => {
                    monacoSettled = true;
                    if (editor.getActiveTab() === options.tab) showCreator();
                });
            }
            return;
        }
        host.replaceChildren();
        creator = createCreator();
        editorSurvey = JSON.stringify(editor.getSurvey());
        editorTheme = JSON.stringify(editor.getTheme());
        creatorSurvey = JSON.stringify(creator.JSON);
        creator.render(host);
        watchSitePreview();
        if (window.surveyjsAddonAdminTheme) window.surveyjsAddonAdminTheme.onChange(() => applyAdminTheme(creator));
    }

    function readFullscreen() {
        try {
            return window.localStorage.getItem(FULLSCREEN_KEY) !== "0";
        } catch (error) {
            return true;
        }
    }

    function setFullscreen(button, on) {
        rootElement.classList.toggle("wp-sjs-addon-fullscreen", on);
        document.documentElement.classList.toggle("wp-sjs-addon-fullscreen-mode", on);
        button.setAttribute("aria-pressed", String(on));
        button.querySelector(".dashicons").className = "dashicons " + (on ? "dashicons-fullscreen-exit-alt" : "dashicons-fullscreen-alt");
        button.querySelector(".wp-sjs-addon-toolbar-button__label").textContent = on ? labels.exitFullscreen : labels.fullscreen;
        try {
            window.localStorage.setItem(FULLSCREEN_KEY, on ? "1" : "0");
        } catch (error) {
            return;
        }
    }

    function toolbarButton(className, iconName, text) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "button wp-sjs-button-icon wp-sjs-addon-toolbar-button " + className;
        const icon = document.createElement("span");
        icon.className = "dashicons " + iconName;
        icon.setAttribute("aria-hidden", "true");
        const label = document.createElement("span");
        label.className = "wp-sjs-addon-toolbar-button__label";
        label.textContent = text;
        button.append(icon, label);
        return button;
    }

    function addFullscreenButton() {
        const button = toolbarButton("wp-sjs-addon-fullscreen-toggle", "", "");
        button.addEventListener("click", () => setFullscreen(button, !rootElement.classList.contains("wp-sjs-addon-fullscreen")));
        (document.getElementById("wp-sjs-full-preview") || saveButton).before(button);
        setFullscreen(button, readFullscreen());
    }

    if (saveButton) addFullscreenButton();
    document.addEventListener("surveyjs:editor-full-preview", onFullPreview);
    document.addEventListener("click", (event) => {
        if (event.target.closest("#wp-sjs-save, #wp-sjs-embed, [data-sjs-embed-save], .wp-sjs-editor__tabs .nav-tab")) flushToEditor();
    }, true);
    window.addEventListener("beforeunload", flushToEditor, true);
    document.addEventListener("surveyjs:editor-flush", flushToEditor);
    document.addEventListener("surveyjs:editor-external-change", () => {
        if (creator) pullFromEditor();
    });
    document.addEventListener("surveyjs:editor-review-issues", (event) => {
        event.preventDefault();
        if (editor.getActiveTab() !== options.tab) editor.switchTab(options.tab);
        reviewIssues(event.detail.path);
    });
    document.addEventListener("surveyjs:editor-tab", (event) => {
        if (event.detail.tab === options.tab) showCreator();
        else if (creator) placeSitePreview();
    });
    if (editor.getActiveTab() === options.tab) showCreator();
})();
