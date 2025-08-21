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
   .sass('assets/src/scss/main.scss', 'assets/dist/css/main.css')
   .options({
       processCssUrls: false
   });

// mix.js('assets/src/js/main.js', 'assets/dist/js/main.js');
mix.js('assets/src/js/admin.js', 'assets/dist/js/admin.js');
