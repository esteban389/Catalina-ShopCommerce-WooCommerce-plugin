/**
 * ShopCommerce Sync Admin JavaScript
 *
 * @package ShopCommerce_Sync
 */

(function($) {
    'use strict';

    // ShopCommerce Admin object
    window.shopcommerce_admin = window.shopcommerce_admin || {};

    // Initialize on document ready
    $(document).ready(function() {
        initAdmin();
    });

    /**
     * Initialize admin functionality
     */
    function initAdmin() {
        // Initialize AJAX handlers
        initAjaxHandlers();

        // Initialize UI components
        initUIComponents();

        // Initialize tooltips and help text
        initTooltips();
    }

    /**
     * Initialize AJAX handlers
     */
    function initAjaxHandlers() {
        // Test connection
        $(document).on('click', '.test-connection-btn', function(e) {
            e.preventDefault();
            testConnection($(this));
        });

        // Run sync
        $(document).on('click', '.run-sync-btn', function(e) {
            e.preventDefault();
            runSync($(this));
        });

        // Clear cache
        $(document).on('click', '.clear-cache-btn', function(e) {
            e.preventDefault();
            clearCache($(this));
        });

        // Get queue status
        $(document).on('click', '.refresh-queue-btn', function(e) {
            e.preventDefault();
            refreshQueueStatus($(this));
        });

        // Reset jobs
        $(document).on('click', '.reset-jobs-btn', function(e) {
            e.preventDefault();
            resetJobs($(this));
        });

        // Get activity log
        $(document).on('click', '.refresh-activity-btn', function(e) {
            e.preventDefault();
            refreshActivityLog($(this));
        });

        // View activity details
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();
            viewActivityDetails($(this));
        });

        // Close modals
        $(document).on('click', '.close-modal', function(e) {
            e.preventDefault();
            $(this).closest('.shopcommerce-modal').hide();
        });

        // Close modal when clicking outside
        $(document).on('click', '.shopcommerce-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Filter activity log
        $(document).on('change', '#activity-filter', function(e) {
            e.preventDefault();
            filterActivityLog($(this));
        });

        // Brand selection for sync
        $(document).on('change', '#brand-select', function(e) {
            e.preventDefault();
            toggleBrandSyncButton($(this));
        });

        // Run brand sync
        $(document).on('click', '#run-brand-sync-btn', function(e) {
            e.preventDefault();
            runBrandSync($(this));
        });

        // Run specific job
        $(document).on('click', '.run-job-btn', function(e) {
            e.preventDefault();
            runSpecificJob($(this));
        });

        // Test API settings
        $(document).on('click', '#test-api-settings-btn', function(e) {
            e.preventDefault();
            testApiSettings($(this));
        });

        // Update settings
        $(document).on('click', '#save-settings', function(e) {
            e.preventDefault();
            updateSettings($(this));
        });

        // Reset settings
        $(document).on('click', '#reset-settings-btn', function(e) {
            e.preventDefault();
            resetSettings($(this));
        });

        // Batches page filtering
        $(document).on('click', '#filter-batches-btn', function(e) {
            e.preventDefault();
            filterBatches();
        });

        // Clear filters
        $(document).on('click', '#clear-filters-btn', function(e) {
            e.preventDefault();
            clearBatchFilters();
        });

        // Process next batch from batches page
        $(document).on('click', '#process-next-batch-btn', function(e) {
            e.preventDefault();
            processNextBatch($(this));
        });

        // Reset all failed batches
        $(document).on('click', '#reset-all-failed-btn', function(e) {
            e.preventDefault();
            resetAllFailedBatches($(this));
        });

        // View batch details
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();
            viewBatchDetails($(this));
        });

        // View error details
        $(document).on('click', '.view-error-btn', function(e) {
            e.preventDefault();
            viewErrorDetails($(this));
        });

        // Pagination
        $(document).on('click', '#prev-page, #next-page', function(e) {
            e.preventDefault();
            navigateBatches($(this));
        });

        // Close modals
        $(document).on('click', '.modal-close', function(e) {
            e.preventDefault();
            $(this).closest('.shopcommerce-modal').hide();
        });
    }

    /**
     * Initialize UI components
     */
    function initUIComponents() {
        // Initialize tooltips only if jQuery UI tooltip is available
        if (typeof $.fn.tooltip === 'function') {
            $('.help-tip').tooltip({
                position: {
                    my: 'center bottom-10',
                    at: 'center top'
                },
                tooltipClass: 'shopcommerce-tooltip'
            });
        } else {
            // Fallback: use title attributes or simple hover tooltips
            $('.help-tip').each(function() {
                var $tip = $(this);
                var title = $tip.attr('title');
                if (title) {
                    $tip.on('mouseenter', function() {
                        var $tooltip = $('<div class="shopcommerce-simple-tooltip"></div>')
                            .text(title)
                            .css({
                                'position': 'absolute',
                                'background': '#333',
                                'color': '#fff',
                                'padding': '5px 10px',
                                'border-radius': '3px',
                                'font-size': '12px',
                                'z-index': '9999',
                                'display': 'none'
                            });

                        $('body').append($tooltip);

                        var position = $tip.offset();
                        $tooltip.css({
                            'top': position.top - 30,
                            'left': position.left
                        }).fadeIn(200);

                        $tip.data('tooltip', $tooltip);
                    }).on('mouseleave', function() {
                        var $tooltip = $tip.data('tooltip');
                        if ($tooltip) {
                            $tooltip.fadeOut(200, function() {
                                $(this).remove();
                            });
                        }
                    });
                }
            });
        }

        // Initialize confirm dialogs
        $('.confirm-action').on('click', function(e) {
            if (!confirm($(this).data('confirm-message') || 'Are you sure?')) {
                e.preventDefault();
            }
        });

        // Initialize loading states
        initLoadingStates();

        // Initialize auto-refresh
        initAutoRefresh();
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('.shopcommerce-tooltip-trigger').each(function() {
            var $trigger = $(this);
            var tooltipText = $trigger.data('tooltip') || $trigger.attr('title');

            if (tooltipText) {
                $trigger.attr('title', '');

                $trigger.hover(
                    function() {
                        var $tooltip = $('<div class="shopcommerce-tooltip">' + tooltipText + '</div>');
                        $('body').append($tooltip);

                        var triggerOffset = $trigger.offset();
                        var triggerWidth = $trigger.outerWidth();
                        var triggerHeight = $trigger.outerHeight();

                        $tooltip.css({
                            position: 'absolute',
                            top: triggerOffset.top + triggerHeight + 5,
                            left: triggerOffset.left + (triggerWidth / 2) - ($tooltip.outerWidth() / 2),
                            zIndex: 100001
                        }).fadeIn(200);
                    },
                    function() {
                        $('.shopcommerce-tooltip').remove();
                    }
                );
            }
        });
    }

    /**
     * Initialize loading states
     */
    function initLoadingStates() {
        // Add loading spinner to AJAX buttons
        $(document).ajaxStart(function() {
            $('.shopcommerce-admin .button').addClass('disabled');
        });

        $(document).ajaxStop(function() {
            $('.shopcommerce-admin .button').removeClass('disabled');
        });
    }

    /**
     * Initialize auto-refresh functionality
     */
    function initAutoRefresh() {
        // Auto-refresh queue status every 30 seconds on sync control page
        if (window.location.hash === '#sync-control' || window.location.pathname.includes('sync-control')) {
            setInterval(function() {
                if ($('.shopcommerce-control-panel').length) {
                    refreshQueueStatus($('.refresh-queue-btn'));
                }
            }, 30000);
        }
    }

    /**
     * Test API connection
     */
    function testConnection($btn) {
        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Testing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_test_connection',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Connection test successful!', 'success');
                } else {
                    showMessage('Connection test failed: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Connection test failed: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Run sync operation
     */
    function runSync($btn) {
        var fullSync = $btn.data('full-sync') || false;
        var originalText = $btn.html();
        var confirmMessage = fullSync ?
            'Full sync may take a long time. Are you sure?' :
            'Are you sure you want to run the sync now?';

        if (!confirm(confirmMessage)) {
            return;
        }

        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Running...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_run_sync',
                nonce: shopcommerce_admin.nonce,
                full_sync: fullSync
            },
            success: function(response) {
                if (response.success) {
                    displaySyncResults(response.data);
                    showMessage('Sync completed successfully!', 'success');
                } else {
                    showMessage('Sync failed: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Sync failed: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Clear cache
     */
    function clearCache($btn) {
        if (!confirm('Are you sure you want to clear the cache?')) {
            return;
        }

        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Clearing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_clear_cache',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Cache cleared successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Cache clear failed: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Cache clear failed: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Refresh queue status
     */
    function refreshQueueStatus($btn) {
        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Refreshing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_queue_status',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateQueueDisplay(response.data);
                    showMessage('Queue status updated!', 'success');
                } else {
                    showMessage('Failed to refresh queue: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to refresh queue: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Reset jobs
     */
    function resetJobs($btn) {
        if (!confirm('Are you sure you want to reset the jobs queue?')) {
            return;
        }

        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Resetting...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_reset_jobs',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Jobs reset successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Jobs reset failed: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Jobs reset failed: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Refresh activity log
     */
    function refreshActivityLog($btn) {
        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Refreshing...');

        var limit = 50;
        var activityType = $('#activity-filter').val();

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_activity_log',
                nonce: shopcommerce_admin.nonce,
                limit: limit,
                activity_type: activityType
            },
            success: function(response) {
                if (response.success) {
                    updateActivityLogDisplay(response.data);
                    showMessage('Activity log refreshed!', 'success');
                } else {
                    showMessage('Failed to refresh activity log: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to refresh activity log: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * View activity details
     */
    function viewActivityDetails($btn) {
        var activity = $btn.data('activity');
        var $modal = $('#activity-details-modal');
        var $content = $('#activity-details-content');

        if (activity && $modal.length && $content.length) {
            $content.text(JSON.stringify(activity, null, 2));
            $modal.show();
        }
    }

    /**
     * Filter activity log
     */
    function filterActivityLog($select) {
        var filter = $select.val();
        var url = new URL(window.location);
        url.searchParams.set('activity_filter', filter);
        window.location.href = url.toString();
    }

    /**
     * Toggle brand sync button
     */
    function toggleBrandSyncButton($select) {
        var $btn = $('#run-brand-sync-btn');
        $btn.prop('disabled', $select.val() === '');
    }

    /**
     * Run brand sync
     */
    function runBrandSync($btn) {
        var brand = $('#brand-select').val();
        if (!brand) {
            return;
        }

        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Running...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_run_sync',
                nonce: shopcommerce_admin.nonce,
                brand: brand
            },
            success: function(response) {
                if (response.success) {
                    displaySyncResults(response.data);
                    showMessage('Brand sync completed successfully!', 'success');
                } else {
                    showMessage('Brand sync failed: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Brand sync failed: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Run specific job
     */
    function runSpecificJob($btn) {
        var brand = $btn.data('brand');
        var categories = $btn.data('categories');

        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Running...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_run_sync',
                nonce: shopcommerce_admin.nonce,
                brand: brand,
                categories: categories
            },
            success: function(response) {
                if (response.success) {
                    displaySyncResults(response.data);
                    showMessage('Job completed successfully!', 'success');
                } else {
                    showMessage('Job failed: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Job failed: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Test API settings
     */
    function testApiSettings($btn) {
        var formData = {
            action: 'shopcommerce_test_connection',
            nonce: shopcommerce_admin.nonce
        };

        // Add current form values
        $('input[type="text"], input[type="password"], input[type="url"], input[type="number"], select').each(function() {
            if ($(this).attr('name') && $(this).attr('name').startsWith('shopcommerce_')) {
                formData[$(this).attr('name')] = $(this).val();
            }
        });

        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Testing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                var $result = $('#api-test-result');
                if (response.success) {
                    $result.removeClass('error').addClass('success').html('<p>✓ API connection successful!</p>');
                } else {
                    $result.removeClass('success').addClass('error').html('<p>✗ API connection failed: ' + response.data.error + '</p>');
                }
            },
            error: function(xhr, status, error) {
                var $result = $('#api-test-result');
                $result.removeClass('success').addClass('error').html('<p>✗ Connection test failed due to network error.</p>');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Update settings
     */
    function updateSettings($btn) {
        var formData = $('#shopcommerce-settings-form').serializeArray();
        var settings = {};

        $.each(formData, function(i, field) {
            if (field.name.startsWith('shopcommerce_')) {
                settings[field.name] = field.value;
            }
        });

        var originalText = $btn.html();
        $btn.prop('disabled', true).addClass('loading').html('<span class="dashicons dashicons-update spin"></span> Saving...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_update_settings',
                nonce: shopcommerce_admin.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Settings saved successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to save settings: ' + response.data.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to save settings: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading').html(originalText);
            }
        });
    }

    /**
     * Reset settings
     */
    function resetSettings($btn) {
        if (!confirm('Are you sure you want to reset all settings to their default values?')) {
            return;
        }

        if (!confirm('This action cannot be undone. Are you absolutely sure?')) {
            return;
        }

        // Reset form fields to defaults
        $('#shopcommerce_api_username').val('pruebas@hekalsoluciones.com');
        $('#shopcommerce_api_password').val('');
        $('#shopcommerce_api_base_url').val('https://shopcommerce.mps.com.co:7965/');
        $('#shopcommerce_api_timeout').val('840');
        $('#shopcommerce_cron_interval').val('hourly');
        $('#shopcommerce_batch_size').val('100');
        $('#shopcommerce_enable_logging').prop('checked', true);
        $('#shopcommerce_log_retention_days').val('30');
        $('#shopcommerce_default_stock_status').val('instock');
        $('#shopcommerce_product_status').val('publish');
        $('#shopcommerce_update_images').prop('checked', true);
        $('#shopcommerce_create_categories').prop('checked', true);

        showMessage('Settings have been reset to defaults. Click "Save Settings" to apply.', 'warning');
    }

    /**
     * Display sync results
     */
    function displaySyncResults(results) {
        var $container = $('#sync-results-container');
        if (!$container.length) {
            return;
        }

        var html = '<div class="sync-results">';

        if (results.job) {
            html += '<h3>Job: ' + escapeHtml(results.job.brand) + '</h3>';
        }

        if (results.results) {
            html += '<div class="result-stats">';
            html += '<div class="result-stat">Created: ' + (results.results.created || 0) + '</div>';
            html += '<div class="result-stat">Updated: ' + (results.results.updated || 0) + '</div>';
            html += '<div class="result-stat">Errors: ' + (results.results.errors || 0) + '</div>';
            html += '</div>';
        }

        html += '</div>';
        $container.html(html);
    }

    /**
     * Update queue display
     */
    function updateQueueDisplay(queueData) {
        // This would update the queue display with new data
        // Implementation depends on the specific HTML structure
        console.log('Queue data updated:', queueData);
    }

    /**
     * Update activity log display
     */
    function updateActivityLogDisplay(activityData) {
        var $container = $('.shopcommerce-activity-log tbody');
        if (!$container.length) {
            return;
        }

        var html = '';
        if (activityData.length === 0) {
            html = '<tr><td colspan="4">No activity found.</td></tr>';
        } else {
            $.each(activityData, function(index, activity) {
                html += '<tr>';
                html += '<td>' + escapeHtml(formatDate(activity.timestamp)) + '</td>';
                html += '<td><span class="activity-type activity-' + escapeHtml(activity.type) + '">' + escapeHtml(ucfirstWords(activity.type.replace('_', ' '))) + '</span></td>';
                html += '<td>' + escapeHtml(activity.description) + '</td>';
                html += '<td>';
                if (activity.data && Object.keys(activity.data).length > 0) {
                    html += '<button type="button" class="button button-small view-details-btn" data-activity=\'' + JSON.stringify(activity).replace(/'/g, '&#39;') + '\'>View Details</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
        }

        $container.html(html);
    }

    /**
     * Show message to user
     */
    function showMessage(message, type) {
        var $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');

        // Add to top of page
        $('.wrap h1').after($message);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Handle manual dismiss
        $message.on('click', '.notice-dismiss', function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    /**
     * Batch Processing Management
     */
    // Refresh batch queue
    $(document).on('click', '#refresh-batch-queue-btn', function(e) {
        e.preventDefault();
        refreshBatchQueue($(this));
    });

    // Process next batch
    $(document).on('click', '#process-batch-btn', function(e) {
        e.preventDefault();
        processNextBatch($(this));
    });

    // Reset failed batches
    $(document).on('click', '#reset-failed-batches-btn', function(e) {
        e.preventDefault();
        resetFailedBatches($(this));
    });

    // Cleanup old batches
    $(document).on('click', '#cleanup-old-batches-btn', function(e) {
        e.preventDefault();
        cleanupOldBatches($(this));
    });

    // Check brand completion
    $(document).on('click', '.check-brand-completion-btn', function(e) {
        e.preventDefault();
        checkBrandCompletion($(this));
    });

    function refreshBatchQueue($button) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_get_batch_queue_stats',
                nonce: shopcommerce_admin.nonce
            },
            beforeSend: function() {
                $button.prop('disabled', true).text('Refreshing...');
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Batch queue refreshed successfully', 'success');
                    // Reload page to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to refresh batch queue: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to refresh batch queue', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Refresh Queue');
            }
        });
    }

    function processNextBatch($button) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_process_pending_batches',
                nonce: shopcommerce_admin.nonce,
                limit: 1
            },
            beforeSend: function() {
                $button.prop('disabled', true).text('Processing...');
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    showMessage('Batch processed successfully', 'success');
                    if (result.results && result.results.length > 0) {
                        showMessage('Processed ' + result.results.length + ' batch(es)', 'success');
                    }
                    // Reload page to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to process batch: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to process batch', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Process Next Batch');
            }
        });
    }

    function resetFailedBatches($button) {
        if (!confirm('Are you sure you want to reset all failed batches? This will move them back to pending status.')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_reset_failed_batches',
                nonce: shopcommerce_admin.nonce
            },
            beforeSend: function() {
                $button.prop('disabled', true).text('Resetting...');
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Reset ' + response.data.reset_count + ' failed batches', 'success');
                    // Reload page to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to reset failed batches: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to reset failed batches', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Reset Failed Batches');
            }
        });
    }

    function cleanupOldBatches($button) {
        var days = prompt('Enter number of days old to cleanup (default 7):', '7');
        if (!days || isNaN(days)) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_cleanup_old_batches',
                nonce: shopcommerce_admin.nonce,
                days_old: parseInt(days)
            },
            beforeSend: function() {
                $button.prop('disabled', true).text('Cleaning up...');
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Cleaned up ' + response.data.batches_deleted + ' batches and ' + response.data.progress_cleaned + ' progress entries', 'success');
                    // Reload page to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to cleanup old batches: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to cleanup old batches', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Cleanup Old Batches');
            }
        });
    }

    function checkBrandCompletion($button) {
        var brand = $button.data('brand');
        if (!brand) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_check_brand_completion',
                nonce: shopcommerce_admin.nonce,
                brand: brand
            },
            beforeSend: function() {
                $button.prop('disabled', true).text('Checking...');
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    if (result.completed) {
                        showMessage('Brand "' + brand + '" sync completed successfully!', 'success');
                    } else {
                        showMessage('Brand "' + brand + '" sync still in progress. ' + (result.stats ? result.stats.pending + ' pending, ' + result.stats.processing + ' processing' : ''), 'info');
                    }
                } else {
                    showMessage('Failed to check brand completion: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to check brand completion', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Check Completion');
            }
        });
    }

    /**
     * Batch Management Functions
     */

    function filterBatches() {
        var status = $('#status-filter').val();
        var brand = $('#brand-filter').val();
        var search = $('#search-input').val();

        var url = new URL(window.location.href);
        if (status) url.searchParams.set('status', status);
        else url.searchParams.delete('status');

        if (brand) url.searchParams.set('brand', brand);
        else url.searchParams.delete('brand');

        if (search) url.searchParams.set('s', search);
        else url.searchParams.delete('s');

        // Reset to first page when filtering
        url.searchParams.delete('paged');

        window.location.href = url.toString();
    }

    function clearBatchFilters() {
        $('#status-filter').val('');
        $('#brand-filter').val('');
        $('#search-input').val('');

        var url = new URL(window.location.href);
        url.searchParams.delete('status');
        url.searchParams.delete('brand');
        url.searchParams.delete('s');
        url.searchParams.delete('paged');

        window.location.href = url.toString();
    }

    function processNextBatch($button) {
        var originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_process_pending_batches',
                nonce: shopcommerce_admin.nonce,
                limit: 1
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Next batch processed successfully', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to process batch: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to process batch', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    function resetAllFailedBatches($button) {
        if (!confirm('Are you sure you want to reset all failed batches? This will move them back to pending status.')) {
            return;
        }

        var originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Resetting...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_reset_failed_batches',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Reset ' + response.data.reset_count + ' failed batches', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Failed to reset failed batches: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to reset failed batches', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    function viewBatchDetails($button) {
        var batchId = $button.data('batch-id');
        if (!batchId) {
            return;
        }

        // Show loading state
        var $modal = $('#batch-details-modal');
        var $content = $('#batch-details-content');
        $content.html('<div class="loading"><span class="dashicons dashicons-update spin"></span> Loading batch details...</div>');
        $modal.show();

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_get_batch_details',
                nonce: shopcommerce_admin.nonce,
                batch_id: batchId
            },
            success: function(response) {
                if (response.success) {
                    var batch = response.data.batch;
                    var batchData = response.data.batch_data || [];

                    var html = '<div class="batch-details">';
                    html += '<h4>Batch Information</h4>';
                    html += '<table class="form-table">';
                    html += '<tr><th>ID:</th><td>' + batch.id + '</td></tr>';
                    html += '<tr><th>Brand:</th><td>' + batch.brand + '</td></tr>';
                    html += '<tr><th>Status:</th><td><span class="batch-status status-' + batch.status + '">' + ucfirstWords(batch.status) + '</span></td></tr>';
                    html += '<tr><th>Batch Index:</th><td>' + batch.batch_index + ' of ' + batch.total_batches + '</td></tr>';
                    html += '<tr><th>Created:</th><td>' + formatDate(batch.created_at) + '</td></tr>';
                    if (batch.started_at) {
                        html += '<tr><th>Started:</th><td>' + formatDate(batch.started_at) + '</td></tr>';
                    }
                    if (batch.completed_at) {
                        html += '<tr><th>Completed:</th><td>' + formatDate(batch.completed_at) + '</td></tr>';
                    }
                    html += '<tr><th>Attempts:</th><td>' + batch.attempts + ' / ' + batch.max_attempts + '</td></tr>';
                    if (batch.error_message) {
                        html += '<tr><th>Error:</th><td class="error-message">' + escapeHtml(batch.error_message) + '</td></tr>';
                    }
                    html += '</table>';

                    if (batchData.length > 0) {
                        html += '<h4>Products in Batch (' + batchData.length + ')</h4>';
                        html += '<div class="batch-products">';
                        html += '<ul class="product-list">';
                        for (var i = 0; i < Math.min(batchData.length, 10); i++) {
                            var product = batchData[i];
                            html += '<li>';
                            html += '<strong>' + escapeHtml(product.nombre || product.name || 'Unknown') + '</strong>';
                            if (product.sku) {
                                html += ' (SKU: ' + escapeHtml(product.sku) + ')';
                            }
                            if (product.precio) {
                                html += ' - $' + parseFloat(product.precio).toFixed(2);
                            }
                            html += '</li>';
                        }
                        if (batchData.length > 10) {
                            html += '<li class="more-items">... and ' + (batchData.length - 10) + ' more products</li>';
                        }
                        html += '</ul>';
                        html += '</div>';
                    }

                    html += '</div>';
                    $content.html(html);
                } else {
                    $content.html('<div class="error">Failed to load batch details: ' + response.data.message + '</div>');
                }
            },
            error: function() {
                $content.html('<div class="error">Failed to load batch details</div>');
            }
        });
    }

    function viewErrorDetails($button) {
        var errorMessage = $button.data('error');
        if (!errorMessage) {
            return;
        }

        var $modal = $('#error-details-modal');
        var $content = $('#error-details-content');

        $content.html('<div class="error-details-content">');
        $content.append('<h4>Error Details</h4>');
        $content.append('<div class="error-message">' + escapeHtml(errorMessage) + '</div>');
        $content.append('</div>');

        $modal.show();
    }

    function navigateBatches($button) {
        var currentPage = parseInt($('#current-page').val()) || 1;
        var totalPages = parseInt($('.total-pages').text()) || 1;
        var newPage = currentPage;

        if ($button.attr('id') === 'prev-page' && currentPage > 1) {
            newPage = currentPage - 1;
        } else if ($button.attr('id') === 'next-page' && currentPage < totalPages) {
            newPage = currentPage + 1;
        }

        if (newPage !== currentPage) {
            var url = new URL(window.location.href);
            url.searchParams.set('paged', newPage);
            window.location.href = url.toString();
        }
    }

    /**
     * Utility functions
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    function ucfirstWords(str) {
        return str.replace(/\b\w/g, function(l) {
            return l.toUpperCase();
        });
    }

})(jQuery);