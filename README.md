# Wuunder Shipping Plugin

This is the Private Github README file for the Wuunder Plugin.

Recommended knowledge:
- [`CLI.MD`](CLI.MD) contains a few scripts that help clean up the database for easy testing, viewing of database entries and endpoints.
- [`NOTES.MD`](NOTES.MD) contains insights and knowledge on API communication
- [`DIAGRAM.MD`](DIAGRAM.MD) Shows code structure in a diagram

A WordPress/WooCommerce plugin that integrates with the Wuunder parcel delivery platform. Wuunder is a shipping management system that connects multiple carriers and provides unified shipping services for e-commerce businesses.

This plugin enables WooCommerce stores to offer Wuunder's shipping methods directly at checkout, with real-time carrier selection and pricing. It includes support for pickup point selection with an integrated parcel shop locator and full WooCommerce Blocks compatibility.

## Features

- **Multiple Shipping Methods**: Integrate various Wuunder carriers and shipping options
- **Pickup Point Locator**: Interactive parcel shop locator with iframe integration
- **WooCommerce Blocks Support**: Full compatibility with WooCommerce block-based checkout
- **Real-time Pricing**: Dynamic shipping cost calculation based on carrier rates
- **Admin Configuration**: Easy setup through WooCommerce settings interface

## Requirements

- PHP 7.4+
- WordPress 6.4+
- WooCommerce (required)
- Node.js 16+ (for development)

## Installation

1. Clone or download this repository to `/wp-content/plugins/`
2. Run `composer install` to install PHP dependencies
3. Run `npm install` to install Node.js dependencies
4. Activate the plugin in WordPress admin

## Development

### Build Commands
```bash
npm run dev        # Build SCSS for development
npm run production # Build SCSS for production
npm run watch      # Watch for changes (SCSS + JavaScript)
npm run build-js   # Build JavaScript blocks
npm run start-js   # Start JavaScript development server
npm run build      # Complete build (includes translations, SCSS, and JS)
```

### Code Quality
```bash
composer check-cs  # Check coding standards
composer fix-cs    # Fix coding standards
```

## Release Process

This plugin uses a **release branch workflow** with automated WordPress.org SVN sync:

### Branch Structure

- **`main`** - Development branch (merge all PRs here)
- **`release`** - Production branch (only updated when ready to release)

### Creating a Release

1. **Develop on `main` branch:**
   - Merge PRs to `main` as usual during development
   - Label PRs appropriately:
     - `feature` or `enhancement` - New features (minor version bump)
     - `bug` or `bugfix` - Bug fixes (patch version bump)
     - `major` or `breaking` - Breaking changes (major version bump)
     - `chore` - Maintenance tasks (patch version bump)
     - `docs` - Documentation updates

2. **When ready to release, create a release PR:**
   - Create PR: `main` → `release`
   - Update version number in `wuunder-shipping.php` header
   - Label the PR based on the type of changes included
   - Review all changes that will be released

3. **Merge the release PR:**
   - Merge PR to `release` branch
   - Release Drafter automatically creates/updates a draft release
   - Build workflow automatically triggers and:
     - ✅ Builds production assets (`npm run production`)
     - ✅ Installs production dependencies (`composer install --no-dev`)
     - ✅ Creates plugin zip package
     - ✅ Uploads package to the draft release

4. **Test the release:**
   - Download the zip from the draft release at https://github.com/workonthewinkel/wuunder/releases
   - Test locally to ensure everything works

5. **Publish the release:**
   - Review version number (auto-calculated from PR labels)
   - Review changelog (auto-generated from PR titles)
   - Click "Publish release"
   - This automatically triggers:
     - ✅ Deploy to WordPress.org SVN repository

6. **Sync back to main (automatic):**
   - After merging to `release`, a PR is automatically created: `release` → `main`
   - Review and merge this PR to keep `main` in sync with version bumps
   - This ensures `main` always has the latest version number

### Manual Release Testing

To test the release build without publishing:
```bash
# Install WP-CLI dist-archive package
wp package install wp-cli/dist-archive-command

# Build production assets
composer install --no-dev --optimize-autoloader
npm ci
WP_CLI_ALLOW_ROOT=1 npm run build

# Create distribution package
wp dist-archive . ./wuunder-shipping.zip --plugin-dirname=wuunder-shipping
```

## Configuration

1. Navigate to WooCommerce > Settings > Wuunder
2. Enter your Wuunder API key in the Settings tab
3. Configure available carriers in the Carriers tab
4. Set up shipping methods in WooCommerce > Settings > Shipping
5. Enable pickup point locator for parcel shop delivery options

### Pickup Point Configuration

The plugin includes an integrated pickup point locator that allows customers to select parcel shops during checkout. This feature works with both classic and block-based WooCommerce checkouts.

### Prerequisites for WordPress.org Deployment

- Add `SVN_USERNAME` and `SVN_PASSWORD` secrets to GitHub repository settings
- Plugin must be approved and have an SVN repository at https://plugins.svn.wordpress.org/wuunder-shipping/

## Plugin Structure

- `src/` - PHP source code with PSR-4 autoloading
- `assets/src/` - SCSS and JavaScript source files
- `src/Views/` - Templates

## License

GPL-2.0-or-later
