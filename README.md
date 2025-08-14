# Wuunder Shipping Plugin

This is the Private Github README file for the Wuunder Plugin.

Recommended knowledge:
- [`CLI.MD`](CLI.MD) contains a few scripts that help clean up the database for easy testing, viewing of database entries and endpoints.
- [`NOTES.MD`](NOTES.MD) contains insights and knowledge on API communication

A WordPress/WooCommerce plugin that integrates with the Wuunder parcel delivery platform. Wuunder is a shipping management system that connects multiple carriers and provides unified shipping services for e-commerce businesses.

This plugin enables WooCommerce stores to offer Wuunder's shipping methods directly at checkout, with real-time carrier selection and pricing.

## Requirements

- PHP 7.4+
- WordPress 6.4+
- WooCommerce (required)
- Node.js (for development)

## Installation

1. Clone or download this repository to `/wp-content/plugins/`
2. Run `composer install` to install PHP dependencies
3. Run `npm install` to install Node.js dependencies
4. Activate the plugin in WordPress admin

## Development

### Build Commands
```bash
npm run dev        # Build for development
npm run production # Build for production
npm run watch      # Watch for changes
```

### Code Quality
```bash
composer analyse   # Run PHPStan static analysis
composer check-cs  # Check coding standards
composer fix-cs    # Fix coding standards
```

## Configuration

1. Navigate to WooCommerce > Settings > Wuunder
2. Enter your Wuunder API key in the Settings tab
3. Configure available carriers in the Carriers tab
4. Set up shipping methods in WooCommerce > Settings > Shipping

## Plugin Structure

- `src/` - PHP source code with PSR-4 autoloading
- `assets/src/` - SCSS and JavaScript source files
- `assets/dist/` - Compiled CSS and JavaScript
- `src/Views/` - Admin interface templates

## License

GPL-2.0-or-later
