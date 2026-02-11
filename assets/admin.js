jQuery(function ($) {

    let previewRun = false;
    let currentSearch = '';
    let currentReplace = '';
    let caseSensitive = false;

    // Select All Tables
    $('#ssr-select-all').on('click', function () {
        $('#ssr-table-selector option').prop('selected', true);
    });

    // Deselect All Tables
    $('#ssr-deselect-all').on('click', function () {
        $('#ssr-table-selector option').prop('selected', false);
    });

    // Select Safe Tables Only (exclude critical tables)
    $('#ssr-select-safe').on('click', function () {
        $('#ssr-table-selector option').each(function () {
            let isCritical = $(this).data('critical') === 1;
            $(this).prop('selected', !isCritical);
        });
    });

    // Enable run button only after preview
    function enableRunButton() {
        $('#ssr-run').prop('disabled', false);
        $('#ssr-preview-required').hide();
        previewRun = true;
    }

    // Disable run button when form changes
    $('#ssr-form input, #ssr-form select').on('change', function () {
        if (previewRun) {
            $('#ssr-run').prop('disabled', true);
            $('#ssr-preview-required').show().text('← Preview required (form was changed)');
            previewRun = false;
        }
    });

    /**
     * Highlight the search term in text
     */
    function highlightChanges(text, search, replacement, isBefore, isCaseSensitive) {
        if (!text || !search) return escapeHtml(text);

        let escapedText = escapeHtml(text);
        let escapedSearch = escapeRegex(search);
        let flags = isCaseSensitive ? 'g' : 'gi';
        let regex = new RegExp(escapedSearch, flags);

        if (isBefore) {
            // Highlight what's being removed (red)
            return escapedText.replace(regex, function(match) {
                return '<mark class="ssr-highlight-remove">' + match + '</mark>';
            });
        } else {
            // Highlight what's being added (green)
            if (replacement) {
                let escapedReplacement = escapeRegex(replacement);
                let replaceRegex = new RegExp(escapedReplacement, flags);
                return escapedText.replace(replaceRegex, function(match) {
                    return '<mark class="ssr-highlight-add">' + match + '</mark>';
                });
            }
            return escapedText;
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        let div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Escape special regex characters
     */
    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function runAction(action) {

        let formData = $('#ssr-form').serialiseArray();
        formData.push({ name: 'action', value: action });
        formData.push({ name: 'nonce', value: SSR_Ajax.nonce });

        // Validate tables are selected
        if ($('#ssr-table-selector').val().length === 0) {
            alert('Please select at least one table.');
            return;
        }

        // Validate search field
        if (!$('#ssr-search').val()) {
            alert('Please enter a search term.');
            return;
        }

        // Store current search/replace values for highlighting
        currentSearch = $('#ssr-search').val();
        currentReplace = $('#ssr-replace').val();
        caseSensitive = $('input[name="case_sensitive"]').is(':checked');

        $('#ssr-results').html('<p><span class="dashicons dashicons-update-alt"></span> Processing...</p>');

        $.post(SSR_Ajax.ajax_url, formData, function (response) {

            if (!response.success) {
                $('#ssr-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                return;
            }

            if (response.data.length === 0) {
                $('#ssr-results').html('<div class="notice notice-info"><p>No matches found.</p></div>');
                return;
            }

            let html = '<h2>Results (' + response.data.length + ' changes found)</h2>';

            if (action === 'ssr_preview') {
                html += '<div class="notice notice-info"><p>This is a preview only. No changes have been made to the database.</p></div>';
            } else {
                html += '<div class="notice notice-success"><p><strong>✓ Replacement completed successfully!</strong> ' + response.data.length + ' changes were made.</p></div>';
            }

            response.data.forEach(function (item) {
                
                // Apply highlighting to show exactly what's changing
                let highlightedBefore = highlightChanges(item.original, currentSearch, currentReplace, true, caseSensitive);
                let highlightedAfter = highlightChanges(item.new, currentSearch, currentReplace, false, caseSensitive);
                
                html += `
                <div class="ssr-diff">
                    <strong>Table:</strong> ${item.table} | 
                    <strong>Column:</strong> ${item.column} | 
                    <strong>ID:</strong> ${item.primary}
                    <div class="ssr-label">Before:</div>
                    <div class="ssr-before">${highlightedBefore}</div>
                    <div class="ssr-label">After:</div>
                    <div class="ssr-after">${highlightedAfter}</div>
                </div>
                `;
            });

            $('#ssr-results').html(html);

            // Enable run button after successful preview
            if (action === 'ssr_preview') {
                enableRunButton();
            }
        }).fail(function () {
            $('#ssr-results').html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
        });
    }

    $('#ssr-preview').on('click', function () {
        runAction('ssr_preview');
    });

    $('#ssr-run').on('click', function () {

        let selectedTables = $('#ssr-table-selector').val();
        let hasCritical = false;

        $('#ssr-table-selector option:selected').each(function () {
            if ($(this).data('critical') === 1) {
                hasCritical = true;
            }
        });

        let confirmMsg = 'Are you sure you want to run this replacement?\n\n';
        confirmMsg += '⚠️ This will PERMANENTLY modify your database!\n';
        confirmMsg += '⚠️ This action CANNOT be undone!\n\n';

        if (hasCritical) {
            confirmMsg += '⚠️⚠️⚠️ WARNING: You have selected CRITICAL SYSTEM TABLES!\n';
            confirmMsg += 'Modifying these tables can break your entire site!\n\n';
        }

        confirmMsg += 'Tables selected: ' + selectedTables.length + '\n';
        confirmMsg += 'Have you backed up your database?\n\n';
        confirmMsg += 'Type YES to confirm:';

        let confirmation = prompt(confirmMsg);

        if (confirmation !== 'YES') {
            alert('Replacement cancelled. You must type YES to proceed.');
            return;
        }

        runAction('ssr_replace');
    });

});