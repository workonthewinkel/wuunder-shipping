/**
 * Wuunder Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Test connection button
        $('#wuunder-test-connection').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#wuunder-test-result');
            const apiKey = $('#wuunder_api_key').val();
            
            if (!apiKey || apiKey.trim() === '') {
                $result.html('<span style="color: red;">✗ Please enter an API key first</span>');
                return;
            }
            
            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active"></span> Testing connection...');
            
            $.ajax({
                url: wuunder_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wuunder_test_connection',
                    api_key: apiKey,
                    nonce: wuunder_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                    } else {
                        $result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">✗ Connection test failed</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 5000);
                }
            });
        });
        
        // Refresh carriers button
        $('#wuunder-refresh-carriers').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#wuunder-carriers-result');

            $(this).find('.spinner').addClass('is-active');
            
            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active"></span> Refreshing carriers...');
            
            $.ajax({
                url: wuunder_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wuunder_refresh_carriers',
                    nonce: wuunder_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                        // Reload page to show updated carriers
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">✗ Failed to refresh carriers</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 5000);
                }
            });
        });

        // Carrier select - filter wuunder-carriers-table rows by data-carrier-code
        $('#wuunder_carrier_filter').on('change', function() {
            const selectedValue = $(this).find('option:selected').val();
            const $rows = $('.wuunder-carriers-table tbody tr');

            if (!selectedValue || selectedValue === '') {
                // Show all rows if nothing selected
                $rows.show();
            } else {
                $rows.each(function() {
                    const carrierCode = $(this).data('carrier-code');
                    if (carrierCode == selectedValue) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });
        
        // Disconnect button
        $('#wuunder-disconnect').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to disconnect? This will clear your API key and all carrier data.')) {
                return;
            }
            
            const $button = $(this);
            const $result = $('#wuunder-test-result');
            
            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active"></span> Disconnecting...');
            
            $.ajax({
                url: wuunder_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wuunder_disconnect',
                    nonce: wuunder_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                        // Reload page to show updated state
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">✗ Failed to disconnect</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 5000);
                }
            });
        });

        $(document.body).on('wc_backbone_modal_loaded', function(e, target) {
            if (target === 'wc-modal-shipping-method-settings') {
                $('#woocommerce_wuunder_shipping_wuunder_carrier').on('change', function() {
                    const $carrierSelect = $(this);
                    const selectedText = $carrierSelect.find('option:selected').text();
                    const $titleField = $('#woocommerce_wuunder_shipping_title');
                    const currentTitle = $titleField.val();
                    
                    // Get all carrier option values
                    const carrierValues = [];
                    $carrierSelect.find('option').each(function() {
                        if ($(this).val()) {
                            carrierValues.push($(this).text());
                        }
                    });
                    
                    // Update title if empty or if current value matches one of the carrier options
                    if (!currentTitle || currentTitle.trim() === '' || carrierValues.includes(currentTitle)) {
                        $titleField.val(selectedText);
                    }
                });
            }
        });
    });

})(jQuery);