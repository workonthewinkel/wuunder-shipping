const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'blocks': path.resolve(__dirname, 'assets/src/js/blocks.js'),
        'admin': path.resolve(__dirname, 'assets/src/js/admin.js'),
        'checkout': path.resolve(__dirname, 'assets/src/js/checkout.js')
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, 'assets/dist/js')
    },
    externals: {
        ...defaultConfig.externals,
        '@wordpress/element': 'wp.element',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/data': 'wp.data',
        '@wordpress/plugins': 'wp.plugins'
    }
};