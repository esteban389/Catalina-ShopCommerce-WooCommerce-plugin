<?php
/**
 * Sync Control template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap shopcommerce-admin">
    <h1>Sync Control</h1>

    <div class="shopcommerce-control-panel">
        <!-- Manual Sync -->
        <div class="control-section">
            <h2>Manual Sync</h2>
            <p>Run sync operations manually to test or force updates.</p>

            <div class="sync-controls">
                <div class="sync-option">
                    <h3>Next Job Sync</h3>
                    <p>Run the next job in the queue (recommended for testing)</p>
                    <button type="button" class="button button-primary" id="run-next-job-btn">
                        Run Next Job
                    </button>
                </div>

                <!-- TEMPORARY: Immediate Sync (Remove before production) -->
                <div class="sync-option" style="border: 2px solid #ffb900; background: #fff8e5;">
                    <h3 style="color: #d63638;">⚠️ Immediate Sync (Temporary)</h3>
                    <p style="color: #d63638; font-weight: bold;">WARNING: This is a temporary feature for development. Remove before production!</p>
                    <p>Execute the next job immediately and synchronously (processes entire catalog at once)</p>
                    <button type="button" class="button button-primary" id="run-immediate-sync-btn" style="background: #d63638; border-color: #d63638;">
                        Run Immediate Sync
                    </button>
                    <p style="font-size: 11px; color: #666; margin-top: 10px;">
                        See REMOVE_WEB_SYNC_README.md for removal instructions
                    </p>
                </div>

                <div class="sync-option">
                    <h3>Full Sync</h3>
                    <p>Run sync for all configured brands (may take a long time)</p>
                    <button type="button" class="button button-primary" id="run-full-sync-btn">
                        Run Full Sync
                    </button>
                </div>

                <div class="sync-option">
                    <h3>Specific Brand Sync</h3>
                    <p>Run sync for a specific brand</p>
                    <select id="brand-select" class="regular-text">
                        <option value="">Select a brand...</option>
                        <?php
                        if ($cron_scheduler) {
                            $jobs = $cron_scheduler->get_jobs();
                            foreach ($jobs as $job) {
                                echo '<option value="' . esc_attr($job['brand']) . '">' . esc_html($job['brand']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <button type="button" class="button button-secondary" id="run-brand-sync-btn" disabled>
                        Run Brand Sync
                    </button>
                </div>
            </div>
        </div>

        <!-- Job Queue -->
        <div class="control-section">
            <h2>Job Queue</h2>
            <p>View and manage the sync job queue.</p>

            <?php if ($cron_scheduler): ?>
                <?php $queue_status = $cron_scheduler->get_queue_status(); ?>

                <div class="queue-info">
                    <div class="queue-stat">
                        <span class="stat-label">Total Jobs:</span>
                        <span class="stat-value"><?php echo count($queue_status['jobs_list']); ?></span>
                    </div>
                    <div class="queue-stat">
                        <span class="stat-label">Current Index:</span>
                        <span class="stat-value"><?php echo $queue_status['current_index']; ?></span>
                    </div>
                    <div class="queue-stat">
                        <span class="stat-label">Current Brand:</span>
                        <span class="stat-value"><?php echo esc_html($queue_status['current_job']['brand'] ?? 'None'); ?></span>
                    </div>
                    <div class="queue-stat">
                        <span class="stat-label">Next Brand:</span>
                        <span class="stat-value"><?php echo esc_html($queue_status['next_job']['brand'] ?? 'None'); ?></span>
                    </div>
                </div>

                <div class="queue-actions">
                    <button type="button" class="button button-secondary" id="refresh-queue-btn">
                        Refresh Queue
                    </button>
                    <button type="button" class="button button-secondary" id="reset-queue-btn">
                        Reset Queue
                    </button>
                </div>

                <div class="job-list">
                    <h3>Job List</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Brand</th>
                                <th>Categories</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queue_status['jobs_list'] as $index => $job): ?>
                                <tr class="<?php echo $index == $queue_status['current_index'] ? 'current-job' : ''; ?>">
                                    <td><?php echo $index + 1; ?><?php echo $index == $queue_status['current_index'] ? ' ← Current' : ''; ?></td>
                                    <td><strong><?php echo esc_html($job['brand']); ?></strong></td>
                                    <td>
                                        <?php
                                        if (empty($job['categories'])) {
                                            echo 'All Categories';
                                        } else {
                                            echo implode(', ', $job['categories']);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small run-job-btn"
                                                data-brand="<?php echo esc_attr($job['brand']); ?>"
                                                data-categories="<?php echo esc_attr(json_encode($job['categories'])); ?>">
                                            Run Now
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Cron scheduler not available.</p>
            <?php endif; ?>
        </div>

        <!-- Batch Processing -->
        <div class="control-section">
            <h2>Batch Processing</h2>
            <p>Manage asynchronous batch processing queue.</p>

            <?php if (isset($GLOBALS['shopcommerce_jobs_store'])): ?>
                <?php
                $jobs_store = $GLOBALS['shopcommerce_jobs_store'];
                $batch_stats = $jobs_store->get_queue_stats();
                ?>

                <div class="batch-info">
                    <div class="batch-stat">
                        <span class="stat-label">Pending Batches:</span>
                        <span class="stat-value"><?php echo $batch_stats['pending'] ?? 0; ?></span>
                    </div>
                    <div class="batch-stat">
                        <span class="stat-label">Processing:</span>
                        <span class="stat-value"><?php echo $batch_stats['processing'] ?? 0; ?></span>
                    </div>
                    <div class="batch-stat">
                        <span class="stat-label">Completed:</span>
                        <span class="stat-value"><?php echo $batch_stats['completed'] ?? 0; ?></span>
                    </div>
                    <div class="batch-stat">
                        <span class="stat-label">Failed:</span>
                        <span class="stat-value"><?php echo $batch_stats['failed'] ?? 0; ?></span>
                    </div>
                </div>

                <div class="batch-actions">
                    <button type="button" class="button button-secondary" id="refresh-batch-queue-btn">
                        Refresh Queue
                    </button>
                    <button type="button" class="button button-secondary" id="process-batch-btn">
                        Process Next Batch
                    </button>
                    <button type="button" class="button button-secondary" id="reset-failed-batches-btn">
                        Reset Failed Batches
                    </button>
                    <button type="button" class="button button-link delete" id="cleanup-old-batches-btn">
                        Cleanup Old Batches
                    </button>
                </div>

                <?php if (!empty($batch_stats['by_brand'])): ?>
                <div class="batch-by-brand">
                    <h3>Batches by Brand</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Pending</th>
                                <th>Processing</th>
                                <th>Completed</th>
                                <th>Failed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batch_stats['by_brand'] as $brand => $stats): ?>
                            <tr>
                                <td><strong><?php echo esc_html($brand); ?></strong></td>
                                <td><?php echo $stats['pending']; ?></td>
                                <td><?php echo $stats['processing']; ?></td>
                                <td><?php echo $stats['completed']; ?></td>
                                <td><?php echo $stats['failed']; ?></td>
                                <td>
                                    <button type="button" class="button button-small check-brand-completion-btn"
                                            data-brand="<?php echo esc_attr($brand); ?>">
                                        Check Completion
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <p>Batch processing not available.</p>
            <?php endif; ?>
        </div>

        <!-- Cron Settings -->
        <div class="control-section">
            <h2>Cron Settings</h2>
            <p>Configure automated sync scheduling.</p>

            <?php if ($cron_scheduler): ?>
                <?php $cron_info = $cron_scheduler->get_cron_info(); ?>

                <div class="cron-info">
                    <div class="cron-stat">
                        <span class="stat-label">Current Schedule:</span>
                        <span class="stat-value"><?php echo esc_html($cron_info['current_schedule'] ?? 'Not scheduled'); ?></span>
                    </div>
                    <div class="cron-stat">
                        <span class="stat-label">Next Run:</span>
                        <span class="stat-value"><?php echo $cron_info['next_scheduled'] ? esc_html($cron_info['next_scheduled']) : 'Not scheduled'; ?></span>
                    </div>
                </div>

                <div class="cron-scheduling">
                    <h3>Change Schedule</h3>
                    <select id="cron-interval" class="regular-text">
                        <option value="">Select interval...</option>
                        <?php
                        $available_intervals = $cron_info['available_schedules'];
                        foreach ($available_intervals as $interval) {
                            $selected = ($cron_info['current_schedule'] == $interval) ? ' selected' : '';
                            echo '<option value="' . esc_attr($interval) . '"' . $selected . '>' . esc_html(ucfirst(str_replace('_', ' ', $interval))) . '</option>';
                        }
                        ?>
                    </select>
                    <button type="button" class="button button-primary" id="update-cron-btn">
                        Update Schedule
                    </button>
                </div>
            <?php else: ?>
                <p>Cron scheduler not available.</p>
            <?php endif; ?>
        </div>

        <!-- API Status -->
        <div class="control-section">
            <h2>API Status</h2>
            <p>Check the status of the ShopCommerce API connection.</p>

            <?php if ($api_client): ?>
                <?php $api_status = $api_client->get_status(); ?>

                <div class="api-status-info">
                    <div class="api-stat">
                        <span class="stat-label">Base URL:</span>
                        <span class="stat-value"><?php echo esc_html($api_status['base_url']); ?></span>
                    </div>
                    <div class="api-stat">
                        <span class="stat-label">Token Cached:</span>
                        <span class="stat-value <?php echo $api_status['token_cached'] ? 'status-good' : 'status-warning'; ?>">
                            <?php echo $api_status['token_cached'] ? 'Yes' : 'No'; ?>
                        </span>
                    </div>
                    <div class="api-stat">
                        <span class="stat-label">Token Expires In:</span>
                        <span class="stat-value">
                            <?php
                            if ($api_status['time_until_expiry'] > 0) {
                                echo gmdate('H:i:s', $api_status['time_until_expiry']);
                            } else {
                                echo 'Expired';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="api-stat">
                        <span class="stat-label">Timeout:</span>
                        <span class="stat-value"><?php echo $api_status['timeout']; ?> seconds</span>
                    </div>
                </div>

                <div class="api-actions">
                    <button type="button" class="button button-secondary" id="test-api-btn">
                        Test API Connection
                    </button>
                    <button type="button" class="button button-secondary" id="clear-api-cache-btn">
                        Clear API Cache
                    </button>
                </div>
            <?php else: ?>
                <p>API client not available.</p>
            <?php endif; ?>
        </div>

        <!-- Sync Results -->
        <div class="control-section">
            <h2>Sync Results</h2>
            <div id="sync-results-container">
                <p>Run a sync operation to see results here.</p>
            </div>
        </div>
    </div>
</div>

<style>
.shopcommerce-control-panel {
    max-width: 1200px;
    margin: 20px 0;
}

.control-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.control-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.sync-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.sync-option {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.sync-option h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.queue-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.queue-stat, .cron-stat, .api-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.queue-actions, .cron-scheduling, .api-actions {
    margin: 20px 0;
}

.job-list {
    margin: 20px 0;
}

.current-job {
    background-color: #f0f6fc !important;
}

.status-good {
    color: #46b450;
    font-weight: bold;
}

.status-warning {
    color: #ffb900;
    font-weight: bold;
}

.status-error {
    color: #dc3232;
    font-weight: bold;
}

#sync-results-container {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    min-height: 100px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Run next job
    $('#run-next-job-btn').on('click', function() {
        runSync(false);
    });

    // TEMPORARY: Run immediate sync
    $('#run-immediate-sync-btn').on('click', function() {
        if (!confirm('WARNING: This will process the entire catalog synchronously, which may take a long time and could timeout. Continue?')) {
            return;
        }

        var $btn = $(this);
        var $results = $('#sync-results-container');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Processing...');
        $results.html('<p>Running immediate sync (this may take several minutes)...</p>');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            timeout: 300000, // 5 minutes timeout
            data: {
                action: 'shopcommerce_run_immediate_sync',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    var html = '<div class="sync-results">';
                    html += '<h3 style="color: #46b450;">✓ Immediate Sync Completed</h3>';
                    html += '<div class="result-stats">';
                    html += '<div class="result-stat"><strong>Brand:</strong> ' + (response.data.brand || 'N/A') + '</div>';
                    html += '<div class="result-stat"><strong>Catalog Count:</strong> ' + (response.data.catalog_count || 0) + '</div>';
                    html += '<div class="result-stat"><strong>Processed:</strong> ' + (response.data.processed_count || 0) + '</div>';
                    html += '<div class="result-stat"><strong>Created:</strong> ' + (response.data.created || 0) + '</div>';
                    html += '<div class="result-stat"><strong>Updated:</strong> ' + (response.data.updated || 0) + '</div>';
                    html += '<div class="result-stat"><strong>Errors:</strong> ' + (response.data.errors || 0) + '</div>';
                    html += '<div class="result-stat"><strong>Skipped:</strong> ' + (response.data.skipped || 0) + '</div>';
                    html += '<div class="result-stat"><strong>Execution Time:</strong> ' + (response.data.execution_time || 0) + ' seconds</div>';
                    html += '</div>';
                    html += '</div>';
                    $results.html(html);
                } else {
                    $results.html('<p class="error">Immediate sync failed: ' + (response.data.error || response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).text(originalText);
                if (status === 'timeout') {
                    $results.html('<p class="error">Sync timed out after 5 minutes. The sync may still be processing in the background. Check logs for details.</p>');
                } else {
                    $results.html('<p class="error">Sync failed due to network error: ' + error + '</p>');
                }
            }
        });
    });

    // Run full sync
    $('#run-full-sync-btn').on('click', function() {
        if (confirm('Full sync may take a long time. Are you sure?')) {
            runSync(true);
        }
    });

    // Brand selection
    $('#brand-select').on('change', function() {
        $('#run-brand-sync-btn').prop('disabled', $(this).val() === '');
    });

    // Run brand sync
    $('#run-brand-sync-btn').on('click', function() {
        var brand = $('#brand-select').val();
        if (brand) {
            runBrandSync(brand);
        }
    });

    // Refresh queue
    $('#refresh-queue-btn').on('click', function() {
        location.reload();
    });

    // Reset queue
    $('#reset-queue-btn').on('click', function() {
        if (confirm('Are you sure you want to reset the queue?')) {
            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_reset_jobs',
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Queue reset successfully!');
                        location.reload();
                    } else {
                        alert('Queue reset failed: ' + response.data.error);
                    }
                }
            });
        }
    });

    // Run specific job
    $('.run-job-btn').on('click', function() {
        var brand = $(this).data('brand');
        var categories = $(this).data('categories');
        runBrandSync(brand, categories);
    });

    // Update cron schedule
    $('#update-cron-btn').on('click', function() {
        var interval = $('#cron-interval').val();
        if (interval) {
            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_update_settings',
                    nonce: shopcommerce_admin.nonce,
                    settings: {
                        'cron_interval': interval
                    }
                },
                success: function(response) {
                    if (response.success) {
                        alert('Cron schedule updated successfully!');
                        location.reload();
                    } else {
                        alert('Failed to update cron schedule: ' + response.data.error);
                    }
                }
            });
        }
    });

    // Test API connection
    $('#test-api-btn').on('click', function() {
        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_test_connection',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('API connection test successful!');
                } else {
                    alert('API connection test failed: ' + response.data.error);
                }
            }
        });
    });

    // Clear API cache
    $('#clear-api-cache-btn').on('click', function() {
        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_clear_cache',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('API cache cleared successfully!');
                    location.reload();
                } else {
                    alert('Failed to clear API cache: ' + response.data.error);
                }
            }
        });
    });

    function runSync(fullSync) {
        var $results = $('#sync-results-container');
        $results.html('<p>Running sync...</p>');

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
                    displayResults(response.data);
                } else {
                    $results.html('<p class="error">Sync failed: ' + response.data.error + '</p>');
                }
            },
            error: function() {
                $results.html('<p class="error">Sync failed due to network error.</p>');
            }
        });
    }

    function runBrandSync(brand, categories) {
        var $results = $('#sync-results-container');
        $results.html('<p>Running sync for ' + brand + '...</p>');

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
                    displayResults(response.data);
                } else {
                    $results.html('<p class="error">Sync failed: ' + response.data.error + '</p>');
                }
            },
            error: function() {
                $results.html('<p class="error">Sync failed due to network error.</p>');
            }
        });
    }

    function displayResults(results) {
        var $results = $('#sync-results-container');
        var html = '<div class="sync-results">';

        if (results.job) {
            html += '<h3>Job: ' + results.job.brand + '</h3>';
        }

        if (results.results) {
            html += '<div class="result-stats">';
            html += '<div class="result-stat">Created: ' + (results.results.created || 0) + '</div>';
            html += '<div class="result-stat">Updated: ' + (results.results.updated || 0) + '</div>';
            html += '<div class="result-stat">Errors: ' + (results.results.errors || 0) + '</div>';
            html += '</div>';
        }

        html += '</div>';
        $results.html(html);
    }
});
</script>