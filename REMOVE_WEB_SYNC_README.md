# Removing Web-Accessible Immediate Sync Endpoint

This document provides step-by-step instructions for removing the temporary web-accessible immediate sync endpoint that was added for development/testing purposes.

## Overview

The immediate sync functionality should only be accessible via command line (CLI) for security and performance reasons. This guide will help you remove the web endpoint while keeping the CLI functionality intact.

## Files to Modify

### 1. Remove AJAX Handler Registration

**File**: `includes/functions-admin.php`

**Location**: Around line 383-384 (in `shopcommerce_register_ajax_handlers()` function)

**Remove this line**:
```php
// TEMPORARY: Immediate sync endpoint (CLI-only in production)
add_action('wp_ajax_shopcommerce_run_immediate_sync', 'shopcommerce_ajax_run_immediate_sync');
```

### 2. Remove AJAX Handler Function

**File**: `includes/functions-admin.php`

**Location**: After the `shopcommerce_ajax_run_sync()` function (around line 518)

**Remove the entire function**:
```php
/**
 * TEMPORARY: AJAX handler for immediate sync (next job in queue)
 * 
 * WARNING: This is a temporary endpoint for development/testing.
 * Remove this function and its action registration before production deployment.
 * See REMOVE_WEB_SYNC_README.md for removal instructions.
 */
function shopcommerce_ajax_run_immediate_sync() {
    // ... entire function body ...
}
```

### 3. Remove Button from Admin Interface

**File**: `admin/templates/sync-control.php`

**Location**: In the "Manual Sync" section, after the "Next Job Sync" option (around line 30)

**Remove this entire block**:
```php
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
```

### 4. Remove JavaScript Handler

**File**: `admin/templates/sync-control.php`

**Location**: In the `<script>` section, after the `$('#run-next-job-btn')` handler (around line 420)

**Remove this entire block**:
```javascript
// TEMPORARY: Run immediate sync
$('#run-immediate-sync-btn').on('click', function() {
    // ... entire handler code ...
});
```

## Verification Steps

After removing the code:

1. **Check Admin Interface**: 
   - Go to WordPress Admin → ShopCommerce Sync → Sync Control
   - Verify the "Immediate Sync" button is no longer visible

2. **Test AJAX Endpoint**:
   - Open browser developer console
   - Try to call the endpoint manually (should return 404 or error)
   - The endpoint `shopcommerce_run_immediate_sync` should not exist

3. **Verify CLI Still Works**:
   - Run: `php sync-immediate.php` (should still work)
   - Run: `wp eval-file sync-immediate.php` (should still work)

4. **Check for Remaining References**:
   - Search codebase for: `shopcommerce_run_immediate_sync`
   - Search codebase for: `run-immediate-sync-btn`
   - Search codebase for: `execute_sync_for_job_immediate` (this should remain - it's the method, not the endpoint)

## What Remains After Removal

The following will **still exist** and should **not** be removed:

- ✅ `sync-immediate.php` - CLI script (keep this)
- ✅ `execute_sync_for_job_immediate()` method in `ShopCommerce_Sync` class (keep this)
- ✅ All other sync functionality

## Security Note

After removal, the immediate sync can **only** be executed via:
- Command line: `php sync-immediate.php`
- WP-CLI: `wp eval-file sync-immediate.php`

This ensures that:
- No web-based attacks can trigger long-running sync operations
- No accidental timeouts from web requests
- Better security posture for production environments

## Quick Removal Checklist

- [ ] Remove AJAX action registration from `shopcommerce_register_ajax_handlers()`
- [ ] Remove `shopcommerce_ajax_run_immediate_sync()` function
- [ ] Remove button HTML from sync-control.php template
- [ ] Remove JavaScript click handler from sync-control.php template
- [ ] Verify admin interface no longer shows the button
- [ ] Verify CLI script still works
- [ ] Delete this README file (optional, after verification)

## Support

If you encounter any issues during removal, check:
1. Browser cache (clear it and refresh)
2. WordPress object cache (flush if using caching plugins)
3. PHP opcode cache (restart PHP-FPM if needed)

