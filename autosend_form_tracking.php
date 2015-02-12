<?php
/*
Plugin Name: Autosend Form Tracking
Plugin URI: 
Description: For step by step directions on how to use and install: <a href="http://autosend.io/faq">http://autosend.io/faq</a>
Version: 0.8.0
Author: <a href="http://autosend.io/">Autosend</a>
Author Email: 
License:


  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
*/
require(  plugin_dir_path( __FILE__ ).'libs/simple_html_dom.php' );

class AutosendFormTracking {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'Autosend Form Tracking';
	const slug = 'autosend_form_tracking';
	const version = '0.8.0';
	
	private $defFields = array('phone'=>'sanitize_text_field', 'email'=>'sanitize_email', 'name'=>'sanitize_text_field');
	
	private $inputFields = array();
	
	function __construct() {
		global $wpdb;
		
		$this->db = $wpdb;
		$this->form_table = $this->db->prefix.'autosend_tracking';
		$this->user_table = $this->db->prefix.'autosend_user_data';
		
		register_activation_hook( __FILE__, array( &$this, 'install_autosend_form_tracking' ) );
		register_deactivation_hook(__FILE__, array( &$this, 'delete_autosend_form_tracking' ));
		//Hook up to the init action
		add_action( 'init', array( &$this, 'init_autosend_form_tracking' ) );
		
		$this->path_tmpl = plugin_dir_path( __FILE__ ).'templates/';
	}
 
