<?php
/*
Copyright 2012  Amaury Balmer (amaury@beapi.fr)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Suppress DateTime warnings
date_default_timezone_set(@date_default_timezone_get());

// Auth only for PHP/Apache
if ( strpos( php_sapi_name( ), 'cgi' ) === false ) {
	define('LOGIN', getenv("PHPINFO_LOGIN"));
	define('PASSWORD', getenv("PHPINFO_PASSWORD"));

	if( !isset($_SERVER['PHP_AUTH_USER']) || ($_SERVER['PHP_AUTH_PW'] != PASSWORD || $_SERVER['PHP_AUTH_USER'] != LOGIN) ) {
		header('WWW-Authenticate: Basic realm="Authentification"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Authentification failed';
		exit();
	}
}

function phpwpinfo( ) {
	$info = new PHP_WP_Info( );
	$info->init_all_tests( );
}

/**
 * TODO: Use or not session for save DB configuration
 */
class PHP_WP_Info {
	private $debug_mode = true;
	private $php_version = '5.2.4';
	private $mysql_version = '5.0';

	private $db_infos = array( );
	private $db_link = null;

	public function __construct( ) {
		@session_start( );

		if ( $this->debug_mode == true ) {
			ini_set( 'display_errors', 1 );
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', dirname( __FILE__ ) . '/error_log.txt' );
			error_reporting( E_ALL );
		}

		// Check GET for phpinfo
		if ( isset( $_GET ) && isset( $_GET['phpinfo'] ) && $_GET['phpinfo'] == 'true' ) {
			phpinfo( );
			exit( );
		}

		// Check GET for self-destruction
		if ( isset( $_GET ) && isset( $_GET['self-destruction'] ) && $_GET['self-destruction'] == 'true' ) {
			@unlink( __FILE__ );
			clearstatcache();
			if ( is_file(__FILE__) ) {
				die( 'Self-destruction KO ! Sorry, but you must remove me manually !' );
			}
			die( 'Self-destruction OK !' );
		}

		$this->_check_request_mysql( );
		$this->_check_request_adminer( );
		$this->_check_request_phpsecinfo( );
		$this->_check_request_wordpress( );
	}

	public function init_all_tests( ) {
		$this->get_header( );

		$this->test_versions( );
		$this->test_php_config( );
		$this->test_php_extensions( );
		$this->test_mysql_config( );
		$this->test_apache_modules( );
		$this->test_form_mail( );

		$this->get_footer( );
	}

	/**
	 * Main test, check if php/mysql are installed and right version for WP
	 */
	public function test_versions( ) {
		$this->html_table_open( 'General informations & tests PHP/MySQL Version', '', 'Required', 'Current' );

		// Webserver used
		$this->html_table_row( 'Web server', $this->_get_current_webserver( ), '', 'info', 2 );

		// Test PHP Version
		$sapi_type = php_sapi_name( );
		if ( strpos( $sapi_type, 'cgi' ) !== false ) {
			$this->html_table_row( 'PHP Type', 'CGI with Apache Worker or another webserver', '', 'success', 2 );
		} else {
			$this->html_table_row( 'PHP Type', 'Apache Module (low performance)', '', 'warning', 2 );
		}

		// Test PHP Version
		$php_version = phpversion( );
		if ( version_compare( $php_version, $this->php_version, '>=' ) ) {
			$this->html_table_row( 'PHP Version', $this->php_version, $php_version, 'success' );
		} else {
			$this->html_table_row( 'PHP Version', $this->php_version, $php_version, 'error' );
		}

		// Test MYSQL Client extensions/version
		if ( !extension_loaded( 'mysql' ) || !is_callable( 'mysql_connect' ) ) {
			$this->html_table_row( 'PHP MySQL Extension', 'Required', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'PHP MySQL Extension', 'Required', 'Installed', 'success' );
			$this->html_table_row( 'PHP MySQL Client Version', $this->mysql_version, mysql_get_client_info( ), 'info' );
		}

		// Test MySQL Server Version
		if ( $this->db_link != false && is_callable( 'mysql_get_server_info' ) ) {
			$mysql_version = preg_replace( '/[^0-9.].*/', '', mysql_get_server_info( $this->db_link ) );
			if ( version_compare( $mysql_version, $this->mysql_version, '>=' ) ) {
				$this->html_table_row( 'MySQL Version', $this->mysql_version, $mysql_version, 'success' );
			} else {
				$this->html_table_row( 'MySQL Version', $this->mysql_version, $mysql_version, 'error' );
			}
		} else {
			// Show MySQL Form
			$this->html_form_mysql( ($this->db_infos === false) ? true : false );

			$this->html_table_row( 'MySQL Version', $this->mysql_version, 'Not available, needs credentials.', 'warning' );
		}

		$this->html_table_close( );
	}

