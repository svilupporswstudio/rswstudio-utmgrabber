<?php
/**
 * Plugin Name:       RSW UTM Grabber
 * Description:       Acquisisce UTM e click ID dall'URL, li conserva nei cookie e mantiene la compatibilità con i wrapper legacy di HandL UTM Grabber.
 * Version:           1.5.0
 * Requires at least: 7.0
 * Tested up to:      7.2
 * Requires PHP:      7.4
 * Author:            rswstudio
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rsw-utm-grabber
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RSW_UTM_Grabber {
	const VERSION      = '1.5.0';
	const OPTION_DAYS  = 'rsw_utm_grabber_cookie_days';
	const DEFAULT_DAYS = 30;

	/**
	 * Mappa tra shortcode wrapper legacy e parametro tracciato.
	 *
	 * @var array<string, string>
	 */
	private static $shortcodes = array(
		'utm_campaign_i' => 'utm_campaign',
		'utm_source_i'   => 'utm_source',
		'utm_medium_i'   => 'utm_medium',
		'utm_term_i'     => 'utm_term',
		'utm_content_i'  => 'utm_content',
		'gclid_i'        => 'gclid',
		'fbclid_i'       => 'fbclid',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'capture_query_parameters' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_script' ), 20 );

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_settings_link' ) );

		/*
		 * Contact Form 7: non registriamo [utm_*_i] come form-tag CF7.
		 * Prendiamo invece l'HTML finale e sostituiamo %s in base al name dell'input.
		 */
		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'replace_cf7_placeholders' ), PHP_INT_MAX );
		add_filter( 'wpcf7_posted_data', array( __CLASS__, 'inject_cf7_posted_data' ), 999 );
		add_filter( 'wpcf7_special_mail_tags', array( __CLASS__, 'cf7_special_mail_tags' ), 999, 4 );
		add_filter( 'wpcf7_mail_components', array( __CLASS__, 'cf7_mail_components_fallback' ), 999, 3 );
	}

	/**
	 * @return array<int, string>
	 */
	private static function get_parameters() {
		return array_values( self::$shortcodes );
	}

	public static function register_shortcodes() {
		foreach ( self::$shortcodes as $shortcode => $parameter ) {
			add_shortcode( $shortcode, array( __CLASS__, 'render_wrapper_shortcode' ) );
		}
	}

	public static function capture_query_parameters() {
		if (
			is_admin()
			|| wp_doing_ajax()
			|| wp_doing_cron()
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		) {
			return;
		}

		foreach ( self::get_parameters() as $parameter ) {
			$found = false;
			$value = self::get_url_parameter( $parameter, $found );

			if ( $found ) {
				self::set_tracking_cookie( $parameter, $value );
			}
		}
	}

	/**
	 * Legge un parametro dalla query normale. Come fallback tollera anche query
	 * erroneamente codificate tutte insieme, per esempio ?utm_source%3Dgoogle%26...
	 */
	private static function get_url_parameter( $parameter, &$found = false ) {
		$found = false;

		if ( array_key_exists( $parameter, $_GET ) && ! is_array( $_GET[ $parameter ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$found = true;
			return self::sanitize_tracking_value( wp_unslash( $_GET[ $parameter ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$raw_query = isset( $_SERVER['QUERY_STRING'] ) && is_scalar( $_SERVER['QUERY_STRING'] )
			? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		if ( '' === $raw_query || ( false === stripos( $raw_query, '%3D' ) && false === stripos( $raw_query, '%26' ) ) ) {
			return '';
		}

		$decoded = rawurldecode( $raw_query );
		if ( $decoded === $raw_query || false === strpos( $decoded, '=' ) ) {
			return '';
		}

		$values = array();
		parse_str( $decoded, $values );
		if ( isset( $values[ $parameter ] ) && ! is_array( $values[ $parameter ] ) ) {
			$found = true;
			return self::sanitize_tracking_value( $values[ $parameter ] );
		}

		return '';
	}

	/** URL > cookie > vuoto. */
	private static function get_tracking_value( $parameter ) {
		if ( ! is_string( $parameter ) || ! in_array( $parameter, self::get_parameters(), true ) ) {
			return '';
		}

		$found = false;
		$value = self::get_url_parameter( $parameter, $found );
		if ( $found ) {
			return $value;
		}

		if ( isset( $_COOKIE[ $parameter ] ) && ! is_array( $_COOKIE[ $parameter ] ) ) {
			return self::sanitize_tracking_value( wp_unslash( $_COOKIE[ $parameter ] ) );
		}

		return '';
	}

	private static function sanitize_tracking_value( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 1024, 'UTF-8' );
		}

		$value = substr( $value, 0, 1024 );
		return wp_check_invalid_utf8( $value, true );
	}

	private static function set_tracking_cookie( $name, $value ) {
		$name  = (string) $name;
		$value = (string) $value;

		$args = array(
			'expires'  => time() + ( DAY_IN_SECONDS * self::get_cookie_days() ),
			'path'     => '/',
			'domain'   => '',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		);

		$args = apply_filters( 'rsw_utm_grabber_cookie_args', $args, $name, $value );
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$samesite       = isset( $args['samesite'] ) && is_string( $args['samesite'] ) ? $args['samesite'] : 'Lax';
		$cookie_options = array(
			'expires'  => isset( $args['expires'] ) ? (int) $args['expires'] : 0,
			'path'     => isset( $args['path'] ) && is_string( $args['path'] ) ? $args['path'] : '/',
			'domain'   => isset( $args['domain'] ) && is_string( $args['domain'] ) ? $args['domain'] : '',
			'secure'   => ! empty( $args['secure'] ),
			'httponly' => ! empty( $args['httponly'] ),
			'samesite' => in_array( $samesite, array( 'Lax', 'Strict', 'None' ), true ) ? $samesite : 'Lax',
		);

		if ( ! headers_sent() ) {
			setcookie( $name, $value, $cookie_options );
		}

		/* Disponibile anche nello stesso request. */
		$_COOKIE[ $name ] = $value;
	}

	private static function get_cookie_days() {
		$days = absint( get_option( self::OPTION_DAYS, self::DEFAULT_DAYS ) );
		if ( $days < 1 ) {
			$days = self::DEFAULT_DAYS;
		}
		return min( $days, 3650 );
	}

	/**
	 * Compatibilità WordPress standard, fuori da CF7.
	 */
	public static function render_wrapper_shortcode( $atts = array(), $content = null, $tag = '' ) {
		unset( $atts );

		if ( ! is_string( $tag ) || ! isset( self::$shortcodes[ $tag ] ) ) {
			return '';
		}

		$value = self::get_tracking_value( self::$shortcodes[ $tag ] );
		if ( null === $content ) {
			return esc_html( $value );
		}

		if ( ! is_string( $content ) ) {
			return '';
		}

		/* Esegue eventuali shortcode annidati prima di inserire il valore utente. */
		$processed = do_shortcode( $content );

		return str_replace( '%s', esc_attr( $value ), $processed );
	}

	/**
	 * Cuore della compatibilità CF7/HandL.
	 *
	 * Non importa come sono annidati i wrapper. Per ogni <input>:
	 * 1. legge name="...";
	 * 2. se è uno dei 7 parametri, sostituisce %s nell'attributo value col valore URL/cookie;
	 * 3. alla fine rimuove SOLO dal markup renderizzato i marker [utm_*_i].
	 *
	 * Il contenuto salvato nel form CF7 non viene modificato.
	 */
	public static function replace_cf7_placeholders( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$original_html = $html;
		$parameters    = self::get_parameters();

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $html );

			while ( $processor->next_tag( array( 'tag_name' => 'INPUT' ) ) ) {
				$raw_name = $processor->get_attribute( 'name' );
				if ( ! is_string( $raw_name ) || '' === $raw_name ) {
					continue;
				}

				$name = strtolower( trim( $raw_name ) );
				if ( ! in_array( $name, $parameters, true ) ) {
					continue;
				}

				$current_value = $processor->get_attribute( 'value' );
				if ( is_string( $current_value ) && false !== strpos( $current_value, '%s' ) ) {
					$tracking_value = self::get_tracking_value( $name );
					$processor->set_attribute(
						'value',
						str_replace( '%s', $tracking_value, $current_value )
					);
				}
			}

			$html = $processor->get_updated_html();
		} else {
			$replaced_html = preg_replace_callback(
				'/<input\b[^>]*>/i',
				function ( $matches ) use ( $parameters ) {
					$input = $matches[0];

					/* Supporta name quotato o non quotato. */
					if ( ! preg_match( '/\bname\s*=\s*(?:(["\'])(.*?)\1|([^\s>]+))/i', $input, $name_match ) ) {
						return $input;
					}

					$raw_name = isset( $name_match[2] ) && '' !== $name_match[2] ? $name_match[2] : ( $name_match[3] ?? '' );
					$name     = strtolower( html_entity_decode( $raw_name, ENT_QUOTES, 'UTF-8' ) );
					if ( ! in_array( $name, $parameters, true ) ) {
						return $input;
					}

					$value = esc_attr( self::get_tracking_value( $name ) );

					/* Sostituisce value="%s", value='%s' oppure value=%s. */
					$replaced = preg_replace_callback(
						'/\bvalue\s*=\s*(?:(["\'])%s\1|%s)(?=\s|\/?>)/i',
						function ( $value_match ) use ( $value ) {
							$quote = ! empty( $value_match[1] ) ? $value_match[1] : '"';
							return 'value=' . $quote . $value . $quote;
						},
						$input,
						1
					);

					/* Fallback prudenziale per markup non standard. */
					if ( is_string( $replaced ) && $replaced === $input && false !== strpos( $input, '%s' ) ) {
						$replaced = str_replace( '%s', $value, $input );
					}

					return is_string( $replaced ) ? $replaced : $input;
				},
				$html
			);

			$html = is_string( $replaced_html ) ? $replaced_html : $original_html;
		}

		/*
		 * Manteniamo i wrapper nel form salvato in amministrazione, ma non li
		 * stampiamo come testo nel front-end. Non serve che siano correttamente annidati.
		 */
		foreach ( array_keys( self::$shortcodes ) as $shortcode ) {
			$html = str_ireplace( '[' . $shortcode . ']', '', $html );
			$html = str_ireplace( '[/' . $shortcode . ']', '', $html );
		}

		return $html;
	}

	/**
	 * Fallback lato browser, utile con full-page cache: il JS ricalcola i valori
	 * usando la URL/cookie del visitatore effettivo.
	 */
	public static function enqueue_frontend_script() {
		wp_enqueue_script(
			'rsw-utm-grabber',
			plugins_url( 'assets/rsw-utm-grabber.js', __FILE__ ),
			array(),
			self::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'parameters' => self::get_parameters(),
			'cookieDays' => self::get_cookie_days(),
			'version'    => self::VERSION,
		);

		wp_add_inline_script(
			'rsw-utm-grabber',
			'window.RSWUTMGrabber = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Legge il valore inviato via POST per un parametro, ricadendo su URL/cookie
	 * se il campo risulta assente, vuoto o rimasto al placeholder "%s".
	 */
	private static function resolve_submitted_parameter_value( $parameter ) {
		if ( isset( $_POST[ $parameter ] ) && ! is_array( $_POST[ $parameter ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_value = self::sanitize_tracking_value( wp_unslash( $_POST[ $parameter ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' !== $post_value && '%s' !== $post_value ) {
				return $post_value;
			}
		}

		return self::get_tracking_value( $parameter );
	}

	/**
	 * Rende i valori disponibili a CF7 anche quando gli input raw HTML non sono
	 * stati registrati come form-tag nativi.
	 */
	public static function inject_cf7_posted_data( $posted_data ) {
		$posted_data = is_array( $posted_data ) ? $posted_data : array();

		foreach ( self::get_parameters() as $parameter ) {
			$resolved = self::resolve_submitted_parameter_value( $parameter );

			if ( isset( $_POST[ $parameter ] ) && ! is_array( $_POST[ $parameter ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$posted_data[ $parameter ] = $resolved;
			} elseif ( ! array_key_exists( $parameter, $posted_data ) || '' === $posted_data[ $parameter ] || '%s' === $posted_data[ $parameter ] ) {
				$posted_data[ $parameter ] = $resolved;
			}
		}

		return $posted_data;
	}

	public static function cf7_special_mail_tags( $output, $name, $html, $mail_tag = null ) {
		unset( $mail_tag );

		if ( ! is_string( $name ) || ! in_array( $name, self::get_parameters(), true ) ) {
			return $output;
		}

		$value = self::resolve_submitted_parameter_value( $name );

		return $html ? esc_html( $value ) : $value;
	}

	/**
	 * Ultimo fallback: sostituisce direttamente [utm_campaign], [gclid], ecc.
	 * nei componenti della mail se CF7 li ha lasciati invariati.
	 */
	public static function cf7_mail_components_fallback( $components, $contact_form = null, $mail = null ) {
		unset( $contact_form, $mail );

		if ( ! is_array( $components ) ) {
			return $components;
		}

		$replace = array();
		foreach ( self::get_parameters() as $parameter ) {
			$replace[ '[' . $parameter . ']' ] = self::resolve_submitted_parameter_value( $parameter );
		}

		foreach ( $components as $key => $component ) {
			if ( 'attachments' === $key ) {
				continue;
			}

			if ( is_string( $component ) ) {
				$components[ $key ] = strtr( $component, $replace );
			} elseif ( is_array( $component ) ) {
				foreach ( $component as $sub_key => $sub_component ) {
					if ( is_string( $sub_component ) ) {
						$components[ $key ][ $sub_key ] = strtr( $sub_component, $replace );
					}
				}
			}
		}

		return $components;
	}

	public static function register_settings() {
		register_setting(
			'rsw_utm_grabber_settings',
			self::OPTION_DAYS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_cookie_days' ),
				'default'           => self::DEFAULT_DAYS,
			)
		);

		add_settings_section( 'rsw_utm_grabber_main', __( 'Impostazioni cookie', 'rsw-utm-grabber' ), '__return_false', 'rsw-utm-grabber' );
		add_settings_field(
			self::OPTION_DAYS,
			__( 'Durata cookie (giorni)', 'rsw-utm-grabber' ),
			array( __CLASS__, 'render_cookie_days_field' ),
			'rsw-utm-grabber',
			'rsw_utm_grabber_main',
			array( 'label_for' => self::OPTION_DAYS )
		);
	}

	public static function sanitize_cookie_days( $value ) {
		$value = absint( $value );
		if ( $value < 1 ) {
			return 1;
		}
		return min( $value, 3650 );
	}

	public static function add_settings_page() {
		add_options_page(
			__( 'RSW UTM Grabber', 'rsw-utm-grabber' ),
			__( 'RSW UTM Grabber', 'rsw-utm-grabber' ),
			'manage_options',
			'rsw-utm-grabber',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function add_settings_link( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		$url = admin_url( 'options-general.php?page=rsw-utm-grabber' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Impostazioni', 'rsw-utm-grabber' ) . '</a>' );
		return $links;
	}

	public static function render_cookie_days_field() {
		$value = (string) self::get_cookie_days();
		?>
		<input type="number" id="<?php echo esc_attr( self::OPTION_DAYS ); ?>" name="<?php echo esc_attr( self::OPTION_DAYS ); ?>" value="<?php echo esc_attr( $value ); ?>" min="1" max="3650" step="1" class="small-text" />
		<p class="description"><?php esc_html_e( 'Da 1 a 3650 giorni. Il valore predefinito è 30.', 'rsw-utm-grabber' ); ?></p>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RSW UTM Grabber', 'rsw-utm-grabber' ); ?></h1>
			<p><strong><?php echo esc_html( 'Versione ' . self::VERSION ); ?></strong></p>
			<p><?php esc_html_e( 'Priorità valori: URL → cookie → campo vuoto.', 'rsw-utm-grabber' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
				<?php
				settings_fields( 'rsw_utm_grabber_settings' );
				do_settings_sections( 'rsw-utm-grabber' );
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Sintassi HandL compatibile', 'rsw-utm-grabber' ); ?></h2>
			<p><?php esc_html_e( 'Non è necessario modificare i form esistenti. Il plugin sostituisce %s leggendo il name di ogni input.', 'rsw-utm-grabber' ); ?></p>
			<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:900px;overflow:auto;">[utm_campaign_i]&lt;input type=&quot;hidden&quot; name=&quot;utm_campaign&quot; value=&quot;%s&quot;&gt;[/utm_campaign_i]
[utm_source_i]&lt;input type=&quot;hidden&quot; name=&quot;utm_source&quot; value=&quot;%s&quot;&gt;[/utm_source_i]
[utm_medium_i]&lt;input type=&quot;hidden&quot; name=&quot;utm_medium&quot; value=&quot;%s&quot;&gt;[/utm_medium_i]
[utm_term_i]&lt;input type=&quot;hidden&quot; name=&quot;utm_term&quot; value=&quot;%s&quot;&gt;[/utm_term_i]
[utm_content_i]&lt;input type=&quot;hidden&quot; name=&quot;utm_content&quot; value=&quot;%s&quot;&gt;[/utm_content_i]
[gclid_i]&lt;input type=&quot;hidden&quot; name=&quot;gclid&quot; value=&quot;%s&quot;&gt;[/gclid_i]
[fbclid_i]&lt;input type=&quot;hidden&quot; name=&quot;fbclid&quot; value=&quot;%s&quot;&gt;[/fbclid_i]</pre>
		</div>
		<?php
	}
}

RSW_UTM_Grabber::init();
