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

### Automated Workflows

The release process uses several automated GitHub Actions workflows:

1. **Release Drafter** (`.github/workflows/release-drafter.yml`)
   - Triggers: Push to `release` branch
   - Creates/updates draft releases with auto-generated changelogs

2. **Release PR Checklist** (`.github/workflows/release-pr-changelog.yml`)
   - Triggers: PR opened to `release` branch
   - Posts a checklist reminder to verify version and changelog are updated

3. **Build Release Package** (`.github/workflows/build-release.yml`)
   - Triggers: Push to `release` branch
   - Builds production assets and attaches zip to draft release
   - Can be manually triggered to rebuild

4. **Deploy to WordPress.org** (`.github/workflows/wordpress-plugin-deploy.yml`)
   - Triggers: Release published
   - Downloads pre-built zip from release assets
   - Deploys to WordPress.org SVN repository

5. **Sync Release to Main** (`.github/workflows/sync-release-to-main.yml`)
   - Triggers: Release published
   - Automatically pushes `release` branch to `main` to keep them in sync
   - No PR created (already reviewed in release PR)

### Creating a Release

1. **Develop on `main` branch:**
   - Merge PRs to `main` as usual during development
   - Label PRs appropriately for changelog organization:
     - `feature` or `enhancement` - New features
     - `bug` or `bugfix` - Bug fixes
     - `major` or `breaking` - Breaking changes
     - `chore` - Maintenance tasks
     - `docs` - Documentation updates

2. **When ready to release, create a release PR:**
   - Create PR: `main` → `release`
   - **Update version number in `wuunder-shipping.php` header** (e.g., `0.7.2` → `0.7.3`)
   - **Update `readme.txt` changelog section** with changes for this release
   - A bot will automatically post a **checklist reminder** (you can check it off once done)
   - Review all changes that will be released

3. **Merge the release PR:**
   - Merge PR to `release` branch
   - Two workflows automatically trigger:
     - **Release Drafter**: Creates/updates draft release (tagged with version from plugin file)
     - **Build Release Package**: Builds production assets and attaches zip to the draft
       - Installs production dependencies (`composer install --no-dev`)
       - Builds production assets (`npm run production`)
       - Creates plugin zip package
       - Uploads package to the draft release (may take 1-2 minutes)

4. **Test the release:**
   - Wait 1-2 minutes for the "Build Release Package" workflow to complete
   - Download the zip from the draft release at https://github.com/workonthewinkel/wuunder/releases
   - Test locally to ensure everything works
   - **Note**: If zip is missing, manually trigger the "Build Release Package" workflow from the Actions tab

5. **Publish the release:**
   - Review version number (read from `wuunder-shipping.php`)
   - Review changelog (auto-generated from PR titles and labels)
   - Test the attached zip file one final time
   - Click "Publish release"
   - This automatically triggers two workflows:
     - **Deploy to WordPress.org**: Deploys the tested zip to WordPress.org SVN (~30 seconds)
     - **Sync Release to Main**: Automatically pushes `release` to `main` (no PR needed)

That's it! The `main` branch is now automatically synced and you're done.

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
