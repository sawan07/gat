<?php
/**
 * Staff portal: a simple, branded area at /staff/ for GAT staff to see
 * donations, Sunnah Academy applications and website enquiries, without
 * the WordPress admin panel.
 *
 *   /staff/                 dashboard
 *   /staff/donations/       donations list   (gat_view_donations)
 *   /staff/donations/12/    one donation
 *   /staff/students/        applications     (gat_manage_students)
 *   /staff/students/5/      one application (status, payment, notes, edit)
 *   /staff/enquiries/       website messages (gat_manage_enquiries)
 *   /staff/enquiries/77/    one message (mark as done)
 *   /staff/account/         change password, sign out
 *
 * It uses the same data and permissions as the wp-admin screens. Academy
 * Admins and Donations Viewers are sent here instead of wp-admin.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

const GAT_PORTAL_PER_PAGE = 25;

/* ---------------------------------------------------------------------------
 * Routing.
 * ------------------------------------------------------------------------- */
add_action(
	'init',
	function () {
		add_rewrite_rule( '^staff(?:/([a-z]+))?(?:/([0-9]+))?/?$', 'index.php?gat_staff=1&gat_screen=$matches[1]&gat_id=$matches[2]', 'top' );
		if ( get_option( 'gat_core_rewrite_version' ) !== GAT_CORE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'gat_core_rewrite_version', GAT_CORE_VERSION );
		}
	}
);
add_filter(
	'query_vars',
	function ( $vars ) {
		return array_merge( $vars, array( 'gat_staff', 'gat_screen', 'gat_id' ) );
	}
);

/**
 * Portal URL.
 *
 * @param string $screen Screen.
 * @param int    $id     Record ID.
 * @param array  $args   Query args.
 * @return string
 */
function gat_portal_url( $screen = '', $id = 0, $args = array() ) {
	$path = 'staff/' . ( $screen ? $screen . '/' : '' ) . ( $id ? (int) $id . '/' : '' );
	$url  = get_option( 'permalink_structure' ) ? home_url( '/' . $path ) : add_query_arg( array_filter( array( 'gat_staff' => 1, 'gat_screen' => $screen, 'gat_id' => $id ? (int) $id : '' ) ), home_url( '/' ) );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Portal sections the current user may open.
 *
 * @return array screen => array( label, capability, icon ).
 */
function gat_portal_sections() {
	$all = array(
		'donations' => array( __( 'Donations', 'gat-core' ), 'gat_view_donations', 'heart' ),
		'students'  => array( __( 'Students', 'gat-core' ), 'gat_manage_students', 'cap' ),
		'enquiries' => array( __( 'Enquiries', 'gat-core' ), 'gat_manage_enquiries', 'mail' ),
	);
	return array_filter( $all, fn( $s ) => current_user_can( $s[1] ) );
}

/**
 * Whether a user should use the portal instead of wp-admin (staff with
 * portal access and no website-editing rights).
 *
 * @param WP_User|null $user User (default current).
 * @return bool
 */
function gat_portal_only_user( $user = null ) {
	$user = $user ?: wp_get_current_user();
	if ( ! $user || ! $user->exists() || user_can( $user, 'edit_posts' ) || user_can( $user, 'manage_options' ) ) {
		return false;
	}
	foreach ( array( 'gat_view_donations', 'gat_manage_students', 'gat_manage_enquiries' ) as $cap ) {
		if ( user_can( $user, $cap ) ) {
			return true;
		}
	}
	return false;
}

// Portal-only staff: land in the portal after logging in, never see wp-admin
// (except their profile for 2-step login and language, and file downloads).
add_filter(
	'login_redirect',
	function ( $to, $requested, $user ) {
		return ( $user instanceof WP_User && gat_portal_only_user( $user ) ) ? gat_portal_url() : $to;
	},
	20,
	3
);
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! gat_portal_only_user() ) {
			return;
		}
		$page = basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ); // phpcs:ignore
		if ( in_array( $page, array( 'profile.php', 'admin-post.php', 'admin-ajax.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( gat_portal_url() );
		exit;
	},
	1
);
add_filter( 'show_admin_bar', fn( $show ) => gat_portal_only_user() ? false : $show );

// A "Staff portal" link for everyone else who can use it.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( gat_portal_sections() ) {
			$bar->add_node( array( 'id' => 'gat-portal', 'title' => __( 'Staff portal', 'gat-core' ), 'href' => gat_portal_url(), 'parent' => 'site-name' ) );
		}
	},
	80
);

/* ---------------------------------------------------------------------------
 * Request handling.
 * ------------------------------------------------------------------------- */
add_action(
	'parse_request',
	function ( $wp ) {
		if ( empty( $wp->query_vars['gat_staff'] ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Frame-Options: DENY' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: same-origin' );

		$screen = sanitize_key( $wp->query_vars['gat_screen'] ?? '' );
		$id     = absint( $wp->query_vars['gat_id'] ?? 0 );

		if ( ! is_user_logged_in() ) {
			gat_portal_login();
			exit;
		}
		$sections = gat_portal_sections();
		if ( ! $sections ) {
			gat_portal_page( __( 'No access', 'gat-core' ), 'gat_portal_no_access', '' );
			exit;
		}
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore
			gat_portal_handle_post( $screen, $id );
		}
		if ( '' === $screen ) {
			gat_portal_page( __( 'Dashboard', 'gat-core' ), 'gat_portal_dashboard', '' );
		} elseif ( 'account' === $screen ) {
			gat_portal_page( __( 'My account', 'gat-core' ), 'gat_portal_account', 'account' );
		} elseif ( isset( $sections[ $screen ] ) ) {
			$fn = 'gat_portal_' . $screen . ( $id ? '_view' : '_list' );
			gat_portal_page( $sections[ $screen ][0], fn() => $fn( $id ), $screen );
		} else {
			status_header( 404 );
			gat_portal_page( __( 'Not found', 'gat-core' ), fn() => print( '<div class="p-card"><p>' . esc_html__( 'This page doesn’t exist, or you don’t have access to it.', 'gat-core' ) . '</p></div>' ), '' );
		}
		exit;
	},
	1
);

/**
 * Redirect back with a message.
 *
 * @param string $url URL.
 * @param string $msg Message key.
 */
function gat_portal_redirect( $url, $msg ) {
	wp_safe_redirect( add_query_arg( 'msg', $msg, $url ) );
	exit;
}

/**
 * POST actions (all nonce + capability checked).
 *
 * @param string $screen Screen.
 * @param int    $id     Record ID.
 */
