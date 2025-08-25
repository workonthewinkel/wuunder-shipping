import { Message } from '../utils/Message.js';

/**
 * ConnectionTester Component
 * Handles testing the Wuunder API connection
 */
class ConnectionTester {
    constructor() {
        this.button = '#wuunder-test-connection';
        this.result = '#wuunder-test-result';
        this.apiKeyField = '#wuunder_api_key';
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
        });
    }

    bindEvents() {
        $(this.button).on('click', (e) => this.handleTestConnection(e));
    }

    handleTestConnection(e) {
        e.preventDefault();
        
        const $button = $(this.button);
        const $result = $(this.result);
        const apiKey = $(this.apiKeyField).val();
        
        if (!this.validateApiKey(apiKey)) {
            Message.showResult($result, wuunder_admin.i18n.error_prefix + ' ' + wuunder_admin.i18n.please_enter_api_key, 'error');
            return;
        }
        
        Message.disableButton($button);
        Message.showResult($result, '<span class="spinner is-active"></span> ' + wuunder_admin.i18n.testing_connection, 'loading');
        
        this.testConnection(apiKey, $button, $result);
    }

    validateApiKey(apiKey) {
        return apiKey && apiKey.trim() !== '';
    }

    testConnection(apiKey, $button, $result) {
        $.ajax({
            url: wuunder_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wuunder_test_connection',
                api_key: apiKey,
                nonce: wuunder_admin.nonce
            },
            success: (response) => {
                if (response.success) {
                    Message.showResult($result, wuunder_admin.i18n.success_prefix + ' ' + response.data, 'success');
                } else {
                    Message.showResult($result, wuunder_admin.i18n.error_prefix + ' ' + response.data, 'error');
                }
            },
            error: () => {
                Message.showResult($result, wuunder_admin.i18n.error_prefix + ' ' + wuunder_admin.i18n.connection_test_failed, 'error');
            },
            complete: () => {
                Message.enableButton($button);
                Message.autoHideResult($result);
            }
        });
    }
}

export default ConnectionTester;
