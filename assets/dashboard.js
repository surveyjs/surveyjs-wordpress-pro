(function () {
    var data = window.surveyjsAddonDashboard;
    var adminTheme = window.surveyjsAddonAdminTheme;
    if (!data || !window.Chart || !adminTheme) {
        return;
    }
    var charts = [];
    var rtl = document.documentElement.dir === "rtl";

    function palette() {
        adminTheme.isDark();
        return {
            bar: adminTheme.color("chart-1"),
            bar2: adminTheme.color("chart-2"),
            grid: adminTheme.color("grid"),
            axis: adminTheme.color("border"),
            text: adminTheme.color("text-2")
        };
    }

    var colors = palette();
    Chart.defaults.font.family = window.getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = colors.text;
    Chart.defaults.plugins.tooltip.rtl = rtl;

    function bars(values, label, color) {
        return {
            label: label || data.i18n.entries,
            data: values,
            colorKey: color || "bar",
            backgroundColor: colors[color || "bar"],
            hoverBackgroundColor: colors[color || "bar"],
            borderRadius: 4,
            borderSkipped: "start",
            maxBarThickness: 28
        };
    }

    function countAxis(extra) {
        return Object.assign({
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: colors.grid },
            border: { display: false }
        }, extra);
    }

    function categoryAxis(extra) {
        return Object.assign({ grid: { display: false }, border: { color: colors.axis } }, extra);
    }

    function recolor(chart) {
        chart.data.datasets.forEach(function (dataset) {
            dataset.backgroundColor = colors[dataset.colorKey];
            dataset.hoverBackgroundColor = colors[dataset.colorKey];
        });
        Object.keys(chart.options.scales).forEach(function (key) {
            var scale = chart.options.scales[key];
            scale.ticks.color = colors.text;
            if (scale.grid.display !== false) {
                scale.grid.color = colors.grid;
            } else {
                scale.border.color = colors.axis;
            }
        });
        chart.update();
    }

    adminTheme.onChange(function () {
        colors = palette();
        Chart.defaults.color = colors.text;
        charts.forEach(recolor);
    });

    var timeline = document.getElementById("wp-sjs-addon-dashboard-timeline");
    if (timeline) {
        charts.push(new Chart(timeline, {
            type: "bar",
            data: { labels: data.timeline.labels, datasets: [bars(data.timeline.values)] },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                return data.timeline.titles[items[0].dataIndex];
                            }
                        }
                    }
                },
                scales: {
                    x: categoryAxis({ reverse: rtl, ticks: { autoSkip: true, maxRotation: 0 } }),
                    y: countAxis({ position: rtl ? "right" : "left" })
                }
            }
        }));
    }

    var views = document.getElementById("wp-sjs-addon-dashboard-views");
    if (views && data.views) {
        charts.push(new Chart(views, {
            type: "bar",
            data: {
                labels: data.timeline.labels,
                datasets: [
                    bars(data.views.views, data.i18n.views),
                    bars(data.views.submissions, data.i18n.submissions, "bar2")
                ]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: true, position: "top", align: "start", rtl: rtl, labels: { boxWidth: 12, boxHeight: 12 } },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                return data.timeline.titles[items[0].dataIndex];
                            },
                            footer: function (items) {
                                return data.i18n.conversion.replace("%s", data.views.conversion[items[0].dataIndex]);
                            }
                        }
                    }
                },
                scales: {
                    x: categoryAxis({ reverse: rtl, ticks: { autoSkip: true, maxRotation: 0 } }),
                    y: countAxis({ position: rtl ? "right" : "left" })
                }
            }
        }));
    }

    var forms = document.getElementById("wp-sjs-addon-dashboard-forms");
    if (forms) {
        var urlAt = function (elements) {
            return elements.length ? data.forms.urls[elements[0].index] : "";
        };
        charts.push(new Chart(forms, {
            type: "bar",
            data: { labels: data.forms.labels, datasets: [bars(data.forms.values)] },
            options: {
                indexAxis: "y",
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false, axis: "y" },
                plugins: { legend: { display: false } },
                scales: {
                    x: countAxis({ reverse: rtl }),
                    y: categoryAxis({
                        position: rtl ? "right" : "left",
                        ticks: {
                            callback: function (value) {
                                var label = this.getLabelForValue(value);
                                return label.length > 32 ? label.slice(0, 31) + "…" : label;
                            }
                        }
                    })
                },
                onHover: function (event, elements) {
                    event.native.target.style.cursor = urlAt(elements) ? "pointer" : "default";
                },
                onClick: function (event, elements) {
                    var url = urlAt(elements);
                    if (url) {
                        window.location.href = url;
                    }
                }
            }
        }));
    }
})();
