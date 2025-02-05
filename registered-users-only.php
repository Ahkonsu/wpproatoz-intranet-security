<?php /*

**************************************************************************

Plugin Name:  Intranet: Limit access to registered users
Plugin URI:   http://WPPluginsAtoZ.com
OrigPlugin URI:   http://www.viper007bond.com/wordpress-plugins/registered-users-only/
Description:  This is a fork of the Registered Users Only Plugin at <a href="http://www.viper007bond.com/wordpress-plugins/registered-users-only/">Registered Users Only</a>. Redirects all non-logged in users to your login form. This also allows you to create a list of IPs that can bypass the lockdown so you can create a company intranet website. Add your own custom message for the login page as well as a single page that users can register for the site at. Make sure to <a href="options-general.php?page=registered-users-only">disable registration</a> if you want your blog truely private.
Version:      1.0
Author: WP Plugins A to Z
OrigAuthor:       Viper007Bond
Author URI:   http://WPPluginsAtoZ.com/
OriginalAuthor URI:   http://www.viper007bond.com/

**************************************************************************/
////
// remove update notice for forked plugins
function remove_update_notification($value) {

    if ( isset( $value ) && is_object( $value ) ) {
        unset( $value->response[ plugin_basename(__FILE__) ] );
    }

    return $value;
}
add_filter( 'site_transient_update_plugins', 'remove_update_notification' );
/////
class RegisteredUsersOnly {
	var $exclusions = array();

	// Class initialization
	function RegisteredUsersOnly () {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's folder and name it "registered-users-only-[value in wp-config].mo"
		load_plugin_textdomain( 'registered-users-only', '/wp-content/plugins/registered-users-only' );

		// Register our hooks
		add_action( 'wp', array(&$this, 'MaybeRedirect') );
		add_action( 'init', array(&$this, 'LoginFormMessage') );
		add_action( 'admin_menu', array(&$this, 'AddAdminMenu') );
		add_action( 'login_head', array(&$this, 'NoIndex'), 1 );

		if ( isset($_POST['regusersonly_action']) && 'update' == $_POST['regusersonly_action'] )
			add_action( 'init', array(&$this, 'POSTHandle') );
	}


	// Register the options page
	function AddAdminMenu() {
		add_options_page( __('Registered Users Only Options', 'registered-users-only'), __('Registered Only', 'registered-users-only'), 'manage_options', 'registered-users-only', array(&$this, 'OptionsPage') );
	}


	// Depending on conditions, run an authentication check
	function MaybeRedirect() {
		// If the user is logged in, then abort
		if ( current_user_can('read') ) return;
			
		//$ipaddress = $_SERVER["REMOTE_ADDR"];
		//if ($ipaddress =='67.244.22.206') return;
		//CUSTOMIZED LOGIN FROM IPs - KS
		$allowed = array();
			for ($i=0; $i<254; $i++)
			{
				$allowed[] = '65.163.81.'. $i;	//MC2 Internal net block
			}
			$allowed[] = '72.37.171.60';	//mc2 network
			$allowed[] = '10.1.21.66';
			$allowed[] = '161.69.22.122';
			$allowed[] = '70.66.190.13'; //john
		//var_dump($allowed);
			if (in_array ($_SERVER['REMOTE_ADDR'], $allowed)){
				return;
			}
		
		$settings = get_option( 'registered-users-only' );

		// Feeds
		if ( 1 == $settings['feeds'] && is_feed() ) return;

		// This is a base array of pages that will be EXCLUDED from being blocked
		$this->exclusions = array(
			'wp-login.php',
			'wp-register.php',
			'register.php',
			'wp-cron.php', // Just incase
			'wp-trackback.php',
			'wp-app.php',
			'xmlrpc.php',
			'wp-signup.php',
			'wp-activate.php',
			'user.php',
			'activate.php',
			'activate-success.php',
			'signups.php',
			'activate-error.php',
			'activate-no-key.php'
		);

		// If the current script name is in the exclusion list, abort
		if ( in_array( basename($_SERVER['PHP_SELF']), apply_filters( 'registered-users-only_exclusions', $this->exclusions) ) ) return;
		// Still here? Okay, then redirect to the login form
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
		if( $protocol.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] == get_site_url()."/register/" ) return;

		auth_redirect();
	}


	// Use some deprecate code (yeah, I know) to insert a "You must login" error message to the login form
	// If this breaks in the future, oh well, it's just a pretty message for users
	function LoginFormMessage() {
		// Don't show the error message if anything else is going on (registration, etc.)
		if ( 'wp-login.php' != basename($_SERVER['PHP_SELF']) || !empty($_POST) || ( !empty($_GET) && empty($_GET['redirect_to']) ) ) return;

		global $error;
		$error = __( '<a href="/register/">Register now</a> or login below.', 'registered-users-only' );
//		$error = __( 'Inside MC2 Intranet is now available wherever you are – in the office or on mobile. But to do so you need to register and then login for the first time. It is fast and easy. <br><br><a href="/register/">Register now</a> or login below.', 'registered-users-only' );
	}


	// Tell bots to go away (they shouldn't index the login form)
	function NoIndex() {
		echo "	<meta name='robots' content='noindex,nofollow' />\n";
	}


	// Update options submitted from the options form
	function POSTHandle() {
		if ( !current_user_can('manage_options') )
			wp_die(__('Cheatin&#8217; uh?'));

		check_admin_referer('registered-users-only');

		$settings = array(
			'feeds' => $_POST['regusersonly_feeds'],
		);

		update_option( 'registered-users-only', $settings );

		update_option( 'users_can_register', $_POST['users_can_register'] );

		wp_redirect( add_query_arg('updated', 'true') );
		exit();
	}


	// Output the configuration page for the plugin
	function OptionsPage() {
		$settings = get_option( 'registered-users-only' );
?>

<div class="wrap">
	<h2><?php _e( 'Registered Users Only', 'registered-users-only' ); ?></h2>

	<form method="post" action="">
<?php wp_nonce_field('registered-users-only') ?>

<table class="form-table">
	<tr valign="top">
		<th scope="row"><?php _e( 'Membership' ); ?></th>
		<td>
			<label for="users_can_register">
				<input name="users_can_register" type="checkbox" id="users_can_register" value="1"<?php checked('1', get_option('users_can_register')); ?> />
				<?php _e( 'Anyone can register' ) ?>
			</label><br />
			<?php _e( 'This is a default WordPress option placed here for easy changing.', 'registered-users-only' ); ?>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e( 'Guest Access', 'registered-users-only' ); ?></th>
		<td>
			<label for="regusersonly_feeds">
				<input name="regusersonly_feeds" type="checkbox" id="regusersonly_feeds" value="1"<?php checked('1', $settings['feeds']); ?> />
				<?php _e( 'Allow access to your post and comment feeds (Warning: this will reveal all post contents to guests!)', 'registered-users-only' ); ?>
			</label><br />
		</td>
	</tr>
</table>

<p class="submit">
	<input type="submit" name="Submit" value="<?php _e( 'Save Changes' ) ?>" />
	<input type="hidden" name="regusersonly_action" value="update" />
</p>
</form>

</div>

<?php
	}
}

// Start this plugin once all other plugins are fully loaded
add_action( 'plugins_loaded', create_function( '', 'global $RegisteredUsersOnly; $RegisteredUsersOnly = new RegisteredUsersOnly();' ) );
?>