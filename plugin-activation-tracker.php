<?php
/*
 * Plugin Name: Activation Tracker for WP Plugins
 * Plugin URI: https://github.com/tim-green/wp-activation-tracker/
 * Description: This is a plugin which is very useful in many instances when each plugin's status will change where many plugins are installed and activations/devactivation by multiple users
 * Version: 2.0
 * Author: Tim green
 * Author URI: https://www.timgreen.ws
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
class act_plugin_activation_deactivation_date {

	/**
	 * Holds the de/activation date for all plugins.
	 *
	 * @since  1.0
	 * @access private
	 * @var array
	 */
	private $options = array();

	/**
	 * Constructor.
	 * Sets up activation hook, localization support and registers some essential hooks.
	 *
	 * @since  1.0
	 * @access public
	 * @return Plugin_Activation_deactivation_Date
	 */
	public function __construct() {
		// Register essential hooks. Pay special attention to {activate/deactivate}_plugin.
		add_filter( 'admin_init' , 					array( $this, 'act_register_settings_field_wp' ) );
		add_filter( 'manage_plugins_columns', 		array( $this, 'act_plugins_columns_name_gwl' ) );
		add_action( 'activate_plugin', 				array( $this, 'act_gwl_plugin_status_changed' ) );
		add_action( 'deactivate_plugin', 			array( $this, 'act_gwl_plugin_status_changed' ) );
		add_action( 'admin_head-plugins.php', 		array( $this, 'act_column_css_styles' ) );
		add_action( 'manage_plugins_custom_column', array( $this, 'act_gwl_activated_columns' ), 10, 3 );
		add_action( 'manage_plugins_custom_column', array( $this, 'act_gwl_deactivated_columns' ), 10, 4 );
		add_action( 'manage_plugins_custom_column', array( $this, 'act_gwl_activated_ip_address_columns' ), 10, 5 );
		add_action( 'manage_plugins_custom_column', array( $this, 'act_gwl_deactivated_ip_address_columns' ), 10, 6 );
		add_action( 'manage_plugins_custom_column', array( $this, 'act_gwl_activated_user_id_columns' ), 10, 7 );
		add_action( 'manage_plugins_custom_column', array( $this, 'act_gwl_deactivated_user_id_columns' ), 10, 8 );

		//cretae table//
		global $wpdb;
		$table_name = $wpdb->prefix . 'act_plugin_status';
		$sql = "CREATE TABLE $table_name (
		id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
		plugin_name varchar(250) NOT NULL,
		timestamp varchar(50) NOT NULL,
		ip_adddress varchar(50) NOT NULL,
		user_id varchar(50) NOT NULL,
		status varchar(50) NOT NULL,
		PRIMARY KEY  (id)
	);";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

		// Get them options, and keep around for later use
	$this->options = get_option( 'act_activated_plugins_gwl', array() );
		// Load our text domain
	load_plugin_textdomain( 'gwlate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		// Runs on activation only
	register_activation_hook( __FILE__, array( $this, 'act_activation_gwl' ) );
}

	/**
	 * Runs when a plugin changes status, and adds the de/activation timestamp
	 * to $this->options, then stores it in the options table.
	 *
	 * @since  1.1
	 * @param string $plugin The path to the de/activated plugin
	 */
	public function act_gwl_plugin_status_changed( $plugin ) 
	{   
		//print_r($plugin); die();

		$ip = $_SERVER['REMOTE_ADDR'];
		$user_id = get_current_user_id();
		$this->options[ $plugin ] = array(
			'status' 	=> current_filter() == 'activate_plugin' ? 'activated' : 'deactivated',
			'timestamp' => current_time( 'timestamp' ),
			'ip_adddress' => $ip,
			'plugin_name' => $plugin,
			'user_id' => $user_id
		);

		update_option( 'act_activated_plugins_gwl', $this->options );

		global $wpdb;
		$table_name = $wpdb->prefix . 'act_plugin_status';
		$success = $wpdb->insert($table_name, $this->options[ $plugin ]);       

	}

	/**
	 * Sets up the column headings.
	 * 
	 * @since 1.0
	 * @uses   $status Indicates on which plugin screen we are currently
	 * @param  array $columns All of the columns for the plugins page
	 * @return array The same array with our new column
	 */
	public function act_plugins_columns_name_gwl( $columns ) {
		global $status; 

		// If we're either on the Must Use or Drop-ins tabs, there's no reason to show the column
		if ( ! in_array( $status, array( 'mustuse', 'dropins' ) ) ){

			if ( ! in_array( $status, array( 'recently_activated', 'active' ) ) ){
				$columns['last_activated_date'] = __( 'Activated', 'gwlate' );
			}
			
			if( ! in_array( $status, array( 'recently_activated', 'inactive' ) ) ){
				$columns['last_deactivated_date'] = __( 'Deactivated', 'gwlate' );

			}

			if( ! in_array( $status, array( 'recently_activated', 'active' ) ) ){
				$columns['activated_ip_address_columns'] = __( 'Activated IP Number:', 'gwlate' );

			}
			if( ! in_array( $status, array( 'recently_activated', 'inactive' ) ) ){
				$columns['deactivated_ip_address_columns'] = __( 'Deactivated IP Number:', 'gwlate' );

			}

			if( ! in_array( $status, array( 'recently_activated', 'active' ) ) ){
				$columns['activated_user_id_columns'] = __( 'Activated By', 'gwlate' );

			}
			if( ! in_array( $status, array( 'recently_activated', 'inactive' ) ) ){
				$columns['deactivated_user_id_columns'] = __( 'Deactivated By', 'gwlate' );
			}

			return $columns;
			
		}
	}

	/**
	 * Outputs the date when this plugin was last activated. Repeats for all plugins.
	 *
	 * @since  1.0
	 * @param  string $column_name The column key
	 * @param  string $plugin_file The path to the current plugin in the loop
	 * @param  array $plugin_data Extra plugin data
	 */
	public function act_gwl_activated_columns( $column_name, $plugin_file, $plugin_data ) 
	{   
		global $wpdb;
		$current_plugin = &$this->options[ $plugin_file ];

		//print_r($current_plugin);
		
		if ( $column_name == 'last_activated_date')
		{   

			$last_updated_row = $wpdb->get_row("SELECT timestamp FROM ".$wpdb->prefix."act_plugin_status WHERE status = 'activated' and plugin_name = '".$current_plugin['plugin_name']."' ORDER by timestamp DESC LIMIT 1");
			
			if ( ! empty( $current_plugin ) )
			{
				if(!empty($last_updated_row)){
					echo $this->act_gwl_display_date(  $last_updated_row->timestamp );
				}
			}

		}
	}

    /**
	 * Outputs the date when this plugin was last deactivated. Repeats for all plugins.
	 *
	 * @since  1.0
	 * @param  string $column_name The column key
	 * @param  string $plugin_file The path to the current plugin in the loop
	 * @param  array $plugin_data Extra plugin data
	 */
    public function act_gwl_deactivated_columns( $column_name, $plugin_file, $plugin_data ) 
    {   
    	global $wpdb;
    	$current_plugin = &$this->options[ $plugin_file ];
    	
    	if ( $column_name == 'last_deactivated_date' )
    	{	
    		
    		if ( ! empty( $current_plugin ) )
    		{
    			$last_updated_row = $wpdb->get_row("SELECT timestamp FROM ".$wpdb->prefix."act_plugin_status WHERE status = 'deactivated' and plugin_name = '".$current_plugin['plugin_name']."' ORDER by id DESC LIMIT 1");
    			
    			if(!empty($last_updated_row))
    			{
    				echo $this->act_gwl_display_date(  $last_updated_row->timestamp );
    			}
    		}
    	}
    }

    /**
	 * Outputs the IP address when this plugin was the last activated. Repeats for all plugins.
	 *
	 * @since  1.0
	 * @param  string $column_name The column key
	 * @param  string $plugin_file The path to the current plugin in the loop
	 * @param  array $plugin_data Extra plugin data
	 */

    public function act_gwl_activated_ip_address_columns( $column_name, $plugin_file, $plugin_data ) 
    {
    	global $wpdb;
    	$current_plugin = &$this->options[ $plugin_file ];

    	if ( $column_name == 'activated_ip_address_columns')
    	{
    		$last_updated_row = $wpdb->get_row("SELECT ip_adddress FROM ".$wpdb->prefix."act_plugin_status WHERE status = 'activated' and plugin_name = '".$current_plugin['plugin_name']."' ORDER by id DESC LIMIT 1");
    		
    		if ( ! empty( $current_plugin ) )
    		{
    			if(!empty($last_updated_row))
    			{
    				echo $last_updated_row->ip_adddress;
    			}
    		}
    	}
    }


	/**
	 * Outputs the IP address when this plugin was the last deactivated. Repeats for all plugins.
	 *
	 * @since  1.0
	 * @param  string $column_name The column key
	 * @param  string $plugin_file The path to the current plugin in the loop
	 * @param  array $plugin_data Extra plugin data
	 */

	public function act_gwl_deactivated_ip_address_columns( $column_name, $plugin_file, $plugin_data ) 
	{   
		global $wpdb;
		$current_plugin = &$this->options[ $plugin_file ];

		if ( $column_name == 'deactivated_ip_address_columns' )
		{		
			$last_updated_row = $wpdb->get_row("SELECT ip_adddress FROM ".$wpdb->prefix."act_plugin_status WHERE status = 'deactivated' and plugin_name = '".$current_plugin['plugin_name']."' ORDER by id DESC LIMIT 1");

			if ( ! empty( $current_plugin ))
			{   
				if(!empty($last_updated_row))
				{
					echo $last_updated_row->ip_adddress;
				}
			}
		}
	}

