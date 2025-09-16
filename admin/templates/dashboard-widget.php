<?php
/**
 * Dashboard Widget template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="shopcommerce-dashboard-widget">
    <div class="widget-stats">
        <div class="stat-item">
            <span class="stat-number"><?php echo number_format($stats['products']['total_synced_products'] ?? 0); ?></span>
            <span class="stat-label">Total Products</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo number_format($stats['activity']['total_products_synced'] ?? 0); ?></span>
            <span class="stat-label">Synced</span>
        </div>
        <div class="stat-item">
            <span class="stat-number <?php echo ($stats['activity']['total_errors'] ?? 0) > 0 ? 'errors' : ''; ?>">
                <?php echo number_format($stats['activity']['total_errors'] ?? 0); ?>
            </span>
            <span class="stat-label">Errors</span>
        </div>
    </div>

    <div class="widget-status">
        <h4>Current Status</h4>
        <ul>
            <li>
                <strong>API:</strong>
                <span class="<?php echo $stats['api']['token_cached'] ? 'status-good' : 'status-warning'; ?>">
                    <?php echo $stats['api']['token_cached'] ? 'Connected' : 'Disconnected'; ?>
                </span>
            </li>
            <li>
                <strong>Next Job:</strong>
                <?php echo esc_html($stats['queue']['current_job']['brand'] ?? 'Idle'); ?>
            </li>
            <li>
                <strong>Last Sync:</strong>
                <?php
                $last_sync = $stats['activity']['last_sync_time'] ?? null;
                echo $last_sync ? human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' ago' : 'Never';
                ?>
            </li>
        </ul>
    </div>

    <?php if (!empty($recent_activities)): ?>
        <div class="widget-activity">
            <h4>Recent Activity</h4>
            <ul>
                <?php foreach (array_slice($recent_activities, 0, 3) as $activity): ?>
                    <li>
                        <span class="activity-time">
                            <?php echo human_time_diff(strtotime($activity['timestamp']), current_time('timestamp')); ?> ago
                        </span>
                        <span class="activity-desc">
                            <?php echo esc_html($activity['description']); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="widget-actions">
        <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync'); ?>" class="button button-primary">
            View Dashboard
        </a>
        <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync-control'); ?>" class="button button-secondary">
            Run Sync
        </a>
    </div>
</div>

<style>
.shopcommerce-dashboard-widget {
    padding: 10px;
}

.widget-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-item .stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 2px;
}

.stat-item .stat-number.errors {
    color: #dc3232;
}

.stat-item .stat-label {
    display: block;
    font-size: 12px;
    color: #666;
}

.widget-status ul, .widget-activity ul {
    margin: 0;
    padding: 0 0 0 15px;
}

.widget-status li, .widget-activity li {
    margin-bottom: 5px;
}

.status-good {
    color: #46b450;
}

.status-warning {
    color: #ffb900;
}

.activity-time {
    display: block;
    font-size: 11px;
    color: #666;
    margin-bottom: 2px;
}

.widget-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.widget-actions .button {
    margin-right: 5px;
}
</style>