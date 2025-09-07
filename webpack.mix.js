// webpack.mix.js

const mix = require('laravel-mix');

mix.disableSuccessNotifications();

// Configure webpack for ES6 modules
mix.webpackConfig({
    resolve: {
        extensions: ['.js', '.jsx']
    }
});

mix.sass('assets/src/scss/admin.scss', 'assets/dist/css/admin.css')
   .sass('assets/src/scss/checkout.scss', 'assets/dist/css/checkout.css')
   .sass('assets/src/scss/blocks-checkout.scss', 'assets/dist/css/blocks.css')
   .sass('assets/src/scss/debug.scss', 'assets/dist/css/debug.css')
   .options({
       processCssUrls: false
   });

// JavaScript files are now built by webpack.config.js (WordPress Scripts)
