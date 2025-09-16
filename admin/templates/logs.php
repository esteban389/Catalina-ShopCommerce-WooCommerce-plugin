<?php

/**
 * Log Viewer Page Template
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get handlers
$logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

// Get pagination and filter parameters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$lines_per_page = isset($_GET['lines']) ? min(500, max(10, intval($_GET['lines']))) : 100;
$log_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$offset = ($current_page - 1) * $lines_per_page;

// Get log file contents
$log_lines = $logger ? $logger->get_log_file_contents($lines_per_page * 3) : []; // Get more lines for filtering
$total_lines = count($log_lines);

// Filter log lines
$filtered_lines = [];
if ($log_lines) {
    foreach ($log_lines as $line) {
        $log_entry = json_decode($line, true);
        if (!$log_entry) continue;

        // Filter by log level
        if ($log_level && isset($log_entry['level']) && $log_entry['level'] !== $log_level) {
            continue;
        }

        // Filter by search term
        if ($search_term) {
            $search_in = isset($log_entry['message']) ? $log_entry['message'] : '';
            $search_in .= isset($log_entry['context']) ? json_encode($log_entry['context']) : '';
            if (stripos($search_in, $search_term) === false) {
                continue;
            }
        }

        $filtered_lines[] = $log_entry;
    }
}

$total_filtered_lines = count($filtered_lines);
$filtered_lines = array_slice($filtered_lines, $offset, $lines_per_page);
$total_pages = max(1, ceil($total_filtered_lines / $lines_per_page));

// Get log levels for filter dropdown
$log_levels = [
    'debug' => 'Debug',
    'info' => 'Info',
    'warning' => 'Warning',
    'error' => 'Error',
    'critical' => 'Critical'
];

// Check if log file exists
$log_file_exists = file_exists(SHOPCOMMERCE_SYNC_LOGS_DIR . 'shopcommerce-sync.log');
$log_file_size = $log_file_exists ? filesize(SHOPCOMMERCE_SYNC_LOGS_DIR . 'shopcommerce-sync.log') : 0;
$log_file_path = SHOPCOMMERCE_SYNC_LOGS_DIR . 'shopcommerce-sync.log';

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Log Viewer', 'shopcommerce-sync'); ?></h1>

    <?php if (!$log_file_exists): ?>
        <div class="notice notice-warning">
            <p><?php _e('Log file does not exist. No logs have been generated yet.', 'shopcommerce-sync'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($log_file_exists): ?>
        <div class="log-viewer-info">
            <p>
                <?php printf(
                    __('Log file: %s | Size: %s | Total entries: %s', 'shopcommerce-sync'),
                    '<code>' . esc_html($log_file_path) . '</code>',
                    size_format($log_file_size),
                    number_format($total_lines)
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Filters and Controls -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="" class="log-filters">
                <input type="hidden" name="page" value="shopcommerce-sync-logs">

                <!-- Log Level Filter -->
                <select name="level" id="log-level-filter">
                    <option value=""><?php _e('All Levels', 'shopcommerce-sync'); ?></option>
                    <?php foreach ($log_levels as $level => $label): ?>
                        <option value="<?php echo esc_attr($level); ?>" <?php selected($log_level, $level); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Search -->
                <input type="text"
                       name="search"
                       id="log-search"
                       placeholder="<?php _e('Search logs...', 'shopcommerce-sync'); ?>"
                       value="<?php echo esc_attr($search_term); ?>">

                <!-- Lines per page -->
                <select name="lines" id="lines-per-page">
                    <option value="10" <?php selected($lines_per_page, 10); ?>>10</option>
                    <option value="25" <?php selected($lines_per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($lines_per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($lines_per_page, 100); ?>>100</option>
                    <option value="250" <?php selected($lines_per_page, 250); ?>>250</option>
                    <option value="500" <?php selected($lines_per_page, 500); ?>>500</option>
                </select>

                <input type="submit" class="button" value="<?php _e('Filter', 'shopcommerce-sync'); ?>">
                <a href="?page=shopcommerce-sync-logs" class="button"><?php _e('Clear Filters', 'shopcommerce-sync'); ?></a>
            </form>
        </div>

        <div class="alignright actions">
            <?php if ($log_file_exists && $logger): ?>
                <button type="button"
                        class="button button-secondary"
                        id="clear-log-file"
                        onclick="shopcommerce_clear_log_file()">
                    <?php _e('Clear Log File', 'shopcommerce-sync'); ?>
                </button>
                <button type="button"
                        class="button"
                        id="refresh-logs"
                        onclick="shopcommerce_refresh_logs()">
                    <?php _e('Refresh', 'shopcommerce-sync'); ?>
                </button>
            <?php endif; ?>
        </div>

        <br class="clear">
    </div>

    <!-- Log Entries Table -->
    <div class="log-viewer-container">
        <?php if (empty($filtered_lines)): ?>
            <div class="notice notice-info">
                <p><?php _e('No log entries found matching your filters.', 'shopcommerce-sync'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped log-viewer-table">
                <thead>
                    <tr>
                        <th scope="col" class="log-timestamp"><?php _e('Timestamp', 'shopcommerce-sync'); ?></th>
                        <th scope="col" class="log-level"><?php _e('Level', 'shopcommerce-sync'); ?></th>
                        <th scope="col" class="log-message"><?php _e('Message', 'shopcommerce-sync'); ?></th>
                        <th scope="col" class="log-context"><?php _e('Context', 'shopcommerce-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_lines as $log_entry): ?>
                        <?php
                        $timestamp = isset($log_entry['timestamp']) ? $log_entry['timestamp'] : '';
                        $level = isset($log_entry['level']) ? $log_entry['level'] : 'info';
                        $message = isset($log_entry['message']) ? $log_entry['message'] : '';
                        $context = isset($log_entry['context']) ? $log_entry['context'] : [];

                        // Format timestamp
                        $formatted_time = $timestamp ? date_i18n('Y-m-d H:i:s', strtotime($timestamp)) : '';

                        // Get level CSS class
                        $level_class = 'log-level-' . esc_attr($level);
                        ?>
                        <tr class="log-entry <?php echo $level_class; ?>">
                            <td class="log-timestamp">
                                <?php echo esc_html($formatted_time); ?>
                            </td>
                            <td class="log-level">
                                <span class="log-level-badge <?php echo $level_class; ?>">
                                    <?php echo esc_html(ucfirst($level)); ?>
                                </span>
                            </td>
                            <td class="log-message">
                                <?php echo esc_html($message); ?>
                            </td>
                            <td class="log-context">
                                <?php if (!empty($context)): ?>
                                    <details>
                                        <summary><?php _e('View Context', 'shopcommerce-sync'); ?></summary>
                                        <pre class="log-context-data"><?php echo esc_html(json_encode($context, JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                <?php else: ?>
                                    <span class="log-no-context"><?php _e('None', 'shopcommerce-sync'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        _n('%s item', '%s items', $total_filtered_lines, 'shopcommerce-sync'),
                        number_format($total_filtered_lines)
                    ); ?>
                </span>

                <span class="pagination-links">
                    <?php
                    // Previous page link
                    if ($current_page > 1):
                        $prev_url = add_query_arg([
                            'page' => 'shopcommerce-sync-logs',
                            'paged' => $current_page - 1,
                            'level' => $log_level,
                            'search' => $search_term,
                            'lines' => $lines_per_page
                        ]);
                        ?>
                        <a class="prev-page" href="<?php echo esc_url($prev_url); ?>">
                            <span aria-hidden="true">‹</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan" aria-hidden="true">‹</span>
                    <?php endif; ?>

                    <?php
                    // Page numbers
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1):
                        $first_url = add_query_arg([
                            'page' => 'shopcommerce-sync-logs',
                            'paged' => 1,
                            'level' => $log_level,
                            'search' => $search_term,
                            'lines' => $lines_per_page
                        ]);
                        ?>
                        <a class="page-numbers" href="<?php echo esc_url($first_url); ?>">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="tablenav-pages-navspan">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++):
                        $page_url = add_query_arg([
                            'page' => 'shopcommerce-sync-logs',
                            'paged' => $i,
                            'level' => $log_level,
                            'search' => $search_term,
                            'lines' => $lines_per_page
                        ]);
                        ?>
                        <?php if ($i === $current_page): ?>
                            <span class="page-numbers current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a class="page-numbers" href="<?php echo esc_url($page_url); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php
                    if ($end_page < $total_pages):
                        if ($end_page < $total_pages - 1):
                            ?>
                            <span class="tablenav-pages-navspan">...</span>
                        <?php endif; ?>
                        <?php
                        $last_url = add_query_arg([
                            'page' => 'shopcommerce-sync-logs',
                            'paged' => $total_pages,
                            'level' => $log_level,
                            'search' => $search_term,
                            'lines' => $lines_per_page
                        ]);
                        ?>
                        <a class="page-numbers" href="<?php echo esc_url($last_url); ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php
                    // Next page link
                    if ($current_page < $total_pages):
                        $next_url = add_query_arg([
                            'page' => 'shopcommerce-sync-logs',
                            'paged' => $current_page + 1,
                            'level' => $log_level,
                            'search' => $search_term,
                            'lines' => $lines_per_page
                        ]);
                        ?>
                        <a class="next-page" href="<?php echo esc_url($next_url); ?>">
                            <span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan" aria-hidden="true">›</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.log-viewer-info {
    margin-bottom: 20px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}

.log-viewer-container {
    margin: 20px 0;
}

.log-viewer-table {
    margin-top: 0;
}

.log-viewer-table th {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 10;
}

.log-timestamp {
    width: 180px;
    white-space: nowrap;
}

.log-level {
    width: 100px;
    text-align: center;
}

.log-message {
    max-width: 400px;
    word-break: break-word;
}

.log-context {
    width: 200px;
}

.log-level-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: #fff;
}

.log-level-debug {
    background-color: #72777c;
}

.log-level-info {
    background-color: #0073aa;
}

.log-level-warning {
    background-color: #ff9800;
}

.log-level-error {
    background-color: #dc3232;
}

.log-level-critical {
    background-color: #8b0000;
}

.log-context-data {
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    padding: 10px;
    margin-top: 5px;
    border-radius: 3px;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.log-no-context {
    color: #999;
    font-style: italic;
}

.log-entry.log-level-debug {
    background-color: rgba(114, 119, 124, 0.05);
}

.log-entry.log-level-error {
    background-color: rgba(220, 50, 50, 0.05);
}

.log-entry.log-level-critical {
    background-color: rgba(139, 0, 0, 0.05);
}

details {
    cursor: pointer;
}

details summary {
    color: #0073aa;
    font-weight: normal;
}

details summary:hover {
    text-decoration: underline;
}

@media screen and (max-width: 782px) {
    .log-timestamp,
    .log-level,
    .log-message,
    .log-context {
        width: auto;
    }

    .log-viewer-table {
        font-size: 14px;
    }
}
</style>

<script>
function shopcommerce_clear_log_file() {
    if (!confirm('<?php _e('Are you sure you want to clear the log file? This action cannot be undone.', 'shopcommerce-sync'); ?>')) {
        return;
    }

    jQuery.post(ajaxurl, {
        action: 'shopcommerce_clear_log_file',
        nonce: '<?php echo wp_create_nonce('shopcommerce_admin_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('<?php _e('Log file cleared successfully.', 'shopcommerce-sync'); ?>');
            location.reload();
        } else {
            alert('<?php _e('Error clearing log file: ', 'shopcommerce-sync'); ?>' + response.data.message);
        }
    });
}

function shopcommerce_refresh_logs() {
    location.reload();
}
</script>