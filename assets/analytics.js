(function () {
    var config = window.surveyjsProAnalytics;
    var root = document.getElementById("wp-sjs-pro-analytics");
    if (!config || !root) {
        return;
    }
    var message = root.querySelector(".wp-sjs-pro-analytics__message");
    var progress = root.querySelector(".wp-sjs-pro-analytics__progress");
    var notice = root.querySelector(".wp-sjs-pro-analytics__notice");
    var container = root.querySelector(".wp-sjs-pro-analytics__container");
    var stateKey = "surveyjs-pro-" + config.view + "-" + config.formId;

    function format(text, values) {
        return text.replace(/%(\d+)\$s/g, function (match, index) {
            return values[index - 1];
        });
    }

    function number(value) {
        return Number(value).toLocaleString();
    }

    function showMessage(text) {
        message.textContent = text;
        message.hidden = !text;
    }

    function readState() {
        try {
            return JSON.parse(window.localStorage.getItem(stateKey) || "null");
        } catch (e) {
            return null;
        }
    }

    function writeState(state) {
        try {
            window.localStorage.setItem(stateKey, JSON.stringify(state));
        } catch (e) {
        }
    }

    function persistState(visualizer) {
        var state = readState();
        if (state) {
            try {
                visualizer.state = state;
            } catch (e) {
                writeState(null);
            }
        }
        visualizer.onStateChanged.add(function (_, newState) {
            writeState(newState);
        });
    }

    function fetchPage(offset, limit) {
        return fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: new URLSearchParams({ action: config.action, form: config.formId, offset: offset, limit: limit, _ajax_nonce: config.nonce })
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (!result || !result.success || !Array.isArray(result.data)) {
                throw new Error("Failed to load entries");
            }
            return result.data;
        });
    }

    function loadEntries(target) {
        var entries = [];
        progress.max = target;
        progress.hidden = false;
        function next() {
            progress.value = entries.length;
            showMessage(format(config.i18n.loading, [number(entries.length), number(target)]));
            if (entries.length >= target) {
                return Promise.resolve(entries);
            }
            return fetchPage(entries.length, Math.min(config.pageSize, target - entries.length)).then(function (page) {
                Array.prototype.push.apply(entries, page);
                return page.length ? next() : entries;
            });
        }
        return next();
    }

    function rows(entries, dateKey) {
        return entries.map(function (entry) {
            var row = Object.assign({}, entry.data);
            row[config.dateField] = entry[dateKey];
            return row;
        });
    }

    function renderCharts(survey, entries) {
        if (window.Chart && window.ChartDataLabels) {
            window.Chart.register(window.ChartDataLabels);
        }
        var dashboard = new SurveyAnalytics.Dashboard({
            survey: survey,
            questions: survey.getAllQuestions(),
            data: rows(entries, "date"),
            dateFieldName: config.dateField
        });
        dashboard.locale = survey.locale;
        persistState(dashboard);
        dashboard.render(container);
    }


    var survey = new Survey.Model(config.survey);
    if (config.locale && config.locale !== "en") {
        survey.locale = config.locale;
    }
    survey.onTextMarkdown.add(function (_, options) {
        var text = options.text.replace(/\[([^\]]+)\]\((https?:\/\/[^\s()]+)\)/g, "$1");
        if (text !== options.text) options.html = text;
    });
    if (!survey.getAllQuestions().length) {
        showMessage(config.i18n.noQuestions);
        return;
    }
    if (!config.total) {
        showMessage(config.i18n.noEntries);
        return;
    }
    var target = config.maxEntries ? Math.min(config.total, config.maxEntries) : config.total;
    loadEntries(target).then(function (entries) {
        progress.hidden = true;
        if (!entries.length) {
            showMessage(config.i18n.noEntries);
            return;
        }
        showMessage("");
        if (config.total > entries.length) {
            notice.querySelector("p").textContent = format(config.i18n.limited, [number(entries.length), number(config.total)]);
            notice.hidden = false;
        }
        renderCharts(survey, entries);
    }).catch(function () {
        progress.hidden = true;
        showMessage(config.i18n.error);
    });
})();