	public function test_php_extensions( ) {
		$this->html_table_open( 'PHP Extensions', '', 'Required', 'Current' );

		if ( !is_callable( 'gd_info' ) ) {
			$this->html_table_row( 'GD', 'Required', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'GD', 'Required', 'Installed', 'success' );
		}

		if ( !class_exists( 'ZipArchive' ) ) {
			$this->html_table_row( 'ZIP', 'Recommended', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'ZIP', 'Recommended', 'Installed', 'success' );
		}

		if ( !is_callable( 'ftp_connect' ) ) {
			$this->html_table_row( 'FTP', 'Recommended', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'FTP', 'Recommended', 'Installed', 'success' );
		}

		if ( !is_callable( 'exif_read_data' ) ) {
			$this->html_table_row( 'Exif', 'Recommended', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'Exif', 'Recommended', 'Installed', 'success' );
		}

		if ( !is_callable( 'curl_init' ) ) {
			$this->html_table_row( 'CURL', 'Recommended', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'CURL', 'Recommended', 'Installed', 'success' );
		}

		if ( is_callable( 'eaccelerator_put' ) ) {
			$this->html_table_row( 'Opcode (APC or Xcache or eAccelerator or Zend Optimizer)', 'Recommended', 'eAccelerator Installed', 'success' );
		} elseif ( is_callable( 'xcache_set' ) ) {
			$this->html_table_row( 'Opcode (APC or Xcache or eAccelerator or Zend Optimizer)', 'Recommended', 'XCache Installed', 'success' );
		} elseif ( is_callable( 'apc_store' ) ) {
			$this->html_table_row( 'Opcode (APC or Xcache or eAccelerator or Zend Optimizer)', 'Recommended', 'APC Installed', 'success' );
		} elseif ( is_callable( 'zend_optimizer_version' ) ) {
			$this->html_table_row( 'Opcode (APC or Xcache or eAccelerator or Zend Optimizer)', 'Recommended', 'Zend Optimizer Installed', 'success' );
		} else {
			$this->html_table_row( 'Opcode (APC or Xcache or eAccelerator or Zend Optimizer)', 'Recommended', 'Not installed', 'error' );
		}

		if ( !class_exists( 'Memcache' ) ) {
			$this->html_table_row( 'Memcache', 'Optional', 'Not installed', 'info' );
		} else {
			$this->html_table_row( 'Memcache', 'Optional', 'Installed', 'success' );
		}

		if ( !is_callable( 'mb_substr' ) ) {
			$this->html_table_row( 'Multibyte String', 'Recommended', 'Not installed', 'error' );
		} else {
			$this->html_table_row( 'Multibyte String', 'Recommended', 'Installed', 'success' );
		}

		if ( !class_exists( 'tidy' ) ) {
			$this->html_table_row( 'Tidy', 'Optional', 'Not installed', 'info' );
		} else {
			$this->html_table_row( 'Tidy', 'Optional', 'Installed', 'success' );
		}

		if ( !is_callable( 'finfo_open' ) && !is_callable( 'mime_content_type' ) ) {
			$this->html_table_row( 'Mime type', 'Optional', 'Not installed', 'info' );
		} else {
			$this->html_table_row( 'Mime type', 'Optional', 'Installed', 'success' );
		}

		if ( !is_callable( 'hash' ) && !is_callable( 'mhash' ) ) {
			$this->html_table_row( 'Hash', 'Optional', 'Not installed', 'info' );
		} else {
			$this->html_table_row( 'Hash', 'Optional', 'Installed', 'success' );
		}

		if ( !is_callable( 'set_time_limit' ) ) {
			$this->html_table_row( 'set_time_limit', 'Optional', 'Not Available', 'info' );
		} else {
			$this->html_table_row( 'set_time_limit', 'Optional', 'Available', 'success' );
		}

		$this->html_table_close( );
	}

	public function test_apache_modules( ) {
		if ( $this->_get_current_webserver( ) != 'Apache' ) {
			return false;
		}

		$current_modules = (array)$this->_get_apache_modules( );
		$modules = array( 'mod_deflate', 'mod_env', 'mod_expires', 'mod_headers', 'mod_filter', 'mod_mime', 'mod_rewrite', 'mod_setenvif' );

		$this->html_table_open( 'Apache Modules', '', 'Required', 'Current' );

		foreach ( $modules as $module ) {
			$name = ucfirst( str_replace( 'mod_', '', $module ) );
			if ( !in_array( $module, $current_modules ) ) {
				$this->html_table_row( $name, 'Recommended', 'Not installed', 'error' );
			} else {
				$this->html_table_row( $name, 'Recommended', 'Installed', 'success' );
			}
		}

		$this->html_table_close( );
		return true;
	}

