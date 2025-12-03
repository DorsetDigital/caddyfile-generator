(function($) {
    $(document).on('click', '.process-deploy', function(e) {
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
            success: function(response) {
                if (response.html) {
                    $('#process-results').html(response.html);
                }
            },
            error: function(xhr, status, error) {
                console.log('XHR Response:', xhr.responseText);
                $('#process-results').html(
                    '<div class="alert alert-danger">Error: ' + error + '<br>Check console for details</div>'
                );
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
})(jQuery);
