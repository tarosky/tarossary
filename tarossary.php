<?php
/**
Plugin Name: Tarossary
Plugin URI: https://github.com/tarosky/tarossary
Description: In Tarossary you can create your own glossary and links will automatically be attached to articles
Author: TAROSKY INC.
Author URI: https://tarosky.co.jp
Version: 0.9.0
*/
//	定義文
define('TRS_POST_TYPE', 'trs_glossary');
define('TRS_OPT_SETTING', 'trs_setting');
define('TRS_TRANSIENT_CACHED_CONTENT', 'trs_cached_content');

new TAROSSARY();
Class TAROSSARY{

	protected $trs_metabox_list = [
		'trs_glossary_link' => [
			'label' => 'link',
			'type' => 'text',
		],
		'trs_glossary_is_seal' => [
			'label' => 'seal',
			'type' => 'checkbox',
		],
	];
	protected $default_options = [
		'display_post_type'	=> ['post'],
		'target_post_type'	=> [TRS_POST_TYPE],
		'time'				=> 0
	];

	function __construct() {
		add_action('init', array($this, 'trs_init'));
		add_action('admin_menu', array($this, 'trs_menu'));
		add_action('save_post', array($this, 'trs_save_post'), 10, 2);
		add_action('admin_enqueue_scripts', array($this, 'trs_enqueue'));
		add_filter('post_type_link', array($this, 'trs_type_link'), 10, 2);
		add_filter('rewrite_rules_array', array($this, 'trs_rewrite_rules_array'));
		add_filter('the_content', array( $this, 'trs_the_content_glossary' ), 9);
	}

	/**
	 * 用語集用の入稿要素を追加
	 */
	function trs_init(){
		load_plugin_textdomain('tarossary-textdomain', false, dirname( plugin_basename( __FILE__ ) ) .'/languages');

		//Tarossary用の用語集
		register_post_type(
			TRS_POST_TYPE,
			[
				'label'					=> __('glossary', 'tarossary-textdomain'),
				'description'			=> __('Glossary for Tarossary', 'tarossary-textdomain'),
				'public'				=> true,
				'exclude_from_search'	=> true,
				'menu_position'			=> 20,
				'menu_icon'				=> 'dashicons-book-alt',
				'supports'				=> ['title', 'editor', 'thumbnail', 'excerpt'],
				'has_archive'			=> true,
				'register_meta_box_cb'	=> array( $this, 'trs_glossary_metabox' ),
			]
		);
	}

	/**
	 * 用語集入稿内容
	 */
	function trs_glossary_metabox(){
		add_meta_box(TRS_POST_TYPE, __('Settings', 'tarossary-textdomain'), array($this, 'trs_glossary_metabox_display'));
	}

	/**
	 * オプション設定
	 */
	function trs_menu(){
		add_options_page('Setting', 'Tarossary', 'manage_options', TRS_POST_TYPE, array($this, 'trs_admin_display'));
	}

	/**
	 *
	 */
	function trs_enqueue(){
		global $pagenow;
		if( $pagenow == 'options-general.php' ) {
			if( !empty($_GET['page']) && TRS_POST_TYPE == $_GET['page'] ) {
				wp_enqueue_style( 'tarossary', plugins_url( 'tarossary.css', __FILE__ )  );
			}
		}
	}
	/**
	 * オプション画面
	 */
	function trs_admin_display(){
		$this->trs_check_options();

		$post_types = get_post_types();
		$setting = $this->trs_get_options();
		$nonce = wp_create_nonce( plugin_basename(__FILE__) );
		$time = !empty($setting['time']) ? intval($setting['time']) : 0 ;
?>
	<div class="trs-setting">
		<form action="#" method="POST">
			<input type="hidden" name="trossary_nonce" id="tarossary_nonce" value="<?= $nonce ?>" />
			<div class="trs-setting-display-post">
				<div class="trs-setting-info">
					<h2><?= __('Post type to be automatically linked', 'tarossary-textdomain') ?></h2>
					<span class="trs-setting-description"><?= __('If there is a term in the body content of the checked post type, it will be automatically converted to the link.', 'tarossary-textdomain') ?></span>
				</div>
				<?php foreach( $post_types as $type) : ?>
					<?php
						$id = sprintf('d_%s', $type);
						$checked = "";
						if( !empty($setting['display_post_type']) && false !== array_search( $type, $setting['display_post_type'] ) ) {
							$checked = "checked='checked'";
						}
						$label = '';
						if( $obj = get_post_type_object($type) ){
							$label = $obj->label;
						}
					?>
					<div><input type="checkbox" name="display_post_type[]" id="<?= $id; ?>" <?= $checked; ?> value="<?= $type; ?>"><label for="<?= $id; ?>"><?= $label ?></label></div>
				<?php endforeach; ?>
			</div>

			<div class="trs-setting-target-post">
				<div class="trs-setting-info">
					<h2><?= __('Post type as a term', 'tarossary-textdomain') ?></h2>
					<span class="trs-setting-description"><?= __('Treat the title of the checked post type as a term.', 'tarossary-textdomain') ?></span>
				</div>
				<?php foreach( $post_types as $type) : ?>
					<?php
						$id = sprintf('t_%s', $type);
						$checked = "";
						if( !empty($setting['target_post_type']) && false !== array_search( $type, $setting['target_post_type'] ) ) {
							$checked = "checked='checked'";
						}
						$label = '';
						if( $obj = get_post_type_object($type) ){
							$label = $obj->label;
						}
					?>
					<div><input type="checkbox" name="target_post_type[]" id="<?= $id; ?>" <?= $checked; ?> value="<?= $type; ?>"><label for="<?= $id; ?>"><?= $label ?></label></div>
				<?php endforeach; ?>
			</div>

			<div class="trs-setting-time">
				<?= __('Cache time(Seconds)', 'tarossary-textdomain') ?>: <input type="text" name="time" value="<?= $time; ?>">
			</div>
			<input class="trs-setting-button" type="submit" value=<?= __('Save', 'tarossary-textdomain') ?>>
		</form>
	</div>

<?php
	}

	/**
	 *
	 * @param type $link
	 * @param type $post
	 * @return type
	 */
	function trs_type_link($link, $post){
		if( TRS_POST_TYPE === $post->post_type ) {
			$link = apply_filters('trs_filter_set_type_link', $link, $post);
		}
		return $link;
	}

	/**
	 *
	 * @param type $rules
	 * @return type
	 */
	function trs_rewrite_rules_array($rules){
		$rules = apply_filters('trs_filter_set_rewrite_rules_array', $rules);
		return $rules;
	}

	/**
	 * 用語集入稿ページ
	 * @param type $post
	 */
	function trs_glossary_metabox_display($post){
		$nonce = wp_create_nonce( plugin_basename(__FILE__) );

		$input_tag = '';
		foreach( $this->trs_metabox_list as $key => $meta_info ) {
			$get_meta = get_post_meta( $post->ID, $key, true );
			switch( $meta_info['type'] ) {
				case 'text' :
					$input_tag .= sprintf( '<div>%s: <input name="%s" value="%s" type="text"></div>', $meta_info['label'], $key, $get_meta );
					break;
				case 'checkbox' :
					$checked = $get_meta ? 'checked="checked"' : '' ;
					$input_tag .= sprintf( '<div>%s: <input name="%s" %s value="true" type="checkbox"></div>', $meta_info['label'], $key, $checked );
					break;
			}
		}

		$input_tag = apply_filters('trs_filter_set_metabox', $input_tag);
		echo <<<HTML
	<input type="hidden" name="trossary_nonce" id="tarossary_nonce" value="{$nonce}" />
	{$input_tag}
HTML;
	}

	/**
	 *
	 * @param type $post_id
	 * @return type
	 */
	function trs_save_post($post_id, $post){
		if ( empty($_POST['trossary_nonce']) || !wp_verify_nonce( $_POST['trossary_nonce'], plugin_basename(__FILE__) )) {
			return $post_id;
		}
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}
		if ( !current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		foreach( $this->trs_metabox_list as $key => $meta ) {
			update_post_meta($post_id, $key, $_POST[$key]);
		}
	}

	/**
	 *
	 */
	function trs_check_options(){
		if ( empty($_POST['trossary_nonce']) || !wp_verify_nonce( $_POST['trossary_nonce'], plugin_basename(__FILE__) )) {
			return ;
		}
		unset($_POST['trossary_nonce']);
		update_option(TRS_OPT_SETTING, serialize( $_POST ));
	}

	/**
	 *
	 * @return type
	 */
	function trs_get_options(){
		$options = $this->default_options;
		if( $set_opt = get_option( TRS_OPT_SETTING ) ){
			$options = array_intersect_key(unserialize($set_opt), $options) ;
		}
		return $options;
	}

	/**
	 *
	 * @param type $post_id
	 */
	function trs_get_chached_content( $hash ){
		$cache_content = null;

		if( false !== ($transient_data = get_transient($hash)) ) {
			$cache_content = $transient_data;
		}
		return $cache_content;
	}

	/**
	 *
	 * @param type $post_id
	 * @return string
	 */
	function trs_set_chached_content( $custom_content, $hash, $expiration = 0 ){
		if( $expiration ) {
			set_transient( $hash, $custom_content, $expiration );
		}
	}

	/**
	 *
	 * @param type $content
	 * @return type
	 */
	function trs_the_content_glossary( $content ){
		global $wpdb;
		$base_content = $content;

		$options = $this->trs_get_options();
		if( empty($options) || empty($options['display_post_type']) || empty($options['target_post_type']) ) {
			return $content;
		}

		$display_select_list = $options['display_post_type'];
		if( (!(is_singular($display_select_list) && is_main_query())) || ( is_admin() && !isset(array_flip($display_select_list)[get_post_type()]) ) ) {
			return $content;
		}

		$cache_wait = $options['time'];
		if( $cache_wait && $cached_content = $this->trs_get_chached_content( md5($base_content) ) ){
			return $cached_content;
		}

		$keys = [];
		$key_list = [];
		$target_posts = '';
		foreach( $options['target_post_type'] as $key => $target ) {
			$delimiter = $key === 0 ? '' : ',' ;
			$target_posts .= sprintf( " %s'%s'", $delimiter, $target );
		}
		$sql = <<<SQL
		SELECT ID, post_title, post_type ,CHAR_LENGTH(post_title) AS LEN FROM {$wpdb->posts}
		WHERE post_type IN ( {$target_posts} )
		AND post_status = 'publish'
		ORDER BY LEN DESC
SQL;
		$result = $wpdb->get_results($sql);
		foreach( $result as $target ) {
			$set_url = '';
			if( $target->post_type == TRS_POST_TYPE ) {
				if( !$set_url = esc_attr( get_post_meta( $target->ID, 'trs_glossary_link', true ) ) ) {
					$set_url = get_the_permalink($target->ID);
				}
			}
			else {
				$set_url = get_the_permalink($target->ID);
			}
			$title = apply_filters('trs_filter_set_title', $target->post_title);
			$set_url = apply_filters( 'trs_filter_set_url', $set_url, $target );

			$key_list[$title] = [
				'url' => $set_url,
				'pattern' => 0,
			];
		}

		$insensitive = '';
		$content = $this->trs_findtags($content);
		foreach($key_list as $word => $key){
			$name = trim(stripslashes(stripslashes($word)));

			if($name && is_array($key) ){
				$link = explode('|',$key['url']);

				$replace1 = '<a href="'.$link[0].'">';
				$replace2 = '</a>';

				$escapes = array('.','$','^','[',']','?','+','(',')','*','|','\\');

				foreach($escapes as $s){
					$r = '\\\\'.$s;
					$name = str_replace($s, stripslashes($r), $name);
				}

				$needle ='@()('.$name.')()@';
				$extra = '';

				switch( intval($key['pattern']) ) {
					case 1 :
						$insensitive = 'i';
						break;
				}

				if(!empty($link)){
					if(trim(str_replace(array('http://','https://','www.'),'',$link[0]),'/') == trim(str_replace(array('http://','https://','www.'),'',get_permalink()),'/')){
						continue;

					}

					$content = $this->trs_findtags($content,false);
					$set = !empty($insensitive) ? $insensitive : '' ;
					$content = $this->trs_replace($content, $needle, $replace1, $replace2, -1, $set);
				}
				unset($name, $insensitive);
			}
		}

		$content = $this->trs_findblocks($content);
		$this->trs_set_chached_content( $content, md5($base_content) ,$cache_wait );
//		set_transient(TRS_TRANSIENT_CACHED_CONTENT, $content, $cache_wait);

		return $content;
	}

	/**
	 *
	 * @global type $wpdb
	 * @param type $post_id
	 */
/*
	function trs_cache_clear( $post_id = 0 ){
		$options = $this->trs_get_options();
		if( empty($options['time']) || $options['time'] === 0 ) {
			return;
		}

		if( $post_id ) {
			delete_transient( sprintf('%s_%s', TRS_TRANSIENT_CACHED_CONTENT, $post_id) );
		}
	}
 *
 */

	/**
	 *
	 * @global type $replace
	 * @param type $haystack
	 * @param type $needle
	 * @param type $replace1
	 * @param type $replace2
	 * @param type $times
	 * @param type $insensitive
	 * @return type
	 */
	function trs_replace($haystack, $needle, $replace1, $replace2, $times=-1,$insensitive=''){
		global $replace;
		$replace = array($replace1,$replace2);
		$result = preg_replace_callback($needle.$insensitive, array($this, 'trs_replace_callback'), $haystack);
		return $result;
	}

	/**
	 *
	 * @global type $replace
	 * @param type $matches
	 * @return string
	 */
	function trs_replace_callback($matches){
		global $replace;
		$x='';
			$par_open = strpos($matches[2],'('); //check to see if their are an even number of parenthesis.
			$par_close = strpos($matches[2],')');

			if($par_open !== false && $par_close === false || $par_open === false && $par_close !== false )
				return $matches[1].$matches[2].$matches[count($matches)-1];
		$result = $matches[1].$replace[0].$x.$matches[2].$replace[1].$matches[count($matches)-1];
		return $result;
	}

	/**
	 *
	 * @global type $protectblocks
	 * @param type $content
	 * @param type $firstrun
	 * @return type
	 */
	function trs_findtags($content,$firstrun=true){
		global $protectblocks;

	//protects a tags
		$content = preg_replace_callback('!(\<a[^>]*\>([^>]*)\>)!ims', array($this, 'trs_returnblocks'), $content);
		if($firstrun){
			$content = preg_replace_callback('!(\<code\>[\S\s]*?\<\/code\>)!ims', array($this, 'trs_returnblocks'), $content);
			$content = preg_replace_callback('!(\[tags*\][\S\s]*?\[\/tags*\])!ims', array($this, 'trs_returnblocks'), $content);
			$content = preg_replace_callback('!(\<img[^>]*\>)!ims', array($this, 'trs_returnblocks'), $content);
			$content = preg_replace_callback('!(([A-Za-z]{3,9})://([-;:&=\+\$,\w]+@{1})?([-A-Za-z0-9\.]+)+:?(\d+)?((/[-\+~%/\.\w]+)?\??([-\+=&;%@\.\w]+)?#?([\w]+)?)?)!', array($this, 'trs_returnblocks'), $content);
			$content = preg_replace_callback('!([-A-Za-z0-9_]+\.[A-Za-z][A-Za-z][A-Za-z]?\W?)!', array($this, 'trs_returnblocks'), $content);
		}

		return $content;
	}

	/**
	 *
	 * @global type $protectblocks
	 * @param type $blocks
	 * @return type
	 */
	function trs_returnblocks($blocks){
		global $protectblocks;
		$protectblocks[] = $blocks[1];
		return '[block]'.(count($protectblocks)-1).'[/block]';
	}

	/**
	 *
	 * @global type $protectblocks
	 * @param type $output
	 * @return type
	 */
	function trs_findblocks($output){
		global $protectblocks;
			if(is_array($protectblocks)){
				$output = preg_replace_callback('!(\[block\]([0-9]*?)\[\/block\])!', array($this, 'trs_return_tags'), $output);
			}
			$protectblocks = '';
		return $output;
	}

	/**
	 *
	 * @global type $protectblocks
	 * @param type $blocks
	 * @return \type
	 */
	function trs_return_tags($blocks){
		global $protectblocks;
		return $protectblocks[$blocks[2]];
	}
}
