<?php
/** Isolated PHP regression tests using WordPress function doubles (no database). */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}
define( 'ABSPATH', __DIR__ );
class WP_Post {
	public $ID = 82477;
	public $post_type = 'page';
}
$state = array();
function plugin_dir_url( $file ) { return 'https://example.test/plugin/'; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function current_user_can( $cap, ...$args ) { global $state; return 'manage_options' === $cap ? $state['admin'] : $state['edit']; }
function wp_is_post_autosave( $id ) { global $state; return $state['autosave']; }
function wp_is_post_revision( $id ) { global $state; return $state['revision']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && 'merk_signage_studio_save' === $action; }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $value ) ? $value : null; }
function esc_url_raw( $value ) { return preg_match( '/^https?:\/\//i', $value ) ? $value : ''; }
function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key, $single ) { global $state; return $state['config']; }
function update_post_meta( $id, $key, $value ) { global $state; $state['writes']++; $state['config'] = $value; }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html( $value ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( (string) $value ); }
function esc_textarea( $value ) { return esc_html( $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function wp_nonce_field( ...$args ) { echo '<input name="merk_signage_studio_nonce">'; }
function selected( $a, $b, $echo ) { return (string) $a === (string) $b ? ' selected' : ''; }
function add_meta_box( ...$args ) { global $state; $state['boxes']++; }
function wp_enqueue_style( $handle, ...$args ) { global $state; $state['styles'][] = $handle; }
function wp_enqueue_script( $handle, ...$args ) { global $state; $state['scripts'][] = $handle; }
function wp_localize_script( $handle, $object, $values ) { global $state; $state['localized'] = array( $object, $values ); }
require dirname( __DIR__ ) . '/merk-signage-studio.php';
$plugin = new Merk_Signage_Studio();
$post = new WP_Post();
$baseline = array(
	'background_url' => 'https://example.test/background.png',
	'layers' => array( array( 'label' => 'Soup', 'text' => 'Original', 'x' => 10, 'y' => 20, 'width' => 300, 'font_size' => 32, 'color' => '#ffffff', 'font_weight' => 400, 'align' => 'left' ) ),
);
$passed = 0;
$failed = 0;
set_error_handler( function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function reset_case( $admin = true ) {
	global $state, $baseline;
	$state = array( 'admin' => $admin, 'edit' => true, 'autosave' => false, 'revision' => false, 'config' => $baseline, 'writes' => 0, 'boxes' => 0, 'styles' => array(), 'scripts' => array(), 'localized' => array() );
	$_POST = array( 'merk_signage_studio_nonce' => 'valid', 'merk_signage' => $baseline );
	$_GET = array( 'post' => '82477' );
}
function expect( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function test_case( $name, $callback ) {
	global $passed, $failed;
	reset_case();
	try { $callback(); $passed++; echo "PASS: $name\n"; }
	catch ( Throwable $error ) { $failed++; echo "FAIL: $name: {$error->getMessage()}\n"; }
}
test_case( 'metabox accepts WordPress string argument, rejects other pages', function() use ( $plugin, $post ) {
	global $state;
	$plugin->add_metabox( 'page', $post );
	expect( 1 === $state['boxes'], 'Test metabox not registered' );
	$other = clone $post; $other->ID = 7;
	$plugin->add_metabox( 'page', $other );
	expect( 1 === $state['boxes'], 'Other page changed' );
} );
test_case( 'valid admin save succeeds', function() use ( $plugin, $post ) {
	global $state;
	$_POST['merk_signage']['layers'][0]['text'] = '<script>alert(1)</script>Soup';
	$plugin->save_page( 82477, $post, true );
	expect( 1 === $state['writes'], 'Admin save failed' );
	expect( false === strpos( $state['config']['layers'][0]['text'], '<script>' ), 'HTML retained' );
} );
test_case( 'missing form preserves all data', function() use ( $plugin, $post ) {
	global $state, $baseline;
	unset( $_POST['merk_signage'] );
	$plugin->save_page( 82477, $post, true );
	expect( 0 === $state['writes'] && $baseline === $state['config'], 'Missing form overwrote config' );
} );
test_case( 'nested arrays rejected for every scalar field without TypeError', function() use ( $plugin, $post ) {
	global $state;
	foreach ( array( 'label', 'text', 'x', 'y', 'width', 'font_size', 'color', 'font_weight', 'align' ) as $field ) {
		reset_case(); $_POST['merk_signage']['layers'][0][$field] = array( 'malformed' );
		$plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Array accepted in ' . $field );
	}
	reset_case(); $_POST['merk_signage']['background_url'] = array( 'malformed' );
	$plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Background array accepted' );
} );
test_case( 'operator changes only text despite forged layout', function() use ( $plugin, $post ) {
	global $state, $baseline;
	reset_case( false );
	$_POST['merk_signage']['background_url'] = 'https://foreign.test/tracker';
	$_POST['merk_signage']['layers'][0] = array( 'text' => "New soup\nAllergens", 'x' => 1500, 'color' => array( 'forged' ), 'label' => 'Forged label' );
	$plugin->save_page( 82477, $post, true );
	$expected = $baseline; $expected['layers'][0]['text'] = "New soup\nAllergens";
	expect( 1 === $state['writes'] && $expected === $state['config'], 'Operator altered layout or lost newlines' );
} );
test_case( 'operator cannot add, remove or renumber layers', function() use ( $plugin, $post ) {
	global $state;
	foreach ( array( array(), array( 4 => array( 'text' => 'Renumbered' ) ), array( array( 'text' => 'One' ), array( 'text' => 'Two' ) ) ) as $layers ) {
		reset_case( false ); $_POST['merk_signage'] = array( 'layers' => $layers );
		$plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Invalid operator layers accepted' );
	}
} );
test_case( 'operator empty text preserves its layer', function() use ( $plugin, $post ) {
	global $state;
	reset_case( false ); $_POST['merk_signage'] = array( 'layers' => array( array( 'text' => '' ) ) );
	$plugin->save_page( 82477, $post, true );
	expect( 1 === $state['writes'] && 1 === count( $state['config']['layers'] ), 'Empty text removed layer' );
} );
test_case( 'operator has no design fields or drag script', function() use ( $plugin, $post ) {
	global $state;
	reset_case( false ); ob_start(); $plugin->render_metabox( $post ); $markup = ob_get_clean();
	expect( false !== strpos( $markup, '[text]' ), 'Text input missing' );
	foreach ( array( 'background_url', '[x]', '[color]', 'merk-signage-canvas', 'merk-signage-add-layer' ) as $forbidden ) { expect( false === strpos( $markup, $forbidden ), 'Design field exposed: ' . $forbidden ); }
	$plugin->enqueue_admin_assets( 'post.php' );
	expect( 1 === count( $state['styles'] ) && empty( $state['scripts'] ), 'Operator drag script loaded or CSS missing' );
} );
test_case( 'admin preview CSS and row template load', function() use ( $plugin ) {
	global $state;
	$plugin->enqueue_admin_assets( 'post.php' );
	expect( 1 === count( $state['styles'] ) && 1 === count( $state['scripts'] ), 'Admin assets missing' );
	expect( 'MerkSignageStudio' === $state['localized'][0] && false !== strpos( $state['localized'][1]['rowTemplate'], '__INDEX__' ), 'Incorrect localized object or row template' );
} );
test_case( 'unauthorized user, invalid nonce and other IDs cannot save', function() use ( $plugin, $post ) {
	global $state;
	reset_case(); $state['edit'] = false; $plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Unauthorized save' );
	reset_case(); $_POST['merk_signage_studio_nonce'] = array( 'bad' ); $plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Array nonce accepted' );
	reset_case(); $_POST['merk_signage_studio_nonce'] = 'bad'; $plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Invalid nonce accepted' );
	reset_case(); $plugin->save_page( 7, $post, true ); expect( 0 === $state['writes'], 'Other ID accepted' );
} );
test_case( 'autosaves and revisions preserve configuration', function() use ( $plugin, $post ) {
	global $state;
	foreach ( array( 'autosave', 'revision' ) as $flag ) { reset_case(); $state[$flag] = true; $plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], $flag . ' overwrote configuration' ); }
} );
test_case( 'oversized configuration rejected', function() use ( $plugin, $post ) {
	global $state;
	$_POST['merk_signage']['layers'] = array_fill( 0, 101, $_POST['merk_signage']['layers'][0] );
	$plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Too many layers accepted' );
	reset_case( false ); $_POST['merk_signage']['layers'][0]['text'] = str_repeat( 'x', 8001 );
	$plugin->save_page( 82477, $post, true ); expect( 0 === $state['writes'], 'Oversized text accepted' );
} );
echo "$passed passed; $failed failed\n";
exit( $failed ? 1 : 0 );