	public function test_php_config( ) {
		$this->html_table_open( 'PHP Configuration', '', 'Recommended', 'Current' );

		$value = ini_get( 'register_globals' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'register_globals', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'register_globals', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'magic_quotes_runtime' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'magic_quotes_runtime', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'magic_quotes_runtime', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'magic_quotes_sybase' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'magic_quotes_sybase', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'magic_quotes_sybase', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'register_long_arrays' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'register_long_arrays', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'register_long_arrays', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'register_argc_argv ' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'register_argc_argv ', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'register_argc_argv ', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'memory_limit' );
		if ( intval( $value ) < 64 ) {
			$this->html_table_row( 'memory_limit', '64M', $value, 'error' );
		} else {
			$this->html_table_row( 'memory_limit', '64M', $value, 'success' );
		}

		$value = ini_get( 'file_uploads' );
		if ( strtolower( $value ) == 'on' || $value == '1' ) {
			$this->html_table_row( 'file_uploads', 'On', 'On', 'success' );
		} else {
			$this->html_table_row( 'file_uploads', 'On', 'Off', 'error' );
		}

		$value = ini_get( 'upload_max_filesize' );
		if ( intval( $value ) < 32 ) {
			$this->html_table_row( 'upload_max_filesize', '32M', $value, 'warning' );
		} else {
			$this->html_table_row( 'upload_max_filesize', '32M', $value, 'success' );
		}

		$value = ini_get( 'post_max_size' );
		if ( intval( $value ) < 32 ) {
			$this->html_table_row( 'post_max_size', '32M', $value, 'warning' );
		} else {
			$this->html_table_row( 'post_max_size', '32M', $value, 'success' );
		}

		$value = ini_get( 'short_open_tag' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'short_open_tag', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'short_open_tag', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'safe_mode' );
		if ( strtolower( $value ) == 'on' ) {
			$this->html_table_row( 'safe_mode', 'Off', 'On', 'warning' );
		} else {
			$this->html_table_row( 'safe_mode', 'Off', 'Off', 'success' );
		}

		$value = ini_get( 'open_basedir' );
		$this->html_table_row( 'open_basedir', $value, '', 'info', 2 );

		$value = ini_get( 'zlib.output_compression' );
		$this->html_table_row( 'zlib.output_compression', $value, '', 'info', 2 );

		$value = ini_get( 'output_handler' );
		$this->html_table_row( 'output_handler', $value, '', 'info', 2 );

		$value = ini_get( 'expose_php' );
		if ( $value == '0' || strtolower( $value ) == 'off' || empty($value) ) {
			$this->html_table_row( 'expose_php', '0 or Off', $value, 'success' );
		} else {
			$this->html_table_row( 'expose_php', '0 or Off', $value, 'error' );
		}

		$value = ini_get( 'upload_tmp_dir' );
		$this->html_table_row( 'upload_tmp_dir', $value, '', 'info', 2 );
		if ( is_dir( $value ) && @is_writable( $value ) ) {
			$this->html_table_row( 'upload_tmp_dir writable ?', 'Yes', 'Yes', 'success' );
		} else {
			$this->html_table_row( 'upload_tmp_dir writable ?', 'Yes', 'No', 'error' );
		}

		$value = '/tmp/';
		$this->html_table_row( 'System temp dir', $value, '', 'info', 2 );
		if ( is_dir( $value ) && @is_writable( $value ) ) {
			$this->html_table_row( 'System temp dir writable ?', 'Yes', 'Yes', 'success' );
		} else {
			$this->html_table_row( 'System temp dir writable ?', 'Yes', 'No', 'error' );
		}

		$value = dirname( __FILE__ );
		$this->html_table_row( 'Current dir', $value, '', 'info', 2 );
		if ( is_dir( $value ) && @is_writable( $value ) ) {
			$this->html_table_row( 'Current dir writable ?', 'Yes', 'Yes', 'success' );
		} else {
			$this->html_table_row( 'Current dir writable ?', 'Yes', 'No', 'error' );
		}

		if ( is_callable( 'apc_store' ) ) {
			$value = ini_get( 'apc.shm_size' );
			if ( intval( $value ) < 32 ) {
				$this->html_table_row( 'apc.shm_size', '32M', $value, 'warning' );
			} else {
				$this->html_table_row( 'apc.shm_size', '32M', $value, 'success' );
			}
		}

		$this->html_table_close( );
	}

