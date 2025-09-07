<?php
/**
 * Settings tabs navigation
 *
 * @package Wuunder\Shipping
 * @var string $current_section Current active section
 * @var bool   $has_api_key     Whether API key is configured
 * @var array  $available_sections Available sections
 */

defined( 'ABSPATH' ) || exit;

// Default section labels
$section_labels = apply_filters(
	'wuunder_settings_section_labels',
	[
		'carriers' => __( 'Carriers', 'wuunder-shipping' ),
		'settings' => __( 'Settings', 'wuunder-shipping' ),
	]
);
?>

<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
	<?php foreach ( $available_sections as $section_key ) : ?>
		<?php if ( isset( $section_labels[ $section_key ] ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=' . $section_key ) ); ?>" 
				class="nav-tab <?php echo $current_section === $section_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $section_labels[ $section_key ] ); ?>
			</a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>