function gat_portal_handle_post( $screen, $id ) {
	$p      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	$action = sanitize_key( $p['gat_action'] ?? '' );
	if ( ! wp_verify_nonce( (string) ( $p['_gat_nonce'] ?? '' ), 'gat_portal_' . $action . '_' . $id ) ) {
		gat_portal_redirect( gat_portal_url( $screen, $id ), 'expired' );
	}

	if ( 'student_status' === $action && current_user_can( 'gat_manage_students' ) && ( $s = gat_get( 'students', $id ) ) ) {
		$upd = array( 'notes' => sanitize_textarea_field( $p['notes'] ?? '' ) );
		if ( isset( gat_labels()['application'][ $p['status'] ?? '' ] ) ) {
			$upd['status'] = $p['status'];
		}
		if ( isset( gat_labels()['payment'][ $p['payment_status'] ?? '' ] ) ) {
			$upd['payment_status'] = $p['payment_status'];
			if ( 'paid' === $p['payment_status'] && ! $s['paid_at'] ) {
				$upd['paid_at'] = current_time( 'mysql', true );
			}
		}
		gat_update( 'students', $id, $upd );
		gat_portal_redirect( gat_portal_url( 'students', $id ), 'saved' );
	}

	if ( 'student_edit' === $action && current_user_can( 'gat_manage_students' ) && gat_get( 'students', $id ) ) {
		$upd = array();
		foreach ( array( 'student_name', 'postcode', 'parent_name', 'relationship', 'phone', 'emergency_name', 'emergency_phone', 'class_name' ) as $f ) {
			$upd[ $f ] = sanitize_text_field( $p[ $f ] ?? '' );
		}
		$upd['email'] = sanitize_email( $p['email'] ?? '' );
		foreach ( array( 'address', 'student_info', 'additional_info' ) as $f ) {
			$upd[ $f ] = sanitize_textarea_field( $p[ $f ] ?? '' );
		}
		foreach ( array( 'dob', 'start_date' ) as $f ) {
			$d         = sanitize_text_field( $p[ $f ] ?? '' );
			$upd[ $f ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : null;
		}
		if ( '' === $upd['student_name'] || '' === $upd['parent_name'] ) {
			gat_portal_redirect( gat_portal_url( 'students', $id, array( 'edit' => 1 ) ), 'required' );
		}
		gat_update( 'students', $id, $upd );
		gat_portal_redirect( gat_portal_url( 'students', $id ), 'saved' );
	}

	if ( 'donation_notes' === $action && current_user_can( 'manage_options' ) && gat_get( 'donations', $id ) ) {
		gat_update( 'donations', $id, array( 'notes' => sanitize_textarea_field( $p['notes'] ?? '' ) ) );
		gat_portal_redirect( gat_portal_url( 'donations', $id ), 'saved' );
	}

	if ( 'enquiry_done' === $action && current_user_can( 'gat_manage_enquiries' ) ) {
		$post = get_post( $id );
		if ( $post && 'gat_enquiry' === $post->post_type ) {
			$done = ! empty( $p['done'] );
			if ( $done ) {
				update_post_meta( $id, '_gat_done', current_time( 'mysql', true ) );
				update_post_meta( $id, '_gat_done_by', get_current_user_id() );
			} else {
				delete_post_meta( $id, '_gat_done' );
				delete_post_meta( $id, '_gat_done_by' );
			}
			gat_portal_redirect( $done ? gat_portal_url( 'enquiries' ) : gat_portal_url( 'enquiries', $id ), $done ? 'done' : 'reopened' );
		}
	}

	if ( 'password' === $action ) {
		$user = wp_get_current_user();
		$new  = (string) ( $p['new_password'] ?? '' );
		if ( ! wp_check_password( (string) ( $p['current_password'] ?? '' ), $user->user_pass, $user->ID ) ) {
			gat_portal_redirect( gat_portal_url( 'account' ), 'wrong_password' );
		}
		if ( strlen( $new ) < 10 ) {
			gat_portal_redirect( gat_portal_url( 'account' ), 'short_password' );
		}
		if ( $new !== (string) ( $p['confirm_password'] ?? '' ) ) {
			gat_portal_redirect( gat_portal_url( 'account' ), 'mismatch' );
		}
		wp_set_password( $new, $user->ID ); // Signs out every session…
		wp_set_auth_cookie( $user->ID, false, is_ssl() ); // …so sign this one back in.
		wp_set_current_user( $user->ID );
		gat_portal_redirect( gat_portal_url( 'account' ), 'password_changed' );
	}

	gat_portal_redirect( gat_portal_url( $screen, $id ), 'not_allowed' );
}

/**
 * Hidden fields for a portal form.
 *
 * @param string $action Action.
 * @param int    $id     Record ID.
 */
function gat_portal_form_fields( $action, $id = 0 ) {
	printf( '<input type="hidden" name="gat_action" value="%s">', esc_attr( $action ) );
	printf( '<input type="hidden" name="_gat_nonce" value="%s">', esc_attr( wp_create_nonce( 'gat_portal_' . $action . '_' . (int) $id ) ) );
}

/* ---------------------------------------------------------------------------
 * Sign in.
 * ------------------------------------------------------------------------- */

/**
 * Failed sign-in counter key (per visitor IP).
 *
 * @return string
 */
function gat_portal_fail_key() {
	$ip = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore
	return 'gat_portal_fail_' . md5( $ip . wp_salt( 'auth' ) );
}

/**
 * The portal URL being requested (to return there after signing in).
 *
 * @return string
 */
function gat_portal_current_url() {
	$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore
	$url = set_url_scheme( 'http://' . wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' ) . $uri );
	return wp_validate_redirect( $url, gat_portal_url() );
}

/**
 * Sign-in page and form handling.
 */
function gat_portal_login() {
	$error = '';
	$login = '';
	$key   = gat_portal_fail_key();
	$fails = (int) get_transient( $key );

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore
		$p     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$login = sanitize_text_field( $p['log'] ?? '' );
		if ( $fails >= 5 ) {
			$error = __( 'Too many attempts. Please wait 15 minutes and try again, or reset your password.', 'gat-core' );
		} elseif ( ! wp_verify_nonce( (string) ( $p['_gat_nonce'] ?? '' ), 'gat_portal_login' ) ) {
			$error = __( 'The page had expired. Please try again.', 'gat-core' );
		} else {
			$user = wp_authenticate( $login, (string) ( $p['pwd'] ?? '' ) );
			if ( is_wp_error( $user ) ) {
				set_transient( $key, $fails + 1, 15 * MINUTE_IN_SECONDS );
				$error = __( 'Wrong email/username or password.', 'gat-core' );
			} elseif ( class_exists( 'Two_Factor_Core' ) && Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
				// 2-step login is handled by WordPress's own sign-in page.
				wp_safe_redirect( wp_login_url( gat_portal_current_url() ) );
				exit;
			} else {
				delete_transient( $key );
				$remember = ! empty( $p['rememberme'] );
				wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
				wp_set_current_user( $user->ID );
				do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
				wp_safe_redirect( remove_query_arg( 'loggedout', gat_portal_current_url() ) );
				exit;
			}
		}
	}

	gat_portal_head( __( 'Staff sign in', 'gat-core' ), 'p-login' );
	?>
	<main class="p-login__box">
		<div class="p-login__brand">
			<img src="<?php echo esc_url( GAT_CORE_URL . 'assets/chat-logo.png' ); ?>" alt="" width="64" height="64">
			<div><strong>Global Alliance Trust</strong><span><?php esc_html_e( 'Staff portal', 'gat-core' ); ?></span></div>
		</div>
		<h1><?php esc_html_e( 'Sign in', 'gat-core' ); ?></h1>
		<?php if ( $error ) : ?>
			<div class="p-alert p-alert--error" role="alert"><?php echo esc_html( $error ); ?></div>
		<?php elseif ( isset( $_GET['loggedout'] ) ) : // phpcs:ignore ?>
			<div class="p-alert" role="status"><?php esc_html_e( 'You have signed out.', 'gat-core' ); ?></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( remove_query_arg( 'loggedout', gat_portal_current_url() ) ); ?>" class="p-form">
			<input type="hidden" name="_gat_nonce" value="<?php echo esc_attr( wp_create_nonce( 'gat_portal_login' ) ); ?>">
			<label for="p-log"><?php esc_html_e( 'Email or username', 'gat-core' ); ?></label>
			<input id="p-log" name="log" type="text" autocomplete="username" required value="<?php echo esc_attr( $login ); ?>" autofocus>
			<label for="p-pwd"><?php esc_html_e( 'Password', 'gat-core' ); ?></label>
			<input id="p-pwd" name="pwd" type="password" autocomplete="current-password" required>
			<label class="p-check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Keep me signed in on this device', 'gat-core' ); ?></label>
			<button type="submit" class="p-btn p-btn--primary p-btn--block"><?php esc_html_e( 'Sign in', 'gat-core' ); ?></button>
		</form>
		<p class="p-login__links"><a href="<?php echo esc_url( wp_lostpassword_url( gat_portal_url() ) ); ?>"><?php esc_html_e( 'Forgotten your password?', 'gat-core' ); ?></a></p>
		<p class="p-login__links"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">← <?php esc_html_e( 'Back to the website', 'gat-core' ); ?></a></p>
	</main>
	<?php
	gat_portal_foot();
}

/* ---------------------------------------------------------------------------
 * Layout.
 * ------------------------------------------------------------------------- */

/**
 * Document head.
 *
 * @param string $title Title.
 * @param string $body  Body class.
 */
function gat_portal_head( $title, $body = '' ) {
	?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' · GAT staff portal' ); ?></title>
<link rel="icon" href="<?php echo esc_url( GAT_CORE_URL . 'assets/chat-logo.png' ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Montserrat:wght@700&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( GAT_CORE_URL . 'assets/staff.css?ver=' . GAT_CORE_VERSION ); ?>">
</head>
<body class="p-body <?php echo esc_attr( $body ); ?>">
	<?php
}

/**
 * Document end.
 */
function gat_portal_foot() {
	?>
<script src="<?php echo esc_url( GAT_CORE_URL . 'assets/staff.js?ver=' . GAT_CORE_VERSION ); ?>" defer></script>
</body>
</html>
	<?php
}

