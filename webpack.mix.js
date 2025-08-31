// webpack.mix.js

const mix = require('laravel-mix');

mix.disableSuccessNotifications();

// Configure webpack for ES6 modules and React
mix.webpackConfig({
    resolve: {
        extensions: ['.js', '.jsx'],
        alias: {
            '@wordpress/element': require.resolve('@wordpress/element'),
            '@wordpress/i18n': require.resolve('@wordpress/i18n'),
            '@wordpress/components': require.resolve('@wordpress/components'),
            '@wordpress/data': require.resolve('@wordpress/data'),
            '@wordpress/plugins': require.resolve('@wordpress/plugins'),
        }
    },
    externals: {
        '@wordpress/element': 'wp.element',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/components': 'wp.components',
        '@wordpress/data': 'wp.data',
        '@wordpress/plugins': 'wp.plugins',
        '@woocommerce/blocks-checkout': 'wc.blocksCheckout',
        '@woocommerce/blocks-registry': 'wc.blocksRegistry',
    }
});

mix.sass('assets/src/scss/admin.scss', 'assets/dist/css/admin.css')
   .sass('assets/src/scss/main.scss', 'assets/dist/css/main.css')
   .sass('assets/src/scss/checkout.scss', 'assets/dist/css/checkout.css')
   .sass('assets/src/scss/blocks-checkout.scss', 'assets/dist/css/blocks/checkout-pickup.css')
   .options({
       processCssUrls: false
   });

// mix.js('assets/src/js/main.js', 'assets/dist/js/main.js');
mix.js('assets/src/js/admin.js', 'assets/dist/js/admin.js')
   .js('assets/src/js/checkout.js', 'assets/dist/js/checkout.js');
