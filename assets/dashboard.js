(function () {
    var data = window.surveyjsProDashboard;
    var adminTheme = window.surveyjsProAdminTheme;
    if (!data || !window.Chart || !adminTheme) {
        return;
    }
    var charts = [];
    var rtl = document.documentElement.dir === "rtl";

    function palette() {
        adminTheme.isDark();
        return {
            bar: adminTheme.color("chart-1"),
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

    function bars(values) {
        return {
            label: data.i18n.entries,
            data: values,
            backgroundColor: colors.bar,
            hoverBackgroundColor: colors.bar,
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
            dataset.backgroundColor = colors.bar;
            dataset.hoverBackgroundColor = colors.bar;
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

    var timeline = document.getElementById("wp-sjs-pro-dashboard-timeline");
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

    var forms = document.getElementById("wp-sjs-pro-dashboard-forms");
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