	public function test_mysql_config( ) {
		if ( $this->db_link == false ) {
			return false;
		}

		$this->html_table_open( 'MySQL Configuration', '', 'Recommended', 'Current' );

		$result = mysql_query( "SHOW VARIABLES LIKE 'have_query_cache'", $this->db_link );
		if ( $result != false ) {
			while ( $row = mysql_fetch_assoc( $result ) ) {
				if ( strtolower( $row['Value'] ) == 'yes' ) {
					$this->html_table_row( "Query cache", 'Yes', 'Yes', 'success' );
				} else {
					$this->html_table_row( "Query cache", 'Yes', 'False', 'error' );
				}
			}
		}

		$result = mysql_query( "SHOW VARIABLES LIKE 'query_cache_size'", $this->db_link );
		if ( $result != false ) {
			while ( $row = mysql_fetch_assoc( $result ) ) {
				if ( intval( $row['Value'] ) >= 8388608 ) {
					$this->html_table_row( "Query cache size", '8M', $this->_format_bytes( (int)$row['Value'] ), 'success' );
				} else {
					$this->html_table_row( "Query cache size", '8M', $this->_format_bytes( (int)$row['Value'] ), 'error' );
				}
			}
		}

		$result = mysql_query( "SHOW VARIABLES LIKE 'query_cache_type'", $this->db_link );
		if ( $result != false ) {
			while ( $row = mysql_fetch_assoc( $result ) ) {
				if ( strtolower( $row['Value'] ) == 'on' || strtolower( $row['Value'] ) == '1' ) {
					$this->html_table_row( "Query cache type", '1 or on', strtolower( $row['Value'] ), 'success' );
				} else {
					$this->html_table_row( "Query cache type", '1', strtolower( $row['Value'] ), 'error' );
				}
			}
		}

		$result = mysql_query( "SHOW VARIABLES LIKE 'log_slow_queries'", $this->db_link );
		if ( $result != false ) {
			while ( $row = mysql_fetch_assoc( $result ) ) {
				if ( strtolower( $row['Value'] ) == 'yes' || strtolower( $row['Value'] ) == 'on' ) {
					$this->html_table_row( "Log slow queries", 'Yes', 'Yes', 'success' );
				} else {
					$this->html_table_row( "Log slow queries", 'Yes', 'False', 'error' );
				}
			}
		}

		$result = mysql_query( "SHOW VARIABLES LIKE 'long_query_time'", $this->db_link );
		if ( $result != false ) {
			while ( $row = mysql_fetch_assoc( $result ) ) {
				if ( intval( $row['Value'] ) <= 2 ) {
					$this->html_table_row( "Long query time", '2', ((int)$row['Value']), 'success' );
				} else {
					$this->html_table_row( "Long query time", '2', ((int)$row['Value']), 'error' );
				}
			}
		}

		$this->html_table_close( );
		return true;
	}

	public function test_form_mail( ) {
		$this->html_table_open( 'Email Configuration', '', '', '' );
		$this->html_form_email( );
		$this->html_table_close( );
	}

	/**
	 * Start HTML, call CSS/JS from CDN
	 * Link to Github
	 * TODO: Add links to Codex/WP.org
	 * TODO: Add colors legend
	 */
	public function get_header( ) {
		$output = '';
		$output .= '<!DOCTYPE html>' . "\n";
		$output .= '<html lang="en">' . "\n";
		$output .= '<head>' . "\n";
		$output .= '<meta charset="utf-8">' . "\n";
		$output .= '<meta name="robots" content="noindex,nofollow">' . "\n";
		$output .= '<title>PHP WordPress Info</title>' . "\n";
		$output .= '<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.1.0/css/bootstrap-combined.min.css" rel="stylesheet">' . "\n";
		$output .= '<style>.table tbody tr.warning td{background-color:#FCF8E3;}</style>' . "\n";
		$output .= '<!--[if lt IE 9]> <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script> <![endif]-->' . "\n";
		$output .= '</head>' . "\n";
		$output .= '<body style="padding:10px 0;">' . "\n";
		$output .= '<div class="container">' . "\n";
		$output .= '<div class="navbar">' . "\n";
		$output .= '<div class="navbar-inner">' . "\n";
		$output .= '<a class="brand" href="#">PHP WordPress Info</a>' . "\n";
		$output .= '<ul class="nav pull-right">' . "\n";
		$output .= '<li><a href="https://github.com/herewithme/phpwpinfo">Project on Github</a></li>' . "\n";

		if ( $this->db_link != false ) {
			$output .= '<li class="dropdown">' . "\n";
			$output .= '<a href="#" class="dropdown-toggle" data-toggle="dropdown">MySQL <b class="caret"></b></a>' . "\n";
			$output .= '<ul class="dropdown-menu">' . "\n";
			$output .= '<li><a href="?mysql-variables=true">MySQL Variables</a></li>' . "\n";
			$output .= '<li><a href="?logout=true">Logout</a></li>' . "\n";
			$output .= '</ul>' . "\n";
			$output .= '</li>' . "\n";
		}

		$output .= '<li class="dropdown">' . "\n";
		$output .= '<a href="#" class="dropdown-toggle" data-toggle="dropdown">Tools <b class="caret"></b></a>' . "\n";
		$output .= '<ul class="dropdown-menu">' . "\n";
		$output .= '<li><a href="?phpinfo=true">PHPinfo()</a></li>' . "\n";

		// Adminer
		if ( !is_file( dirname( __FILE__ ) . '/adminer.php' ) && is_writable(dirname(__FILE__)) ) {
			$output .= '<li><a href="?adminer=install">Install Adminer</a></li>' . "\n";
		} else {
			$output .= '<li><a href="adminer.php">Adminer</a></li>' . "\n";
			$output .= '<li><a href="?adminer=uninstall">Uninstall Adminer</a></li>' . "\n";
		}

		// PHP sec info
		if ( !is_dir( dirname( __FILE__ ) . '/phpsecinfo' ) && is_writable(dirname(__FILE__) ) && class_exists('ZipArchive') ) {
			$output .= '<li><a href="?phpsecinfo=install">Install PhpSecInfo</a></li>' . "\n";
		} else {
			$output .= '<li><a href="?phpsecinfo=load">PhpSecInfo</a></li>' . "\n";
			$output .= '<li><a href="?phpsecinfo=uninstall">Uninstall PhpSecInfo</a></li>' . "\n";
		}

		// WordPress
		if ( !is_dir( dirname( __FILE__ ) . '/wordpress' ) && is_writable(dirname(__FILE__) )&& class_exists('ZipArchive') ) {
			$output .= '<li><a href="?wordpress=install">Download & Extract WordPress</a></li>' . "\n";
		} else {
			$output .= '<li><a href="wordpress/">WordPress</a></li>' . "\n";
		}

		$output .= '<li><a href="?self-destruction=true">Self-destruction</a></li>' . "\n";
		$output .= '</ul>' . "\n";
		$output .= '</li>' . "\n";

		$output .= '</ul>' . "\n";
		$output .= '</div>' . "\n";
		$output .= '</div>' . "\n";

		echo $output;
	}

