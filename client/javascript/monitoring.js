(function ($) {
    var refreshTimer = null;
    var searchTimer = null;

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function formatPercent(value) {
        if (value === null || typeof value === 'undefined') {
            return '—';
        }
        return Number(value).toFixed(Number(value) === 100 ? 0 : 2) + '%';
    }

    function formatResponseTime(value) {
        if (value === null || typeof value === 'undefined') {
            return '—';
        }
        return value >= 1000
            ? (value / 1000).toFixed(2) + 's'
            : value + 'ms';
    }

    function formatLastCheck(timestamp) {
        if (!timestamp) {
            return 'Never';
        }

        var seconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
        if (seconds < 60) {
            return seconds + 's ago';
        }

        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) {
            return minutes + 'm ago';
        }

        var hours = Math.floor(minutes / 60);
        if (hours < 48) {
            return hours + 'h ago';
        }

        return Math.floor(hours / 24) + 'd ago';
    }

    function stateBadge(state) {
        var className = 'bg-secondary';

        if (state === 'UP') {
            className = 'bg-success';
        } else if (state === 'DEGRADED') {
            className = 'bg-warning text-dark';
        } else if (state === 'DOWN') {
            className = 'bg-danger';
        }

        return '<span class="badge ' + className + '">' +
            escapeHtml(state) +
            '</span>';
    }

    function summaryCard(label, value, modifier) {
        var className = modifier ? ' border-' + modifier : '';
        return '<div class="col-sm-6 col-lg-3 mb-3">' +
            '<div class="card h-100' + className + '">' +
            '<div class="card-body">' +
            '<div class="text-muted small">' + escapeHtml(label) + '</div>' +
            '<div class="h3 mb-0">' + escapeHtml(value) + '</div>' +
            '</div></div></div>';
    }

    function renderDashboard(data) {
        var summary = data.summary || {};

        $('#farpoint-summary').html(
            summaryCard('Total', summary.total || 0) +
            summaryCard('Up', summary.up || 0, 'success') +
            summaryCard('Degraded', summary.degraded || 0, 'warning') +
            summaryCard('Down', summary.down || 0, 'danger')
        );

        var monitors = data.monitors || [];
        if (!monitors.length) {
            $('#farpoint-monitor-rows').html(
                '<tr><td colspan="7" class="text-muted">No Farpoint monitors configured.</td></tr>'
            );
        } else {
            var rows = monitors.map(function (monitor) {
                var availability = monitor.availability || {};
                var h24 = availability['24h'] || {};
                var d7 = availability['7d'] || {};
                var d30 = availability['30d'] || {};

                return '<tr>' +
                    '<td><strong>' + escapeHtml(monitor.name) + '</strong>' +
                    '<div class="small text-muted">' + escapeHtml(monitor.url) + '</div></td>' +
                    '<td>' + stateBadge(monitor.state) + '</td>' +
                    '<td>' + escapeHtml(formatResponseTime(monitor.response_time_ms)) + '</td>' +
                    '<td>' + escapeHtml(formatLastCheck(monitor.last_checked_at)) + '</td>' +
                    '<td>' + escapeHtml(formatPercent(h24.uptime_percent)) + '</td>' +
                    '<td>' + escapeHtml(formatPercent(d7.uptime_percent)) + '</td>' +
                    '<td>' + escapeHtml(formatPercent(d30.uptime_percent)) + '</td>' +
                    '</tr>';
            });

            $('#farpoint-monitor-rows').html(rows.join(''));
        }

        $('#farpoint-service-status')
            .removeClass('alert-danger alert-warning')
            .addClass('alert-info')
            .text(
                'Farpoint connected. Last refreshed ' +
                new Date(data.generated_at || Date.now()).toLocaleTimeString()
            );
    }

    function loadDashboard() {
        var form = $('#Form_EditForm');
        var url = form.data('dashboard-url');

        if (!url) {
            return;
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            data: {
                state: $('#farpoint-state-filter').val() || '',
                search: $('#farpoint-search').val() || '',
                sort: $('#farpoint-sort').val() || 'name',
                direction: $('#farpoint-direction').val() || 'asc',
                per_page: 100
            },
            success: renderDashboard,
            error: function (xhr) {
                var message = 'Unable to load Farpoint monitoring data.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    message += ' ' + xhr.responseJSON.error;
                }

                $('#farpoint-service-status')
                    .removeClass('alert-info alert-warning')
                    .addClass('alert-danger')
                    .text(message);
            }
        });
    }

    $(document).on('click', '.farpoint-control', function (e) {
        e.preventDefault();

        var button = $(this);
        var url = button.data('url');
        var isPause = button.attr('id') === 'farpoint-pause';

        if (isPause && !window.confirm(
            'Pause all Farpoint monitoring? No monitored websites will be checked until monitoring is resumed.'
        )) {
            return;
        }

        $('.farpoint-control').prop('disabled', true);
        $('#farpoint-service-status')
            .removeClass('alert-danger alert-warning')
            .addClass('alert-info')
            .text(isPause ? 'Pausing Farpoint monitoring…' : 'Resuming Farpoint monitoring…');

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-SecurityID': $('input[name="SecurityID"]').val()
            },
            success: function (response) {
                var failed = response.failed || 0;
                var message = isPause
                    ? 'Farpoint monitoring paused.'
                    : 'Farpoint monitoring resumed.';

                message += ' ' + (response.succeeded || 0) +
                    '/' + (response.total || 0) + ' monitors updated.';

                $('#farpoint-service-status')
                    .removeClass('alert-info alert-danger')
                    .addClass(failed ? 'alert-warning' : 'alert-success')
                    .text(message);

                loadDashboard();
            },
            error: function (xhr) {
                var message = 'Farpoint control action failed.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    message += ' ' + xhr.responseJSON.error;
                }

                $('#farpoint-service-status')
                    .removeClass('alert-info')
                    .addClass('alert-danger')
                    .text(message);
            },
            complete: function () {
                $('.farpoint-control').prop('disabled', false);
            }
        });
    });

    $(document).on(
        'change',
        '#farpoint-state-filter, #farpoint-sort, #farpoint-direction',
        function () {
            loadDashboard();
        }
    );

    $(document).on('input', '#farpoint-search', function () {
        if (searchTimer) {
            window.clearTimeout(searchTimer);
        }

        searchTimer = window.setTimeout(loadDashboard, 250);
    });

    $(document).on('click', '#Menu-DorsetDigital-Caddy-Admin-MonitoringAdmin', function () {
        window.setTimeout(loadDashboard, 100);
    });

    $(function () {
        loadDashboard();

        if (refreshTimer) {
            window.clearInterval(refreshTimer);
        }

        refreshTimer = window.setInterval(loadDashboard, 10000);
    });
})(jQuery);