/**
	 * Outputs the User when this plugin was the last activated. Repeats for all plugins.
	 *
	 * @since  1.0
	 * @param  string $column_name The column key
	 * @param  string $plugin_file The path to the current plugin in the loop
	 * @param  array $plugin_data Extra plugin data
	 */

public function act_gwl_activated_user_id_columns( $column_name, $plugin_file, $plugin_data ) 
{   
	global $wpdb;
	$current_plugin = &$this->options[ $plugin_file ];
	if ( $column_name == 'activated_user_id_columns')
	{
		$last_updated_row = $wpdb->get_row("SELECT user_id FROM ".$wpdb->prefix."act_plugin_status WHERE status = 'activated' and plugin_name = '".$current_plugin['plugin_name']."' ORDER by id DESC LIMIT 1");

		if ( ! empty( $current_plugin ) ){

			if(!empty($last_updated_row))
			{
				$user = get_user_by( 'id', $last_updated_row->user_id );
				echo  $user->display_name;
			}

		}
	}
}


	/**
	 * Outputs the User when this plugin was the last deactivated. Repeats for all plugins.
	 *
	 * @since  1.0
	 * @param  string $column_name The column key
	 * @param  string $plugin_file The path to the current plugin in the loop
	 * @param  array $plugin_data Extra plugin data
	 */

	public function act_gwl_deactivated_user_id_columns( $column_name, $plugin_file, $plugin_data ) 
	{   
		global $wpdb;
		$current_plugin = &$this->options[ $plugin_file ];

		if ( $column_name == 'deactivated_user_id_columns' )
		{		
			$last_updated_row = $wpdb->get_row("SELECT user_id FROM ".$wpdb->prefix."act_plugin_status WHERE status = 'deactivated' and plugin_name = '".$current_plugin['plugin_name']."' ORDER by id DESC LIMIT 1");

			if ( ! empty( $current_plugin ) ){
				
				if(!empty($last_updated_row))
				{
					$user = get_user_by( 'id', $last_updated_row->user_id );
					echo  $user->display_name;
				}
			}
		}
	}


	/**
	 * Register a settings field under Settings > General
	 *
	 * @since  1.0
	 * @uses register_setting Registers the setting option
	 * @uses add_settings_field Regisers the field
	 */
	public function act_register_settings_field_wp() {
		register_setting( 'general', 'act_display_relative_date_gwl', 'esc_attr' );
		add_settings_field( 'gwl_relative_date', esc_html__( 'Plugin Activation Date Format', 'gwlate' ), array( $this, 'act_fields_html_wp' ), 'general', 'default', array( 'label_for' => 'act_display_relative_date_gwl' ) );

		register_setting( 'general', 'act_display_after_days_remove_record_gwl', 'esc_attr' );
		add_settings_field( 'gwl_relative_date_1', esc_html__( 'Plugin tracker status record store only', 'gwlate' ), array( $this, 'act_fields_html_wp_1' ), 'general', 'default', array( 'label_for' => 'act_display_after_days_remove_record_gwl' ) );
	}

	/**
	 * Prints the field's HTML
	 *
	 * @since  1.0
	 * @param  array $args Extra arguments passed by add_settings_field
	 */
	public function act_fields_html_wp( $args ) {
		$value = get_option( 'act_display_relative_date_gwl', 0 );
		$the_input = sprintf( '<input type="checkbox" id="act_display_relative_date_gwl" name="act_display_relative_date_gwl" %s /> %s', checked( $value, 'on', false ), esc_html__( ' Display date and time?', 'gwlate') );
		printf( '<label for="act_display_relative_date_gwl">%s</label>', $the_input );
	}

    /**
	 * Prints the field's HTML
	 *
	 * @since  1.0
	 * @param  array $args Extra arguments passed by add_settings_field
	 */
    public function act_fields_html_wp_1( $args ) 
    {
    	$days = get_option( 'act_display_after_days_remove_record_gwl', 0 );
    	if(empty($days))
    	{
    		$value = 60;
    	}else{
    		$value =  $days;
    	}
    	echo '<input class="small-text" type="number" id="act_display_after_days_remove_record_gwl" min=1 name="act_display_after_days_remove_record_gwl" value="' . esc_html($value) . '" />';
    	printf( '<label for="act_display_after_days_remove_record_gwl">%s</label> ',' Days (default 60 days)' );
    }

	/**
	 * Displays the de/activation date for every plugin respectively.
	 *
	 * @since  1.0
	 * @uses apply_filters() Calls 'gwl_date_time_format' for plugins to alter the output date format.
	 * @param  string $timestamp The timestamp for the current plugin in the loop
	 * @return string The formatted date
	 */
	public function act_gwl_display_date( $timestamp ) {
		$is_relative = 'on' == get_option( 'act_display_relative_date_gwl' ) ? false : true;
		$date_time_format = apply_filters( 'act_date_time_format_gwl', sprintf( '%s - %s', get_option('date_format'), get_option('time_format') ) );

		if ( $is_relative )
		{
			return sprintf( esc_html__( '%s ago', 'gwlate' ), human_time_diff( $timestamp, current_time( 'timestamp' ) ) );
		}
		else
		{
			return date_i18n( $date_time_format, $timestamp );
		}
	}
	/**
	 * Set our column's width so it's more readable.
	 *
	 * @since  1.0
	 */
	public function act_column_css_styles() {
		?>
		<style>#last_activated_date, #last_deactivated_date { width: 18%; } 
		.last_deactivated_date, .deactivated_ip_address_columns, .deactivated_user_id_columns { color:#721c24 !important; } 
		.last_activated_date, .activated_ip_address_columns, .activated_user_id_columns{ color: #106a4e !important; }
		#last_deactivated_date, #last_activated_date { width: 110px !important; }
		#activated_ip_address_columns, #deactivated_ip_address_columns, #activated_user_id_columns, #deactivated_user_id_columns{ width: 110px !important; } 

	</style>
	<?php
}

	/**
	 * Runs on activation, registers a few options for this plugin to operate.
	 * 
	 * @since 1.0
	 * @uses add_option()
	 */
	public function act_activation_gwl() 
	{
		add_option( 'act_activated_plugins_gwl' );
		add_option( 'act_display_relative_date_gwl' );
		add_option( 'act_display_after_days_remove_record_gwl' );
	}

}

function act_wpshout_action_gwl( ) 
{   
	$days = get_option( 'act_display_after_days_remove_record_gwl' );

	if(empty($days))
	{
		$days = 60;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'act_plugin_status';
	$d2 = date('c', strtotime('-'.$days.'days'));
	$timestamp = strtotime($d2);
	$wpdb->query("DELETE FROM " . $wpdb->prefix . "act_plugin_status WHERE timestamp < '".$timestamp."'");
}
add_action( 'wp_footer', 'act_wpshout_action_gwl' );

// Initiate the plugin. Access everywhere using $global Plugin_Activation_deactivation_Date
$GLOBALS['act_plugin_activation_deactivation_date'] = new act_plugin_activation_deactivation_date;