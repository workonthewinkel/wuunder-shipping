import { Message } from '../utils/Message.js';

/**
 * DisconnectManager Component
 * Handles disconnecting from Wuunder API
 */
class DisconnectManager {
    constructor() {
        this.button = '#wuunder-disconnect';
        this.result = '#wuunder-test-result';
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
        });
    }

    bindEvents() {
        $(this.button).on('click', (e) => this.handleDisconnect(e));
    }

    handleDisconnect(e) {
        e.preventDefault();
        
        if (!this.confirmDisconnect()) {
            return;
        }
        
        const $button = $(this.button);
        const $result = $(this.result);
        
        Message.disableButton($button);
        Message.showResult($result, '<span class="spinner is-active"></span> ' + wuunder_admin.i18n.disconnecting, 'loading');
        
        this.disconnect($button, $result);
    }

    confirmDisconnect() {
        return confirm(wuunder_admin.i18n.confirm_disconnect);
    }

    disconnect($button, $result) {
        $.ajax({
            url: wuunder_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wuunder_disconnect',
                nonce: wuunder_admin.nonce
            },
            success: (response) => {
                if (response.success) {
                    Message.showResult($result, wuunder_admin.i18n.success_prefix + ' ' + response.data, 'success');
                    Message.reloadPage();
                } else {
                    Message.showResult($result, wuunder_admin.i18n.error_prefix + ' ' + response.data, 'error');
                }
            },
            error: () => {
                Message.showResult($result, wuunder_admin.i18n.error_prefix + ' ' + wuunder_admin.i18n.failed_disconnect, 'error');
            },
            complete: () => {
                Message.enableButton($button);
                Message.autoHideResult($result);
            }
        });
    }
}

export default DisconnectManager;
