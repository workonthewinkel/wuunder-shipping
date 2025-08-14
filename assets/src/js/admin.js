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
    });

})(jQuery);