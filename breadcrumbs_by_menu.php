<?php
/**
 * Plugin Name: Breadcrumbs by menu
 * Plugin URI: http://www.holest.com/
 * Description: Breadcrumb formed by respecting menu item (previously clicked on) parents as defined in containing menu
 * Version: 1.0.3
 * Author: Holest Engineering
 * Author URI: http://www.holest.com
 * Requires at least: 3.0
 * Tested up to: 5.6.0
 * License: GPLv2
 * Text Domain: breadcrumbs_by_menu
 * Domain Path: /languages/
 *
 * @package breadcrumbs_by_menu
 * @category Core
 * @author Ivan Milic, Holest Engineering March 2014
 */

/*

Copyright (c) holest.com

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

if ( !function_exists('add_action') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if ( ! class_exists( 'breadcrumbs_by_menu' ) ) {
	class breadcrumbs_by_menu{
		var $settings        = array();
		var $item            = null;
		var $items           = null;
		var $breadcrumb      = null;
		var $permalink_paths = null;
		var $MID;
		
		function breadcrumbs_by_menu(){
		    $this->settings = get_option('BCBM_SETTINGS');
			
			if(isset($_REQUEST['bcbm_do_save']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST'){
				
				if (!isset($_POST['bcbm_nonce_check'])) 
					die("<br><br>CSRF: Hmm .. looks like you didn't send any credentials.. No access for you!");
				
				add_action( 'wp_loaded' ,array( $this, 'saveBackendOptions'));
				
			}elseif(empty($this->settings)){
				$this->settings = array(
				   'separator' => '>',
                   'linked' => '1'				   
				);
				update_option('BCBM_SETTINGS',(array)$this->settings);
			}
			
			add_action('admin_menu',array( $this, 'register_plugin_menu_item'));
			add_action('wp_print_styles', array( $this, 'includeAssets'));
			add_action('wp_head', array( $this, 'registerScripts'));
			add_shortcode( 'bcbm', array( $this, 'registerShortcode'));
            			
		}
		
		function sanitize_multiline($str){
			$lines = array_map( 'sanitize_text_field',explode("\n",$str));
			$lines = array_map( 'stripslashes',$lines);
			return implode( "\n", $lines);
		}
		
		function saveBackendOptions(){
			
			if(current_user_can('administrator')){
				if (!wp_verify_nonce($_POST['bcbm_nonce_check'],'bcbm_update_settings')) 
					die("<br><br>CSRF: Hmm .. looks like you didn't send any credentials.. No access for you!");

				$this->settings["root_item_text"] = sanitize_text_field($_REQUEST['bcbm_root_item_text']);
				$this->settings["separator"] 	  = sanitize_text_field($_REQUEST['bcbm_separator']);
				
				$this->settings["linked"] 		  = isset($_REQUEST['bcbm_linked']) == '1' ? '1' : ''; //CAN BE 0 or 1
				
				$this->settings["before"] 		  = $this->sanitize_multiline($_REQUEST['bcbm_before']);
				$this->settings["after"] 		  = $this->sanitize_multiline($_REQUEST['bcbm_after']);
				
				$this->settings["custom_script"]  = $this->sanitize_multiline($_REQUEST['bcbm_custom_script']);
				
				update_option('BCBM_SETTINGS',(array)$this->settings);
			}else
				wp_die("You can not edit properties of this plugin!");
		}
		
		function Init(){
		
		    if($this->breadcrumb)
				return;
			
		    $menus = get_terms( 'nav_menu', array( 'hide_empty' => false ) ); 
			$menu_items = array();
			
			foreach($menus as $menu ){
			  $menu_items = array_merge($menu_items, wp_get_nav_menu_items($menu->name));
			}
		
			$current_permalink = get_permalink();
			
			$this->items = array();
			$permalink_items = array();
			foreach ($menu_items as $key => $menu_item ) {
				if($menu_item->ID){
					$this->items[$menu_item->ID] = $menu_item;
				    if( $menu_item->url == $current_permalink){
						$permalink_items[] = $menu_item;
					}
				}
			}
			
			if(!count($permalink_items))
				return;
				
			$this->permalink_paths = array();
			foreach($permalink_items as $pp_item){
			   $this->permalink_paths[] = $this->getItemUrlHierarchy($pp_item,$this->items);
			}
			
			$this->item = $this->items[$_COOKIE['nav_menuitem_id']];
			
			if(!$this->item && count($permalink_items)){
				$this->item = $permalink_items[0];
			}else if($this->item->url != $current_permalink){
				if(count($permalink_items)){
					$this->item = $permalink_items[0];
				}else
					return;
			}
			
			$this->MID        = $this->item->ID;
			$this->breadcrumb = $this->getItemPath($this->item,$this->items);
		}
		
		function registerScripts(){
		    $this->Init();
		    ?>
			<script type='text/javascript'>
			  var bbm_item      = '<?php echo $this->MID; ?>'; 
			  var bbm_paths     = <?php echo json_encode($this->permalink_paths); ?>;
			  var bbm_item_path = <?php echo json_encode($this->getItemUrlHierarchy($this->item,$this->items)); ?>;
			</script>
			<?php
		}
		
		function getItemPath($item,$items){
		    $path = array();
		    do{
				$path[] = $item;
				$item = $items[$item->menu_item_parent];   	    
			}while($item);
			$path = array_reverse($path);
			return $path;
		}
		
		function getItemUrlHierarchy($item,$items){
		    $path = array();
		    do{
				$path[] = $item->url;
				$item = $items[$item->menu_item_parent];   	    
			}while($item);
			$path = array_reverse($path);
			return $path;
		}
		
		function registerShortcode( $atts ) {
		    $this->Init();
			if(!$this->MID)
				return;
			
			extract(shortcode_atts($this->settings, $atts));
			foreach($this->settings as $key => $value){
			   if(!isset($atts[$key])){
					$atts[$key] = $this->settings[$key];
			   }
			}
			
			$n = 0;
			ob_start();
			
			echo "<div class='breadcrumbs-by-menu' >";
			echo esc_textarea($atts["before"]);
			
			if(trim($atts["root_item_text"])){
			   $first = $n == 0? "first" : ""; 
			   $last  = $n == (count($this->breadcrumb) - 1)? "last" : ""; 
			   
			   echo "<span class='bcbm-item level$n $first $last' >";
			   if($atts['linked'] && !$last) echo "<a href='".get_home_url()."'>";
			   echo esc_js($atts["root_item_text"]);
			   if($atts['linked'] && !$last) echo "</a>";
			   echo "</span>";
			   if(!$last) echo "<span class='bcbm-separator level$n $first $last'>".esc_js($atts['separator'])."</span>";
			   $n++;
			}
			 
			foreach($this->breadcrumb as $part){
			    $first = $n == 0? "first" : ""; 
			    $last  = $n == (count($this->breadcrumb) - 1)? "last" : ""; 
			   
				echo "<span class='bcbm-item level$n $first $last' >";
				if($atts['linked'] && !$last) echo "<a href='". $part->url ."'>";
				echo $part->title;
				if($atts['linked'] && !$last) echo "</a>";
				echo "</span>";
				if(!$last) echo "<span class='bcbm-separator level$n $first $last'>".esc_js($atts['separator'])."</span>";
				$n++;
			}
			
			echo esc_textarea($atts['after']);
			echo "</div>";
			
			if($this->settings["custom_script"]){
			   echo "<script type='text/javascript'>
			         ".esc_textarea($this->settings["custom_script"])."
			         </script>";
			}
			
			$out = ob_get_contents();
			ob_end_clean();

			return $out;
		}

		
		public function includeAssets(){
			wp_enqueue_style( 'breadcrumbs_by_menu_css', plugins_url('/breadcrumbs_by_menu.css', __FILE__));
			wp_enqueue_script( 'breadcrumbs_by_menu_js', plugins_url('/breadcrumbs_by_menu.js', __FILE__),array('jquery'));
		}
		
		public function register_plugin_menu_item(){
		    add_menu_page( "Breadcrumbs by menu settings","BCBM Settings", "manage_options", "breadcrumbs_by_menu_settings",array( $this, 'display'), "dashicons-admin-generic",32);
		}
		
		public function display(){
		
		   ?>
		    <div class="breadcrumbs-by-menu-admin">
			  <h2>Breadcrumbs by menu settings</h2>
			  
			 
			  <hr/>
			  <form method="POST" >
			  <input type="hidden" name="bcbm_do_save"  value="1" />
			  <table>
				 <tr>
					<td colspan="2">
						<input name="bcbm_nonce_check" type="hidden" value="<?php echo wp_create_nonce('bcbm_update_settings'); ?>" />
					</td>
				 </tr>
			     <tr><td>Root item text</td> <td> <input style="width:300px;" type="text" name="bcbm_root_item_text" value="<?php echo esc_js($this->settings["root_item_text"]);?>" /> </td></tr>
				 <tr><td>Separator</td> <td> <input style="width:300px;" type="text" name="bcbm_separator" value="<?php echo esc_js($this->settings["separator"]);?>" /> </td></tr>
				 <tr><td>Linked parts</td> <td> <input style="float:left;" type="checkbox" name="bcbm_linked" value="1" <?php echo esc_js($this->settings["linked"] ? ' checked="checked" ' : "");?> /> </td></tr>
				 <tr><td></td> <td></td> </tr>
				 <tr><td>Before</td> <td> <textarea style="width:300px;" name="bcbm_before" ><?php echo esc_textarea($this->settings["before"]);?></textarea> </td></tr>
				 <tr><td>After</td> <td> <textarea style="width:300px;" name="bcbm_after" ><?php echo esc_textarea($this->settings["after"]);?></textarea> </td></tr>
				 <tr><td colspan="2" > <hr/></td></tr>
				 <tr>
				 <td colspan="2" > 
				  <h4>Custom script</h4>
				  <textarea style="width:50%;min-height:300px;" name="bcbm_custom_script"><?php echo esc_textarea($this->settings["custom_script"]);?></textarea>
				  <h6>Availble javascript variables:</h6>
				  <code>bbm_item       - ID of current item</code><br/>
				  <code>bbm_item_path  - current item permalink path ['Second menu parent URL','First menu parent URL','current item URL']</code><br/>
                  <code>bbm_paths      - array of arrays of all menu items permalink paths in all menus that target current page</code><br/>
<pre>
	//EXAMPLE:
	//PROBLEM: Site has top and side menu (css path: .mp_left UL.menu). Some items in both menus point to same page. 
	//         If we click on item in top menu we want to find best mathing item from side menu and mark it as active (add class .active):
	if(bbm_item_path){
		var selector = ".mp_left " + bbm_item_path.map(function(i){
		   return 'LI:has(>a[href="' + i + '"])';
		}).join(" ");
		var item = jQuery(selector);
		while(!item[0] && bbm_item_path.length > 0){
			bbm_item_path.shift();
			selector = ".mp_left " + bbm_item_path.map(function(i){
			   return 'LI:has(>a[href="' + i + '"])';
			}).join(" ");
			item = jQuery(selector);
		}
		item.addClass('active');
	}
</pre>
		  
				 
				 </td>
				 </tr>
				 <tr><td colspan="2" > <hr/></td></tr>
				 <tr><td></td> <td> <input style="float:right;font-size:22px;padding:12px 30px;" type="submit"  value="Save" /> </td></tr>
				 
			  </table>
			  </form>
			  <hr/>
			  <strong>
			  <p style="font-size:18px;">- Basic shortcode (will take parameter values from settings)</p>
			  <code>[bcbm]</code>
			  <br/>
			  </strong>
			  <br/>
			  <br/>
			  
			  <h3>Parameter listing</h3>
			  <p class="note">(**If not explicity set in shortcode plugin will use default values set in above settings form)</p>
			  <ul>
				<li>root_item_text (default = None, text to display as first item, blank value disables it)</li>
				<li>separator (default = &gt;, separator text)</li>
				<li>linked (default = 1, link path items)</li>
				<li>before (default = '', HTML to output before shortcode rendering)</li>
				<li>after (default = '', HTML to output after shortcode rendering)</li>
			  </ul>
			  <p><code>[bcbm root_item_text="[for homepage, if blank not displayed]" separator="[separator text]" linked="[1|0 - should items link to pages]" before="[html to insert before]" after="[html to insert after]"]</code></p>
			  <br/>
			  <br/>
			  <strong>
			  <p>Provided by <a href="http://www.holest.com" >Holest Engineering</a> under GPLv2 license</p>
			  </strong>
		    </div>
		   <?php
		}
	
	
	}
	
	$GLOBALS['breadcrumbs_by_menu'] = new breadcrumbs_by_menu();
}

?>