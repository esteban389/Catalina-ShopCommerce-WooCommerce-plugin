<?php

/**
 * Debug script to identify WordPress cron scheduling issues
 * Add this to your plugin or run it temporarily to diagnose the problem
 */

class ShopCommerce_Cron_Debug {
    
    /**
     * Run comprehensive cron diagnostics
     */
    public function run_diagnostics() {
        echo "<h2>ShopCommerce Cron Diagnostics</h2>\n";
        
        // 1. Check if WordPress cron is enabled
        $this->check_wp_cron_enabled();
        
        // 2. Check custom schedules registration
        $this->check_custom_schedules();
        
        // 3. Check hook registration
        $this->check_hook_registration();
        
        // 4. Check current cron events
        $this->check_current_cron_events();
        
        // 5. Test manual scheduling
        $this->test_manual_scheduling();
        
        // 6. Check plugin activation state
        $this->check_plugin_state();
    }
    
    /**
     * Check if WordPress cron is enabled
     */
    private function check_wp_cron_enabled() {
        echo "<h3>1. WordPress Cron Status</h3>\n";
        
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo "❌ WordPress cron is DISABLED (DISABLE_WP_CRON = true)\n";
            echo "   Solution: Set DISABLE_WP_CRON to false in wp-config.php or use system cron\n";
        } else {
            echo "✅ WordPress cron is enabled\n";
        }
        
        if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) {
            echo "ℹ️ ALTERNATE_WP_CRON is enabled\n";
        }
        
        echo "\n";
    }
    
    /**
     * Check if custom schedules are properly registered
     */
    private function check_custom_schedules() {
        echo "<h3>2. Custom Schedules Registration</h3>\n";
        
        $schedules = wp_get_schedules();
        $custom_schedules = ['every_minute', 'every_15_minutes', 'every_30_minutes'];
        
        foreach ($custom_schedules as $schedule) {
            if (isset($schedules[$schedule])) {
                echo "✅ {$schedule} is registered (interval: {$schedules[$schedule]['interval']}s)\n";
            } else {
                echo "❌ {$schedule} is NOT registered\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Check if the cron hook is properly registered as an action
     */
    private function check_hook_registration() {
        echo "<h3>3. Hook Registration</h3>\n";
        
        $hook_name = 'shopcommerce_product_sync_hook';
        
        if (has_action($hook_name)) {
            echo "✅ Hook '{$hook_name}' is registered as an action\n";
            
            // Get priority and callback info
            global $wp_filter;
            if (isset($wp_filter[$hook_name])) {
                $callbacks = $wp_filter[$hook_name]->callbacks;
                foreach ($callbacks as $priority => $functions) {
                    foreach ($functions as $function) {
                        $callback_info = is_array($function['function']) 
                            ? get_class($function['function'][0]) . '->' . $function['function'][1]
                            : $function['function'];
                        echo "   - Priority {$priority}: {$callback_info}\n";
                    }
                }
            }
        } else {
            echo "❌ Hook '{$hook_name}' is NOT registered as an action\n";
            echo "   This means the constructor might not be running or add_action is failing\n";
        }
        
        echo "\n";
    }
    
    /**
     * Check current cron events
     */
    private function check_current_cron_events() {
        echo "<h3>4. Current Cron Events</h3>\n";
        
        $hook_name = 'shopcommerce_product_sync_hook';
        $cron_array = _get_cron_array();
        $found_events = [];
        
        foreach ($cron_array as $timestamp => $hooks) {
            if (isset($hooks[$hook_name])) {
                $found_events[] = [
                    'timestamp' => $timestamp,
                    'datetime' => date('Y-m-d H:i:s', $timestamp),
                    'events' => $hooks[$hook_name]
                ];
            }
        }
        
        if (empty($found_events)) {
            echo "❌ No cron events found for '{$hook_name}'\n";
            
            // Check if ANY cron events exist
            if (empty($cron_array)) {
                echo "❌ No cron events exist at all - this indicates a serious cron system issue\n";
            } else {
                echo "ℹ️ Other cron events exist, but not ours\n";
                echo "   Total cron events in system: " . count($cron_array) . "\n";
            }
        } else {
            echo "✅ Found " . count($found_events) . " cron event(s) for '{$hook_name}':\n";
            foreach ($found_events as $event) {
                echo "   - Scheduled for: {$event['datetime']} (timestamp: {$event['timestamp']})\n";
                foreach ($event['events'] as $key => $details) {
                    echo "     Schedule: " . ($details['schedule'] ?? 'single') . "\n";
                    if (isset($details['args'])) {
                        echo "     Args: " . json_encode($details['args']) . "\n";
                    }
                }
            }
        }
        
        // Check wp_next_scheduled
        $next_scheduled = wp_next_scheduled($hook_name);
        if ($next_scheduled) {
            echo "✅ wp_next_scheduled() returns: " . date('Y-m-d H:i:s', $next_scheduled) . "\n";
        } else {
            echo "❌ wp_next_scheduled() returns false\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test manual scheduling
     */
    private function test_manual_scheduling() {
        echo "<h3>5. Test Manual Scheduling</h3>\n";
        
        $hook_name = 'shopcommerce_product_sync_hook';
        $test_hook = $hook_name . '_test';
        
        // Clear any existing test events
        wp_clear_scheduled_hook($test_hook);
        
        // Try to schedule a test event
        echo "Testing scheduling with 'hourly' interval...\n";
        $result = wp_schedule_event(time() + 300, 'hourly', $test_hook);
        
        if ($result === false) {
            echo "❌ wp_schedule_event() returned false\n";
            echo "   This indicates a problem with the scheduling function itself\n";
        } else {
            echo "✅ wp_schedule_event() succeeded\n";
            
            // Check if it was actually scheduled
            $next_test = wp_next_scheduled($test_hook);
            if ($next_test) {
                echo "✅ Test event scheduled for: " . date('Y-m-d H:i:s', $next_test) . "\n";
            } else {
                echo "❌ Test event was not actually scheduled\n";
            }
            
            // Clean up
            wp_clear_scheduled_hook($test_hook);
            echo "✅ Test event cleaned up\n";
        }
        
        echo "\n";
    }
    
    /**
     * Check plugin activation state
     */
    private function check_plugin_state() {
        echo "<h3>6. Plugin State</h3>\n";
        
        // Check if activation hook was called
        echo "Checking activation indicators...\n";
        
        // Check if jobs are initialized
        $jobs = get_option('shopcommerce_sync_jobs');
        if (is_array($jobs) && !empty($jobs)) {
            echo "✅ Sync jobs are initialized (" . count($jobs) . " jobs)\n";
        } else {
            echo "❌ Sync jobs are not initialized\n";
            echo "   This suggests the activation hook didn't run or failed\n";
        }
        
        // Check job index
        $job_index = get_option('shopcommerce_sync_jobs_index');
        if ($job_index !== false) {
            echo "✅ Job index is set to: {$job_index}\n";
        } else {
            echo "❌ Job index is not set\n";
        }
        
        echo "\n";
    }
}

?>