/**
 * Small inline icons.
 *
 * @param string $name Icon.
 * @return string SVG.
 */
function gat_portal_icon( $name ) {
	$paths = array(
		'home'   => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/>',
		'heart'  => '<path d="M12 20.5 4.5 13a5 5 0 0 1 7.1-7.1l.4.4.4-.4A5 5 0 0 1 19.5 13z"/>',
		'cap'    => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
		'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6 8.5 7 8.5-7"/>',
		'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'out'    => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
		'down'   => '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/>',
		'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
		'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
	);
	return '<svg class="p-ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Full page with sidebar.
 *
 * @param string   $title   Title.
 * @param callable $content Content callback.
 * @param string   $current Current section.
 */
function gat_portal_page( $title, $content, $current ) {
	$user = wp_get_current_user();
	$nav  = array( '' => array( __( 'Dashboard', 'gat-core' ), 'home' ) );
	foreach ( gat_portal_sections() as $k => $s ) {
		$nav[ $k ] = array( $s[0], $s[2] );
	}
	$nav['account'] = array( __( 'My account', 'gat-core' ), 'user' );

	gat_portal_head( $title );
	?>
	<a class="p-skip" href="#p-main"><?php esc_html_e( 'Skip to content', 'gat-core' ); ?></a>
	<header class="p-top">
		<button class="p-menu-btn" type="button" aria-controls="p-nav" aria-expanded="false"><?php echo gat_portal_icon( 'menu' ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'gat-core' ); ?></span></button>
		<a class="p-brand" href="<?php echo esc_url( gat_portal_url() ); ?>">
			<img src="<?php echo esc_url( GAT_CORE_URL . 'assets/chat-logo.png' ); ?>" alt="" width="36" height="36">
			<span><strong>Global Alliance Trust</strong><small><?php esc_html_e( 'Staff portal', 'gat-core' ); ?></small></span>
		</a>
		<span class="p-top__user"><?php echo esc_html( $user->display_name ); ?></span>
	</header>
	<div class="p-shell">
		<nav class="p-nav" id="p-nav" aria-label="<?php esc_attr_e( 'Staff portal', 'gat-core' ); ?>">
			<ul>
				<?php foreach ( $nav as $k => list( $label, $icon ) ) : ?>
					<li><a href="<?php echo esc_url( gat_portal_url( $k ) ); ?>"<?php echo $k === $current ? ' aria-current="page"' : ''; ?>><?php echo gat_portal_icon( $icon ); // phpcs:ignore ?><?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<ul class="p-nav__end">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php echo gat_portal_icon( 'globe' ); // phpcs:ignore ?><?php esc_html_e( 'View website', 'gat-core' ); ?></a></li>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<li><a href="<?php echo esc_url( admin_url() ); ?>"><?php echo gat_portal_icon( 'home' ); // phpcs:ignore ?><?php esc_html_e( 'Website editor (wp-admin)', 'gat-core' ); ?></a></li>
				<?php endif; ?>
				<li><a href="<?php echo esc_url( wp_logout_url( gat_portal_url( '', 0, array( 'loggedout' => 1 ) ) ) ); ?>"><?php echo gat_portal_icon( 'out' ); // phpcs:ignore ?><?php esc_html_e( 'Sign out', 'gat-core' ); ?></a></li>
			</ul>
		</nav>
		<main class="p-main" id="p-main" tabindex="-1">
			<?php gat_portal_message(); ?>
			<?php call_user_func( $content ); ?>
		</main>
	</div>
	<?php
	gat_portal_foot();
}

/**
 * Flash message from ?msg=.
 */
function gat_portal_message() {
	$msgs = array(
		'saved'            => array( __( 'Saved.', 'gat-core' ), '' ),
		'done'             => array( __( 'Enquiry marked as done.', 'gat-core' ), '' ),
		'reopened'         => array( __( 'Enquiry moved back to New.', 'gat-core' ), '' ),
		'password_changed' => array( __( 'Your password has been changed.', 'gat-core' ), '' ),
		'expired'          => array( __( 'The page had expired, so nothing was saved. Please try again.', 'gat-core' ), 'error' ),
		'not_allowed'      => array( __( 'You don’t have permission to do that.', 'gat-core' ), 'error' ),
		'required'         => array( __( 'Student name and parent/guardian name are required.', 'gat-core' ), 'error' ),
		'wrong_password'   => array( __( 'Your current password is not correct.', 'gat-core' ), 'error' ),
		'short_password'   => array( __( 'The new password must be at least 10 characters.', 'gat-core' ), 'error' ),
		'mismatch'         => array( __( 'The two new passwords don’t match.', 'gat-core' ), 'error' ),
	);
	$key = sanitize_key( $_GET['msg'] ?? '' ); // phpcs:ignore
	if ( isset( $msgs[ $key ] ) ) {
		printf( '<div class="p-alert%s" role="%s">%s</div>', $msgs[ $key ][1] ? ' p-alert--error' : ' p-alert--ok', $msgs[ $key ][1] ? 'alert' : 'status', esc_html( $msgs[ $key ][0] ) );
	}
}

/**
 * Status badge.
 *
 * @param string $group  Label group.
 * @param string $status Status.
 * @return string
 */
function gat_portal_badge( $group, $status ) {
	$tone = array(
		'pending'      => 'amber',
		'approved'     => 'green',
		'paid'         => 'green',
		'active'       => 'green',
		'declined'     => 'red',
		'failed'       => 'red',
		'refunded'     => 'purple',
	);
	return sprintf( '<span class="p-badge p-badge--%s">%s</span>', esc_attr( $tone[ $status ] ?? 'grey' ), esc_html( gat_labels()[ $group ][ $status ] ?? $status ) );
}

/**
 * Page heading.
 *
 * @param string $title   Title.
 * @param string $actions Actions HTML.
 * @param string $back    Back link URL.
 * @param string $back_label Back link label.
 */
function gat_portal_title( $title, $actions = '', $back = '', $back_label = '' ) {
	if ( $back ) {
		printf( '<p class="p-back"><a href="%s">← %s</a></p>', esc_url( $back ), esc_html( $back_label ) );
	}
	echo '<div class="p-title"><h1>' . esc_html( $title ) . '</h1>' . ( $actions ? '<div class="p-title__actions">' . $actions . '</div>' : '' ) . '</div>'; // phpcs:ignore
}

/**
 * Pagination.
 *
 * @param int    $total Total rows.
 * @param int    $page  Current page.
 * @param string $base  Base URL (with filters).
 */
function gat_portal_pager( $total, $page, $base ) {
	$pages = (int) ceil( $total / GAT_PORTAL_PER_PAGE );
	echo '<div class="p-pager"><span>' . esc_html( sprintf( /* translators: %d count */ _n( '%d record', '%d records', $total, 'gat-core' ), $total ) ) . '</span>';
	if ( $pages > 1 ) {
		echo '<span class="p-pager__links">';
		if ( $page > 1 ) {
			printf( '<a class="p-btn" href="%s">← %s</a>', esc_url( add_query_arg( 'pg', $page - 1, $base ) ), esc_html__( 'Previous', 'gat-core' ) );
		}
		echo '<span>' . esc_html( sprintf( /* translators: 1: page 2: pages */ __( 'Page %1$d of %2$d', 'gat-core' ), $page, $pages ) ) . '</span>';
		if ( $page < $pages ) {
			printf( '<a class="p-btn" href="%s">%s →</a>', esc_url( add_query_arg( 'pg', $page + 1, $base ) ), esc_html__( 'Next', 'gat-core' ) );
		}
		echo '</span>';
	}
	echo '</div>';
}

/**
 * Current page number.
 *
 * @return int
 */
function gat_portal_pg() {
	return max( 1, absint( $_GET['pg'] ?? 1 ) ); // phpcs:ignore
}

/**
 * Definition list of label => value (empty values skipped).
 *
 * @param array $rows Rows.
 */
function gat_portal_dl( $rows ) {
	echo '<dl class="p-dl">';
	foreach ( $rows as $label => $value ) {
		if ( is_array( $value ) ) { // Pre-escaped HTML.
			printf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html( $label ), $value[0] ); // phpcs:ignore
		} elseif ( '' !== trim( (string) $value ) ) {
			printf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html( $label ), nl2br( esc_html( $value ) ) );
		}
	}
	echo '</dl>';
}

