import { Message } from '../utils/Message.js';

/**
 * CarrierList Component
 * Handles refreshing and managing Wuunder carriers
 */
class CarrierList {
    constructor() {
        this.button = '#wuunder-refresh-carriers';
        this.result = '#wuunder-carriers-result';
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
        });
    }

    bindEvents() {
        $(this.button).on('click', (e) => this.handleRefreshCarriers(e));
    }

    handleRefreshCarriers(e) {
        e.preventDefault();
        
        const $button = $(this.button);
        const $result = $(this.result);
        
        Message.disableButton($button);
        Message.showResult($result, '<span class="spinner is-active"></span> ' + wuunder_admin.i18n.refreshing_carriers, 'loading');
        
        this.refreshCarriers($button, $result);
    }

    refreshCarriers($button, $result) {
        $.ajax({
            url: wuunder_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wuunder_refresh_carriers',
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
                Message.showResult($result, wuunder_admin.i18n.error_prefix + ' ' + wuunder_admin.i18n.failed_refresh_carriers, 'error');
            },
            complete: () => {
                Message.enableButton($button);
                Message.autoHideResult($result);
            }
        });
    }
}

export default CarrierList;
