(function ($) {
    $(document).on('click', '.process-deploy', function (e) {
        e.preventDefault();

        var button = $(this);
        var processURL = button.data('url');
        var originalText = button.text(); // store original button text

        button.prop('disabled', true).text('Processing...');
        $('#process-results').html('<p><em>Running process, please wait...</em></p>');

        $.ajax({
            url: processURL,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-SecurityID': $('input[name="SecurityID"]').val()
            },
            success: function (response) {
                if (response.html) {
                    $('#process-results').html(response.html);
                }
            },
            error: function (xhr, status, error) {
                console.log('XHR Response:', xhr.responseText);
                $('#process-results').html(
                    '<div class="alert alert-danger">Error: ' + error + '<br>Check console for details</div>'
                );
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    $(document).on('click', '.process-env-sync', function (e) {
        e.preventDefault();

        var button = $(this);
        var originalText = button.text();
        button.prop('disabled', true).text('Writing ENV files...');
        $('#process-results').html('<p><em>Checking ENV files...</em></p>');

        $.ajax({
            url: button.data('url'),
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-SecurityID': $('input[name="SecurityID"]').val()
            },
            success: function (response) {
                $('#process-results').html(response.html || 'No ENV updates required.');
            },
            error: function (xhr, status, error) {
                var message = (xhr.responseJSON && xhr.responseJSON.error) || error;
                $('#process-results').html(
                    '<div class="alert alert-danger">ENV update failed: ' +
                    $('<div>').text(message).html() + '</div>'
                );
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    $(document).on('click', '.process-farpoint-sync', function (e) {
        e.preventDefault();

        var button = $(this);
        var processURL = button.data('url');
        var originalText = button.text();

        if (!window.confirm(
            'Force Farpoint to match the local uptime-monitor settings? ' +
            'Missing monitors will be created, existing monitors updated, and orphaned Farpoint monitors removed.'
        )) {
            return;
        }

        button.prop('disabled', true).text('Syncing...');
        $('#process-results').html('<p><em>Synchronising Farpoint monitors...</em></p>');

        $.ajax({
            url: processURL,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-SecurityID': $('input[name="SecurityID"]').val()
            },
            success: function (response) {
                if (response.html) {
                    $('#process-results').html(response.html);
                }
            },
            error: function (xhr, status, error) {
                var message = error;
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    message = xhr.responseJSON.error;
                }
                $('#process-results').html(
                    '<div class="alert alert-danger">Farpoint sync failed: ' +
                    $('<div>').text(message).html() +
                    '</div>'
                );
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    $(document).on('click', '.process-gatekeeper-sync', function (e) {
        e.preventDefault();

        var button = $(this);
        var processURL = button.data('url');
        var originalText = button.text();

        if (!window.confirm(
            'Force Gatekeeper to match the live control-panel configuration? ' +
            'Gatekeeper site records which are not represented by live hosts may be removed.'
        )) {
            return;
        }

        button.prop('disabled', true).text('Syncing...');
        $('#process-results').html('<p><em>Synchronising Gatekeeper...</em></p>');

        $.ajax({
            url: processURL,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-SecurityID': $('input[name="SecurityID"]').val()
            },
            success: function (response) {
                if (response.html) {
                    $('#process-results').html(response.html);
                }
            },
            error: function (xhr, status, error) {
                var message = error;
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    message = xhr.responseJSON.error;
                }
                $('#process-results').html(
                    '<div class="alert alert-danger">Gatekeeper sync failed: ' +
                    $('<div>').text(message).html() +
                    '</div>'
                );
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

})(jQuery);
