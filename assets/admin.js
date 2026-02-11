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
            $('#ssr-preview-required').show().text(SSR_Ajax.preview_required);
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
            return escapedText.replace(regex, function(match) {
                return '<mark class="ssr-highlight-remove">' + match + '</mark>';
            });
        } else {
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

        let formData = $('#ssr-form').serializeArray();
        formData.push({ name: 'action', value: action });
        formData.push({ name: 'nonce', value: SSR_Ajax.nonce });

        // Validate tables are selected
        if ($('#ssr-table-selector').val().length === 0) {
            alert(SSR_Ajax.select_table_error);
            return;
        }

        // Validate search field
        if (!$('#ssr-search').val()) {
            alert(SSR_Ajax.enter_search_error);
            return;
        }

        // Store current search/replace values for highlighting
        currentSearch = $('#ssr-search').val();
        currentReplace = $('#ssr-replace').val();
        caseSensitive = $('input[name="case_sensitive"]').is(':checked');

        $('#ssr-results').html('<p><span class="dashicons dashicons-update-alt"></span> ' + SSR_Ajax.processing + '</p>');

        $.post(SSR_Ajax.ajax_url, formData, function (response) {

            if (!response.success) {
                $('#ssr-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                return;
            }

            if (response.data.length === 0) {
                $('#ssr-results').html('<div class="notice notice-info"><p>' + SSR_Ajax.no_matches + '</p></div>');
                return;
            }

            let html = '<h2>' + SSR_Ajax.results_title + ' (' + response.data.length + ' ' + SSR_Ajax.changes_found + ')</h2>';

            if (action === 'ssr_preview') {
                html += '<div class="notice notice-info"><p>' + SSR_Ajax.preview_notice + '</p></div>';
            } else {
                html += '<div class="notice notice-success"><p><strong>✓ ' + SSR_Ajax.replacement_done + '</strong> ' + response.data.length + ' ' + SSR_Ajax.changes_done + '.</p></div>';
            }

            response.data.forEach(function (item) {
                let highlightedBefore = highlightChanges(item.original, currentSearch, currentReplace, true, caseSensitive);
                let highlightedAfter = highlightChanges(item.new, currentSearch, currentReplace, false, caseSensitive);

                html += `
                <div class="ssr-diff">
                    <strong>${SSR_Ajax.table_label}</strong> ${item.table} | 
                    <strong>${SSR_Ajax.column_label}</strong> ${item.column} | 
                    <strong>${SSR_Ajax.id_label}</strong> ${item.primary}
                    <div class="ssr-label">${SSR_Ajax.before_label}</div>
                    <div class="ssr-before">${highlightedBefore}</div>
                    <div class="ssr-label">${SSR_Ajax.after_label}</div>
                    <div class="ssr-after">${highlightedAfter}</div>
                </div>
                `;
            });

            $('#ssr-results').html(html);

            if (action === 'ssr_preview') {
                enableRunButton();
            }

        }).fail(function () {
            $('#ssr-results').html('<div class="notice notice-error"><p>' + SSR_Ajax.error_occurred + '</p></div>');
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

        let confirmMsg = SSR_Ajax.run_confirm + '\n\n';
        confirmMsg += SSR_Ajax.permanent_modify + '\n';
        confirmMsg += SSR_Ajax.cannot_undo + '\n\n';

        if (hasCritical) {
            confirmMsg += SSR_Ajax.critical_warning + '\n';
            confirmMsg += SSR_Ajax.critical_info + '\n\n';
        }

        confirmMsg += SSR_Ajax.tables_selected + ' ' + selectedTables.length + '\n';
        confirmMsg += SSR_Ajax.backed_up + '\n\n';
        confirmMsg += SSR_Ajax.type_yes;

        let confirmation = prompt(confirmMsg);

        if (confirmation !== 'YES') {
            alert(SSR_Ajax.cancelled);
            return;
        }

        runAction('ssr_replace');
    });

});