/**
 * Link helpers for contact details.
 *
 * @param string $email Email.
 * @return array Pre-escaped.
 */
function gat_portal_mailto( $email ) {
	return $email ? array( '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' ) : '';
}

/**
 * Phone link.
 *
 * @param string $phone Phone.
 * @return array|string
 */
function gat_portal_tel( $phone ) {
	return $phone ? array( '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' ) : '';
}

/* ---------------------------------------------------------------------------
 * Screens.
 * ------------------------------------------------------------------------- */

/**
 * Signed in without access.
 */
function gat_portal_no_access() {
	echo '<div class="p-card"><h1>' . esc_html__( 'No access yet', 'gat-core' ) . '</h1><p>' . esc_html__( 'Your account doesn’t have access to any part of the staff portal. Please ask the administrator to give you the right role (Academy Admin or Donations Viewer).', 'gat-core' ) . '</p></div>';
}

/**
 * Dashboard.
 */
function gat_portal_dashboard() {
	global $wpdb;
	$user = wp_get_current_user();
	/* translators: %s first name */
	gat_portal_title( sprintf( __( 'Welcome, %s', 'gat-core' ), $user->first_name ?: $user->display_name ) );
	$sections = gat_portal_sections();

	if ( isset( $sections['donations'] ) ) {
		echo '<section class="p-section"><div class="p-section__head"><h2>' . esc_html__( 'Donations', 'gat-core' ) . '</h2><a href="' . esc_url( gat_portal_url( 'donations' ) ) . '">' . esc_html__( 'See all', 'gat-core' ) . ' →</a></div>';
		echo '<div class="p-stats">';
		foreach ( gat_donation_summary_data() as list( $label, $value ) ) {
			echo '<div class="p-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
		}
		echo '</div>';
		$rows = $wpdb->get_results( 'SELECT * FROM ' . gat_table( 'donations' ) . " WHERE status IN ('paid','active','cancelled') ORDER BY id DESC LIMIT 5", ARRAY_A ); // phpcs:ignore
		gat_portal_donations_table( $rows, __( 'No donations received yet.', 'gat-core' ) );
		echo '</section>';
	}

	if ( isset( $sections['students'] ) ) {
		$t      = gat_table( 'students' );
		$counts = $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status", OBJECT_K ); // phpcs:ignore
		$paid   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE payment_status = 'paid'" ); // phpcs:ignore
		echo '<section class="p-section"><div class="p-section__head"><h2>' . esc_html__( 'Sunnah Academy applications', 'gat-core' ) . '</h2><a href="' . esc_url( gat_portal_url( 'students' ) ) . '">' . esc_html__( 'See all', 'gat-core' ) . ' →</a></div>';
		echo '<div class="p-stats">';
		foreach ( gat_labels()['application'] as $k => $label ) {
			printf( '<a class="p-stat p-stat--link" href="%s"><span>%s</span><strong>%d</strong></a>', esc_url( gat_portal_url( 'students', 0, array( 'status' => $k ) ) ), esc_html( $label ), (int) ( $counts[ $k ]->n ?? 0 ) );
		}
		printf( '<div class="p-stat"><span>%s</span><strong>%d</strong></div>', esc_html__( 'Fees paid', 'gat-core' ), $paid ); // phpcs:ignore
		echo '</div>';
		$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 5", ARRAY_A ); // phpcs:ignore
		gat_portal_students_table( $rows, __( 'No applications yet.', 'gat-core' ) );
		echo '</section>';
	}

	if ( isset( $sections['enquiries'] ) ) {
		$new = gat_portal_enquiry_query( 'new', 1, 5 );
		echo '<section class="p-section"><div class="p-section__head"><h2>' . esc_html__( 'New enquiries', 'gat-core' ) . ' <span class="p-count">' . (int) $new->found_posts . '</span></h2><a href="' . esc_url( gat_portal_url( 'enquiries' ) ) . '">' . esc_html__( 'See all', 'gat-core' ) . ' →</a></div>';
		gat_portal_enquiries_table( $new->posts, __( 'No new enquiries. All caught up!', 'gat-core' ) );
		echo '</section>';
	}
}

/* ---- Donations ---------------------------------------------------------- */

/**
 * Donation filters from the URL (portal uses "q" for search).
 *
 * @return array
 */
function gat_portal_donation_filters() {
	$g = wp_unslash( $_GET ); // phpcs:ignore
	return array(
		'status'    => sanitize_key( $g['status'] ?? '' ),
		'frequency' => sanitize_key( $g['frequency'] ?? '' ),
		'source'    => sanitize_key( $g['source'] ?? '' ),
		'month'     => preg_match( '/^\d{4}-\d{2}$/', $g['month'] ?? '' ) ? $g['month'] : '',
		'gift_aid'  => ! empty( $g['gift_aid'] ) ? '1' : '',
		's'         => sanitize_text_field( $g['q'] ?? '' ),
	);
}

/**
 * Donations table.
 *
 * @param array  $rows  Rows.
 * @param string $empty Empty message.
 */
function gat_portal_donations_table( $rows, $empty ) {
	if ( ! $rows ) {
		echo '<div class="p-empty">' . esc_html( $empty ) . '</div>';
		return;
	}
	echo '<div class="p-table-wrap"><table class="p-table"><thead><tr>';
	foreach ( array( __( 'Ref', 'gat-core' ), __( 'Date', 'gat-core' ), __( 'Donor', 'gat-core' ), __( 'Amount', 'gat-core' ), __( 'Cause', 'gat-core' ), __( 'Status', 'gat-core' ) ) as $h ) {
		echo '<th scope="col">' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$url = gat_portal_url( 'donations', $r['id'] );
		printf(
			'<tr><td data-label="%s"><a class="p-rowlink" href="%s">%s</a></td><td data-label="%s">%s</td><td data-label="%s">%s%s</td><td data-label="%s"><strong>%s</strong>%s%s</td><td data-label="%s">%s</td><td data-label="%s">%s</td></tr>',
			esc_attr__( 'Ref', 'gat-core' ),
			esc_url( $url ),
			esc_html( $r['ref'] ),
			esc_attr__( 'Date', 'gat-core' ),
			esc_html( get_date_from_gmt( $r['created_at'], 'j M Y' ) ),
			esc_attr__( 'Donor', 'gat-core' ),
			esc_html( $r['donor_name'] ),
			$r['anonymous'] ? ' <span class="p-muted">(' . esc_html__( 'private', 'gat-core' ) . ')</span>' : '',
			esc_attr__( 'Amount', 'gat-core' ),
			esc_html( gat_money( $r['amount_pence'] ) ),
			'monthly' === $r['frequency'] ? ' <span class="p-muted">' . esc_html__( '/month', 'gat-core' ) . '</span>' : '',
			$r['gift_aid'] ? ' <span class="p-tag">' . esc_html__( 'Gift Aid', 'gat-core' ) . '</span>' : '',
			esc_attr__( 'Cause', 'gat-core' ),
			esc_html( $r['cause'] ),
			esc_attr__( 'Status', 'gat-core' ),
			gat_portal_badge( 'donation', $r['status'] ) // phpcs:ignore
		);
	}
	echo '</tbody></table></div>';
}

/**
 * Donations list.
 */
