/**
 * Message Utility
 * Handles common message display functionality
 */
export class Message {
    static showResult($result, message, type = 'info') {
        const color = this.getColorForType(type);
        $result.html(`<span style="color: ${color};">${message}</span>`);
    }

    static getColorForType(type) {
        const colors = {
            'error': 'red',
            'success': 'green',
            'warning': 'orange',
            'info': 'inherit',
            'loading': 'inherit'
        };
        return colors[type] || colors.info;
    }

    static autoHideResult($result, delay = 5000) {
        setTimeout(() => {
            $result.fadeOut();
        }, delay);
    }

    static disableButton($button) {
        $button.prop('disabled', true);
    }

    static enableButton($button) {
        $button.prop('disabled', false);
    }

    static reloadPage(delay = 1500) {
        setTimeout(() => {
            window.location.reload();
        }, delay);
    }
}
