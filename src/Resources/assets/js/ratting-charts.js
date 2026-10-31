/**
 * Ratting Manager charts.
 *
 * Every chart on the dashboards, the ratter page and the corporation tab is
 * drawn here, so they share one look: the suite's dark chart chrome, the
 * series colours from ratting-manager.css in their fixed order, and ISK in
 * the same short form everywhere. A figure worked out from the corporation's
 * cut is drawn striped in its series colour rather than in a colour of its
 * own, so it reads as the same thing, less certain.
 */
(function (window) {
    'use strict';

    var css = getComputedStyle(document.documentElement);
    function token(name, fallback) {
        var v = css.getPropertyValue(name);
        return v && v.trim() ? v.trim() : fallback;
    }

    var COLORS = {
        s1: token('--rm-series-1', '#3987e5'),
        s2: token('--rm-series-2', '#d95926'),
        s3: token('--rm-series-3', '#199e70'),
        s4: token('--rm-series-4', '#c98500'),
        text: '#c2c7d0',
        grid: 'rgba(255, 255, 255, 0.08)',
        surface: '#161922',
        muted: '#6c757d'
    };

    function formatISK(value) {
        var v = Math.abs(value);
        var sign = value < 0 ? '-' : '';
        if (v >= 1e12) return sign + (v / 1e12).toFixed(2) + 'T';
        if (v >= 1e9) return sign + (v / 1e9).toFixed(2) + 'B';
        if (v >= 1e6) return sign + (v / 1e6).toFixed(1) + 'M';
        if (v >= 1e3) return sign + (v / 1e3).toFixed(1) + 'K';
        return sign + Math.round(v);
    }

    function hatch(color) {
        var c = document.createElement('canvas');
        c.width = c.height = 8;
        var ctx = c.getContext('2d');
        ctx.fillStyle = color + '55';
        ctx.fillRect(0, 0, 8, 8);
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-2, 10); ctx.lineTo(10, -2);
        ctx.moveTo(6, 10); ctx.lineTo(10, 6);
        ctx.moveTo(-2, 2); ctx.lineTo(2, -2);
        ctx.stroke();
        return ctx.createPattern(c, 'repeat');
    }

    function hm(minutes) {
        minutes = Math.round(minutes || 0);
        var h = Math.floor(minutes / 60);
        var m = minutes % 60;
        return h > 0 ? h + 'h ' + (m < 10 ? '0' : '') + m + 'm' : m + 'm';
    }

    function floats(arr) {
        return (arr || []).map(function (v) { return parseFloat(v) || 0; });
    }

    /**
     * Charts are drawn when their canvas first comes into view, not all at
     * once on page load: the dashboards hold a dozen or more, and drawing
     * every one up front kept the browser busy for seconds. A canvas in a
     * tab that is not open has no size, so it is drawn when the tab opens.
     * Browsers without IntersectionObserver draw everything straight away.
     */
    var observer = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            draw(entry.target);
        });
    }, { rootMargin: '200px 0px' });

    function draw(el) {
        var config = el._rmPending;
        if (!config) return;
        el._rmPending = null;
        if (el._rmChart) el._rmChart.destroy();
        el._rmChart = new Chart(el, config);
    }

    function make(id, config) {
        var el = document.getElementById(id);
        if (!el || typeof Chart === 'undefined') return null;
        if (el._rmChart) {
            el._rmChart.destroy();
            el._rmChart = null;
        }
        el._rmPending = config;
        if (observer) {
            observer.unobserve(el);
            observer.observe(el);
        } else {
            draw(el);
        }
        return el;
    }

    function iskScale(stacked) {
        return {
            stacked: !!stacked,
            beginAtZero: true,
            ticks: { color: COLORS.text, callback: function (v) { return formatISK(v); } },
            grid: { color: COLORS.grid }
        };
    }

    function categoryScale(stacked) {
        return { stacked: !!stacked, ticks: { color: COLORS.text, maxRotation: 0, autoSkip: true }, grid: { display: false } };
    }

    function iskTooltip() {
        return {
            callbacks: {
                label: function (ctx) {
                    var v = ctx.parsed.y !== undefined && ctx.chart.options.indexAxis !== 'y' ? ctx.parsed.y : ctx.parsed.x;
                    return ' ' + ctx.dataset.label + ': ' + formatISK(v) + ' ISK';
                }
            }
        };
    }

    function legend(show) {
        return { display: show, labels: { color: COLORS.text, boxWidth: 12, usePointStyle: false } };
    }

    function bar(label, data, color, opts) {
        return Object.assign({
            label: label,
            data: floats(data),
            backgroundColor: color,
            borderColor: COLORS.surface,
            borderWidth: { top: 2 },
            borderRadius: 3,
            barPercentage: 0.7,
            categoryPercentage: 0.8
        }, opts || {});
    }

    var R = {
        formatISK: formatISK,

        /** Income per month: read and worked out, bounties and ESS, optionally the corporation's cut as a line. */
        incomeMonthly: function (id, d, t) {
            var sets = [
                bar(t.series_bounty, d.bounty, COLORS.s1, { stack: 'income' }),
                bar(t.series_bounty_estimated, d.bounty_estimated, hatch(COLORS.s1), { stack: 'income' }),
                bar(t.series_ess, d.ess, COLORS.s2, { stack: 'income' }),
                bar(t.series_ess_estimated, d.ess_estimated, hatch(COLORS.s2), { stack: 'income' })
            ];
            if (d.corp_tax) {
                sets.push({
                    type: 'line', label: t.series_corp_tax, data: floats(d.corp_tax), borderColor: COLORS.s3,
                    backgroundColor: COLORS.s3, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: 0.25
                });
            }
            return make(id, {
                type: 'bar',
                data: { labels: d.labels, datasets: sets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { x: categoryScale(true), y: iskScale(true) },
                    plugins: { legend: legend(true), tooltip: iskTooltip() }
                }
            });
        },

        /** Income per day with its seven-day average. */
        dailyIncome: function (id, d, t) {
            return make(id, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [
                        { label: t.series_income, data: floats(d.income), borderColor: COLORS.s1, backgroundColor: COLORS.s1 + '33', fill: true, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.2 },
                        { label: t.seven_day_average, data: floats(d.average), borderColor: COLORS.muted, borderDash: [5, 4], borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: false, tension: 0.2 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { x: categoryScale(false), y: iskScale(false) },
                    plugins: { legend: legend(true), tooltip: iskTooltip() }
                }
            });
        },

        /** One measure across named things, longest bar first. */
        horizontalBar: function (id, labels, values, seriesLabel, isk) {
            return make(id, {
                type: 'bar',
                data: { labels: labels, datasets: [bar(seriesLabel, values, COLORS.s1, { borderWidth: 0, barPercentage: 0.75 })] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    scales: {
                        x: isk ? iskScale(false) : { beginAtZero: true, ticks: { color: COLORS.text, precision: 0 }, grid: { color: COLORS.grid } },
                        y: { ticks: { color: COLORS.text }, grid: { display: false } }
                    },
                    plugins: {
                        legend: legend(false),
                        tooltip: isk ? iskTooltip() : { callbacks: { label: function (ctx) { return ' ' + seriesLabel + ': ' + ctx.parsed.x.toLocaleString(); } } }
                    }
                }
            });
        },

        /**
         * Each session's bounty ISK per hour in time order, as a line over a
         * shaded area, with the period's median dashed across it. Points show
         * on hover; a lone session keeps its point so it can be seen at all.
         */
        sessions: function (id, points, t) {
            points = (points || []).slice().sort(function (a, b) { return a.x - b.x; });
            var sorted = points.map(function (p) { return p.y; }).sort(function (a, b) { return a - b; });
            var n = sorted.length;
            var median = n ? (n % 2 ? sorted[(n - 1) / 2] : (sorted[n / 2 - 1] + sorted[n / 2]) / 2) : 0;
            var first = n ? points[0].x : 0;
            var last = n ? points[n - 1].x : 0;

            return make(id, {
                type: 'line',
                data: {
                    datasets: [{
                        label: t.series_session_rate,
                        data: points.map(function (p) { return { x: p.x, y: p.y, minutes: p.minutes }; }),
                        borderColor: COLORS.s1,
                        backgroundColor: COLORS.s1 + '33',
                        fill: 'origin',
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: n < 2 ? 4 : 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 8,
                        pointBackgroundColor: COLORS.s1
                    }, {
                        label: t.series_median,
                        data: [{ x: first, y: median }, { x: last, y: median }],
                        borderColor: COLORS.muted,
                        borderDash: [5, 4],
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointHitRadius: 0,
                        fill: false
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    scales: {
                        x: {
                            type: 'linear',
                            ticks: { color: COLORS.text, maxTicksLimit: 6, callback: function (v) { return new Date(v).toISOString().slice(5, 10); } },
                            grid: { color: COLORS.grid }
                        },
                        y: iskScale(false)
                    },
                    plugins: {
                        legend: legend(true),
                        tooltip: {
                            filter: function (item) { return item.datasetIndex === 0; },
                            callbacks: {
                                title: function (items) {
                                    return items.length ? new Date(items[0].raw.x).toISOString().replace('T', ' ').slice(0, 16) + ' EVE' : '';
                                },
                                label: function (ctx) {
                                    return [
                                        ' ' + t.series_session_rate + ': ' + formatISK(ctx.raw.y) + ' ISK',
                                        ' ' + hm(ctx.raw.minutes),
                                        ' ' + t.series_median + ': ' + formatISK(median) + ' ISK'
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        },

        /** Part of a whole in at most four parts, the first four series colours in order. */
        doughnut: function (id, labels, values) {
            return make(id, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: floats(values), backgroundColor: [COLORS.s1, COLORS.s2, COLORS.s3, COLORS.s4], borderColor: COLORS.surface, borderWidth: 2 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '60%',
                    plugins: {
                        legend: { position: 'right', labels: { color: COLORS.text, boxWidth: 12 } },
                        tooltip: { callbacks: { label: function (ctx) {
                            var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0) || 1;
                            return ' ' + ctx.label + ': ' + formatISK(ctx.parsed) + ' ISK (' + Math.round(ctx.parsed / total * 100) + '%)';
                        } } }
                    }
                }
            });
        },

        /** Players and characters per month. */
        activeRatters: function (id, d, t) {
            return make(id, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [
                        { label: t.series_players, data: d.players, borderColor: COLORS.s1, backgroundColor: COLORS.s1, borderWidth: 2, pointRadius: 3, tension: 0.2 },
                        { label: t.series_characters, data: d.characters, borderColor: COLORS.s2, backgroundColor: COLORS.s2, borderWidth: 2, pointRadius: 3, tension: 0.2 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { x: categoryScale(false), y: { beginAtZero: true, ticks: { color: COLORS.text, precision: 0 }, grid: { color: COLORS.grid } } },
                    plugins: { legend: legend(true) }
                }
            });
        },

        /** New, returning and continuing players above the line, those who stopped below it. */
        movement: function (id, d, t) {
            return make(id, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        bar(t.series_continuing, d.continuing, COLORS.s1, { stack: 'm' }),
                        bar(t.series_new, d['new'], COLORS.s2, { stack: 'm' }),
                        bar(t.series_returning, d.returning, COLORS.s3, { stack: 'm' }),
                        bar(t.series_lapsed, (d.lapsed || []).map(function (v) { return -v; }), COLORS.s4, { stack: 'm', borderWidth: { bottom: 2 } })
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: categoryScale(true),
                        y: { stacked: true, ticks: { color: COLORS.text, precision: 0, callback: function (v) { return Math.abs(v); } }, grid: { color: COLORS.grid } }
                    },
                    plugins: {
                        legend: legend(true),
                        tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + Math.abs(ctx.parsed.y); } } }
                    }
                }
            });
        },

        /**
         * Time in each slot of the week or the day: hours ratted by default,
         * or another count through opts.value. The tooltip adds the slot's
         * bounties and their ISK per hour.
         */
        timeBars: function (id, rows, t, opts) {
            opts = opts || {};
            rows = rows || [];
            var i = t.insights || {};
            var valueOf = opts.value || function (r) { return r.minutes / 60; };
            return make(id, {
                type: 'bar',
                data: {
                    labels: rows.map(opts.label || function (r) { return r.label; }),
                    datasets: [bar(opts.series || i.chart_hours, rows.map(valueOf), COLORS.s1, { borderWidth: 0, barPercentage: 0.85, categoryPercentage: 0.9 })]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        x: categoryScale(false),
                        y: {
                            beginAtZero: true,
                            ticks: { color: COLORS.text, callback: opts.tick || function (v) { return hm(v * 60); } },
                            grid: { color: COLORS.grid }
                        }
                    },
                    plugins: {
                        legend: legend(false),
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var r = rows[ctx.dataIndex];
                                    var lines = opts.extra ? opts.extra(r) : [];
                                    lines.push(' ' + i.chart_hours + ': ' + hm(r.minutes));
                                    lines.push(' ' + i.chart_bounty + ': ' + formatISK(r.bounty) + ' ISK');
                                    if (r.minutes > 0) {
                                        lines.push(' ' + i.chart_bounty_rate + ': ' + formatISK(r.isk_per_hour) + ' ISK');
                                    }
                                    return lines;
                                }
                            }
                        }
                    }
                }
            });
        },

        /** ISK per hour in each of the most-ratted systems, best paying first. */
        efficiency: function (id, rows, t) {
            var i = t.insights || {};
            rows = (rows || []).filter(function (r) { return r.minutes > 0; })
                .sort(function (a, b) { return b.isk_per_hour - a.isk_per_hour; });
            return make(id, {
                type: 'bar',
                data: {
                    labels: rows.map(function (r) { return r.name; }),
                    datasets: [bar(i.chart_rate, rows.map(function (r) { return r.isk_per_hour; }), COLORS.s1, { borderWidth: 0, barPercentage: 0.75 })]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    scales: { x: iskScale(false), y: { ticks: { color: COLORS.text }, grid: { display: false } } },
                    plugins: {
                        legend: legend(false),
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var r = rows[ctx.dataIndex];
                                    return [
                                        ' ' + i.chart_rate + ': ' + formatISK(r.isk_per_hour) + ' ISK',
                                        ' ' + i.chart_hours + ': ' + hm(r.minutes),
                                        ' ' + i.chart_income + ': ' + formatISK(r.income) + ' ISK'
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        },

        /** What the players received each month beside what landed in the corporation's wallet, on one ISK axis. */
        earnedAndTaxed: function (id, rows, t) {
            var i = t.insights || {};
            rows = rows || [];
            var tooltip = iskTooltip();
            tooltip.callbacks.footer = function (items) {
                var r = rows[items[0].dataIndex];
                return r.tax_share === null ? '' : i.tax_rate + ': ' + r.tax_share + '%';
            };
            return make(id, {
                type: 'bar',
                data: {
                    labels: rows.map(function (r) { return r.label; }),
                    datasets: [
                        bar(i.earned, rows.map(function (r) { return r.income; }), COLORS.s1),
                        bar(i.tax_landed, rows.map(function (r) { return r.corp_tax; }), COLORS.s3)
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { x: categoryScale(false), y: iskScale(false) },
                    plugins: { legend: legend(true), tooltip: tooltip }
                }
            });
        },

        personal: function (d, t) {
            if (!d) return;
            R.incomeMonthly('rm-chart-monthly', d.monthly, t);
            R.dailyIncome('rm-chart-daily', d.daily, t);
            R.horizontalBar('rm-chart-systems', d.systems.map(function (s) { return s.name; }), d.systems.map(function (s) { return s.income; }), t.series_income, true);
            R.horizontalBar('rm-chart-factions', d.kills.factions.labels, d.kills.factions.values, t.rats, false);
            R.horizontalBar('rm-chart-sizes', d.kills.sizes.labels, d.kills.sizes.values, t.rats, false);
            R.sessions('rm-chart-sessions', d.sessions, t);
            if (d.characters && d.characters.labels.length > 1) {
                R.horizontalBar('rm-chart-characters', d.characters.labels, d.characters.values, t.series_income, true);
            }
            R.timeBars('rm-chart-weekdays', d.weekdays, t, { label: function (r) { return r.short; } });
            R.timeBars('rm-chart-hours', d.hours, t, { label: function (r) { return r.label.slice(0, 2); } });
            R.efficiency('rm-chart-efficiency', d.efficiency, t);
        },

        corporation: function (d, t) {
            if (!d) return;
            R.incomeMonthly('rm-corp-monthly', d.monthly, t);
            R.dailyIncome('rm-corp-daily', d.daily, t);
            R.horizontalBar('rm-corp-systems', d.systems.map(function (s) { return s.name; }), d.systems.map(function (s) { return s.income; }), t.series_income, true);
            R.horizontalBar('rm-corp-factions', d.kills.factions.labels, d.kills.factions.values, t.rats, false);
            R.horizontalBar('rm-corp-sizes', d.kills.sizes.labels, d.kills.sizes.values, t.rats, false);
            R.activeRatters('rm-corp-active', d.movement, t);
            R.movement('rm-corp-movement', d.movement, t);
            R.doughnut('rm-corp-concentration', d.concentration.labels, d.concentration.values);

            var i = t.insights || {};
            R.timeBars('rm-corp-weekdays', d.weekdays, t, {
                label: function (r) { return r.short; },
                extra: function (r) { return [' ' + i.chart_players_per_day + ': ' + r.players_per_day]; }
            });
            R.timeBars('rm-corp-hours', d.hours, t, {
                label: function (r) { return r.label.slice(0, 2); },
                value: function (r) { return r.player_hours; },
                series: i.chart_player_hours,
                tick: function (v) { return Number.isInteger(v) ? v : ''; },
                extra: function (r) { return [' ' + i.chart_player_hours + ': ' + r.player_hours]; }
            });
            R.efficiency('rm-corp-efficiency', d.efficiency, t);
            R.earnedAndTaxed('rm-corp-months', d.months, t);
        }
    };

    if (typeof Chart !== 'undefined') {
        Chart.defaults.color = COLORS.text;
        Chart.defaults.borderColor = COLORS.grid;
        Chart.defaults.font.family = "'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    }

    window.RattingCharts = R;
})(window);
