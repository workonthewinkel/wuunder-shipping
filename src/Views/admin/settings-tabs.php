<?php
/**
 * Settings tabs navigation
 *
 * @package Wuunder\Shipping
 * @var string $current_section Current active section
 * @var bool   $has_api_key     Whether API key is configured
 */

defined( 'ABSPATH' ) || exit;
?>

<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=carriers' ) ); ?>" 
		class="nav-tab <?php echo $current_section === 'carriers' ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Carriers', 'wuunder-shipping' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=settings' ) ); ?>" 
		class="nav-tab <?php echo $current_section === 'settings' ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Settings', 'wuunder-shipping' ); ?>
	</a>
</nav>