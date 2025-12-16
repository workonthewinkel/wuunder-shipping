# Wuunder Shipping Plugin

This is the Private Github README file for the Wuunder Plugin.

## Installation

1. Clone or download this repository to `/wp-content/plugins/`
2. Run `composer install` to install PHP dependencies
3. Run `npm install` to install Node.js dependencies
4. Activate the plugin in WordPress admin

## WP-CLI Commands

```bash
wp wuunder clear              # Remove all Wuunder settings
wp wuunder delete_orders --yes # Delete all shop orders
```

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

### Debug Mode

When running `composer install` with dev dependencies, a Debug tab appears in WooCommerce > Settings > Wuunder. This shows:

- **Wuunder API - Shipping Carriers** - Carriers from API (non-parcelshop)
- **Wuunder API - Pickup Carriers** - Carriers with `accepts_parcelshop_delivery = true`
- **Wuunder Shipping Methods** - Configured shipping methods with carrier and enabled status
- **Wuunder Pickup Methods** - Configured pickup methods with available carriers
- **REST API Output** - Last 10 orders as seen by external services

To test carrier unavailability, create an mu-plugin:

```php
<?php
// wp-content/mu-plugins/wuunder-debug-exclude-carriers.php
add_filter( 'wuunder_api_carriers', function( $carriers ) {
    $exclude = [
        'DHL_PARCEL:DHL_CONNECT_2SHOP',
        'DPD:DPD_HOME',
    ];

    foreach ( $exclude as $carrier_id ) {
        unset( $carriers[ $carrier_id ] );
    }

    return $carriers;
} );
```

Then refresh carriers in Wuunder settings to see the disable logic in action.

## Release Process

This plugin uses a **release branch workflow** with automated WordPress.org SVN sync:

### Branch Structure

- **`main`** - Development branch (merge all PRs here)
- **`release`** - Production branch (only updated when ready to release)

### Creating a Release

1. **Develop on `main` branch:**
   - Merge PRs to `main` as usual during development

2. **When ready to release, create a release PR:**
   - Create new `versions/x.x.x` branch based on main.
   - **Update version number in `wuunder-shipping.php` header** (e.g., `0.7.2` → `0.7.3`)
   - **Update `readme.txt` changelog section** with changes for this release
   - This will fire some workflows, wait until completed to inspect any issues.

3. **Merge the release PR:**
   - Merge PR to `release` branch
   - Two workflows automatically trigger:
     - **Release Drafter**: Creates/updates draft release (tagged with version from plugin file)
     - **Build Release Package**: Builds production assets and attaches zip to the draft

4. **Test the release:**
   - Wait a few minutes for the "Build Release Package" workflow to complete
   - Download the zip from the draft release at https://github.com/workonthewinkel/wuunder/releases
   - Test locally or in staging environment to ensure everything works

5. **Publish the release:**
   - Review version number (read from `wuunder-shipping.php`)
   - Review changelog (auto-generated from PR titles and labels)
   - Test the attached zip file one final time
   - Click "Publish release"
   - This automatically triggers two workflows:
     - **Deploy to WordPress.org**: Deploys the tested zip to WordPress.org SVN (~30 seconds)
     - **Sync Release to Main**: Automatically pushes `release` to `main` (no PR needed)


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

### Version Management

Version numbers are **manually managed** in the `wuunder-shipping.php` file header:
- Update the version in your release PR (`main` → `release`)
- Release Drafter reads this version and uses it for the GitHub release tag
- PR labels are used only for organizing the changelog, not for version calculation
- Follow [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`

### Changelog Management

**WordPress.org readme.txt:**
- Maintained manually in `readme.txt` under the `== Changelog ==` section
- Update the changelog when creating your release PR (before merging)
- A bot posts a checklist reminder to help ensure you don't forget
- Ensures WordPress.org users see proper changelog on the plugin page

**GitHub Releases:**
- Automatically generated from merged PR titles and labels
- Organized into categories (Features, Bug Fixes, Maintenance, Documentation)
- Created when you publish the release

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

## Filters

### `wuunder_api_carriers`

Filter carriers returned from the Wuunder API before they are processed.

```php
add_filter( 'wuunder_api_carriers', function( $carriers ) {
    // Remove specific carrier products
    unset( $carriers['DHL_PARCEL:DHL_CONNECT_2SHOP'] );

    return $carriers;
} );
```

**Parameters:**
- `$carriers` (array) - Associative array of carriers keyed by method ID (`carrier_code:carrier_product_code`)

**Use cases:**
- Testing carrier unavailability scenarios
- Filtering out specific carriers for certain environments

### `wuunder_pickup_available_carriers`

Filter available carriers shown in pickup method settings.

```php
add_filter( 'wuunder_pickup_available_carriers', function( $options ) {
    // $options is array of carrier_code => carrier_name
    return $options;
} );
```

## Plugin Structure

- `src/` - PHP source code with PSR-4 autoloading
- `assets/src/` - SCSS and JavaScript source files
- `src/Views/` - Templates

## License

GPL-2.0-or-later