	/**
	 * Close HTML, call JS
	 */
	public function get_footer( ) {
		$output = '';

		$output .= '<footer>&copy; <a href="http://beapi.fr">BeAPI</a> '.date('Y').'</footer>' . "\n";
		$output .= '</div>' . "\n";
		$output .= '<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.min.js"></script>' . "\n";
		$output .= '<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.1.0/js/bootstrap.min.js"></script>' . "\n";
		$output .= '</body>' . "\n";
		$output .= '</html>' . "\n";

		echo $output;
	}

	/**
	 * Open a HTML table
	 */
	public function html_table_open( $title = '', $col1 = '', $col2 = '', $col3 = '' ) {
		$output = '';
		$output .= '<table class="table table-bordered">' . "\n";
		$output .= '<caption>' . $title . '</caption>' . "\n";
		$output .= '<thead>' . "\n";

		if ( !empty( $col1 ) || !empty( $col2 ) || !empty( $col3 ) ) {
			$output .= '<tr>' . "\n";
			$output .= '<th width="40%">' . $col1 . '</th>' . "\n";
			$output .= '<th width="30%">' . $col2 . '</th>' . "\n";
			$output .= '<th width="30%">' . $col3 . '</th>' . "\n";
			$output .= '</tr>' . "\n";
		}

		$output .= '</thead>' . "\n";
		$output .= '<tbody>' . "\n";

		echo $output;
	}

	/**
	 * Close HTML table
	 */
	public function html_table_close( ) {
		$output = '';
		$output .= '</tbody>' . "\n";
		$output .= '</table>' . "\n";

		echo $output;
	}

	/**
	 * Add table row
	 * Status available : success, error, warning, info
	 */
	public function html_table_row( $col1 = '', $col2 = '', $col3 = '', $status = 'success', $colspan = false ) {
		$output = '';
		$output .= '<tr class="' . $status . '">' . "\n";

		if ( $colspan == 2 ) {
			$output .= '<td>' . $col1 . '</td>' . "\n";
			$output .= '<td colspan="' . $colspan . '">' . $col2 . '</td>' . "\n";
		} else {
			$output .= '<td>' . $col1 . '</td>' . "\n";
			$output .= '<td>' . $col2 . '</td>' . "\n";
			$output .= '<td>' . $col3 . '</td>' . "\n";
		}

		$output .= '</tr>' . "\n";

		echo $output;
	}

	/**
	 * Form HTML for MySQL Login
	 * @param  boolean $show_error_credentials [description]
	 * @return void                          [description]
	 */
	public function html_form_mysql( $show_error_credentials = false ) {
		$output = '';
		$output .= '<tr>' . "\n";
		$output .= '<td colspan="3">' . "\n";

		if ( $show_error_credentials == true )
			$output .= '<div class="alert alert-error">Credentials invalid.</div>' . "\n";

		$output .= '<form class="form-inline" method="post" action="">' . "\n";
		$output .= '<input type="text" class="input-small" name="credentials[host]" placeholder="localhost" value="localhost">' . "\n";
		$output .= '<input type="text" class="input-small" name="credentials[user]" placeholder="user">' . "\n";
		$output .= '<input type="password" class="input-small" name="credentials[password]" placeholder="password">' . "\n";
		$output .= '<label class="checkbox">' . "\n";
		$output .= '<input type="checkbox" name="remember"> Remember' . "\n";
		$output .= '</label>' . "\n";
		$output .= '<button name="mysql-connection" type="submit" class="btn">Login</button>' . "\n";
		$output .= '<span class="help-inline">We must connect to the MySQL server to check the configuration</span>' . "\n";
		$output .= '</form>' . "\n";
		$output .= '</td>' . "\n";
		$output .= '</tr>' . "\n";

		echo $output;
	}

