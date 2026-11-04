(function () {
    var data = window.surveyjsProDashboard;
    if (!data || !window.Chart) {
        return;
    }
    var styles = window.getComputedStyle(document.body);
    var color = styles.getPropertyValue("--wp-admin-theme-color").trim() || "#2271b1";
    var gridColor = "#f0f0f1";

    Chart.defaults.font.family = styles.fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = "#50575e";

    function bars(values) {
        return {
            label: data.i18n.entries,
            data: values,
            backgroundColor: color,
            hoverBackgroundColor: color,
            borderRadius: 4,
            borderSkipped: "start",
            maxBarThickness: 28
        };
    }

    function countAxis() {
        return {
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: gridColor },
            border: { display: false }
        };
    }

    function categoryAxis(extra) {
        return Object.assign({ grid: { display: false }, border: { color: "#c3c4c7" } }, extra);
    }

    var timeline = document.getElementById("wp-sjs-pro-dashboard-timeline");
    if (timeline) {
        new Chart(timeline, {
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
                    x: categoryAxis({ ticks: { autoSkip: true, maxRotation: 0 } }),
                    y: countAxis()
                }
            }
        });
    }

    var forms = document.getElementById("wp-sjs-pro-dashboard-forms");
    if (forms) {
        var urlAt = function (elements) {
            return elements.length ? data.forms.urls[elements[0].index] : "";
        };
        new Chart(forms, {
            type: "bar",
            data: { labels: data.forms.labels, datasets: [bars(data.forms.values)] },
            options: {
                indexAxis: "y",
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false, axis: "y" },
                plugins: { legend: { display: false } },
                scales: {
                    x: countAxis(),
                    y: categoryAxis({
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
        });
    }
})();