function gat_portal_donations_list() {
	global $wpdb;
	$f     = gat_portal_donation_filters();
	$pg    = gat_portal_pg();
	$where = gat_donations_where( $f );
	$t     = gat_table( 'donations' );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", GAT_PORTAL_PER_PAGE, ( $pg - 1 ) * GAT_PORTAL_PER_PAGE ), ARRAY_A ); // phpcs:ignore

	$export_args = array_filter( array_merge( $f, array( 'action' => 'gat_export_donations' ) ) );
	$export      = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'gat_export_donations' );
	$actions     = sprintf( '<a class="p-btn" href="%s">%s%s</a>', esc_url( $export ), gat_portal_icon( 'down' ), esc_html__( 'Download (Excel/CSV)', 'gat-core' ) );
	if ( gat_setting( 'gift_aid' ) ) {
		$ga       = wp_nonce_url( add_query_arg( array( 'action' => 'gat_export_donations', 'giftaid_schedule' => 1 ), admin_url( 'admin-post.php' ) ), 'gat_export_donations' );
		$actions .= sprintf( ' <a class="p-btn" href="%s">%s%s</a>', esc_url( $ga ), gat_portal_icon( 'down' ), esc_html__( 'Gift Aid claim file', 'gat-core' ) );
	}
	gat_portal_title( __( 'Donations', 'gat-core' ), $actions );

	echo '<div class="p-stats">';
	foreach ( gat_donation_summary_data() as list( $label, $value ) ) {
		echo '<div class="p-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}
	echo '</div>';
	?>
	<form class="p-filters" method="get" action="<?php echo esc_url( gat_portal_url( 'donations' ) ); ?>">
		<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
			<input type="hidden" name="gat_staff" value="1"><input type="hidden" name="gat_screen" value="donations">
		<?php endif; ?>
		<label><span><?php esc_html_e( 'Search', 'gat-core' ); ?></span><input type="search" name="q" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="<?php esc_attr_e( 'Name, email, ref or cause', 'gat-core' ); ?>"></label>
		<label><span><?php esc_html_e( 'Status', 'gat-core' ); ?></span><select name="status">
			<option value=""><?php esc_html_e( 'All', 'gat-core' ); ?></option>
			<option value="received" <?php selected( $f['status'], 'received' ); ?>><?php esc_html_e( 'Received (paid or monthly)', 'gat-core' ); ?></option>
			<?php foreach ( gat_labels()['donation'] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
		</select></label>
		<label><span><?php esc_html_e( 'Type', 'gat-core' ); ?></span><select name="frequency">
			<option value=""><?php esc_html_e( 'All', 'gat-core' ); ?></option>
			<option value="one_off" <?php selected( $f['frequency'], 'one_off' ); ?>><?php esc_html_e( 'One-off', 'gat-core' ); ?></option>
			<option value="monthly" <?php selected( $f['frequency'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'gat-core' ); ?></option>
		</select></label>
		<label><span><?php esc_html_e( 'Made via', 'gat-core' ); ?></span><select name="source">
			<option value=""><?php esc_html_e( 'All', 'gat-core' ); ?></option>
			<option value="website" <?php selected( $f['source'], 'website' ); ?>><?php esc_html_e( 'Donate page', 'gat-core' ); ?></option>
			<option value="assistant" <?php selected( $f['source'], 'assistant' ); ?>><?php esc_html_e( 'Chat assistant', 'gat-core' ); ?></option>
		</select></label>
		<label><span><?php esc_html_e( 'Month', 'gat-core' ); ?></span><input type="month" name="month" value="<?php echo esc_attr( $f['month'] ); ?>"></label>
		<?php if ( gat_setting( 'gift_aid' ) ) : ?>
			<label class="p-check"><input type="checkbox" name="gift_aid" value="1" <?php checked( $f['gift_aid'], '1' ); ?>> <?php esc_html_e( 'Gift Aid only', 'gat-core' ); ?></label>
		<?php endif; ?>
		<div class="p-filters__btns"><button class="p-btn p-btn--primary" type="submit"><?php esc_html_e( 'Filter', 'gat-core' ); ?></button>
		<a class="p-btn p-btn--ghost" href="<?php echo esc_url( gat_portal_url( 'donations' ) ); ?>"><?php esc_html_e( 'Clear', 'gat-core' ); ?></a></div>
	</form>
	<?php
	gat_portal_donations_table( $rows, __( 'No donations match these filters.', 'gat-core' ) );
	$base = gat_portal_url( 'donations', 0, array_filter( array( 'q' => $f['s'], 'status' => $f['status'], 'frequency' => $f['frequency'], 'source' => $f['source'], 'month' => $f['month'], 'gift_aid' => $f['gift_aid'] ) ) );
	gat_portal_pager( $total, $pg, $base );
}

/**
 * One donation.
 *
 * @param int $id ID.
 */
function gat_portal_donations_view( $id ) {
	$d = gat_get( 'donations', $id );
	if ( ! $d ) {
		gat_portal_title( __( 'Donation not found', 'gat-core' ), '', gat_portal_url( 'donations' ), __( 'All donations', 'gat-core' ) );
		return;
	}
	$monthly = 'monthly' === $d['frequency'];
	gat_portal_title( gat_money( $d['amount_pence'] ) . ( $monthly ? ' ' . __( 'a month', 'gat-core' ) : '' ) . ' · ' . $d['ref'], '', gat_portal_url( 'donations' ), __( 'All donations', 'gat-core' ) );
	echo '<p class="p-badges">' . gat_portal_badge( 'donation', $d['status'] ) . '</p>'; // phpcs:ignore
	echo '<div class="p-cols"><div class="p-card">';
	gat_portal_dl(
		array(
			__( 'Amount', 'gat-core' )          => gat_money( $d['amount_pence'] ) . ' ' . ( $monthly ? __( 'per month', 'gat-core' ) : __( 'one-off', 'gat-core' ) ),
			__( 'Received so far', 'gat-core' ) => gat_money( $d['total_paid_pence'] ) . ( $monthly ? ' (' . sprintf( /* translators: %d payments */ _n( '%d payment', '%d payments', (int) $d['payments_count'], 'gat-core' ), (int) $d['payments_count'] ) . ')' : '' ),
			__( 'Cause', 'gat-core' )           => $d['cause'],
			__( 'Dedication', 'gat-core' )      => $d['dedication'],
			__( 'Donor', 'gat-core' )           => $d['donor_name'] . ( $d['anonymous'] ? ' (' . __( 'wishes to stay private', 'gat-core' ) . ')' : '' ),
			__( 'Email', 'gat-core' )           => gat_portal_mailto( $d['email'] ),
			__( 'Gift Aid', 'gat-core' )        => $d['gift_aid'] ? __( 'Yes', 'gat-core' ) . ' (+' . gat_money( (int) round( $d['amount_pence'] * 0.25 ) ) . ')' : __( 'No', 'gat-core' ),
			__( 'Address', 'gat-core' )         => trim( $d['address'] . "\n" . $d['postcode'] ),
			__( 'Message', 'gat-core' )         => $d['message'],
			__( 'Made via', 'gat-core' )        => 'assistant' === $d['source'] ? __( 'Chat assistant', 'gat-core' ) : __( 'Donate page', 'gat-core' ),
			__( 'Date', 'gat-core' )            => get_date_from_gmt( $d['created_at'], 'j F Y, H:i' ),
			__( 'Last payment', 'gat-core' )    => $d['last_paid_at'] ? get_date_from_gmt( $d['last_paid_at'], 'j F Y, H:i' ) : '',
		)
	);
	echo '</div><div class="p-card">';
	echo '<h2>' . esc_html__( 'Notes', 'gat-core' ) . '</h2>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" class="p-form">';
		gat_portal_form_fields( 'donation_notes', $d['id'] );
		echo '<label for="p-notes" class="screen-reader-text">' . esc_html__( 'Notes', 'gat-core' ) . '</label><textarea id="p-notes" name="notes" rows="6">' . esc_textarea( (string) $d['notes'] ) . '</textarea>';
		echo '<button class="p-btn p-btn--primary" type="submit">' . esc_html__( 'Save notes', 'gat-core' ) . '</button></form>';
	} else {
		echo $d['notes'] ? '<p>' . nl2br( esc_html( $d['notes'] ) ) . '</p>' : '<p class="p-muted">' . esc_html__( 'No notes.', 'gat-core' ) . '</p>';
	}
	echo '<p class="p-help">' . esc_html__( 'Payment status updates automatically from Stripe. Refunds and cancelling monthly gifts are done in Stripe.', 'gat-core' ) . '</p>';
	echo '</div></div>';
}

/* ---- Students ----------------------------------------------------------- */

/**
 * Student filters from the URL.
 *
 * @return array
 */
function gat_portal_student_filters() {
	$g = wp_unslash( $_GET ); // phpcs:ignore
	return array(
		'status'  => sanitize_key( $g['status'] ?? '' ),
		'payment' => sanitize_key( $g['payment'] ?? '' ),
		'class'   => sanitize_text_field( $g['class'] ?? '' ),
		's'       => sanitize_text_field( $g['q'] ?? '' ),
	);
}

/**
 * Students table.
 *
 * @param array  $rows  Rows.
 * @param string $empty Empty message.
 */
function gat_portal_students_table( $rows, $empty ) {
	if ( ! $rows ) {
		echo '<div class="p-empty">' . esc_html( $empty ) . '</div>';
		return;
	}
	$cols = array( __( 'Student ID', 'gat-core' ), __( 'Student', 'gat-core' ), __( 'Parent / Guardian', 'gat-core' ), __( 'Contact number', 'gat-core' ), __( 'Class', 'gat-core' ), __( 'Applied', 'gat-core' ), __( 'Payment', 'gat-core' ), __( 'Application', 'gat-core' ) );
	echo '<div class="p-table-wrap"><table class="p-table"><thead><tr>';
	foreach ( $cols as $h ) {
		echo '<th scope="col">' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$cells = array(
			'<a class="p-rowlink" href="' . esc_url( gat_portal_url( 'students', $r['id'] ) ) . '">' . esc_html( $r['ref'] ) . '</a>',
			'<strong>' . esc_html( $r['student_name'] ) . '</strong>',
			esc_html( $r['parent_name'] ),
			esc_html( $r['phone'] ),
			esc_html( $r['class_name'] ),
			esc_html( get_date_from_gmt( $r['created_at'], 'j M Y' ) ),
			gat_portal_badge( 'payment', $r['payment_status'] ),
			gat_portal_badge( 'application', $r['status'] ),
		);
		echo '<tr>';
		foreach ( $cells as $i => $c ) {
			echo '<td data-label="' . esc_attr( $cols[ $i ] ) . '">' . $c . '</td>'; // phpcs:ignore
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Students list.
 */
function gat_portal_students_list() {
	global $wpdb;
	$f      = gat_portal_student_filters();
	$pg     = gat_portal_pg();
	$t      = gat_table( 'students' );
	$where  = gat_students_where( $f );
	$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", GAT_PORTAL_PER_PAGE, ( $pg - 1 ) * GAT_PORTAL_PER_PAGE ), ARRAY_A ); // phpcs:ignore
	$counts = $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status", OBJECT_K ); // phpcs:ignore
	$classes = array_unique( array_filter( array_merge( gat_setting_lines( 'enrol_classes' ), (array) $wpdb->get_col( "SELECT DISTINCT class_name FROM $t" ) ) ) ); // phpcs:ignore
	sort( $classes );

	$export  = wp_nonce_url( add_query_arg( array_filter( array_merge( $f, array( 'action' => 'gat_export_students' ) ) ), admin_url( 'admin-post.php' ) ), 'gat_export_students' );
	$actions = sprintf( '<a class="p-btn" href="%s">%s%s</a> <a class="p-btn p-btn--ghost" href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $export ), gat_portal_icon( 'down' ), esc_html__( 'Download (Excel/CSV)', 'gat-core' ), esc_url( gat_page_url( 'sunnah-academy/enrol' ) ), esc_html__( 'Open the enrolment form', 'gat-core' ) );
	gat_portal_title( __( 'Sunnah Academy students', 'gat-core' ), $actions );

	$keep = array_filter( array( 'q' => $f['s'], 'payment' => $f['payment'], 'class' => $f['class'] ) );
	echo '<nav class="p-tabs" aria-label="' . esc_attr__( 'Application status', 'gat-core' ) . '">';
	$all = array_sum( array_map( fn( $c ) => (int) $c->n, $counts ) );
	printf( '<a href="%s"%s>%s <span>%d</span></a>', esc_url( gat_portal_url( 'students', 0, $keep ) ), $f['status'] ? '' : ' aria-current="page"', esc_html__( 'All', 'gat-core' ), $all ); // phpcs:ignore
	foreach ( gat_labels()['application'] as $k => $l ) {
		printf( '<a href="%s"%s>%s <span>%d</span></a>', esc_url( gat_portal_url( 'students', 0, array_merge( $keep, array( 'status' => $k ) ) ) ), $f['status'] === $k ? ' aria-current="page"' : '', esc_html( $l ), (int) ( $counts[ $k ]->n ?? 0 ) ); // phpcs:ignore
	}
	echo '</nav>';
	?>
	<form class="p-filters" method="get" action="<?php echo esc_url( gat_portal_url( 'students' ) ); ?>">
		<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
			<input type="hidden" name="gat_staff" value="1"><input type="hidden" name="gat_screen" value="students">
		<?php endif; ?>
		<?php if ( $f['status'] ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $f['status'] ); ?>"><?php endif; ?>
		<label><span><?php esc_html_e( 'Search', 'gat-core' ); ?></span><input type="search" name="q" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="<?php esc_attr_e( 'Student, parent, ref, email, phone, postcode', 'gat-core' ); ?>"></label>
		<label><span><?php esc_html_e( 'Payment', 'gat-core' ); ?></span><select name="payment">
			<option value=""><?php esc_html_e( 'All', 'gat-core' ); ?></option>
			<?php foreach ( gat_labels()['payment'] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['payment'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
		</select></label>
		<?php if ( $classes ) : ?>
			<label><span><?php esc_html_e( 'Class', 'gat-core' ); ?></span><select name="class">
				<option value=""><?php esc_html_e( 'All', 'gat-core' ); ?></option>
				<?php foreach ( $classes as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $f['class'], $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?>
			</select></label>
		<?php endif; ?>
		<div class="p-filters__btns"><button class="p-btn p-btn--primary" type="submit"><?php esc_html_e( 'Filter', 'gat-core' ); ?></button>
		<a class="p-btn p-btn--ghost" href="<?php echo esc_url( gat_portal_url( 'students' ) ); ?>"><?php esc_html_e( 'Clear', 'gat-core' ); ?></a></div>
	</form>
	<?php
	gat_portal_students_table( $rows, __( 'No applications match these filters.', 'gat-core' ) );
	gat_portal_pager( $total, $pg, gat_portal_url( 'students', 0, array_filter( array_merge( $keep, array( 'status' => $f['status'] ) ) ) ) );
	echo '<p class="p-help">' . esc_html__( 'This list is private. Only Administrators and Academy Admins can see it.', 'gat-core' ) . '</p>';
}

/**
 * One application.
 *
 * @param int $id ID.
 */
function gat_portal_students_view( $id ) {
	$s = gat_get( 'students', $id );
	if ( ! $s ) {
		gat_portal_title( __( 'Application not found', 'gat-core' ), '', gat_portal_url( 'students' ), __( 'All students', 'gat-core' ) );
		return;
	}
	if ( ! empty( $_GET['edit'] ) ) { // phpcs:ignore
		gat_portal_student_edit( $s );
		return;
	}
	$edit = sprintf( '<a class="p-btn" href="%s">%s</a>', esc_url( gat_portal_url( 'students', $s['id'], array( 'edit' => 1 ) ) ), esc_html__( 'Edit details', 'gat-core' ) );
	gat_portal_title( $s['student_name'] . ' · ' . $s['ref'], $edit, gat_portal_url( 'students' ), __( 'All students', 'gat-core' ) );
	echo '<p class="p-badges">' . gat_portal_badge( 'application', $s['status'] ) . ' ' . gat_portal_badge( 'payment', $s['payment_status'] ) . ' <span class="p-muted">' . esc_html( sprintf( /* translators: %s date */ __( 'Applied %s', 'gat-core' ), get_date_from_gmt( $s['created_at'], 'j F Y, H:i' ) ) ) . '</span></p>'; // phpcs:ignore
	$decl = json_decode( (string) $s['declarations'], true );
	echo '<div class="p-cols p-cols--wide"><div>';

	echo '<div class="p-card"><h2>' . esc_html__( 'Student', 'gat-core' ) . '</h2>';
	gat_portal_dl(
		array(
			__( 'Full name', 'gat-core' )         => $s['student_name'],
			__( 'Date of birth', 'gat-core' )     => $s['dob'] ? wp_date( 'j F Y', strtotime( $s['dob'] . ' 12:00:00' ) ) : '',
			__( 'Address', 'gat-core' )           => trim( $s['address'] . "\n" . $s['postcode'] ),
			__( 'Other information', 'gat-core' ) => $s['student_info'],
		)
	);
	echo '</div><div class="p-card"><h2>' . esc_html__( 'Parent / guardian', 'gat-core' ) . '</h2>';
	gat_portal_dl(
		array(
			__( 'Name', 'gat-core' )              => $s['parent_name'] . ( $s['relationship'] ? ' (' . $s['relationship'] . ')' : '' ),
			__( 'Telephone', 'gat-core' )         => gat_portal_tel( $s['phone'] ),
			__( 'Email', 'gat-core' )             => gat_portal_mailto( $s['email'] ),
			__( 'Emergency contact', 'gat-core' ) => trim( $s['emergency_name'] . ' ' . $s['emergency_phone'] ),
		)
	);
	echo '</div><div class="p-card"><h2>' . esc_html__( 'Enrolment', 'gat-core' ) . '</h2>';
	gat_portal_dl(
		array(
			__( 'Programme / class', 'gat-core' )      => $s['class_name'],
			__( 'Preferred start', 'gat-core' )        => $s['start_date'] ? wp_date( 'j F Y', strtotime( $s['start_date'] . ' 12:00:00' ) ) : '',
			__( 'Additional information', 'gat-core' ) => $s['additional_info'],
			__( 'Fee', 'gat-core' )                    => (int) $s['fee_pence'] ? gat_money( $s['fee_pence'] ) . ( $s['paid_at'] ? ' · ' . sprintf( /* translators: %s date */ __( 'paid %s', 'gat-core' ), get_date_from_gmt( $s['paid_at'], 'j M Y' ) ) : '' ) : __( 'No fee', 'gat-core' ),
			__( 'Declarations', 'gat-core' )           => $decl ? __( 'All confirmed by the parent/guardian', 'gat-core' ) : '',
		)
	);
	echo '</div></div>';
	?>
	<div class="p-card p-card--sticky">
		<h2><?php esc_html_e( 'Decision', 'gat-core' ); ?></h2>
		<form method="post" class="p-form">
			<?php gat_portal_form_fields( 'student_status', $s['id'] ); ?>
			<fieldset class="p-seg">
				<legend><?php esc_html_e( 'Application', 'gat-core' ); ?></legend>
				<?php foreach ( gat_labels()['application'] as $k => $l ) : ?>
					<label class="p-seg__opt p-seg__opt--<?php echo esc_attr( $k ); ?>"><input type="radio" name="status" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['status'], $k ); ?>><span><?php echo esc_html( $l ); ?></span></label>
				<?php endforeach; ?>
			</fieldset>
			<label for="p-pay"><?php esc_html_e( 'Payment', 'gat-core' ); ?></label>
			<select id="p-pay" name="payment_status">
				<?php foreach ( gat_labels()['payment'] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['payment_status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
			</select>
			<p class="p-help"><?php esc_html_e( 'Updated automatically by Stripe. Change it only for fees paid another way (e.g. cash).', 'gat-core' ); ?></p>
			<label for="p-notes"><?php esc_html_e( 'Staff notes (private)', 'gat-core' ); ?></label>
			<textarea id="p-notes" name="notes" rows="5"><?php echo esc_textarea( (string) $s['notes'] ); ?></textarea>
			<button class="p-btn p-btn--primary p-btn--block" type="submit"><?php esc_html_e( 'Save', 'gat-core' ); ?></button>
		</form>
	</div>
	<?php
	echo '</div>';
}

/**
 * Edit an application's details.
 *
 * @param array $s Row.
 */
function gat_portal_student_edit( $s ) {
	gat_portal_title( __( 'Edit details', 'gat-core' ) . ' · ' . $s['ref'], '', gat_portal_url( 'students', $s['id'] ), $s['student_name'] );
	$fields = array(
		__( 'Student', 'gat-core' )           => array(
			'student_name' => array( __( 'Full name', 'gat-core' ), 'text', true ),
			'dob'          => array( __( 'Date of birth', 'gat-core' ), 'date' ),
			'address'      => array( __( 'Address', 'gat-core' ), 'textarea' ),
			'postcode'     => array( __( 'Postcode', 'gat-core' ), 'text' ),
			'student_info' => array( __( 'Other information', 'gat-core' ), 'textarea' ),
		),
		__( 'Parent / guardian', 'gat-core' ) => array(
			'parent_name'     => array( __( 'Full name', 'gat-core' ), 'text', true ),
			'relationship'    => array( __( 'Relationship to student', 'gat-core' ), 'text' ),
			'phone'           => array( __( 'Telephone', 'gat-core' ), 'tel' ),
			'email'           => array( __( 'Email', 'gat-core' ), 'email' ),
			'emergency_name'  => array( __( 'Emergency contact name', 'gat-core' ), 'text' ),
			'emergency_phone' => array( __( 'Emergency contact telephone', 'gat-core' ), 'tel' ),
		),
		__( 'Enrolment', 'gat-core' )         => array(
			'class_name'      => array( __( 'Programme / class', 'gat-core' ), 'text' ),
			'start_date'      => array( __( 'Preferred start date', 'gat-core' ), 'date' ),
			'additional_info' => array( __( 'Additional information', 'gat-core' ), 'textarea' ),
		),
	);
	echo '<form method="post" class="p-form p-form--edit">';
	gat_portal_form_fields( 'student_edit', $s['id'] );
	foreach ( $fields as $group => $list ) {
		echo '<div class="p-card"><h2>' . esc_html( $group ) . '</h2><div class="p-grid">';
		foreach ( $list as $name => $def ) {
			$req = ! empty( $def[2] ) ? ' required' : '';
			echo '<div class="p-field' . ( 'textarea' === $def[1] ? ' p-field--wide' : '' ) . '"><label for="e-' . esc_attr( $name ) . '">' . esc_html( $def[0] ) . ( $req ? ' *' : '' ) . '</label>';
			if ( 'textarea' === $def[1] ) {
				echo '<textarea id="e-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( (string) $s[ $name ] ) . '</textarea>';
			} else {
				echo '<input id="e-' . esc_attr( $name ) . '" type="' . esc_attr( $def[1] ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $s[ $name ] ) . '"' . $req . '>'; // phpcs:ignore
			}
			echo '</div>';
		}
		echo '</div></div>';
	}
	printf( '<div class="p-form__actions"><button class="p-btn p-btn--primary" type="submit">%s</button> <a class="p-btn p-btn--ghost" href="%s">%s</a></div>', esc_html__( 'Save changes', 'gat-core' ), esc_url( gat_portal_url( 'students', $s['id'] ) ), esc_html__( 'Cancel', 'gat-core' ) );
	echo '</form>';
}

/* ---- Enquiries ---------------------------------------------------------- */

/**
 * Enquiries query.
 *
 * @param string $tab      new|done|all.
 * @param int    $page     Page.
 * @param int    $per_page Per page.
 * @param string $search   Search.
 * @return WP_Query
 */
function gat_portal_enquiry_query( $tab, $page = 1, $per_page = GAT_PORTAL_PER_PAGE, $search = '' ) {
	$args = array(
		'post_type'      => 'gat_enquiry',
		'post_status'    => array( 'private', 'publish', 'draft', 'pending' ),
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'orderby'        => 'date',
		'order'          => 'DESC',
		's'              => $search,
	);
	if ( 'new' === $tab ) {
		$args['meta_query'] = array( array( 'key' => '_gat_done', 'compare' => 'NOT EXISTS' ) ); // phpcs:ignore
	} elseif ( 'done' === $tab ) {
		$args['meta_query'] = array( array( 'key' => '_gat_done', 'compare' => 'EXISTS' ) ); // phpcs:ignore
	}
	return new WP_Query( $args );
}

/**
 * Enquiries table.
 *
 * @param WP_Post[] $posts Posts.
 * @param string    $empty Empty message.
 */
function gat_portal_enquiries_table( $posts, $empty ) {
	if ( ! $posts ) {
		echo '<div class="p-empty">' . esc_html( $empty ) . '</div>';
		return;
	}
	$forms = gat_core_enquiry_forms();
	$cols  = array( __( 'Received', 'gat-core' ), __( 'Form', 'gat-core' ), __( 'From', 'gat-core' ), __( 'Subject', 'gat-core' ), __( 'Status', 'gat-core' ) );
	echo '<div class="p-table-wrap"><table class="p-table"><thead><tr>';
	foreach ( $cols as $h ) {
		echo '<th scope="col">' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $posts as $p ) {
		$type    = get_post_meta( $p->ID, '_gat_type', true );
		$from    = trim( get_post_meta( $p->ID, '_gat_name', true ) . ( 'advertising' === $type ? ' · ' . get_post_meta( $p->ID, '_gat_company', true ) : '' ) );
		$subject = get_post_meta( $p->ID, '_gat_subject', true ) ?: ( get_post_meta( $p->ID, '_gat_topic', true ) ?: $p->post_title );
		$done    = (bool) get_post_meta( $p->ID, '_gat_done', true );
		$cells   = array(
			esc_html( get_the_date( 'j M Y, H:i', $p ) ),
			esc_html( $forms[ $type ]['label'] ?? $type ),
			esc_html( $from ),
			'<a class="p-rowlink" href="' . esc_url( gat_portal_url( 'enquiries', $p->ID ) ) . '">' . esc_html( $subject ) . '</a>',
			$done ? '<span class="p-badge p-badge--grey">' . esc_html__( 'Done', 'gat-core' ) . '</span>' : '<span class="p-badge p-badge--amber">' . esc_html__( 'New', 'gat-core' ) . '</span>',
		);
		echo '<tr>';
		foreach ( $cells as $i => $c ) {
			echo '<td data-label="' . esc_attr( $cols[ $i ] ) . '">' . $c . '</td>'; // phpcs:ignore
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Enquiries list.
 */
function gat_portal_enquiries_list() {
	$tab    = sanitize_key( $_GET['tab'] ?? 'new' ); // phpcs:ignore
	$tab    = in_array( $tab, array( 'new', 'done', 'all' ), true ) ? $tab : 'new';
	$search = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore
	$pg     = gat_portal_pg();
	$q      = gat_portal_enquiry_query( $tab, $pg, GAT_PORTAL_PER_PAGE, $search );
	gat_portal_title( __( 'Enquiries from the website', 'gat-core' ) );
	$tabs = array(
		'new'  => array( __( 'New', 'gat-core' ), gat_portal_enquiry_query( 'new', 1, 1 )->found_posts ),
		'done' => array( __( 'Done', 'gat-core' ), gat_portal_enquiry_query( 'done', 1, 1 )->found_posts ),
		'all'  => array( __( 'All', 'gat-core' ), gat_portal_enquiry_query( 'all', 1, 1 )->found_posts ),
	);
	echo '<nav class="p-tabs" aria-label="' . esc_attr__( 'Enquiry status', 'gat-core' ) . '">';
	foreach ( $tabs as $k => list( $label, $n ) ) {
		printf( '<a href="%s"%s>%s <span>%d</span></a>', esc_url( gat_portal_url( 'enquiries', 0, array( 'tab' => $k ) ) ), $tab === $k ? ' aria-current="page"' : '', esc_html( $label ), (int) $n ); // phpcs:ignore
	}
	echo '</nav>';
	?>
	<form class="p-filters" method="get" action="<?php echo esc_url( gat_portal_url( 'enquiries' ) ); ?>">
		<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
			<input type="hidden" name="gat_staff" value="1"><input type="hidden" name="gat_screen" value="enquiries">
		<?php endif; ?>
		<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
		<label><span><?php esc_html_e( 'Search', 'gat-core' ); ?></span><input type="search" name="q" value="<?php echo esc_attr( $search ); ?>"></label>
		<div class="p-filters__btns"><button class="p-btn p-btn--primary" type="submit"><?php esc_html_e( 'Search', 'gat-core' ); ?></button></div>
	</form>
	<?php
	gat_portal_enquiries_table( $q->posts, 'new' === $tab ? __( 'No new enquiries. All caught up!', 'gat-core' ) : __( 'Nothing here.', 'gat-core' ) );
	gat_portal_pager( (int) $q->found_posts, $pg, gat_portal_url( 'enquiries', 0, array_filter( array( 'tab' => $tab, 'q' => $search ) ) ) );
}

/**
 * One enquiry.
 *
 * @param int $id ID.
 */
function gat_portal_enquiries_view( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'gat_enquiry' !== $post->post_type ) {
		gat_portal_title( __( 'Enquiry not found', 'gat-core' ), '', gat_portal_url( 'enquiries' ), __( 'All enquiries', 'gat-core' ) );
		return;
	}
	$forms = gat_core_enquiry_forms();
	$type  = get_post_meta( $id, '_gat_type', true );
	$def   = $forms[ $type ] ?? $forms['contact'];
	$done  = get_post_meta( $id, '_gat_done', true );
	gat_portal_title( $post->post_title, '', gat_portal_url( 'enquiries' ), __( 'All enquiries', 'gat-core' ) );
	$rows = array(
		__( 'Form', 'gat-core' )     => $def['label'],
		__( 'Received', 'gat-core' ) => get_the_date( 'j F Y, H:i', $post ),
	);
	foreach ( $def['fields'] as $key => $f ) {
		$val = (string) get_post_meta( $id, '_gat_' . $key, true );
		if ( 'email' === $key ) {
			$val = gat_portal_mailto( $val );
		} elseif ( 'phone' === $key ) {
			$val = gat_portal_tel( $val );
		} elseif ( 'website' === $key && $val ) {
			$val = array( '<a href="' . esc_url( $val ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $val ) . '</a>' );
		}
		$rows[ $f[0] ] = $val;
	}
	echo '<div class="p-cols"><div class="p-card">';
	gat_portal_dl( $rows );
	echo '</div><div class="p-card">';
	$email = (string) get_post_meta( $id, '_gat_email', true );
	if ( $email ) {
		$subject = 'Re: ' . ( get_post_meta( $id, '_gat_subject', true ) ?: __( 'Your enquiry to Global Alliance Trust', 'gat-core' ) );
		printf( '<p><a class="p-btn p-btn--primary p-btn--block" href="mailto:%s?subject=%s">%s%s</a></p>', esc_attr( $email ), rawurlencode( $subject ), gat_portal_icon( 'mail' ), esc_html__( 'Reply by email', 'gat-core' ) ); // phpcs:ignore
	}
	echo '<form method="post" class="p-form">';
	gat_portal_form_fields( 'enquiry_done', $id );
	if ( $done ) {
		$by = get_userdata( (int) get_post_meta( $id, '_gat_done_by', true ) );
		echo '<p class="p-muted">' . esc_html( sprintf( /* translators: 1: date 2: name */ __( 'Marked done %1$s%2$s.', 'gat-core' ), get_date_from_gmt( $done, 'j M Y, H:i' ), $by ? ' ' . sprintf( /* translators: %s name */ __( 'by %s', 'gat-core' ), $by->display_name ) : '' ) ) . '</p>';
		echo '<button class="p-btn p-btn--block" type="submit">' . esc_html__( 'Move back to New', 'gat-core' ) . '</button>';
	} else {
		echo '<input type="hidden" name="done" value="1"><button class="p-btn p-btn--block" type="submit">' . esc_html__( 'Mark as done', 'gat-core' ) . '</button>';
	}
	echo '</form></div></div>';
}

/* ---- Account ------------------------------------------------------------ */

/**
 * My account.
 */
function gat_portal_account() {
	$user  = wp_get_current_user();
	$roles = wp_roles();
	gat_portal_title( __( 'My account', 'gat-core' ) );
	echo '<div class="p-cols"><div class="p-card"><h2>' . esc_html__( 'Your details', 'gat-core' ) . '</h2>';
	gat_portal_dl(
		array(
			__( 'Name', 'gat-core' )     => $user->display_name,
			__( 'Email', 'gat-core' )    => $user->user_email,
			__( 'Username', 'gat-core' ) => $user->user_login,
			__( 'Role', 'gat-core' )     => implode( ', ', array_map( fn( $r ) => translate_user_role( $roles->roles[ $r ]['name'] ?? $r ), $user->roles ) ),
		)
	);
	printf( '<p class="p-help">%s <a href="%s">%s</a></p>', esc_html__( '2-step login and admin language:', 'gat-core' ), esc_url( admin_url( 'profile.php' ) ), esc_html__( 'profile settings', 'gat-core' ) );
	echo '</div><div class="p-card"><h2>' . esc_html__( 'Change password', 'gat-core' ) . '</h2>';
	echo '<form method="post" class="p-form" autocomplete="off">';
	gat_portal_form_fields( 'password', 0 );
	echo '<label for="p-cur">' . esc_html__( 'Current password', 'gat-core' ) . '</label><input id="p-cur" type="password" name="current_password" autocomplete="current-password" required>';
	echo '<label for="p-new">' . esc_html__( 'New password (at least 10 characters)', 'gat-core' ) . '</label><input id="p-new" type="password" name="new_password" autocomplete="new-password" minlength="10" required>';
	echo '<label for="p-new2">' . esc_html__( 'New password again', 'gat-core' ) . '</label><input id="p-new2" type="password" name="confirm_password" autocomplete="new-password" minlength="10" required>';
	echo '<button class="p-btn p-btn--primary" type="submit">' . esc_html__( 'Change password', 'gat-core' ) . '</button></form>';
	echo '</div></div>';
}