	/**
	 * Form for test email
	 *
	 * @return void                          [description]
	 */
	public function html_form_email( ) {
		$output = '';
		$output .= '<tr>' . "\n";
		$output .= '<td colspan="3">' . "\n";

		if ( isset( $_POST['test-email'] ) && isset( $_POST['mail'] ) ) {
			if ( !filter_var( $_POST['mail'], FILTER_VALIDATE_EMAIL ) ) {// Invalid
				$output .= '<div class="alert alert-error">Email invalid.</div>' . "\n";
			} else {// Valid mail
				if ( mail( $_POST['mail'], 'Email test with PHP WP Info', "Line 1\nLine 2\nLine 3\nGreat !" ) ) {// Valid send
					$output .= '<div class="alert alert-success">Mail sent with success.</div>' . "\n";
				} else {// Error send
					$output .= '<div class="alert alert-error">An error occured during mail sending.</div>' . "\n";
				}
			}
		}

		$output .= '<form id="form-email" class="form-inline" method="post" action="#form-email">' . "\n";
		$output .= '<i class="icon-envelope"></i> <input type="mail" class="input-large" name="mail" placeholder="test@sample.com" value="">' . "\n";
		$output .= '<button name="test-email" type="submit" class="btn">Send mail</button>' . "\n";
		$output .= '<span class="help-inline">Send a test email to check that server is doing its job</span>' . "\n";
		$output .= '</form>' . "\n";
		$output .= '</td>' . "\n";
		$output .= '</tr>' . "\n";

		echo $output;
	}

	/**
	 * Stripslashes array
	 * @param  [type] $value [description]
	 * @return [type]        [description]
	 */
	public function stripslashes_deep( $value ) {
		return is_array( $value ) ? array_map( array( &$this, 'stripslashes_deep' ), $value ) : stripslashes( $value );
	}

