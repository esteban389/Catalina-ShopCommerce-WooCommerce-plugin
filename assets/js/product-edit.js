/**
 * ShopCommerce Product Edit Tracking
 *
 * Handles tracking of field changes for ShopCommerce products
 * and provides UI for viewing edit history.
 *
 * @package ShopCommerce_Sync
 */

(function($) {
    'use strict';

    // ShopCommerce Product Edit Tracking
    var ShopCommerceProductEditTracking = {

        /**
         * Initialize the product edit tracking
         */
        init: function() {
            this.$editForm = $('#post');
            this.isShopCommerceProduct = this.checkIfShopCommerceProduct();

            if (this.isShopCommerceProduct) {
                this.setupTracking();
                this.setupEditHistoryUI();
                this.loadEditHistory();
            }
        },

        /**
         * Check if current product is a ShopCommerce product
         */
        checkIfShopCommerceProduct: function() {
            return $('#shopcommerce-is-external-provider').val() === 'shopcommerce';
        },

        /**
         * Setup field change tracking
         */
        setupTracking: function() {
            var self = this;
            this.changedFields = new Set();
            this.originalValues = {};

            // Track form field changes
            this.$editForm.on('change', 'input, select, textarea', function(e) {
                var $field = $(this);
                var fieldName = self.getFieldName($field);
                var currentValue = self.getFieldValue($field);
                var originalValue = self.getOriginalValue($field);

                if (currentValue !== originalValue) {
                    self.changedFields.add(fieldName);
                    self.showFieldChangedIndicator($field, true);
                } else {
                    self.changedFields.delete(fieldName);
                    self.showFieldChangedIndicator($field, false);
                }

                self.updateChangedFieldsCount();
            });

            // Handle before unload to warn about unsaved changes
            $(window).on('beforeunload', function(e) {
                if (self.changedFields.size > 0) {
                    e.preventDefault();
                    return '';
                }
            });

            // Handle form submission
            this.$editForm.on('submit', function() {
                $(window).off('beforeunload');
                self.saveFieldChanges();
            });
        },

        /**
         * Setup edit history UI
         */
        setupEditHistoryUI: function() {
            // Add edit history section to the publish box
            this.addEditHistorySection();

            // Add changed fields indicator to the title area
            this.addChangedFieldsIndicator();
        },

        /**
         * Add edit history section to admin interface
         */
        addEditHistorySection: function() {
            var $historySection = $('<div class="shopcommerce-edit-history">' +
                '<h4>ShopCommerce Edit History</h4>' +
                '<div class="edit-history-content">' +
                    '<p class="loading-history">Loading edit history...</p>' +
                    '<div class="edit-history-list"></div>' +
                '</div>' +
                '</div>');

            $('#misc-publishing-actions').after($historySection);
        },

        /**
         * Add changed fields indicator
         */
        addChangedFieldsIndicator: function() {
            var $indicator = $('<div class="shopcommerce-changed-fields-indicator">' +
                '<span class="changed-fields-count">0</span> fields changed' +
                '<button type="button" class="button button-small view-changed-fields">View Changes</button>' +
                '</div>');

            $('#titlediv').after($indicator);

            // Handle view changes button
            $('.view-changed-fields').on('click', this.showChangedFieldsModal.bind(this));
        },

        /**
         * Load edit history via AJAX
         */
        loadEditHistory: function() {
            var self = this;
            var postId = $('#post_ID').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'shopcommerce_get_product_edit_history',
                    post_id: postId,
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayEditHistory(response.data);
                    } else {
                        self.showHistoryError();
                    }
                },
                error: function() {
                    self.showHistoryError();
                }
            });
        },

        /**
         * Display edit history
         */
        displayEditHistory: function(history) {
            var $list = $('.edit-history-list');

            if (history.length === 0) {
                $list.html('<p>No edit history found for this ShopCommerce product.</p>');
                return;
            }

            var historyHtml = '';
            history.forEach(function(entry) {
                var date = new Date(entry.edit_time);
                var formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();

                historyHtml += '<div class="history-entry">' +
                    '<div class="history-meta">' +
                        '<span class="history-time">' + formattedDate + '</span>' +
                        '<span class="history-editor">' + entry.editor_name + '</span>' +
                    '</div>' +
                    '<div class="history-changes">' +
                        '<strong>Changed fields:</strong> ' + entry.changed_fields.join(', ') +
                    '</div>' +
                    '</div>';
            });

            $list.html(historyHtml);
        },

        /**
         * Show history loading error
         */
        showHistoryError: function() {
            $('.edit-history-list').html('<p class="error">Unable to load edit history.</p>');
        },

        /**
         * Get field name for tracking
         */
        getFieldName: function($field) {
            var name = $field.attr('name');
            if (!name) {
                return '';
            }

            // Normalize field names
            name = name.replace(/\[\]/g, '');
            name = name.replace(/^meta_/, '');

            return name;
        },

        /**
         * Get field value for comparison
         */
        getFieldValue: function($field) {
            if ($field.is(':checkbox')) {
                return $field.is(':checked') ? 'yes' : 'no';
            } else if ($field.is(':radio')) {
                return $field.filter(':checked').val();
            } else {
                return $field.val();
            }
        },

        /**
         * Get original field value
         */
        getOriginalValue: function($field) {
            var name = this.getFieldName($field);

            // Store original value if not already stored
            if (!(name in this.originalValues)) {
                this.originalValues[name] = this.getFieldValue($field);
            }

            return this.originalValues[name];
        },

        /**
         * Show/hide field changed indicator
         */
        showFieldChangedIndicator: function($field, changed) {
            var $container = $field.closest('.form-field, .form-wrap');

            if (changed) {
                $container.addClass('shopcommerce-field-changed');
                if (!$container.find('.field-changed-indicator').length) {
                    $container.append('<span class="field-changed-indicator">• Changed</span>');
                }
            } else {
                $container.removeClass('shopcommerce-field-changed');
                $container.find('.field-changed-indicator').remove();
            }
        },

        /**
         * Update changed fields count
         */
        updateChangedFieldsCount: function() {
            $('.changed-fields-count').text(this.changedFields.size);

            if (this.changedFields.size > 0) {
                $('.shopcommerce-changed-fields-indicator').addClass('has-changes');
            } else {
                $('.shopcommerce-changed-fields-indicator').removeClass('has-changes');
            }
        },

        /**
         * Show changed fields modal
         */
        showChangedFieldsModal: function() {
            var changedFields = Array.from(this.changedFields);

            if (changedFields.length === 0) {
                alert('No fields have been changed.');
                return;
            }

            var modalHtml = '<div id="shopcommerce-changed-fields-modal" class="shopcommerce-modal">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h3>Changed Fields</h3>' +
                        '<button type="button" class="close-modal">&times;</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<ul class="changed-fields-list">' +
                            changedFields.map(function(field) {
                                return '<li>' + field + '</li>';
                            }).join('') +
                        '</ul>' +
                    '</div>' +
                '</div>' +
            '</div>';

            // Remove existing modal if any
            $('#shopcommerce-changed-fields-modal').remove();

            // Add modal to body
            $('body').append(modalHtml);

            // Show modal
            $('#shopcommerce-changed-fields-modal').show();

            // Handle close button
            $('.close-modal').on('click', function() {
                $('#shopcommerce-changed-fields-modal').remove();
            });

            // Close on outside click
            $(document).on('click', function(e) {
                if ($(e.target).is('#shopcommerce-changed-fields-modal')) {
                    $('#shopcommerce-changed-fields-modal').remove();
                }
            });
        },

        /**
         * Save field changes to meta data
         */
        saveFieldChanges: function() {
            var changedFields = Array.from(this.changedFields);
            var postId = $('#post_ID').val();

            if (changedFields.length === 0) {
                return;
            }

            // Add hidden field to track changed fields
            this.$editForm.append(
                '<input type="hidden" name="shopcommerce_changed_fields" value="' +
                changedFields.join(',') + '">' +
                '<input type="hidden" name="shopcommerce_edit_timestamp" value="' +
                Math.floor(Date.now() / 1000) + '">'
            );
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ShopCommerceProductEditTracking.init();
    });

})(jQuery);