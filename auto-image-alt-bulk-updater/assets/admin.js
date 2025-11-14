jQuery(function ($) {
    var total = parseInt(aiabuSettings.total, 10) || 0;
    var batch = parseInt(aiabuSettings.batchSize, 10) || 20;
    var offset = 0;
    var processed = 0;
    var running = false;

    function updateProgress() {
        var percent = 0;
        if (total > 0) {
            percent = Math.round((processed / total) * 100);
            if (percent > 100) {
                percent = 100;
            }
        }

        $('#aiabu-progress-text').text(processed + ' / ' + total + ' images updated (' + percent + '%)');
        $('#aiabu-progress-fill').css('width', percent + '%');
    }

    function processBatch() {
        if (!running) {
            return;
        }

        $.post(
            aiabuSettings.ajaxUrl,
            {
                action: 'aiabu_bulk_update',
                nonce: aiabuSettings.nonce,
                offset: offset,
                batch: batch
            }
        )
            .done(function (resp) {
                if (!resp || !resp.success) {
                    running = false;
                    $('#aiabu-status').text('Error while processing. Please check the console and try again.');
                    console.log('Bulk update error', resp);
                    $('#aiabu-start').prop('disabled', false);
                    return;
                }

                var data = resp.data || {};
                var processedThis = parseInt(data.processed || 0, 10);
                processed += processedThis;
                offset = parseInt(data.nextOffset || offset, 10);
                total = parseInt(data.total || total, 10);

                updateProgress();

                if (data.done) {
                    running = false;
                    $('#aiabu-status').text('Completed. All images processed.');
                    $('#aiabu-start').prop('disabled', false);
                } else {
                    $('#aiabu-status').text('Working, please keep this page open until it finishes...');
                    processBatch();
                }
            })
            .fail(function () {
                running = false;
                $('#aiabu-status').text('Request failed. Please reload the page and try again.');
                $('#aiabu-start').prop('disabled', false);
            });
    }

    $('#aiabu-start').on('click', function (e) {
        e.preventDefault();

        if (running) {
            return;
        }

        running = true;
        offset = 0;
        processed = 0;

        updateProgress();
        $('#aiabu-status').text('Starting bulk update...');
        $('#aiabu-start').prop('disabled', true);

        processBatch();
    });

    // Initial progress state.
    updateProgress();
});