	/**
	 * Detect current webserver
	 *
	 * @return string        [description]
	 */
	private function _get_current_webserver( ) {
		if ( stristr( $_SERVER['SERVER_SOFTWARE'], 'apache' ) !== false ) :
			return 'Apache';
		elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) !== false ) :
			return 'Lite Speed';
		elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ) :
			return 'nginx';
		elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'lighttpd' ) !== false ) :
			return 'lighttpd';
		elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'iis' ) !== false ) :
			return 'Microsoft IIS';
		else :
			return 'Not detected';
		endif;
	}

	/**
	 * Method for get apaches modules with Apache modules or CGI with .HTACCESS
	 *
	 * @return string        [description]
	 */
	private function _get_apache_modules( ) {
		$apache_modules = (is_callable( 'apache_get_modules' ) ? apache_get_modules( ) : false);

		if ( $apache_modules === false && (isset( $_SERVER['http_mod_env'] ) || isset( $_SERVER['REDIRECT_http_mod_env'] ) ) ) {
			// Test with htaccess to get ENV values
			$apache_modules = array( 'mod_env' );

			if ( isset( $_SERVER['http_mod_rewrite'] ) || isset( $_SERVER['REDIRECT_http_mod_rewrite'] ) ) {
				$apache_modules[] = 'mod_rewrite';
			}
			if ( isset( $_SERVER['http_mod_deflate'] ) || isset( $_SERVER['REDIRECT_http_mod_deflate'] )  ) {
				$apache_modules[] = 'mod_deflate';
			}
			if ( isset( $_SERVER['http_mod_expires'] ) || isset( $_SERVER['REDIRECT_http_mod_expires'] )  ) {
				$apache_modules[] = 'mod_expires';
			}
			if ( isset( $_SERVER['http_mod_filter'] ) || isset( $_SERVER['REDIRECT_http_mod_filter'] )  ) {
				$apache_modules[] = 'mod_filter';
			}
			if ( isset( $_SERVER['http_mod_headers'] ) || isset( $_SERVER['REDIRECT_http_mod_headers'] )  ) {
				$apache_modules[] = 'mod_headers';
			}
			if ( isset( $_SERVER['http_mod_mime'] ) || isset( $_SERVER['REDIRECT_http_mod_mime'] )  ) {
				$apache_modules[] = 'mod_mime';
			}
			if ( isset( $_SERVER['http_mod_setenvif'] ) || isset( $_SERVER['REDIRECT_http_mod_setenvif'] )  ) {
				$apache_modules[] = 'mod_setenvif';
			}
		}

		return $apache_modules;
	}

	/**
	 * Get humans values, take from http://php.net/manual/de/function.filesize.php
	 * @param  integer  $bytes     [description]
	 * @return string          	   [description]
	 */
	private function _format_bytes( $size ) {
		$units = array( ' B', ' KB', ' MB', ' GB', ' TB' );
		for ( $i = 0; $size >= 1024 && $i < 4; $i++ )
			$size /= 1024;
		return round( $size, 2 ) . $units[$i];
	}

	private function _variable_to_html( $variable ) {
		if ( $variable === true ) {
			return 'true';
		} else if ( $variable === false ) {
			return 'false';
		} else if ( $variable === null ) {
			return 'null';
		} else if ( is_array( $variable ) ) {
			$html = "<table class='table table-bordered'>\n";
			$html .= "<thead><tr><th>Key</th><th>Value</th></tr></thead>\n";
			$html .= "<tbody>\n";
			foreach ( $variable as $key => $value ) {
				$value = $this->_variable_to_html( $value );
				$html .= "<tr><td>$key</td><td>$value</td></tr>\n";
			}
			$html .= "</tbody>\n";
			$html .= "</table>";
			return $html;
		} else {
			return strval( $variable );
		}
	}

	function file_get_contents_url( $url ) {
		if ( function_exists( 'curl_init' ) ) {
			$curl = curl_init( );

			curl_setopt( $curl, CURLOPT_URL, $url );
			//The URL to fetch. This can also be set when initializing a session with curl_init().
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, TRUE );
			//TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
			curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
			//The number of seconds to wait while trying to connect.

			curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)' );
			//The contents of the "User-Agent: " header to be used in a HTTP request.
			curl_setopt( $curl, CURLOPT_FAILONERROR, TRUE );
			//To fail silently if the HTTP code returned is greater than or equal to 400.
			curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, TRUE );
			//To follow any "Location: " header that the server sends as part of the HTTP header.
			curl_setopt( $curl, CURLOPT_AUTOREFERER, TRUE );
			//To automatically set the Referer: field in requests where it follows a Location: redirect.
			curl_setopt( $curl, CURLOPT_TIMEOUT, 300 );
			//The maximum number of seconds to allow cURL functions to execute.

			curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, FALSE );

			$contents = curl_exec( $curl );
			curl_close( $curl );

			return $contents;
		} else {
			return file_get_contents( $url );
		}
	}

	function rrmdir( $dir ) {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( $object != "." && $object != ".." ) {
					if ( filetype( $dir . "/" . $object ) == "dir" )
						$this->rrmdir( $dir . "/" . $object );
					else
						unlink( $dir . "/" . $object );
				}
			}
			reset( $objects );
			rmdir( $dir );
		}
	}

	private function _check_request_mysql( ) {
		// Check GET for logout MySQL
		if ( isset( $_GET ) && isset( $_GET['logout'] ) && $_GET['logout'] == 'true' ) {
			// Flush old session if POST submit
			unset( $_SESSION['credentials'] );

			header( "Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'], true );
			exit( );
		}

		// Check POST for MySQL login
		if ( isset( $_POST ) && isset( $_POST['mysql-connection'] ) ) {
			// Flush old session if POST submit
			unset( $_SESSION['credentials'] );

			// Cleanup form data
			$this->db_infos = $this->stripslashes_deep( $_POST['credentials'] );

			// Check remember checkbox
			if ( isset( $_POST['remember'] ) ) {
				$_SESSION['credentials'] = $this->db_infos;
			}
		} else {
			if ( (isset( $_SESSION ) && isset( $_SESSION['credentials'] )) ) {
				$this->db_infos = $_SESSION['credentials'];
			}
		}

		// Check credentials
		if ( !empty( $this->db_infos ) && is_array( $this->db_infos ) && is_callable( 'mysql_connect' ) ) {
			$this->db_link = mysql_connect( $this->db_infos['host'], $this->db_infos['user'], $this->db_infos['password'] );
			if ( $this->db_link == false ) {
				unset( $_SESSION['credentials'] );
				$this->db_infos = false;
			}
		}

		// Check GET for MYSQL variables
		if ( $this->db_link != false && isset( $_GET ) && isset( $_GET['mysql-variables'] ) && $_GET['mysql-variables'] == 'true' ) {
			$result = mysql_query( 'SHOW VARIABLES' );
			if ( !$result ) {
				echo "Could not successfully run query ( 'SHOW VARIABLES' ) from DB: " . mysql_error( );
				exit( );
			}

			if ( mysql_num_rows( $result ) == 0 ) {
				echo "No rows found, nothing to print so am exiting";
				exit( );
			}

			$output = array( );
			while ( $row = mysql_fetch_assoc( $result ) ) {
				$output[$row['Variable_name']] = $row['Value'];
			}
			$this->get_header( );
			echo $this->_variable_to_html( $output );
			$this->get_footer( );
			exit( );
		}
	}

	private function _check_request_adminer( ) {
		// Check GET for Install Adminer
		if ( isset( $_GET ) && isset( $_GET['adminer'] ) && $_GET['adminer'] == 'install' ) {
			$code = $this->file_get_contents_url( 'http://www.adminer.org/latest-mysql-en.php' );
			if ( !empty( $code ) ) {
				$result = file_put_contents( dirname( __FILE__ ) . '/adminer.php', $code );
				if ( $result != false ) {
					header( "Location: http://" . $_SERVER['SERVER_NAME'] . '/adminer.php', true );
					exit( );
				}
			}

			die( 'Impossible to download and install Adminer with this script.' );
		}

		// Check GET for Uninstall Adminer
		if ( isset( $_GET ) && isset( $_GET['adminer'] ) && $_GET['adminer'] == 'uninstall' ) {
			if ( is_file( dirname( __FILE__ ) . '/adminer.php' ) ) {
				$result = unlink( dirname( __FILE__ ) . '/adminer.php' );
				if ( $result != false ) {
					header( "Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'], true );
					exit( );
				}
			}

			die( 'Impossible remove file and uninstall Adminer with this script.' );
		}
	}

	private function _check_request_phpsecinfo( ) {
		// Check GET for Install phpsecinfo
		if ( isset( $_GET ) && isset( $_GET['phpsecinfo'] ) && $_GET['phpsecinfo'] == 'install' ) {
			$code = $this->file_get_contents_url( 'http://www.herewithme.fr/static/funkatron-phpsecinfo-b5a6155.zip' );
			if ( !empty( $code ) ) {
				$result = file_put_contents( dirname( __FILE__ ) . '/phpsecinfo.zip', $code );
				if ( $result != false ) {
					$zip = new ZipArchive;
					if ( $zip->open( dirname( __FILE__ ) . '/phpsecinfo.zip' ) === TRUE ) {
						$zip->extractTo( dirname( __FILE__ ) . '/phpsecinfo/' );
						$zip->close( );

						unlink( dirname( __FILE__ ) . '/phpsecinfo.zip' );
					} else {
						unlink( dirname( __FILE__ ) . '/phpsecinfo.zip' );
						die( 'Impossible to uncompress phpsecinfo with this script.' );
					}

					header( "Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'], true );
					exit( );
				} else {
					die( 'Impossible to write phpsecinfo archive with this script.' );
				}
			} else {
				die( 'Impossible to download phpsecinfo with this script.' );
			}
		}

		// Check GET for Uninstall phpsecinfo
		if ( isset( $_GET ) && isset( $_GET['phpsecinfo'] ) && $_GET['phpsecinfo'] == 'uninstall' ) {
			if ( is_dir( dirname( __FILE__ ) . '/phpsecinfo/' ) ) {
				$this->rrmdir( dirname( __FILE__ ) . '/phpsecinfo/' );
				if ( !is_dir( dirname( __FILE__ ) . '/phpsecinfo/' ) ) {
					header( "Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'], true );
					exit( );
				}
			}

			die( 'Impossible remove file and uninstall phpsecinfo with this script.' );
		}

		// Check GET for load
		if ( isset( $_GET ) && isset( $_GET['phpsecinfo'] ) && $_GET['phpsecinfo'] == 'load' ) {
			if ( is_dir( dirname( __FILE__ ) . '/phpsecinfo/' ) ) {
				require (dirname( __FILE__ ) . '/phpsecinfo/funkatron-phpsecinfo-b5a6155/PhpSecInfo/PhpSecInfo.php');
				phpsecinfo( );
				exit( );
			}
		}
	}

	function _check_request_wordpress() {
		// Check GET for Install wordpress
		if ( isset( $_GET ) && isset( $_GET['wordpress'] ) && $_GET['wordpress'] == 'install' ) {
			if ( !is_file(dirname( __FILE__ ) . '/latest.zip') ) {
				$code = $this->file_get_contents_url( 'http://wordpress.org/latest.zip' );
				if ( !empty( $code ) ) {
					$result = file_put_contents( dirname( __FILE__ ) . '/latest.zip', $code );
					if ( $result == false ) {
						die( 'Impossible to write WordPress archive with this script.' );
					}
				} else {
					die( 'Impossible to download WordPress with this script. You can also send WordPress Zip archive via FTP and renme it latest.zip, the script will only try to decompress it.' );
				}
			}

			if ( is_file(dirname( __FILE__ ) . '/latest.zip') ) {
				$zip = new ZipArchive;
				if ( $zip->open( dirname( __FILE__ ) . '/latest.zip' ) === TRUE ) {
					$zip->extractTo( dirname( __FILE__ ) . '/' );
					$zip->close( );

					unlink( dirname( __FILE__ ) . '/latest.zip' );
				} else {
					unlink( dirname( __FILE__ ) . '/latest.zip' );
					die( 'Impossible to uncompress WordPress with this script.' );
				}
			}
		}
	}
}

// Init render
phpwpinfo( );
