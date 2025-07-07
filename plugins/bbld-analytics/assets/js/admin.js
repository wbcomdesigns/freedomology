/**
 * BBLD Analytics Admin JavaScript
 */

(function($) {
    'use strict';

    // Main Analytics object
    window.bbldAnalytics = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initWidgets();
            this.startAutoRefresh();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Refresh data button
            $(document).on('click', '#refresh-data', this.refreshAllData.bind(this));
            
            // Widget refresh buttons
            $(document).on('click', '.widget-refresh', this.refreshWidget.bind(this));
            
            // Period selector changes
            $(document).on('change', '.period-selector', this.handlePeriodChange.bind(this));
            
            // Widget configuration
            $(document).on('click', '.widget-configure', this.openWidgetConfig.bind(this));
            
            // Modal close
            $(document).on('click', '.bbld-modal-close', this.closeModal.bind(this));
            
            // Widget config form
            $(document).on('submit', '#widget-config-form', this.saveWidgetConfig.bind(this));
            
            // Tab navigation
            $(document).on('click', '.tab-button', this.switchTab.bind(this));
        },

        /**
         * Initialize widgets
         */
        initWidgets: function() {
            $('.bbld-analytics-widget').each(function() {
                var $widget = $(this);
                var widgetId = $widget.attr('id').replace('widget-', '');
                
                // Mark widget as initialized
                $widget.data('initialized', true);
            });
        },

        /**
         * Start auto-refresh if enabled
         */
        startAutoRefresh: function() {
            if (bbldAnalytics.refreshInterval > 0) {
                setInterval(function() {
                    this.refreshDashboardSummary();
                }.bind(this), bbldAnalytics.refreshInterval);
            }
        },

        /**
         * Refresh all data
         */
        refreshAllData: function(e) {
            e.preventDefault();
            
            if (!confirm(bbldAnalytics.strings.confirmRefresh)) {
                return;
            }
            
            this.showLoading();
            
            $.ajax({
                url: bbldAnalytics.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bbld_refresh_metrics',
                    nonce: bbldAnalytics.nonce
                },
                success: function(response) {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showNotice(bbldAnalytics.strings.refreshSuccess, 'success');
                        this.refreshAllWidgets();
                    } else {
                        this.showNotice(response.data || bbldAnalytics.strings.refreshError, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.hideLoading();
                    this.showNotice(bbldAnalytics.strings.refreshError, 'error');
                }.bind(this)
            });
        },

        /**
         * Refresh a specific widget
         */
        refreshWidget: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var widgetId = $button.data('widget');
            var $widget = $('#widget-' + widgetId);
            var period = $widget.find('.period-selector').val() || '30d';
            
            this.loadWidgetData(widgetId, period);
        },

        /**
         * Refresh all widgets
         */
        refreshAllWidgets: function() {
            $('.bbld-analytics-widget').each(function() {
                var $widget = $(this);
                var widgetId = $widget.attr('id').replace('widget-', '');
                var period = $widget.find('.period-selector').val() || '30d';
                
                this.loadWidgetData(widgetId, period);
            }.bind(this));
        },

        /**
         * Load widget data
         */
        loadWidgetData: function(widgetId, period) {
            var $widget = $('#widget-' + widgetId);
            var $content = $widget.find('.widget-content');
            
            // Show loading state
            $content.html('<div class="widget-loading"><div class="spinner is-active"></div><p>' + bbldAnalytics.strings.loading + '</p></div>');
            
            $.ajax({
                url: bbldAnalytics.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bbld_get_widget_data',
                    widget_id: widgetId,
                    period: period,
                    nonce: bbldAnalytics.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.renderWidgetData(widgetId, response.data);
                    } else {
                        this.renderWidgetError(widgetId, response.data || bbldAnalytics.strings.error);
                    }
                }.bind(this),
                error: function() {
                    this.renderWidgetError(widgetId, bbldAnalytics.strings.error);
                }.bind(this)
            });
        },

        /**
         * Render widget data
         */
        renderWidgetData: function(widgetId, data) {
            var $widget = $('#widget-' + widgetId);
            var $content = $widget.find('.widget-content');
            
            // This would typically render the actual widget content
            // For now, we'll trigger a page refresh to show updated data
            location.reload();
        },

        /**
         * Render widget error
         */
        renderWidgetError: function(widgetId, message) {
            var $widget = $('#widget-' + widgetId);
            var $content = $widget.find('.widget-content');
            
            $content.html(
                '<div class="widget-error">' +
                '<div class="error-icon"><span class="dashicons dashicons-warning"></span></div>' +
                '<p>' + message + '</p>' +
                '</div>'
            );
        },

        /**
         * Handle period change
         */
        handlePeriodChange: function(e) {
            var $select = $(e.currentTarget);
            var period = $select.val();
            var widgetId = $select.data('widget');
            
            if (widgetId) {
                this.loadWidgetData(widgetId, period);
            }
        },

        /**
         * Open widget configuration modal
         */
        openWidgetConfig: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var widgetId = $button.data('widget');
            
            $.ajax({
                url: bbldAnalytics.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bbld_get_widget_config',
                    widget_id: widgetId,
                    nonce: bbldAnalytics.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.renderWidgetConfigModal(response.data);
                    } else {
                        this.showNotice(response.data || 'Error loading widget configuration', 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showNotice('Error loading widget configuration', 'error');
                }.bind(this)
            });
        },

        /**
         * Render widget configuration modal
         */
        renderWidgetConfigModal: function(data) {
            var $modal = $('#widget-config-modal');
            var $form = $('#widget-config-form');
            var $fields = $('#widget-config-fields');
            
            // Clear existing fields
            $fields.empty();
            
            // Add widget ID as hidden field
            $fields.append('<input type="hidden" name="widget_id" value="' + data.widget_id + '">');
            
            // Add configuration fields
            if (data.fields) {
                $.each(data.fields, function(key, field) {
                    var fieldHtml = '<div class="form-field">';
                    fieldHtml += '<label for="config_' + key + '">' + field.label + '</label>';
                    
                    if (field.type === 'select') {
                        fieldHtml += '<select name="config[' + key + ']" id="config_' + key + '">';
                        $.each(field.options, function(value, label) {
                            var selected = (field.value === value) ? ' selected' : '';
                            fieldHtml += '<option value="' + value + '"' + selected + '>' + label + '</option>';
                        });
                        fieldHtml += '</select>';
                    } else {
                        fieldHtml += '<input type="' + field.type + '" name="config[' + key + ']" id="config_' + key + '" value="' + (field.value || '') + '">';
                    }
                    
                    if (field.description) {
                        fieldHtml += '<p class="description">' + field.description + '</p>';
                    }
                    
                    fieldHtml += '</div>';
                    $fields.append(fieldHtml);
                });
            }
            
            // Show modal
            $modal.show();
        },

        /**
         * Save widget configuration
         */
        saveWidgetConfig: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var formData = $form.serialize();
            
            $.ajax({
                url: bbldAnalytics.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: formData + '&action=bbld_update_widget_config&nonce=' + bbldAnalytics.nonce,
                success: function(response) {
                    if (response.success) {
                        this.showNotice('Widget configuration saved', 'success');
                        this.closeModal();
                        
                        // Refresh the widget
                        this.loadWidgetData(response.data.widget_id, '30d');
                    } else {
                        this.showNotice(response.data || 'Error saving configuration', 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showNotice('Error saving configuration', 'error');
                }.bind(this)
            });
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.bbld-modal').hide();
        },

        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var tab = $button.data('tab');
            var $widget = $button.closest('.bbld-analytics-widget');
            
            // Update active states
            $widget.find('.tab-button').removeClass('active');
            $widget.find('.tab-panel').removeClass('active');
            
            $button.addClass('active');
            $widget.find('#tab-' + tab).addClass('active');
        },

        /**
         * Refresh dashboard summary
         */
        refreshDashboardSummary: function() {
            $.ajax({
                url: bbldAnalytics.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bbld_get_dashboard_summary',
                    nonce: bbldAnalytics.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateDashboardSummary(response.data);
                    }
                }.bind(this)
            });
        },

        /**
         * Update dashboard summary
         */
        updateDashboardSummary: function(data) {
            if (data.metrics) {
                // Update summary cards
                $('.summary-card').each(function() {
                    var $card = $(this);
                    var metric = $card.data('metric');
                    
                    if (data.metrics[metric] !== undefined) {
                        $card.find('.card-content h3').text(this.formatNumber(data.metrics[metric]));
                    }
                }.bind(this));
            }
            
            if (data.freshness) {
                // Update status bar
                var $statusBar = $('.data-status-bar');
                $statusBar.removeClass('status-fresh status-good status-stale');
                $statusBar.addClass('status-' + data.freshness.status);
                $statusBar.find('.status-text').text(data.freshness.message);
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            $('#loading-overlay').show();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#loading-overlay').hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },

        /**
         * Format number for display
         */
        formatNumber: function(number) {
            if (number >= 1000000) {
                return (number / 1000000).toFixed(1) + 'M';
            } else if (number >= 1000) {
                return (number / 1000).toFixed(1) + 'K';
            }
            return number.toLocaleString();
        },

        /**
         * Format percentage for display
         */
        formatPercentage: function(value, decimals) {
            decimals = decimals || 1;
            return parseFloat(value).toFixed(decimals) + '%';
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle function
         */
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        bbldAnalytics.init();
    });

    // Handle window resize for responsive charts
    $(window).on('resize', bbldAnalytics.debounce(function() {
        // Trigger chart resize if Chart.js is available
        if (typeof Chart !== 'undefined') {
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.resize();
            });
        }
    }, 250));

    // Handle visibility change to pause/resume auto-refresh
    $(document).on('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, pause auto-refresh
            bbldAnalytics.pauseAutoRefresh = true;
        } else {
            // Page is visible, resume auto-refresh
            bbldAnalytics.pauseAutoRefresh = false;
        }
    });

})(jQuery);