	function install_autosend_form_tracking() {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$sql = "CREATE TABLE IF NOT EXISTS `".$this->form_table."` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`form_id` varchar(20) NOT NULL DEFAULT '',
					`page_id` bigint(20) unsigned NOT NULL,
					`event` varchar(20) NOT NULL DEFAULT '',
					`phone` varchar(30) DEFAULT '',
					`email` varchar(30) DEFAULT '',
					`name` varchar(30) DEFAULT '',
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		dbDelta($sql);
		
		$sql = "CREATE TABLE IF NOT EXISTS `".$this->user_table."` (
			`user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`email` varchar(50) NOT NULL,
			`phone` varchar(50) NOT NULL,
			`name` varchar(50) NOT NULL,
			PRIMARY KEY (`user_id`)
		  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		dbDelta($sql); 
		add_option('autos_unique_key', '');
	}
	
	function delete_autosend_form_tracking() {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$sql = "DROP TABLE IF EXISTS `".$this->form_table."`";
		$this->db->query($sql);

		$sql = "DROP TABLE IF EXISTS `".$this->user_table."`";
		$this->db->query($sql);
		delete_option('autos_unique_key');
	}
	
	function init_autosend_form_tracking() {
		// Setup localization
		load_plugin_textdomain( self::slug, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		// Load JavaScript and stylesheets
		$this->register_scripts_and_styles();
		
		wp_register_script( 'autosend_common', plugins_url('js/common.js', __FILE__) ,array('jquery'), self::version);
		
		add_action( 'template_redirect', array( &$this, 'page_load') );//
		add_action( 'wp_ajax_set_event', array( &$this, 'set_event_ajax') );
		add_action( 'wp_ajax_set_form', array( &$this, 'set_form_ajax') );
		add_action( 'wp_ajax_get_forms', array( &$this, 'get_forms_ajax') );
		add_action( 'wp_ajax_set_key', array( &$this, 'set_key_ajax') );
		add_action( 'wp_ajax_set_field_role', array( &$this, 'set_field_role_ajax') );
		add_action( 'wp_ajax_nopriv_identity_user', array( &$this, 'identity_user_ajax') );
		add_action( 'wp_ajax_identity_user', array( &$this, 'identity_user_ajax') );
		add_action('admin_menu', array( &$this, 'add_admin_menu' ));    
	}
	
	private function register_scripts_and_styles() {
		if ( is_admin() ) {
			$this->load_file( self::slug . '-admin-script', '/js/admin.js', true );
			$this->load_file( self::slug . '-admin-style', '/css/admin.css' );
		} else {
			$this->load_file( self::slug . '-script', '/js/widget.js', true );
			$this->load_file( self::slug . '-style', '/css/widget.css' );
		}
	}
	
	private function load_file( $name, $file_path, $is_script = false ) {

		$url = plugins_url($file_path, __FILE__);
		$file = plugin_dir_path(__FILE__) . $file_path;

		if( file_exists( $file ) ) {
			if( $is_script ) {
				wp_register_script( $name, $url, array('jquery'), self::version ); //depends on jquery
				wp_enqueue_script( $name );
			} else {
				wp_register_style( $name, $url,array(), self::version );
				wp_enqueue_style( $name );
			}
		}
	}
	
	private function get_forms_on_page($pageID) {
		return $this->db->get_results("SELECT * FROM `$this->form_table` WHERE `page_id` = '$pageID'", ARRAY_A);
	}
	
	public function page_load() {
		
		$pageID = get_the_ID();
		
		if(!$pageID) {
			return;
		}
		
		$result = $this->get_forms_on_page($pageID);
		
		if(!$result) {
			return;
		}
		
		$autos_unique_key = get_option('autos_unique_key');
		$current_user = wp_get_current_user();
		
		$user_data =  $this->getcookie();		
		
		$form = array();
		
		foreach($result as $row) {
			
			$data = array('event' => $row['event'], 'id' => $row['form_id'] );
			
			foreach($this->defFields as $role=>$santize) {
				$data[$role] = (isset($row[$role])) ? ($row[$role]) : '';
			}
			
			$form[] = $data;
		}
	
		wp_localize_script( 'autosend_common', 'autosend_js', array('user' => $user_data, 'key' => $autos_unique_key, 'formList'=>$form, 'ajaxurl' => admin_url( 'admin-ajax.php' )) );
		wp_enqueue_script( 'autosend_common' );
	}
	
	
	private function setcookie($data) {
		foreach($data as $k=>$row) 
			setcookie( "autosend_user_data[$k]", $row, time() + 365*24*3600, COOKIEPATH, COOKIE_DOMAIN );
	}
	
	private function getcookie() {
		
		$userField = array('phone', 'email', 'name', 'user_id');
		$user_data = array();
		
		if(isset($_COOKIE['autosend_user_data'])) {
			foreach($userField as $field) {
				$user_data[$field] = ( isset($_COOKIE['autosend_user_data'][$field]) ) ? $_COOKIE['autosend_user_data'][$field] : '';
			}
			return $user_data;
		}
		
		return false;
	}
	
	public function set_field_role_ajax() {
		
		$inputName 	= sanitize_text_field($_POST['name']);
		$inputRole	= sanitize_text_field($_POST['role']);
		$pageID 	= intval($_POST['page_id']);
		$form_id	= esc_attr($_POST['form_id']);
		
		$fieldRoles = $this->defFields;
		
		if(empty($inputName) || empty($pageID) || empty($form_id)) {
			die(json_encode( array('error'=>'Wrong data.') ));
		}
		
		if(!array_key_exists($inputRole, $fieldRoles)) {
			die(json_encode( array('error'=>'Wrong role.') ));
		}
		
		$row = $this->db->get_row("SELECT * FROM `$this->form_table` WHERE `page_id` = '$pageID' AND `form_id` = '$form_id'", ARRAY_A);
		
		if(!$row) {
			die(json_encode( array('error'=>'Form is not tracked.') ));
		}
		
		$update = array();
		
		foreach($fieldRoles as $roleName => $santize) {
			
			if($row[$roleName] == $inputName) {
				$update[$roleName] = '';
			} else {
				$update[$roleName] = $row[$roleName];
			}
		}
		
		$update[$inputRole] = $inputName;
		
		$res = $this->db->update($this->form_table, $update, array( 'form_id'=> $form_id, 'page_id'=> $pageID ) );

		if($res) {
			$data = array();
			foreach($update as $k=>$row) {
				$data[$k] = ($row) ? false : true;
			}
			
			$output = array('data'=>$data);
		}
		else
			$output = array('error'=>1);
		die( json_encode($output) );
	}
	
	public function set_key_ajax() {
		$key = sanitize_text_field($_POST['key']);
		update_option('autos_unique_key', $key);
		die( json_encode(array('data'=>'APP Key has been saved.')) );
	}
	
	public function identity_user_ajax() {

		$userField = $this->defFields;
		$user_data = array();
		
		foreach($userField as $field=>$santize) {
			$user_data[$field] = (isset($_POST[$field])) ? $santize($_POST[$field]) : '';
		}

		if(!isset($user_data['email']) && !isset($user_data['phone'])) {
			return;
		}
		
		$userDB = $this->getUser($user_data);
		
		if(!$userDB) {
			$userDB = $this->setUser($user_data);
		}
		
		if(!$userDB) {
			die(array('error'=>'Error has occurred.'));
		}
		
		$this->setcookie($userDB);
		
		die(json_encode(array('data'=>$userDB)));
	}
	
	private function getUser($data) {
		$where = '';
		if(isset($data['email'])) {
			$where = "`email` = '".$data['email']."'";
		}
		else if(isset($data['phone'])) { 
			$where = "`phone` = '".$data['phone']."'";
		}
		
		return $this->db->get_row("SELECT * FROM `{$this->user_table}` WHERE $where LIMIT 1", ARRAY_A ) ;
	}
	private function setUser($data) {
		$id = $this->setUserDB($data);
		$data['user_id'] = $id;
		return $data;
	}
	
	private function setUserDB($data) {
		$res = $this->db->insert($this->user_table, $data);
		return  ($res) ? $this->db->insert_id : false;
	}
	
	public function set_event_ajax() {
		$event 		= sanitize_text_field($_POST['event']);
		$form_id 	= esc_attr($_POST['form_id']);
		$pageID 	=  intval($_POST['page_id']);
		
		$res = $this->db->update($this->form_table, array( 'event'=> $event ), array( 'form_id'=> $form_id, 'page_id'=> $pageID ) );
		
		$output = ($res) ? array('data'=>$res) : array('error'=>1);
		die( json_encode($output) );
	}
	
	public function set_form_ajax() {
		
		$table_fields = array('page_id'=>'intval', 'form_id'=>'esc_attr', 'event'=>'sanitize_text_field');
		$table_data = array();
		
		foreach($table_fields as $row=>$santize) {
			$table_data[$row] = $santize($_POST[$row]);
			if(empty($table_data[$row])) {
				die( json_encode( array('error'=>'Wrong data.') ) );
			}
		}
		
		if($_POST['track'] == '1') {
			$this->db->insert( $this->form_table, $table_data );
		}
		else {
			$this->db->delete( $this->form_table, array( 'page_id'=>  $table_data['page_id'], 'form_id'=> $table_data['form_id'] ) );
		}
		
		die();
	}
	
	public function get_forms_ajax() {
		
		$page_url = esc_url($_POST['page_url']);
		
		if(!preg_match("#".$_SERVER['SERVER_NAME']."/#", $page_url)) {
			$page_url = $_SERVER['SERVER_NAME']."/$page_url";
		}
		
		if(!preg_match("#^http://#", $page_url)) {
			$page_url = "http://$page_url";
		}
		
		$pageID = url_to_postid( $page_url );
		
		if(!$pageID) {
			die(json_encode( array( 'error'=> 'Page was not found.' ) ));
		}
		
		$page = get_page($pageID);
		$trackedForm = $this->get_forms_on_page($pageID);
		
		$content = do_shortcode($page->post_content);

		$parsed = str_get_html($content);

		$data = array();
		
		foreach($parsed->find('form') as $k=>$element) {
			
			$form_data = array();
			
			if(isset($element->id)) {
				$form_data['Form ID'] = $element->id;
			}
			else {
				$element->id = 'page_form_tracking_'.$k;
				$form_data['Form ID'] = $element->id;
			}
			
			$trackedData = $this->search_by_id($element->id, $trackedForm);
		
			$form_data['event'] = ($trackedData) ? $trackedData['event'] : false;
			
			$form_data['children'] = $this->childFinder($element, $trackedData);
			
			$this->inputFields = array();
			
			$data[] = $form_data;
		}

		die( json_encode( array( 'data' => $data, 'pageID' =>  $pageID ) ) );
	}
	
	private function childFinder($element, $formData) {
		$childTags = array('input', 'textarea', 'select', 'a', 'button');
		$exception = array('hidden', 'submit');
		$child = null;
		$formChild = array();
		
		foreach($element->children as $child) {
			
			if($child->children) {
				$this->childFinder($child, $formData);
			}
			
			if( !in_array($child->tag, $childTags) || (isset($child->attr['type']) && in_array( $child->attr['type'], $exception )) ) {
				continue;
			}
			
			$formChild = array();
			
			if($child->tag == 'input') {
				$type = $child->attr['type'];
			}
			else {
				$type = $child->tag;
			}
			
			$name = (isset($child->attr['name'])) ? $child->attr['name'] : '';
			if($child->tag == 'a') {
				$name = $child->attr['href'];
			}
			if( in_array($name, array('email', 'phone')) ) {
				$form_data['identity'] = 1;
			}
			
			$formChild['type'] = $type;
			$formChild['name'] = $name;
			
			foreach($this->defFields as $row=>$santize) {
				if(!isset($formData[$row]))
					continue;
				
				if($formData[$row] == $name) {
					$formChild['role'] = $row;
				}
			}
			
			$this->inputFields[] = $formChild;
		}
		
		return $this->inputFields;
	}
	
	private function search_by_id($id, $array) {
		
		foreach($array as $k=>$value) {
			if($value['form_id'] == $id) {
				return $value;
			}
		}
		
		return false;
	}
	
	public function add_admin_menu() {
		add_menu_page('Autosend Tracking', 'Autosend', 'read', 'autosend_form_tracking', array(&$this, 'autosend_settings'), 'dashicons-share','11.511');
	}
	
	public function autosend_settings() {
		$key = get_option('autos_unique_key');
		require($this->path_tmpl.'admin_page.php');
	}
  
} // end class
new AutosendFormTracking();

?>