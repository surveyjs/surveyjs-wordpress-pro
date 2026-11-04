(function () {
    const editor = window.surveyjsEditor;
    const options = window.surveyjsProCreator;
    const host = document.getElementById("wp-sjs-pro-creator");
    if (!editor || !options || !host || !window.SurveyCreator) return;

    const labels = options.labels;
    const rootElement = document.querySelector(".wp-sjs-editor");
    const saveButton = document.getElementById("wp-sjs-save");
    const GDPR_NAME = "gdpr_agreement";
    const CATEGORY = "surveyjsForms";
    const MEDIA_URL = "surveyjsMediaUrl";
    const FULLSCREEN_KEY = "surveyjs-pro-editor-fullscreen";

    let creator = null;
    let editorSurvey = null;
    let editorTheme = null;
    let creatorSurvey = null;
    let adaptiveNoticeShown = false;

    function isJsonObject(value) {
        return typeof value === "object" && value !== null && !Array.isArray(value);
    }

    function settingInput(key) {
        return rootElement.querySelector(`input[data-setting="${key}"]`);
    }

    function getSetting(key) {
        const input = settingInput(key);
        return Boolean(input && input.checked);
    }

    function setSetting(key, value) {
        const input = settingInput(key);
        if (!input || input.checked === Boolean(value)) return;
        input.checked = Boolean(value);
        input.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function gdprQuestions(survey) {
        return survey.getAllQuestions(false, true, true).filter((question) => question.name === GDPR_NAME);
    }

    function setGdprAgreement(survey, add) {
        const questions = gdprQuestions(survey);
        if (Boolean(add) === (questions.length > 0) || !creator || survey !== creator.survey) return;
        if (add) {
            const page = survey.pages.length > 0 ? survey.pages[survey.pages.length - 1] : survey.addNewPage("page1");
            const question = Survey.Serializer.createClass("boolean");
            // A required boolean also accepts false (checked, then unchecked), so the validator demands true.
            question.fromJSON({
                name: GDPR_NAME,
                displayMode: "checkbox",
                title: editor.config.gdpr.text,
                isRequired: true,
                validators: [{ type: "expression", expression: `{${GDPR_NAME}} = true`, text: editor.config.gdpr.error }]
            });
            page.addElement(question);
            creator.setModified({ type: "ADDED_FROM_PAGEBUTTON", question: question });
            creator.notify(labels.gdprAdded, "info");
        } else {
            questions.forEach((question) => {
                question.delete();
                creator.setModified({ type: "OBJECT_DELETED", target: question });
            });
            creator.notify(labels.gdprRemoved, "info");
        }
    }

    function addFormProperties() {
        const strings = SurveyCreatorCore.editorLocalization.getLocale("en");
        strings.pe.tabs[CATEGORY] = labels.category;
        strings.pehelp.gdprAgreement = labels.gdprHelp;
        strings.pehelp.captchaEnabled = labels.captchaHelp;
        strings.pehelp.saveResume = labels.saveResumeHelp;
        const property = (name, displayName, rest) => Object.assign({
            name: name,
            type: "boolean",
            displayName: displayName,
            category: CATEGORY,
            isSerializable: false
        }, rest);
        Survey.Serializer.addProperties("survey", [
            property("gdprAgreement", labels.gdprAgreement, {
                visibleIndex: 0,
                onGetValue: (survey) => gdprQuestions(survey).length > 0,
                onSetValue: (survey, value) => setGdprAgreement(survey, value)
            }),
            property("captchaEnabled", labels.captchaEnabled, {
                visibleIndex: 1,
                enableIf: () => options.captchaConfigured,
                onGetValue: () => getSetting("captchaEnabled"),
                onSetValue: (survey, value) => setSetting("captchaEnabled", value)
            }),
            property("saveResume", labels.saveResume, {
                visibleIndex: 2,
                onGetValue: () => getSetting("saveResume"),
                onSetValue: (survey, value) => setSetting("saveResume", value)
            })
        ]);
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

    function pullFromEditor() {
        const survey = editor.getSurvey();
        if (survey && JSON.stringify(survey) !== editorSurvey) {
            creator.JSON = survey;
            editorSurvey = JSON.stringify(survey);
            creatorSurvey = JSON.stringify(creator.JSON);
        }
        const theme = editor.getTheme();
        if (theme && JSON.stringify(theme) !== editorTheme) {
            creator.theme = theme;
            editorTheme = JSON.stringify(theme);
        }
    }

    // Creator applies text typed in its JSON tab only when the tab is left, and Base may read the survey before that.
    function flushToEditor() {
        if (!creator) return;
        const jsonTab = creator.activeTab === "json" ? creator.getPlugin("json") : null;
        if (jsonTab && jsonTab.model) jsonTab.model.applyChanges();
        if (JSON.stringify(creator.JSON) !== creatorSurvey) pushSurvey();
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

    function createCreator() {
        const instance = new SurveyCreator.SurveyCreator({
            showLogicTab: true,
            showThemeTab: true,
            showTranslationTab: true,
            showJSONEditorTab: true,
            showPreviewTab: true,
            isAutoSave: true
        });
        // "Saving" only hands each change to the form editor at once: its Save button stores the form, and its unsaved-changes prompt must see every change.
        instance.autoSaveDelay = 0;
        instance.JSON = editor.getSurvey() || {};
        instance.theme = editor.getTheme() || {};
        instance.saveSurveyFunc = (saveNo, callback) => {
            pushSurvey();
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
        return instance;
    }

    function showCreator() {
        if (creator) {
            pullFromEditor();
            return;
        }
        host.replaceChildren();
        creator = createCreator();
        editorSurvey = JSON.stringify(editor.getSurvey());
        editorTheme = JSON.stringify(editor.getTheme());
        creatorSurvey = JSON.stringify(creator.JSON);
        creator.render(host);
    }

    function readFullscreen() {
        try {
            return window.localStorage.getItem(FULLSCREEN_KEY) === "1";
        } catch (error) {
            return false;
        }
    }

    function setFullscreen(button, on) {
        rootElement.classList.toggle("wp-sjs-pro-fullscreen", on);
        document.body.classList.toggle("wp-sjs-pro-fullscreen-mode", on);
        button.setAttribute("aria-pressed", String(on));
        button.querySelector(".dashicons").className = "dashicons " + (on ? "dashicons-fullscreen-exit-alt" : "dashicons-fullscreen-alt");
        button.querySelector(".wp-sjs-pro-fullscreen-toggle__label").textContent = on ? labels.exitFullscreen : labels.fullscreen;
        try {
            window.localStorage.setItem(FULLSCREEN_KEY, on ? "1" : "0");
        } catch (error) {
            return;
        }
    }

    function addFullscreenButton() {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "button wp-sjs-pro-fullscreen-toggle";
        const icon = document.createElement("span");
        icon.className = "dashicons";
        icon.setAttribute("aria-hidden", "true");
        const label = document.createElement("span");
        label.className = "wp-sjs-pro-fullscreen-toggle__label";
        button.append(icon, label);
        button.addEventListener("click", () => setFullscreen(button, !rootElement.classList.contains("wp-sjs-pro-fullscreen")));
        saveButton.before(button);
        setFullscreen(button, readFullscreen());
    }

    addFormProperties();
    if (saveButton) addFullscreenButton();
    document.addEventListener("click", (event) => {
        if (event.target.closest("#wp-sjs-save, .wp-sjs-editor__tabs .nav-tab")) flushToEditor();
    }, true);
    window.addEventListener("beforeunload", flushToEditor, true);
    document.addEventListener("surveyjs:editor-tab", (event) => {
        if (event.detail.tab === options.tab) showCreator();
    });
    if (editor.getActiveTab() === options.tab) showCreator();
})();
