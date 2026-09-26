<?php
/**
 * Plugin Name: Merk Signage Studio
 * Description: Builder-free editor for selected digital-signage pages.
 * Version: 0.3.0
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
define( 'MERK_SIGNAGE_STUDIO_VERSION', '0.3.0' );

final class Merk_Signage_Studio {
	const TEST_PAGE_ID = 82477;
	const META_KEY     = '_merk_signage_studio';
	const OPTION_KEY   = 'merk_signage_studio_pages';

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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'body_class', array( $this, 'add_signage_body_class' ) );
	}

	/**
	 * Return valid signage page IDs. A fresh install starts with TEST_ADMINA.
	 *
	 * @return int[]
	 */
	private function get_managed_page_ids() {
		$page_ids = get_option( self::OPTION_KEY, array( self::TEST_PAGE_ID ) );
		if ( ! is_array( $page_ids ) ) {
			return array();
		}

		$valid_ids = array();
		foreach ( $page_ids as $page_id ) {
			$page_id = absint( $page_id );
			if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
				$valid_ids[] = $page_id;
			}
		}

		return array_values( array_unique( $valid_ids ) );
	}

	/**
	 * Check whether the plugin is enabled for a page.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private function is_managed_page( $page_id ) {
		return in_array( (int) $page_id, $this->get_managed_page_ids(), true );
	}

	/**
	 * Register the administrator-only managed pages setting.
	 */
	public function register_settings() {
		register_setting(
			'merk_signage_studio_settings',
			self::OPTION_KEY,
			array( 'sanitize_callback' => array( $this, 'sanitize_managed_pages' ) )
		);
	}

	/**
	 * Accept only existing WordPress pages in the signage list.
	 *
	 * @param mixed $submitted Submitted page IDs.
	 * @return int[]
	 */
	public function sanitize_managed_pages( $submitted ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->get_managed_page_ids();
		}
		// The hidden empty value lets an administrator intentionally deselect every page.
		if ( '' === $submitted ) {
			$submitted = array();
		}
		if ( ! is_array( $submitted ) ) {
			return $this->get_managed_page_ids();
		}

		$page_ids = array();
		foreach ( $submitted as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$page_id = absint( $value );
			if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
				$page_ids[] = $page_id;
			}
		}

		return array_values( array_unique( $page_ids ) );
	}

	/**
	 * Add settings only to pages selected for this plugin.
	 *
	 * @param string       $post_type Post type name.
	 * @param WP_Post      $post      Current post.
	 */
	public function add_metabox( $post_type, $post ) {
		if ( 'page' !== $post_type || ! ( $post instanceof WP_Post ) || ! $this->is_managed_page( $post->ID ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		add_meta_box(
			'merk-signage-studio',
			__( 'Signage Studio', 'merk-signage-studio' ),
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
		if ( ! ( $post instanceof WP_Post ) || ! $this->is_managed_page( $post->ID ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$can_manage = current_user_can( 'manage_options' );
		$config = get_post_meta( $post->ID, self::META_KEY, true );
		$config = is_array( $config ) ? $config : array();
		$layers = isset( $config['layers'] ) && is_array( $config['layers'] ) ? $config['layers'] : array();

		$background_id = isset( $config['background_id'] ) ? absint( $config['background_id'] ) : 0;
		$background_url = $this->get_background_url( $config );

		wp_nonce_field( 'merk_signage_studio_save', 'merk_signage_studio_nonce' );
		?>
		<div class="merk-signage-studio-metabox">
			<?php if ( $can_manage ) : ?>
			<div>
				<strong><?php esc_html_e( 'Background image', 'merk-signage-studio' ); ?></strong>
				<input type="hidden" name="merk_signage[background_id]" id="merk-signage-background-id" value="<?php echo esc_attr( $background_id ); ?>">
				<input type="hidden" name="merk_signage[background_removed]" id="merk-signage-background-removed" value="0">
				<div class="merk-signage-background-preview" id="merk-signage-background-preview" style="margin-top: 10px; max-width: 300px;">
					<?php if ( $background_url ) : ?>
						<img src="<?php echo esc_url( $background_url ); ?>" alt="<?php esc_attr_e( 'Background preview', 'merk-signage-studio' ); ?>" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'No background selected.', 'merk-signage-studio' ); ?></span>
					<?php endif; ?>
				</div>
				<p style="margin-top: 10px;">
					<button type="button" class="button" id="merk-signage-select-background"><?php esc_html_e( 'Select Background', 'merk-signage-studio' ); ?></button>
					<button type="button" class="button" id="merk-signage-remove-background"><?php esc_html_e( 'Remove Background', 'merk-signage-studio' ); ?></button>
				</p>
			</div>
			<p class="description"><?php esc_html_e( 'Coordinates use the fixed 1920 × 1080 design canvas. Manage which pages use this editor in Signage Settings.', 'merk-signage-studio' ); ?></p>
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
					<?php $this->render_layer_row( (int) $index, $layer, $post->ID ); ?>
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
	private function render_layer_row( $index, $layer, $post_id = 0 ) {
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
				'source'      => 'static',
				'acf_key'     => '',
			)
		);

		if ( ! in_array( $layer['source'], array( 'static', 'acf' ), true ) ) {
			$layer['source'] = 'static';
		}

		$name = 'merk_signage[layers][' . $index . ']';
		$can_manage = current_user_can( 'manage_options' );

		if ( ! $can_manage ) {
			?>
			<div class="merk-operator-layer">
				<label><strong><?php echo esc_html( $layer['label'] ); ?></strong><br>
				<?php if ( 'acf' === $layer['source'] ) : ?>
					<span class="merk-operator-layer-text"><?php echo esc_html( $this->get_layer_text( $layer, $post_id ) ); ?></span>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[text]" value="<?php echo esc_attr( $layer['text'] ); ?>">
				<?php else : ?>
					<textarea class="widefat" name="<?php echo esc_attr( $name ); ?>[text]" rows="3"><?php echo esc_textarea( $layer['text'] ); ?></textarea>
				<?php endif; ?>
				</label>
			</div>
			<?php
			return;
		}

		$acf_fields = $this->get_acf_fields( $post_id );
		?>
		<fieldset class="merk-signage-layer-row" data-layer-key="<?php echo esc_attr( $index ); ?>">
			<legend><?php esc_html_e( 'Text layer', 'merk-signage-studio' ); ?></legend>
			<p><label><?php esc_html_e( 'Label', 'merk-signage-studio' ); ?><br><input name="<?php echo esc_attr( $name ); ?>[label]" type="text" value="<?php echo esc_attr( $layer['label'] ); ?>"></label></p>

			<p class="merk-signage-source-row">
				<label><?php esc_html_e( 'Source', 'merk-signage-studio' ); ?>
					<select name="<?php echo esc_attr( $name ); ?>[source]" class="merk-signage-source-select">
						<option value="static" <?php selected( $layer['source'], 'static' ); ?>><?php esc_html_e( 'Static Text', 'merk-signage-studio' ); ?></option>
						<option value="acf" <?php selected( $layer['source'], 'acf' ); ?>><?php esc_html_e( 'ACF Field', 'merk-signage-studio' ); ?></option>
					</select>
				</label>
			</p>

			<p class="merk-signage-static-text" <?php echo 'static' === $layer['source'] ? '' : 'style="display:none;"'; ?>>
				<label><?php esc_html_e( 'Text', 'merk-signage-studio' ); ?><br><textarea name="<?php echo esc_attr( $name ); ?>[text]" rows="3"><?php echo esc_textarea( $layer['text'] ); ?></textarea></label>
			</p>

			<p class="merk-signage-acf-select" <?php echo 'acf' === $layer['source'] ? '' : 'style="display:none;"'; ?>>
				<label><?php esc_html_e( 'ACF Field', 'merk-signage-studio' ); ?>
					<select name="<?php echo esc_attr( $name ); ?>[acf_key]" class="merk-signage-acf-field-select">
						<option value=""><?php esc_html_e( 'Select Field', 'merk-signage-studio' ); ?></option>
						<?php foreach ( $acf_fields as $field_key => $field_label ) : ?>
							<option value="<?php echo esc_attr( $field_key ); ?>" <?php selected( $layer['acf_key'], $field_key ); ?>><?php echo esc_html( $field_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>

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
		if ( ! $this->is_managed_page( $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
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
			if ( ! $this->valid_config_input( $raw_config, $post_id ) ) {
				return;
			}
			$existing = get_post_meta( $post_id, self::META_KEY, true );
			$existing = is_array( $existing ) ? $existing : array();
			update_post_meta( $post_id, self::META_KEY, $this->sanitize_config( $raw_config, $existing, $post_id ) );
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
			$layer_source = isset( $layer['source'] ) && is_string( $layer['source'] ) ? $layer['source'] : 'static';
			if ( 'static' === $layer_source ) {
				$updated_existing['layers'][ $index ]['text'] = sanitize_textarea_field( $submitted_layer['text'] );
			}
		}
		update_post_meta( $post_id, self::META_KEY, $updated_existing );
	}
	/**
	 * Validate the complete admin form before calling scalar sanitizers.
	 *
	 * @param mixed $raw Submitted configuration.
	 * @return bool
	 */
	private function valid_config_input( $raw, $post_id = 0 ) {
		if ( ! is_array( $raw ) ) {
			return false;
		}
		if ( ! isset( $raw['background_id'] ) || ! is_scalar( $raw['background_id'] ) ) {
			return false;
		}
		if ( ! isset( $raw['background_removed'] ) || ! is_scalar( $raw['background_removed'] ) ) {
			return false;
		}
		if ( ! isset( $raw['layers'] ) || ! is_array( $raw['layers'] ) || count( $raw['layers'] ) > 100 ) {
			return false;
		}
		$string_fields  = array( 'label', 'text', 'color', 'align' );
		$numeric_fields = array( 'x', 'y', 'width', 'font_size', 'font_weight' );
		$eligible_acf_keys = $this->get_acf_fields( $post_id );
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
			$source = isset( $layer['source'] ) && is_string( $layer['source'] ) ? $layer['source'] : 'static';
			$acf_key = isset( $layer['acf_key'] ) && is_string( $layer['acf_key'] ) ? $layer['acf_key'] : '';
			if ( 'static' !== $source && 'acf' !== $source ) {
				return false;
			}
			if ( 'acf' === $source ) {
				if ( '' === $acf_key || ! array_key_exists( $acf_key, $eligible_acf_keys ) ) {
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
	private function sanitize_config( $raw, $existing = array(), $post_id = 0 ) {
		$existing = is_array( $existing ) ? $existing : array();

		$background_id = isset( $raw['background_id'] ) ? absint( $raw['background_id'] ) : 0;
		$background_removed = isset( $raw['background_removed'] ) ? absint( $raw['background_removed'] ) : 0;

		$final_background_id = 0;
		$final_background_url = '';

		if ( $background_removed ) {
			$final_background_id = 0;
			$final_background_url = '';
		} elseif ( $background_id > 0 ) {
			$attachment = get_post( $background_id );
			if ( $attachment && wp_attachment_is_image( $background_id ) ) {
				$url = wp_get_attachment_image_url( $background_id, 'full' );
				if ( $url ) {
					$final_background_id = $background_id;
					$final_background_url = esc_url_raw( $url );
				}
			}
		}

		if ( ! $background_removed && ! $final_background_id && ! $final_background_url ) {
			if ( isset( $existing['background_id'] ) && absint( $existing['background_id'] ) > 0 ) {
				$existing_id = absint( $existing['background_id'] );
				$attachment = get_post( $existing_id );
				if ( $attachment && wp_attachment_is_image( $existing_id ) ) {
					$url = wp_get_attachment_image_url( $existing_id, 'full' );
					if ( $url ) {
						$final_background_id = $existing_id;
						$final_background_url = esc_url_raw( $url );
					}
				}
			}
			if ( ! $final_background_id && ! $final_background_url && isset( $existing['background_url'] ) && is_string( $existing['background_url'] ) ) {
				$legacy_url = esc_url_raw( $existing['background_url'] );
				if ( $legacy_url ) {
					$final_background_url = $legacy_url;
				}
			}
		}

		$config = array(
			'background_id'  => $final_background_id,
			'background_url' => $final_background_url,
			'layers'         => array(),
		);

		$layers = isset( $raw['layers'] ) && is_array( $raw['layers'] ) ? $raw['layers'] : array();
		$eligible_acf_keys = $this->get_acf_fields( $post_id );

		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$source = isset( $layer['source'] ) && is_string( $layer['source'] ) ? $layer['source'] : 'static';
			if ( 'acf' !== $source ) {
				$source = 'static';
			}

			$acf_key = '';
			if ( 'acf' === $source ) {
				$raw_key = isset( $layer['acf_key'] ) && is_string( $layer['acf_key'] ) ? $layer['acf_key'] : '';
				if ( array_key_exists( $raw_key, $eligible_acf_keys ) ) {
					$acf_key = $raw_key;
				} else {
					$source = 'static';
					$acf_key = '';
				}
			}

			$label = sanitize_text_field( isset( $layer['label'] ) ? $layer['label'] : '' );
			$text  = sanitize_textarea_field( isset( $layer['text'] ) ? $layer['text'] : '' );

			if ( 'static' === $source && '' === $label && '' === $text ) {
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
				'source'      => $source,
				'acf_key'     => $acf_key,
			);
		}

		return $config;
	}

private function get_acf_fields( $post_id ) {
    if ( ! function_exists( 'get_field_objects' ) ) {
        return array();
    }

    $fields = get_field_objects( $post_id );

    if ( ! is_array( $fields ) ) {
        return array();
    }

    $result = array();

    foreach ( $fields as $field ) {
        if ( ! is_array( $field ) ) {
            continue;
        }

        if ( ! isset( $field['key'] ) || ! is_string( $field['key'] ) || '' === $field['key'] ) {
            continue;
        }

        if ( ! isset( $field['value'] ) ) {
            continue;
        }

        $value = $field['value'];

        if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
            continue;
        }

        $label = '';
        if ( isset( $field['label'] ) && is_string( $field['label'] ) && '' !== $field['label'] ) {
            $label = $field['label'];
        } elseif ( isset( $field['name'] ) && is_string( $field['name'] ) && '' !== $field['name'] ) {
            $label = $field['name'];
        } else {
            $label = $field['key'];
        }

        $result[ $field['key'] ] = $label;
    }

    return $result;
}

	public function get_layer_text( $layer, $post_id ) {
		if ( ! is_array( $layer ) ) {
			return '';
		}
		$source = isset( $layer['source'] ) && is_string( $layer['source'] ) ? $layer['source'] : 'static';
		if ( 'acf' === $source ) {
			$acf_key = isset( $layer['acf_key'] ) && is_string( $layer['acf_key'] ) ? $layer['acf_key'] : '';
			if ( '' !== $acf_key && function_exists( 'get_field' ) ) {
				$value = get_field( $acf_key, $post_id );
				if ( is_string( $value ) || is_numeric( $value ) ) {
					return (string) $value;
				}
			}
			return '';
		}
		return isset( $layer['text'] ) && is_string( $layer['text'] ) ? $layer['text'] : '';
	}

	public function get_background_url( $config ) {
		$config = is_array( $config ) ? $config : array();

		if ( isset( $config['background_id'] ) && absint( $config['background_id'] ) > 0 ) {
			$id = absint( $config['background_id'] );
			$attachment = get_post( $id );
			if ( $attachment && wp_attachment_is_image( $attachment ) ) {
				$url = wp_get_attachment_image_url( $id, 'full' );
				if ( $url ) {
					return $url;
				}
			}
		}

		if ( isset( $config['background_url'] ) && is_string( $config['background_url'] ) ) {
			$url = esc_url_raw( $config['background_url'] );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}
	/**
	 * Load editor assets only when editing a selected signage page.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	/**
	 * Load editor assets only when editing a selected signage page.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix || ! isset( $_GET['post'] ) || ! is_scalar( $_GET['post'] ) ) {
			return;
		}
		$page_id = absint( $_GET['post'] );
		if ( ! $this->is_managed_page( $page_id ) || ! current_user_can( 'edit_post', $page_id ) ) {
			return;
		}
		wp_enqueue_style( 'merk-signage-studio-admin', MERK_SIGNAGE_STUDIO_URL . 'assets/signage.css', array(), MERK_SIGNAGE_STUDIO_VERSION );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'merk-signage-studio-admin', MERK_SIGNAGE_STUDIO_URL . 'assets/admin.js', array( 'wp-media' ), MERK_SIGNAGE_STUDIO_VERSION, true );

		$config = get_post_meta( $page_id, self::META_KEY, true );
		$config = is_array( $config ) ? $config : array();
		$background_id = isset( $config['background_id'] ) ? absint( $config['background_id'] ) : 0;
		$background_url = $this->get_background_url( $config );

		wp_localize_script(
			'merk-signage-studio-admin',
			'MerkSignageStudio',
			array(
				'rowTemplate'    => $this->get_layer_template( $page_id ),
				'backgroundId'   => $background_id,
				'backgroundUrl'  => $background_url,
				'labels'         => array(
					'selectBackground' => __( 'Select Background', 'merk-signage-studio' ),
					'removeBackground' => __( 'Remove Background', 'merk-signage-studio' ),
				)
			)
		);
	}
	/**
	 * Get a clean HTML template for a new repeatable row.
	 *
	 * @return string
	 */
private function get_layer_template( $post_id ) {
    ob_start();
    $this->render_layer_row( '__INDEX__', array(), $post_id );
    return ob_get_clean();
}
	/**
	 * Load front-end styles only on selected signage pages.
	 */
	public function enqueue_frontend_assets() {
		if ( is_page( $this->get_managed_page_ids() ) ) {
			wp_enqueue_style( 'merk-signage-studio', MERK_SIGNAGE_STUDIO_URL . 'assets/signage.css', array(), MERK_SIGNAGE_STUDIO_VERSION );
		}
	}

	/**
	 * Replace Brizy output only on pages selected for the plugin.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	/**
	 * Replace Brizy output only on pages selected for the plugin.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	public function render_signage( $content ) {
		$page_id = get_queried_object_id();
		if ( ! $this->is_managed_page( $page_id ) || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		$config = get_post_meta( $page_id, self::META_KEY, true );
		$config = is_array( $config ) ? $config : array();
		$background_url = $this->get_background_url( $config );
		$layers         = isset( $config['layers'] ) && is_array( $config['layers'] ) ? $config['layers'] : array();

		// A page keeps rendering its original content until its signage layout is configured.
		if ( '' === $background_url ) {
			return $content;
		}

		$stage_style    = $background_url ? 'background-image:url(' . $background_url . ');' : '';

		ob_start();
		?>
		<div class="merk-signage-frame"><div class="merk-signage-stage"<?php echo $stage_style ? ' style="' . esc_attr( $stage_style ) . '"' : ''; ?>>
			<?php foreach ( $layers as $layer ) : ?>
				<?php
				$layer_text = $this->get_layer_text( $layer, $page_id );
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
				<div class="merk-signage-layer" style="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $layer_text ); ?></div>
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
	 * Add studio and settings entry points for the appropriate users.
	 */
	public function add_studio_menu() {
		add_submenu_page(
			'edit.php?post_type=page',
			__( 'Signage Studio', 'merk-signage-studio' ),
			__( 'Signage Studio', 'merk-signage-studio' ),
			'read',
			'merk-signage-studio',
			array( $this, 'render_studio_page' )
		);
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'edit.php?post_type=page',
				__( 'Signage Settings', 'merk-signage-studio' ),
				__( 'Signage Settings', 'merk-signage-studio' ),
				'manage_options',
				'merk-signage-settings',
				array( $this, 'render_settings_page' )
			);
		}
	}

	/**
	 * Show selected pages the current user is allowed to edit.
	 */
	public function render_studio_page() {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You cannot access Signage Studio.', 'merk-signage-studio' ) );
		}
		$editable_pages = array();
		foreach ( $this->get_managed_page_ids() as $page_id ) {
			if ( current_user_can( 'edit_post', $page_id ) ) {
				$editable_pages[] = get_post( $page_id );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Signage Studio', 'merk-signage-studio' ); ?></h1>
			<?php if ( empty( $editable_pages ) ) : ?>
				<p><?php esc_html_e( 'No signage pages are assigned to your account.', 'merk-signage-studio' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $editable_pages as $page ) : ?>
						<?php if ( $page instanceof WP_Post ) : ?>
							<li><a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php echo esc_html( get_the_title( $page ) ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render administrator-only page selection settings.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change Signage Studio settings.', 'merk-signage-studio' ) );
		}
		$selected_ids = $this->get_managed_page_ids();
		$pages        = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Signage Settings', 'merk-signage-studio' ); ?></h1>
			<p><?php esc_html_e( 'Choose which WordPress pages are managed as signage by this plugin. A user must still have permission to edit each selected page.', 'merk-signage-studio' ); ?></p>
			<form action="options.php" method="post">
				<?php settings_fields( 'merk_signage_studio_settings' ); ?>
				<?php if ( ! empty( $pages ) ) : ?>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Pages managed as signage', 'merk-signage-studio' ); ?></legend>
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[]" value="">
						<?php foreach ( $pages as $page ) : ?>
							<label style="display:block;margin:8px 0">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[]" value="<?php echo esc_attr( $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, $selected_ids, true ) ); ?>>
								<?php echo esc_html( get_the_title( $page ) ); ?> <span class="description">(<?php echo esc_html( $page->post_status ); ?>)</span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php else : ?>
					<p><?php esc_html_e( 'There are no eligible pages yet.', 'merk-signage-studio' ); ?></p>
				<?php endif; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add the signage body class so the full-screen theme styles apply to every selected page.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_signage_body_class( $classes ) {
		if ( is_page( $this->get_managed_page_ids() ) ) {
			$classes[] = 'merk-signage-page';
		}
		return $classes;
	}
}

new Merk_Signage_Studio();
