<?php
/**
 * Plugin Name: Merk Signage Studio
 * Description: Isolated, builder-free signage editor for the TEST_ADMINA page.
 * Version: 0.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Seehank
 * Text Domain: merk-signage-studio
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MERK_SIGNAGE_STUDIO_FILE', __FILE__ );
define( 'MERK_SIGNAGE_STUDIO_URL', plugin_dir_url( __FILE__ ) );
define( 'MERK_SIGNAGE_STUDIO_VERSION', '0.1.1' );

final class Merk_Signage_Studio {
	const TEST_PAGE_ID = 82477;
	const META_KEY     = '_merk_signage_studio';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ), 10, 2 );
		add_action( 'save_post_page', array( $this, 'save_page' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'the_content', array( $this, 'render_signage' ), 100 );
		add_action( 'admin_menu', array( $this, 'add_studio_menu' ) );
	}

	/**
	 * Add settings only to the isolated test page.
	 *
 * @param string       $post_type Post type name.
	 * @param WP_Post      $post      Current post.
	 */
	public function add_metabox( $post_type, $post ) {
		if ( 'page' !== $post_type || ! ( $post instanceof WP_Post ) || self::TEST_PAGE_ID !== (int) $post->ID || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		add_meta_box(
			'merk-signage-studio',
			__( 'Signage Studio (test)', 'merk-signage-studio' ),
			array( $this, 'render_metabox' ),
			'page',
			'normal',
			'high'
		);
	}

	/**
	 * Render the test-page settings form.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_metabox( $post ) {
		if ( ! ( $post instanceof WP_Post ) || self::TEST_PAGE_ID !== (int) $post->ID || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$can_manage = current_user_can( 'manage_options' );
		$config = get_post_meta( $post->ID, self::META_KEY, true );
		$config = is_array( $config ) ? $config : array();
		$layers = isset( $config['layers'] ) && is_array( $config['layers'] ) ? $config['layers'] : array();

		wp_nonce_field( 'merk_signage_studio_save', 'merk_signage_studio_nonce' );
		?>
		<div class="merk-signage-studio-metabox">
			<?php if ( $can_manage ) : ?>
			<p>
				<label for="merk-signage-background-url"><strong><?php esc_html_e( 'Background image URL', 'merk-signage-studio' ); ?></strong></label><br>
				<input class="widefat" id="merk-signage-background-url" name="merk_signage[background_url]" type="url" value="<?php echo esc_attr( isset( $config['background_url'] ) ? $config['background_url'] : '' ); ?>" placeholder="https://…">
			</p>
			<p class="description"><?php esc_html_e( 'Coordinates use the fixed 1920 × 1080 design canvas. This editor applies only to TEST_ADMINA.', 'merk-signage-studio' ); ?></p>
			<div class="merk-signage-layout-preview">
				<h3><?php esc_html_e( 'Visual layout', 'merk-signage-studio' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Drag a text layer directly in the preview. Its X and Y coordinates are updated automatically.', 'merk-signage-studio' ); ?></p>
				<div class="merk-signage-preview-viewport"><div id="merk-signage-canvas"></div></div>
			</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Edit the content below. The background and layout are managed by an administrator.', 'merk-signage-studio' ); ?></p>
			<?php endif; ?>
			<div id="merk-signage-layers" data-next-index="<?php echo esc_attr( count( $layers ) ); ?>">
				<?php foreach ( $layers as $index => $layer ) : ?>
					<?php $this->render_layer_row( (int) $index, $layer ); ?>
				<?php endforeach; ?>
			</div>
			<?php if ( $can_manage ) : ?>
				<p><button class="button" id="merk-signage-add-layer" type="button"><?php esc_html_e( 'Add text layer', 'merk-signage-studio' ); ?></button></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one editable text-layer row.
	 *
	 * @param int   $index Row index.
	 * @param array $layer Layer values.
	 */
	private function render_layer_row( $index, $layer ) {
		$layer = wp_parse_args(
			is_array( $layer ) ? $layer : array(),
			array(
				'label'       => '',
				'text'        => '',
				'x'           => 0,
				'y'           => 0,
				'width'       => 300,
				'font_size'   => 32,
				'color'       => '#ffffff',
				'font_weight' => 400,
				'align'       => 'left',
			)
		);
		$name = 'merk_signage[layers][' . $index . ']';
		if ( ! current_user_can( 'manage_options' ) ) {
			?>
			<div class="merk-operator-layer">
				<label><strong><?php echo esc_html( $layer['label'] ); ?></strong><br>
					<textarea class="widefat" name="<?php echo esc_attr( $name ); ?>[text]" rows="3"><?php echo esc_textarea( $layer['text'] ); ?></textarea>
				</label>
			</div>
			<?php
			return;
		}
		?>
		<fieldset class="merk-signage-layer-row" data-layer-key="<?php echo esc_attr( $index ); ?>">
			<legend><?php esc_html_e( 'Text layer', 'merk-signage-studio' ); ?></legend>
			<p><label><?php esc_html_e( 'Label', 'merk-signage-studio' ); ?><br><input name="<?php echo esc_attr( $name ); ?>[label]" type="text" value="<?php echo esc_attr( $layer['label'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'Text', 'merk-signage-studio' ); ?><br><textarea name="<?php echo esc_attr( $name ); ?>[text]" rows="3"><?php echo esc_textarea( $layer['text'] ); ?></textarea></label></p>
			<p class="merk-signage-grid">
				<label><?php esc_html_e( 'X', 'merk-signage-studio' ); ?><input name="<?php echo esc_attr( $name ); ?>[x]" type="number" min="0" max="1920" value="<?php echo esc_attr( $layer['x'] ); ?>"></label>
				<label><?php esc_html_e( 'Y', 'merk-signage-studio' ); ?><input name="<?php echo esc_attr( $name ); ?>[y]" type="number" min="0" max="1080" value="<?php echo esc_attr( $layer['y'] ); ?>"></label>
				<label><?php esc_html_e( 'Width', 'merk-signage-studio' ); ?><input name="<?php echo esc_attr( $name ); ?>[width]" type="number" min="1" max="1920" value="<?php echo esc_attr( $layer['width'] ); ?>"></label>
				<label><?php esc_html_e( 'Font size', 'merk-signage-studio' ); ?><input name="<?php echo esc_attr( $name ); ?>[font_size]" type="number" min="1" max="300" value="<?php echo esc_attr( $layer['font_size'] ); ?>"></label>
				<label><?php esc_html_e( 'Color', 'merk-signage-studio' ); ?><input name="<?php echo esc_attr( $name ); ?>[color]" type="color" value="<?php echo esc_attr( $layer['color'] ); ?>"></label>
				<label><?php esc_html_e( 'Weight', 'merk-signage-studio' ); ?><select name="<?php echo esc_attr( $name ); ?>[font_weight]"><?php $this->render_options( array( 400, 500, 600, 700, 800 ), $layer['font_weight'] ); ?></select></label>
				<label><?php esc_html_e( 'Align', 'merk-signage-studio' ); ?><select name="<?php echo esc_attr( $name ); ?>[align]"><?php $this->render_options( array( 'left', 'center', 'right' ), $layer['align'] ); ?></select></label>
			</p>
			<p><button class="button-link-delete merk-signage-remove" type="button"><?php esc_html_e( 'Remove layer', 'merk-signage-studio' ); ?></button></p>
		</fieldset>
		<?php
	}

	/**
	 * Render select options.
	 *
	 * @param array        $values Values.
	 * @param string|int   $selected Current value.
	 */
	private function render_options( $values, $selected ) {
		foreach ( $values as $value ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $value ), selected( $selected, $value, false ) );
		}
	}

	/**
	 * Save only configuration belonging to the test page.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function save_page( $post_id, $post, $update ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		if ( self::TEST_PAGE_ID !== (int) $post_id || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['merk_signage_studio_nonce'] ) || ! is_string( $_POST['merk_signage_studio_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['merk_signage_studio_nonce'] ) ), 'merk_signage_studio_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || 'page' !== $post->post_type ) {
			return;
		}
		if ( ! isset( $_POST['merk_signage'] ) || ! is_array( $_POST['merk_signage'] ) ) {
			return;
		}
		$raw_config = wp_unslash( $_POST['merk_signage'] );
		if ( current_user_can( 'manage_options' ) ) {
			if ( ! $this->valid_config_input( $raw_config ) ) {
				return;
			}
			update_post_meta( $post_id, self::META_KEY, $this->sanitize_config( $raw_config ) );
			return;
		}
		$existing = get_post_meta( $post_id, self::META_KEY, true );
		if ( empty( $existing ) || ! is_array( $existing ) ) {
			return;
		}
		$existing_layers  = isset( $existing['layers'] ) && is_array( $existing['layers'] ) ? $existing['layers'] : array();
		$submitted_layers = isset( $raw_config['layers'] ) && is_array( $raw_config['layers'] ) ? $raw_config['layers'] : array();
		if ( count( $existing_layers ) !== count( $submitted_layers ) ) {
			return;
		}
		$count         = count( $existing_layers );
		$expected_keys = $count ? range( 0, $count - 1 ) : array();
		if ( array_keys( $existing_layers ) !== $expected_keys || array_keys( $submitted_layers ) !== $expected_keys ) {
			return;
		}
		if ( ! isset( $raw_config['layers'] ) || ! is_array( $raw_config['layers'] ) ) {
			return;
		}
		$updated_existing = $existing;
		foreach ( $existing_layers as $index => $layer ) {
			if ( ! is_array( $layer ) || ! isset( $submitted_layers[ $index ] ) || ! is_array( $submitted_layers[ $index ] ) ) {
				return;
			}
			$submitted_layer = $submitted_layers[ $index ];
			if ( ! isset( $submitted_layer['text'] ) || ! is_string( $submitted_layer['text'] ) || strlen( $submitted_layer['text'] ) > 8000 ) {
				return;
			}
			$updated_existing['layers'][ $index ]['text'] = sanitize_textarea_field( $submitted_layer['text'] );
		}
		update_post_meta( $post_id, self::META_KEY, $updated_existing );
	}

	/**
	 * Validate the complete admin form before calling scalar sanitizers.
	 *
	 * @param mixed $raw Submitted configuration.
	 * @return bool
	 */
	private function valid_config_input( $raw ) {
		if ( ! is_array( $raw ) || ! isset( $raw['background_url'] ) || ! is_string( $raw['background_url'] ) || strlen( $raw['background_url'] ) > 2048 ) {
			return false;
		}
		if ( ! isset( $raw['layers'] ) || ! is_array( $raw['layers'] ) || count( $raw['layers'] ) > 100 ) {
			return false;
		}
		$string_fields  = array( 'label', 'text', 'color', 'align' );
		$numeric_fields = array( 'x', 'y', 'width', 'font_size', 'font_weight' );
		foreach ( $raw['layers'] as $layer ) {
			if ( ! is_array( $layer ) ) {
				return false;
			}
			foreach ( $string_fields as $field ) {
				if ( ! isset( $layer[ $field ] ) || ! is_string( $layer[ $field ] ) || strlen( $layer[ $field ] ) > ( 'text' === $field ? 8000 : 255 ) ) {
					return false;
				}
			}
			foreach ( $numeric_fields as $field ) {
				if ( ! isset( $layer[ $field ] ) || ! is_scalar( $layer[ $field ] ) || ! is_numeric( $layer[ $field ] ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Sanitize stored configuration.
	 *
	 * @param array $raw Raw form data.
	 * @return array
	 */
	private function sanitize_config( $raw ) {
		$config = array(
			'background_url' => esc_url_raw( isset( $raw['background_url'] ) ? $raw['background_url'] : '' ),
			'layers'         => array(),
		);
		$layers = isset( $raw['layers'] ) && is_array( $raw['layers'] ) ? $raw['layers'] : array();

		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$label = sanitize_text_field( isset( $layer['label'] ) ? $layer['label'] : '' );
			$text  = sanitize_textarea_field( isset( $layer['text'] ) ? $layer['text'] : '' );
			if ( '' === $label && '' === $text ) {
				continue;
			}

			$color  = sanitize_hex_color( isset( $layer['color'] ) ? $layer['color'] : '' );
			$weight = absint( isset( $layer['font_weight'] ) ? $layer['font_weight'] : 400 );
			$align  = sanitize_key( isset( $layer['align'] ) ? $layer['align'] : 'left' );

			$config['layers'][] = array(
				'label'       => $label,
				'text'        => $text,
				'x'           => min( 1920, max( 0, absint( isset( $layer['x'] ) ? $layer['x'] : 0 ) ) ),
				'y'           => min( 1080, max( 0, absint( isset( $layer['y'] ) ? $layer['y'] : 0 ) ) ),
				'width'       => min( 1920, max( 1, absint( isset( $layer['width'] ) ? $layer['width'] : 300 ) ) ),
				'font_size'   => min( 300, max( 1, absint( isset( $layer['font_size'] ) ? $layer['font_size'] : 32 ) ) ),
				'color'       => $color ? $color : '#ffffff',
				'font_weight' => in_array( $weight, array( 400, 500, 600, 700, 800 ), true ) ? $weight : 400,
				'align'       => in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left',
			);
		}

		return $config;
	}

	/**
	 * Load the repeatable-fields script only in the test editor.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix || ! isset( $_GET['post'] ) || ! is_scalar( $_GET['post'] ) || self::TEST_PAGE_ID !== absint( $_GET['post'] ) || ! current_user_can( 'edit_post', self::TEST_PAGE_ID ) ) {
			return;
		}
		wp_enqueue_style( 'merk-signage-studio-admin', MERK_SIGNAGE_STUDIO_URL . 'assets/signage.css', array(), MERK_SIGNAGE_STUDIO_VERSION );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'merk-signage-studio-admin', MERK_SIGNAGE_STUDIO_URL . 'assets/admin.js', array(), MERK_SIGNAGE_STUDIO_VERSION, true );
		wp_localize_script(
			'merk-signage-studio-admin',
			'MerkSignageStudio',
			array(
				'rowTemplate' => $this->get_layer_template(),
			)
		);
	}

	/**
	 * Get a clean HTML template for a new repeatable row.
	 *
	 * @return string
	 */
	private function get_layer_template() {
		ob_start();
		$this->render_layer_row( '__INDEX__', array() );
		return (string) ob_get_clean();
	}

	/**
	 * Load front-end styles only for TEST_ADMINA.
	 */
	public function enqueue_frontend_assets() {
		if ( is_page( self::TEST_PAGE_ID ) ) {
			wp_enqueue_style( 'merk-signage-studio', MERK_SIGNAGE_STUDIO_URL . 'assets/signage.css', array(), MERK_SIGNAGE_STUDIO_VERSION );
		}
	}

	/**
	 * Replace Brizy output only on the test page.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	public function render_signage( $content ) {
		if ( ! is_page( self::TEST_PAGE_ID ) || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		$config = get_post_meta( self::TEST_PAGE_ID, self::META_KEY, true );
		$config = is_array( $config ) ? $config : array();
		$background_url = isset( $config['background_url'] ) ? esc_url( $config['background_url'] ) : '';
		$layers         = isset( $config['layers'] ) && is_array( $config['layers'] ) ? $config['layers'] : array();

		// Activating the plugin must not change the copied Brizy test page until
		// an editor deliberately supplies a new signage background.
		if ( '' === $background_url ) {
			return $content;
		}

		$stage_style    = $background_url ? 'background-image:url(' . $background_url . ');' : '';

		ob_start();
		?>
		<div class="merk-signage-frame"><div class="merk-signage-stage"<?php echo $stage_style ? ' style="' . esc_attr( $stage_style ) . '"' : ''; ?>>
			<?php foreach ( $layers as $layer ) : ?>
				<?php
				$style = sprintf(
					'--x:%1$dpx;--y:%2$dpx;--w:%3$dpx;--font-size:%4$dpx;--color:%5$s;--font-weight:%6$d;--align:%7$s;',
					(int) $layer['x'],
					(int) $layer['y'],
					(int) $layer['width'],
					(int) $layer['font_size'],
					esc_attr( $layer['color'] ),
					(int) $layer['font_weight'],
					esc_attr( $layer['align'] )
				);
				?>
				<div class="merk-signage-layer" style="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $layer['text'] ); ?></div>
			<?php endforeach; ?>
		</div></div>
		<script>
		(function () {
			var stage = document.querySelector('.merk-signage-stage');
			if (!stage) { return; }
			function resizeStage() {
				stage.style.setProperty('--scale', Math.min(window.innerWidth / 1920, window.innerHeight / 1080));
			}
			resizeStage();
			window.addEventListener('resize', resizeStage);
		}());
		</script>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Add the entry point only to users allowed to edit this test page.
	 */
	public function add_studio_menu() {
		if ( ! current_user_can( 'edit_post', self::TEST_PAGE_ID ) ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=page',
			__( 'Signage Studio', 'merk-signage-studio' ),
			__( 'Signage Studio', 'merk-signage-studio' ),
			'read',
			'merk-signage-studio',
			array( $this, 'render_studio_redirect' )
		);
	}

	/**
	 * Redirect the studio menu item to the isolated editor.
	 */
	public function render_studio_redirect() {
		if ( ! current_user_can( 'edit_post', self::TEST_PAGE_ID ) ) {
			wp_die( esc_html__( 'You cannot edit this signage.', 'merk-signage-studio' ) );
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . self::TEST_PAGE_ID . '&action=edit' ) );
		exit;
	}
}

new Merk_Signage_Studio();
