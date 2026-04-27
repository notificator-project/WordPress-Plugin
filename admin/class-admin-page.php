<?php

/**
 * Admin Page Class
 *
 * Handles the admin settings page rendering and AJAX handlers
 *
 * @package NotificatorCompanion
 * @since 1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notificator_Companion_Admin_Page
 *
 * Manages the plugin's admin interface
 */
class Notificator_Companion_Admin_Page
{

	/**
	 * Option name
	 *
	 * @var string
	 */
	private $option_name = 'notificator_companion_settings';

	/**
	 * Parent plugin instance
	 *
	 * @var object
	 */
	private $plugin;

	/**
	 * Constructor
	 *
	 * @param object $plugin Parent plugin instance.
	 */
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets($hook)
	{
		if ('settings_page_notificator-companion' !== $hook && 'toplevel_page_notificator-companion' !== $hook) {
			return;
		}

		// Ensure jQuery is available.
		wp_enqueue_script('jquery');

		// Ensure Dashicons are available (some installs dequeue them).
		wp_enqueue_style('dashicons');

		$dist_css_path = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/dist/admin.css';
		$dist_js_path  = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/dist/admin.js';
		$dark_css_path = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/css/admin-dark.css';
		$has_dist      = file_exists($dist_css_path) && file_exists($dist_js_path);

		if ($has_dist) {
			wp_enqueue_style(
				'notificator-companion-admin-bundle',
				NOTIFICATOR_COMPANION_PLUGIN_URL . 'assets/dist/admin.css',
				array(),
				(string) filemtime($dist_css_path)
			);

			wp_add_inline_style(
				'notificator-companion-admin-bundle',
				'.notificator-search input[type="text"], .notificator-search input[type="search"] { padding-left: 2.75rem !important; } .notificator-search .notificator-search-icon { z-index: 2; }'
			);

			wp_enqueue_script(
				'notificator-companion-admin-bundle',
				NOTIFICATOR_COMPANION_PLUGIN_URL . 'assets/dist/admin.js',
				array('jquery'),
				(string) filemtime($dist_js_path),
				true
			);

		} 

		$script_handle = $has_dist ? 'notificator-companion-admin-bundle' : 'notificator-companion-admin-js';
		wp_add_inline_script(
			$script_handle,
			<<<'NOTIFICATOR_SCAN_BINDING'
(function(){
	var scanSelector = '#scan-plugins-btn, #auto-scan-btn, #notificator-scan-plugins-tool';

	function triggerScan(event){
		if(event){
			event.preventDefault();
			event.stopPropagation();
		}
		if(typeof window.startPluginScan==='function'){
			window.startPluginScan();
		}
	}

	function bindButtons(){
		var buttons = document.querySelectorAll(scanSelector);
		if(!buttons.length){return;}
		buttons.forEach(function(btn){
			if(!btn || btn.getAttribute('data-notificator-scan-bound')==='1'){return;}
			btn.setAttribute('data-notificator-scan-bound','1');
			btn.addEventListener('click',triggerScan);
		});
	}

	function bindDelegated(){
		document.addEventListener('click',function(event){
			var target = event.target instanceof Element ? event.target.closest(scanSelector) : null;
			if(!target){return;}
			triggerScan(event);
		},true);
	}

	function init(){
		bindButtons();
		bindDelegated();
		window.addEventListener('notificator:save:state',function(e){
			var detail = e && e.detail ? e.detail : {};
			if(detail.state==='saved'){
				window.setTimeout(bindButtons,0);
			}
		});
		window.addEventListener('notificator:api-keys:updated',function(){
			window.setTimeout(bindButtons,0);
		});
		window.setTimeout(bindButtons,250);
	}

	if(document.readyState==='loading'){
		document.addEventListener('DOMContentLoaded',init);
	}else{
		init();
	}
})();
NOTIFICATOR_SCAN_BINDING,
			'after'
		);
		wp_add_inline_script(
			$script_handle,
			"(function(){function hasApiKey(){var inputs=Array.prototype.slice.call(document.querySelectorAll('input[name*=\\\"[api_keys]\\\"]'));if(inputs.some(function(input){return input.value&&input.value.trim();})){return true;}var legacy=document.querySelector('input[name*=\\\"[api_key]\\\"]');return !!(legacy&&legacy.value&&legacy.value.trim());}function unlockUi(){Array.prototype.slice.call(document.querySelectorAll('[data-notificator-lock]')).forEach(function(el){el.setAttribute('hidden','');});Array.prototype.slice.call(document.querySelectorAll('[data-notificator-unlock]')).forEach(function(el){el.removeAttribute('hidden');});var locked=document.querySelector('[data-notificator-locked-sections]');if(locked){locked.remove();}var unlocked=document.querySelector('[data-notificator-unlocked-sections]');if(unlocked){unlocked.removeAttribute('hidden');}var logWrap=document.querySelector('[data-notificator-log-wrapper]');if(logWrap){logWrap.removeAttribute('hidden');}var scanStep=document.querySelector('[data-notificator-step=\\\"scan\\\"]');if(scanStep){var lockedClass=scanStep.getAttribute('data-locked-class')||'is-disabled';scanStep.classList.remove(lockedClass);var badge=scanStep.querySelector('[data-notificator-step-badge]');if(badge){var readyClass=badge.getAttribute('data-class-ready');var lockedBadge=badge.getAttribute('data-class-locked');if(lockedBadge){badge.classList.remove(lockedBadge);}if(readyClass){badge.classList.add(readyClass);}}}var apiStatus=document.querySelector('[data-notificator-step=\\\"api\\\"] [data-notificator-step-status]');if(apiStatus){var doneLabel=apiStatus.getAttribute('data-status-done');if(doneLabel){apiStatus.textContent=doneLabel;}}var scanStatus=document.querySelector('[data-notificator-step=\\\"scan\\\"] [data-notificator-step-status]');if(scanStatus){var readyLabel=scanStatus.getAttribute('data-status-ready');if(readyLabel){scanStatus.textContent=readyLabel;}}var scanTool=document.getElementById('notificator-scan-plugins-tool');if(scanTool){scanTool.disabled=false;scanTool.setAttribute('aria-disabled','false');}Array.prototype.slice.call(document.querySelectorAll('[data-notificator-disable]')).forEach(function(el){el.removeAttribute('disabled');el.removeAttribute('aria-disabled');el.removeAttribute('data-notificator-disabled');el.classList.remove('is-disabled');});var apiContainer=document.getElementById('notificator-api-keys');if(apiContainer){apiContainer.setAttribute('data-has-api-key','1');}var addKey=document.getElementById('notificator-add-api-key');if(addKey){addKey.removeAttribute('hidden');}Array.prototype.slice.call(document.querySelectorAll('.notificator-remove-api-key')).forEach(function(btn){btn.removeAttribute('hidden');});}function maybeUnlock(){if(hasApiKey()){unlockUi();}}function watchSave(){window.addEventListener('notificator:save:state',function(e){var detail=e&&e.detail?e.detail:{};if(detail.state==='saved'){maybeUnlock();}});maybeUnlock();}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',watchSave);}else{watchSave();}})();",
			'after'
		);
		wp_add_inline_script(
			$script_handle,
			<<<'NOTIFICATOR_NAV_VISIBILITY'
(function(){
	var navUpdateScheduled=false;

	function isVisible(el){
		if(!el){return false;}
		if(el.hasAttribute('hidden')){return false;}
		if(el.closest('[hidden]')){return false;}
		return !!(el.offsetWidth||el.offsetHeight||el.getClientRects().length);
	}

	function scheduleUpdate(){
		if(navUpdateScheduled){return;}
		navUpdateScheduled=true;
		window.requestAnimationFrame(function(){
			navUpdateScheduled=false;
			updateNavVisibility();
		});
	}

	function updateNavVisibility(){
		var links=document.querySelectorAll('[data-notificator-nav]');
		if(!links.length){return;}
		links.forEach(function(link){
			var selector=link.getAttribute('data-notificator-nav');
			if(!selector){return;}
			var targets=document.querySelectorAll(selector);
			if(!targets.length){
				link.hidden=true;
				return;
			}
			var hasVisibleTarget=Array.prototype.some.call(targets,isVisible);
			link.hidden=!hasVisibleTarget;
		});
	}

	function bind(){
		scheduleUpdate();
		window.addEventListener('notificator:save:state',scheduleUpdate);
		window.addEventListener('resize',scheduleUpdate);
		window.setTimeout(scheduleUpdate,0);
		window.setTimeout(scheduleUpdate,250);
	}

	if(document.readyState==='loading'){
		document.addEventListener('DOMContentLoaded',bind);
	}else{
		bind();
	}
})();
NOTIFICATOR_NAV_VISIBILITY,
			'after'
		);
		wp_add_inline_script(
			$script_handle,
			<<<'NOTIFICATOR_LOG_ACTIONS'
(function(){function notify(message,type,duration){if(window.notificatorToast&&window.notificatorToast.show){window.notificatorToast.show(message,type,duration);return;}if(window.alert){window.alert(message);return;}if(window.console&&console.warn){console.warn(message);} }function postAction(action,nonce,payload){var data=window.notificatorCompanionData||{};var ajaxUrl=data.ajaxUrl||window.ajaxurl||'';if(!ajaxUrl){return Promise.reject(new Error('Missing AJAX URL'));}var params=new URLSearchParams();params.set('action',action);params.set('nonce',nonce);Object.keys(payload||{}).forEach(function(key){params.set(key,payload[key]);});return fetch(ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:params.toString(),credentials:'same-origin'});}function bind(){var data=window.notificatorCompanionData||{};var actions=data.actions||{};var nonces=data.nonces||{};var toggle=document.getElementById('notificator-toggle-log');var toggleToasts=document.getElementById('notificator-toggle-admin-toasts');var exportBtn=document.getElementById('notificator-export-log');var clearBtn=document.getElementById('notificator-clear-log');var table=document.querySelector('.notificator-log-table');if(toggle){toggle.addEventListener('click',function(e){e.preventDefault();e.stopImmediatePropagation();if(!actions.toggleLog||!nonces.toggleLog){notify('Missing AJAX configuration.','error',2200);return;}var isEnabled=toggle.getAttribute('data-log-enabled')==='1';var nextState=isEnabled?'disable':'enable';var confirmText=isEnabled?'Disable notifications log?':'Enable notifications log?';if(!confirm(confirmText)){return;}toggle.disabled=true;postAction(actions.toggleLog,nonces.toggleLog,{state:nextState}).then(function(res){return res.json();}).then(function(json){if(json&&json.success){notify(json.data&&json.data.message?json.data.message:'Log updated.','success',1200);window.setTimeout(function(){window.location.reload();},700);}else{notify((json&&json.data&&json.data.message)||'Failed to update log.','error',2200);}}).catch(function(){notify('Network error.','error',2200);}).finally(function(){toggle.disabled=false;});},true);}if(toggleToasts){toggleToasts.addEventListener('click',function(e){e.preventDefault();e.stopImmediatePropagation();if(!actions.toggleAdminToasts||!nonces.toggleAdminToasts){notify('Missing AJAX configuration.','error',2200);return;}var isEnabled=toggleToasts.getAttribute('data-toasts-enabled')==='1';var nextState=isEnabled?'disable':'enable';var confirmText=isEnabled?'Disable dashboard toasts?':'Enable dashboard toasts?';if(!confirm(confirmText)){return;}toggleToasts.disabled=true;postAction(actions.toggleAdminToasts,nonces.toggleAdminToasts,{state:nextState}).then(function(res){return res.json();}).then(function(json){if(json&&json.success){notify(json.data&&json.data.message?json.data.message:'Dashboard toasts updated.','success',1200);window.setTimeout(function(){window.location.reload();},700);}else{notify((json&&json.data&&json.data.message)||'Failed to update dashboard toasts.','error',2200);}}).catch(function(){notify('Network error.','error',2200);}).finally(function(){toggleToasts.disabled=false;});},true);}if(exportBtn){exportBtn.addEventListener('click',function(e){e.preventDefault();e.stopImmediatePropagation();if(!actions.exportLog||!nonces.exportLog){notify('Missing AJAX configuration.','error',2200);return;}exportBtn.disabled=true;var original=exportBtn.innerHTML;exportBtn.innerHTML='<span class="dashicons dashicons-update spin"></span> Exporting...';postAction(actions.exportLog,nonces.exportLog,{}).then(function(res){return res.json();}).then(function(json){if(!json||!json.success){throw new Error((json&&json.data&&json.data.message)||'Export failed');}var csv=json.data&&json.data.csv?json.data.csv:'';var fileName=json.data&&json.data.file_name?json.data.file_name:'notificator-log.csv';var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});var url=URL.createObjectURL(blob);var link=document.createElement('a');link.href=url;link.download=fileName;document.body.appendChild(link);link.click();link.remove();URL.revokeObjectURL(url);notify('Log exported.','success',1400);}).catch(function(err){notify(err&&err.message?err.message:'Export failed','error',2400);}).finally(function(){exportBtn.disabled=false;exportBtn.innerHTML=original;});},true);}if(clearBtn){clearBtn.addEventListener('click',function(e){e.preventDefault();e.stopImmediatePropagation();if(!actions.clearLog||!nonces.clearLog){notify('Missing AJAX configuration.','error',2200);return;}if(!confirm('Clear all log entries?')){return;}clearBtn.disabled=true;postAction(actions.clearLog,nonces.clearLog,{}).then(function(res){return res.json();}).then(function(json){if(json&&json.success){notify('Log cleared.','success',1200);window.setTimeout(function(){window.location.reload();},700);}else{notify((json&&json.data&&json.data.message)||'Failed to clear log.','error',2200);}}).catch(function(){notify('Network error.','error',2200);}).finally(function(){clearBtn.disabled=false;});},true);}if(table){table.addEventListener('click',function(event){var target=event.target.closest('.notificator-log-delete');if(!target){return;}event.preventDefault();event.stopImmediatePropagation();var entryId=target.getAttribute('data-log-id');if(!entryId){return;}if(!actions.deleteLog||!nonces.deleteLog){notify('Missing AJAX configuration.','error',2200);return;}if(!confirm('Delete this log entry?')){return;}target.disabled=true;postAction(actions.deleteLog,nonces.deleteLog,{entry_id:entryId}).then(function(res){return res.json();}).then(function(json){if(json&&json.success){notify('Log entry deleted.','success',1200);if(target){var row=target.closest('tr');if(row){row.remove();}}}else{notify((json&&json.data&&json.data.message)||'Failed to delete log entry.','error',2200);}}).catch(function(){notify('Network error.','error',2200);}).finally(function(){target.disabled=false;});},true);} }if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',bind);}else{bind();}})();
NOTIFICATOR_LOG_ACTIONS,
			'after'
		);

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Pass PHP data to JavaScript.
		wp_localize_script(
			$has_dist ? 'notificator-companion-admin-bundle' : 'notificator-companion-admin-js',
			'notificatorCompanionData',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'toastsEnabled' => ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'],
				'toastSettings' => array(
					'duration'  => isset( $options['toast_duration'] ) ? (int) $options['toast_duration'] : 3,
					'positionX' => isset( $options['toast_position_x'] ) ? (string) $options['toast_position_x'] : 'right',
					'positionY' => isset( $options['toast_position_y'] ) ? (string) $options['toast_position_y'] : 'top',
					'deliveryMode' => isset( $options['toast_delivery_mode'] ) ? (string) $options['toast_delivery_mode'] : 'account',
					'dismissMode' => isset( $options['toast_dismiss_mode'] ) ? (string) $options['toast_dismiss_mode'] : 'auto',
				),
				'nonces'  => array(
					'scan'         => wp_create_nonce('notificator_companion_refresh_hooks'),
					'test'         => wp_create_nonce('notificator_companion_test'),
					'saveSettings' => wp_create_nonce('notificator_companion_save_settings'),
					'exportHooks'  => wp_create_nonce('notificator_companion_export_hooks'),
					'importHooks'  => wp_create_nonce('notificator_companion_import_hooks'),
					'toggleLog'    => wp_create_nonce('notificator_companion_toggle_log'),
					'exportLog'    => wp_create_nonce('notificator_companion_export_log'),
					'clearLog'     => wp_create_nonce('notificator_companion_clear_log'),
					'deleteLog'    => wp_create_nonce('notificator_companion_delete_log_entry'),
					'toggleAdminToasts' => wp_create_nonce('notificator_companion_toggle_admin_toasts'),
				),
				'actions' => array(
					'scan'         => 'notificator_companion_refresh_hooks',
					'test'         => 'notificator_companion_test',
					'saveSettings' => 'notificator_companion_save_settings',
					'exportHooks'  => 'notificator_companion_export_hooks',
					'importHooks'  => 'notificator_companion_import_hooks',
					'toggleLog'    => 'notificator_companion_toggle_log',
					'exportLog'    => 'notificator_companion_export_log',
					'clearLog'     => 'notificator_companion_clear_log',
					'deleteLog'    => 'notificator_companion_delete_log_entry',
					'toggleAdminToasts' => 'notificator_companion_toggle_admin_toasts',
				),
			)
		);

		// Pass list of active plugins for template filtering.
		wp_localize_script(
			$has_dist ? 'notificator-companion-admin-bundle' : 'notificator-companion-admin-js',
			'notificatorActivePlugins',
			$this->get_active_plugin_identifiers()
		);

		/**
		 * Let third-party plugins/themes register templates.
		 *
		 * Use the action to call notificator_companion_register_template().
		 */
		do_action( 'notificator_companion_register_templates' );

		$registered_templates = function_exists( 'notificator_companion_get_registered_templates' )
			? notificator_companion_get_registered_templates()
			: array();
		$registered_templates = apply_filters( 'notificator_companion_templates', $registered_templates );
		$registered_templates = array_values( array_filter( $registered_templates, 'is_array' ) );
		$woocommerce_order_status_options = $this->get_woocommerce_order_status_options();
		wp_localize_script(
			$has_dist ? 'notificator-companion-admin-bundle' : 'notificator-companion-admin-js',
			'notificatorTemplateLibrary',
			array(
				'templates' => $registered_templates,
				'woocommerceOrderStatuses' => $woocommerce_order_status_options,
			)
		);

		// Backward/forward compatibility for variables used directly by the admin bundle.
		wp_localize_script(
			$has_dist ? 'notificator-companion-admin-bundle' : 'notificator-companion-admin-js',
			'notificatorScenarioTemplatesExtra',
			$registered_templates
		);
		wp_localize_script(
			$has_dist ? 'notificator-companion-admin-bundle' : 'notificator-companion-admin-js',
			'notificatorWooCommerceOrderStatuses',
			$woocommerce_order_status_options
		);
	}

	/**
	 * Get WooCommerce order statuses formatted for dropdowns.
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private function get_woocommerce_order_status_options()
	{
		if (! function_exists('wc_get_order_statuses')) {
			return array();
		}

		$statuses = call_user_func('wc_get_order_statuses');
		$options  = array();

		foreach ($statuses as $key => $label) {
			// wc_get_order_statuses returns keys like 'wc-completed'.
			$slug = is_string($key) ? preg_replace('/^wc-/', '', $key) : '';
			if (empty($slug)) {
				continue;
			}
			$options[] = array(
				'value' => $slug,
				'label' => is_string($label) ? $label : $slug,
			);
		}

		return $options;
	}

	/**
	 * Get list of active plugin identifiers for template filtering.
	 *
	 * @return array<int, string>
	 */
	private function get_active_plugin_identifiers()
	{
		$active_plugins = array('wordpress-core');

		// Check for WooCommerce.
		if (class_exists('WooCommerce')) {
			$active_plugins[] = 'woocommerce';
		}

		// Check for WooCommerce Subscriptions.
		if (class_exists('WC_Subscriptions')) {
			$active_plugins[] = 'woocommerce-subscriptions';
		}

		// Check for Contact Form 7.
		if (function_exists('wpcf7')) {
			$active_plugins[] = 'contact-form-7';
		}

		// Check for Gravity Forms.
		if (class_exists('GFForms')) {
			$active_plugins[] = 'gravityforms';
		}

		// Check for Paid Memberships Pro.
		if (function_exists('pmpro_init')) {
			$active_plugins[] = 'paid-memberships-pro';
		}

		// Check for Yoast SEO.
		if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
			$active_plugins[] = 'wordpress-seo';
		}

		// Check for Rank Math SEO.
		if (
			defined('RANK_MATH_VERSION') ||
			class_exists('RankMath') ||
			class_exists('RankMath\\Helper') ||
			class_exists('\\RankMath\\Helper')
		) {
		// Check for Rank Math Seo
			$active_plugins[] = 'seo-by-rank-math';
		}

		// Check for UpdraftPlus.
		if (defined('UPDRAFTPLUS_DIR') || class_exists('UpdraftPlus') || class_exists('UpdraftPlus_Options')) {
			$active_plugins[] = 'updraftplus';
		}

		// Check for Wordfence.
		if (defined('WORDFENCE_VERSION') || class_exists('wordfence') || class_exists('wfConfig')) {
			$active_plugins[] = 'wordfence';
		}

		// Check for Elementor.
		if (defined('ELEMENTOR_VERSION') || class_exists('\\Elementor\\Plugin')) {
			$active_plugins[] = 'elementor';
		}

		// Check for FluentCRM.
		if (function_exists('fluentcrm') || defined('FLUENTCRM') || class_exists('\\FluentCrm\\App')) {
			$active_plugins[] = 'fluent-crm';
		}

		// Check for WP Rocket.
		if (defined('WP_ROCKET_VERSION') || function_exists('rocket_clean_domain')) {
			$active_plugins[] = 'wp-rocket';
		}

		// Check for Redirection plugin.
		if (
			defined('REDIRECTION_VERSION') ||
			class_exists('Redirection') ||
			class_exists('Red_Item') ||
			function_exists('red_get_options')
		) {
			$active_plugins[] = 'redirection';
		}

		// Check for LiteSpeed Cache.
		if (
			defined('LSCWP_V') ||
			defined('LITESPEED_ON') ||
			class_exists('\\LiteSpeed\\Core') ||
			class_exists('\\LiteSpeed\\Router')
		) {
			$active_plugins[] = 'litespeed-cache';			
		}

		/**
		 * Filter the list of plugin identifiers treated as active for templates.
		 *
		 * This is used only for template visibility, not hook scanning.
		 */
		$active_plugins = apply_filters('notificator_companion_active_plugin_identifiers', $active_plugins);
		$active_plugins = array_values(array_unique(array_filter($active_plugins, 'is_string')));

		return $active_plugins;
	}

	/**
	 * Render the main settings page
	 */
	public function render_settings_page()
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// Get options.
		$options      = get_option($this->option_name);
		if (! is_array($options)) {
			$options = array();
		}
		$log_enabled = ! isset( $options['log_enabled'] ) || (bool) $options['log_enabled'];
		$api_keys     = array();
		$api_key_nicknames = array();
		if (isset($options['api_keys']) && is_array($options['api_keys'])) {
			$api_keys = array_values(
				array_filter(
					array_map(
						function ($v) {
							return is_string($v) ? trim($v) : '';
						},
						$options['api_keys']
					)
				)
			);
			if (isset($options['api_key_nicknames']) && is_array($options['api_key_nicknames'])) {
				$api_key_nicknames = array_map(
					function ($v) {
						return is_string($v) ? trim($v) : '';
					},
					array_values($options['api_key_nicknames'])
				);
			}
		} elseif (isset($options['api_key']) && is_string($options['api_key']) && '' !== trim($options['api_key'])) {
			$api_keys = array(trim($options['api_key']));
			$api_key_nicknames = array('');
		}
		$monitors     = isset($options['monitors']) ? $options['monitors'] : array();
		$hooks        = isset($options['hooks']) ? $options['hooks'] : array();
		$has_api_key  = ! empty($api_keys);
		$admin_toasts_enabled = ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'];
		$toast_duration = isset( $options['toast_duration'] ) ? (int) $options['toast_duration'] : 3;
		if ( $toast_duration < 1 ) {
			$toast_duration = 1;
		} elseif ( $toast_duration > 15 ) {
			$toast_duration = 15;
		}
		$toast_position_x = isset( $options['toast_position_x'] ) ? (string) $options['toast_position_x'] : 'right';
		if ( ! in_array( $toast_position_x, array( 'left', 'center', 'right' ), true ) ) {
			$toast_position_x = 'right';
		}
		$toast_position_y = isset( $options['toast_position_y'] ) ? (string) $options['toast_position_y'] : 'top';
		if ( ! in_array( $toast_position_y, array( 'top', 'bottom' ), true ) ) {
			$toast_position_y = 'top';
		}
		$toast_delivery_mode = isset( $options['toast_delivery_mode'] ) ? (string) $options['toast_delivery_mode'] : 'account';
		if ( ! in_array( $toast_delivery_mode, array( 'account', 'tab' ), true ) ) {
			$toast_delivery_mode = 'account';
		}
		$toast_dismiss_mode = isset( $options['toast_dismiss_mode'] ) ? (string) $options['toast_dismiss_mode'] : 'auto';
		if ( ! in_array( $toast_dismiss_mode, array( 'auto', 'click' ), true ) ) {
			$toast_dismiss_mode = 'auto';
		}
		$save_label   = __('Save Settings', 'notificator-companion');

?>
		<div class="wrap notificator-companion-wrap">
			<div class="notificator-companion-header">
				<div class="flex items-start gap-4">
					<!-- <div class="notificator-header-icon-wrapper">
						<span class="dashicons dashicons-megaphone"></span>
					</div> -->
					<div class="flex-1 min-w-0">
						<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
						<p><?php esc_html_e('Monitor your websites and receive instant alerts on your mobile, IoT devices, and dashboard when events occur.', 'notificator-companion'); ?></p>
					</div>
				</div>
			</div>

			<div id="notificator-admin-notices" hidden></div>

			<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
				<div class="notificator-step-card" data-notificator-step="api">
					<div class="flex items-center gap-3">
						<div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-semibold bg-indigo-600">1</div>
						<div>
							<div class="text-sm font-semibold text-gray-900"><?php esc_html_e('Add API Key', 'notificator-companion'); ?></div>
							<div class="text-xs text-gray-500" data-notificator-step-status data-status-done="<?php echo esc_attr__( 'Done', 'notificator-companion' ); ?>" data-status-locked="<?php echo esc_attr__( 'Required', 'notificator-companion' ); ?>"><?php echo $has_api_key ? esc_html__('Done', 'notificator-companion') : esc_html__('Required', 'notificator-companion'); ?></div>
						</div>
					</div>
				</div>
				<div class="notificator-step-card <?php echo $has_api_key ? '' : 'is-disabled'; ?>" data-notificator-step="scan" data-locked-class="is-disabled">
					<div class="flex items-center gap-3">
						<div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-semibold <?php echo $has_api_key ? 'bg-indigo-600' : 'bg-gray-400'; ?>" data-notificator-step-badge data-class-ready="bg-indigo-600" data-class-locked="bg-gray-400">2</div>
						<div>
							<div class="text-sm font-semibold text-gray-900"><?php esc_html_e('Scan & Add Scenarios', 'notificator-companion'); ?></div>
							<div class="text-xs text-gray-500" data-notificator-step-status data-status-ready="<?php echo esc_attr__( 'Ready', 'notificator-companion' ); ?>" data-status-locked="<?php echo esc_attr__( 'Locked', 'notificator-companion' ); ?>"><?php echo $has_api_key ? esc_html__('Ready', 'notificator-companion') : esc_html__('Locked', 'notificator-companion'); ?></div>
						</div>
					</div>
				</div>
			</div>

			<form action="options.php" method="post" class="notificator-settings-form">
				<?php settings_fields('notificator_companion_settings_group'); ?>

				<div class="notificator-layout">
					<aside class="notificator-sidebar" aria-label="<?php echo esc_attr(__('Sections', 'notificator-companion')); ?>">
						<div class="notificator-nav-card">
							<div class="notificator-nav-title"><?php esc_html_e('Sections', 'notificator-companion'); ?></div>
							<a class="notificator-nav-link" href="#notificator-api" data-notificator-nav="#notificator-api">
								<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('api') ); ?>"></span>
								<?php esc_html_e('API Keys', 'notificator-companion'); ?>
							</a>
							<a class="notificator-nav-link" href="#notificator-templates" data-notificator-nav="#notificator-templates">
								<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('templates') ); ?>"></span>
								<?php esc_html_e('Templates', 'notificator-companion'); ?>
							</a>
							<a class="notificator-nav-link" href="#notificator-builder" data-notificator-nav="#notificator-builder">
								<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('builder') ); ?>"></span>
								<?php esc_html_e('Notifications', 'notificator-companion'); ?>
							</a>
							<a class="notificator-nav-link" href="#notificator-log" data-notificator-nav="#notificator-log">
								<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('log') ); ?>"></span>
								<?php esc_html_e('Log', 'notificator-companion'); ?>
							</a>
							<a class="notificator-nav-link" href="#notificator-help" data-notificator-nav="#notificator-help">
								<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('help') ); ?>"></span>
								<?php esc_html_e('Help', 'notificator-companion'); ?>
							</a>
						</div>
					</aside>

					<div class="notificator-main-content">
						<?php
						$throttle_seconds = isset( $options['throttle_seconds'] ) ? (int) $options['throttle_seconds'] : 30;
						if ( $throttle_seconds < 0 ) {
							$throttle_seconds = 0;
						} elseif ( $throttle_seconds > 3600 ) {
							$throttle_seconds = 3600;
						}
						$scan_hook_limit = isset( $options['scan_hook_limit'] ) ? (int) $options['scan_hook_limit'] : 500;
						if ( $scan_hook_limit < 50 ) {
							$scan_hook_limit = 50;
						} elseif ( $scan_hook_limit > 5000 ) {
							$scan_hook_limit = 5000;
						}
						?>
						<div class="notificator-action-bar" role="toolbar" aria-label="<?php esc_attr_e('Scenario Actions', 'notificator-companion'); ?>">
							<div class="notificator-action-left">
								<span class="notificator-action-title"><?php esc_html_e('Manage Settings', 'notificator-companion'); ?></span>
								<div class="notificator-save-status" id="notificator-save-status-inline" hidden data-state="idle">
									<span class="notificator-save-status-text"><?php esc_html_e('Saved', 'notificator-companion'); ?></span>
								</div>
							</div>

							<div class="notificator-action-right">
								<div class="notificator-action-group">
									<button type="button" id="notificator-add-scenario-top" class="btn-secondary" data-notificator-disable <?php echo $has_api_key ? '' : 'disabled data-notificator-disabled="1" aria-disabled="true"'; ?>>
										<span class="dashicons dashicons-plus-alt2"></span>
										<?php esc_html_e('Add Scenario', 'notificator-companion'); ?>
									</button>

									<details class="notificator-action-menu<?php echo $has_api_key ? '' : ' is-disabled'; ?>" id="notificator-scenarios-menu" x-data data-notificator-disable <?php echo $has_api_key ? '' : 'data-notificator-disabled="1"'; ?> @click.outside="$el.open && ($el.open = false)">
										<summary class="btn-secondary notificator-action-menu-trigger" aria-haspopup="menu" <?php echo $has_api_key ? '' : 'aria-disabled="true"'; ?>>
											<span class="dashicons dashicons-admin-tools"></span>
											<?php esc_html_e('Tools', 'notificator-companion'); ?>
											<span class="dashicons dashicons-arrow-down-alt2 notificator-action-menu-caret" aria-hidden="true"></span>
										</summary>
										<div class="notificator-action-menu-panel" role="menu">
											<div class="notificator-action-menu-section notificator-action-menu-section--limits">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Scan', 'notificator-companion' ); ?>
												</div>
												<button type="button" id="notificator-scan-plugins-tool" class="notificator-action-menu-item" role="menuitem" <?php echo $has_api_key ? '' : 'disabled'; ?> aria-disabled="<?php echo esc_attr( $has_api_key ? 'false' : 'true' ); ?>">
													<span class="dashicons dashicons-update"></span>
													<?php esc_html_e('Scan Plugins', 'notificator-companion'); ?>
												</button>
											</div>
											<div class="notificator-action-menu-section">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Limits', 'notificator-companion' ); ?>
												</div>
												<div class="notificator-toast-setting">
													<label for="notificator-throttle-seconds" class="text-xs text-gray-600">
														<?php esc_html_e( 'Throttle (sec)', 'notificator-companion' ); ?>
													</label>
													<input type="number" id="notificator-throttle-seconds" name="<?php echo esc_attr( $this->option_name ); ?>[throttle_seconds]" min="0" max="3600" value="<?php echo esc_attr( $throttle_seconds ); ?>" data-notificator-disable <?php echo $has_api_key ? '' : 'disabled data-notificator-disabled="1" aria-disabled="true"'; ?> />
												</div>
												<div class="notificator-toast-setting">
													<label for="notificator-scan-hook-limit" class="text-xs text-gray-600">
														<?php esc_html_e( 'Scan Hook Limit', 'notificator-companion' ); ?>
													</label>
													<input type="number" id="notificator-scan-hook-limit" name="<?php echo esc_attr( $this->option_name ); ?>[scan_hook_limit]" min="50" max="5000" value="<?php echo esc_attr( $scan_hook_limit ); ?>" data-notificator-disable <?php echo $has_api_key ? '' : 'disabled data-notificator-disabled="1" aria-disabled="true"'; ?> />
												</div>
											</div>
											<div class="notificator-action-menu-section">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Scenarios', 'notificator-companion' ); ?>
												</div>
												<button type="button" id="notificator-export-scenarios" class="notificator-action-menu-item" role="menuitem">
													<span class="dashicons dashicons-download"></span>
													<?php esc_html_e('Export Scenarios', 'notificator-companion'); ?>
												</button>
												<button type="button" id="notificator-import-scenarios" class="notificator-action-menu-item" role="menuitem">
													<span class="dashicons dashicons-upload"></span>
													<?php esc_html_e('Import Scenarios', 'notificator-companion'); ?>
												</button>
											</div>
											<div class="notificator-action-menu-section">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Log', 'notificator-companion' ); ?>
												</div>
												<button type="button" id="notificator-toggle-log" class="notificator-action-menu-item" role="menuitem" data-log-enabled="<?php echo esc_attr( $log_enabled ? '1' : '0' ); ?>">
													<span class="dashicons <?php echo esc_attr( $log_enabled ? 'dashicons-no' : 'dashicons-yes' ); ?>"></span>
													<?php echo esc_html( $log_enabled ? __( 'Disable Log', 'notificator-companion' ) : __( 'Enable Log', 'notificator-companion' ) ); ?>
												</button>
												<button type="button" id="notificator-export-log" class="notificator-action-menu-item" role="menuitem">
													<span class="dashicons dashicons-media-spreadsheet"></span>
													<?php esc_html_e('Export Log CSV', 'notificator-companion'); ?>
												</button>
											</div>
											<div class="notificator-action-menu-section">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Dashboard', 'notificator-companion' ); ?>
												</div>
												<button type="button" id="notificator-toggle-admin-toasts" class="notificator-action-menu-item" role="menuitem" data-toasts-enabled="<?php echo esc_attr( $admin_toasts_enabled ? '1' : '0' ); ?>">
													<span class="dashicons <?php echo esc_attr( $admin_toasts_enabled ? 'dashicons-no' : 'dashicons-yes' ); ?>"></span>
													<?php echo esc_html( $admin_toasts_enabled ? __( 'Disable Dashboard Toasts', 'notificator-companion' ) : __( 'Enable Dashboard Toasts', 'notificator-companion' ) ); ?>
												</button>
											</div>
											<div class="notificator-action-menu-section">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Toast Settings', 'notificator-companion' ); ?>
												</div>
												<div class="notificator-toast-setting">
													<label class="text-xs text-gray-600" for="notificator-toast-duration">
														<?php esc_html_e( 'Duration (sec)', 'notificator-companion' ); ?>
													</label>
													<input type="number" min="1" max="15" id="notificator-toast-duration" name="<?php echo esc_attr( $this->option_name ); ?>[toast_duration]" value="<?php echo esc_attr( $toast_duration ); ?>" />
												</div>
												<div class="notificator-toast-position-row">
													<label class="text-xs text-gray-600" for="notificator-toast-position-y">
														<?php esc_html_e( 'Position', 'notificator-companion' ); ?>
													</label>
													<select id="notificator-toast-position-y" name="<?php echo esc_attr( $this->option_name ); ?>[toast_position_y]">
														<?php foreach ( array( 'top', 'bottom' ) as $pos_y ) : ?>
															<option value="<?php echo esc_attr( $pos_y ); ?>" <?php selected( $toast_position_y, $pos_y ); ?>><?php echo esc_html( ucfirst( $pos_y ) ); ?></option>
														<?php endforeach; ?>
													</select>
													<select id="notificator-toast-position-x" name="<?php echo esc_attr( $this->option_name ); ?>[toast_position_x]">
														<?php foreach ( array( 'left', 'center', 'right' ) as $pos_x ) : ?>
															<option value="<?php echo esc_attr( $pos_x ); ?>" <?php selected( $toast_position_x, $pos_x ); ?>><?php echo esc_html( ucfirst( $pos_x ) ); ?></option>
														<?php endforeach; ?>
													</select>
												</div>
												<div class="notificator-toast-position-row">
													<label class="text-xs text-gray-600" for="notificator-toast-delivery">
														<?php esc_html_e( 'Delivery', 'notificator-companion' ); ?>
													</label>
													<select id="notificator-toast-delivery" name="<?php echo esc_attr( $this->option_name ); ?>[toast_delivery_mode]">
														<option value="account" <?php selected( $toast_delivery_mode, 'account' ); ?>><?php esc_html_e( 'Per account', 'notificator-companion' ); ?></option>
														<option value="tab" <?php selected( $toast_delivery_mode, 'tab' ); ?>><?php esc_html_e( 'Per tab', 'notificator-companion' ); ?></option>
													</select>
												</div>
												<div class="notificator-toast-position-row">
													<label class="text-xs text-gray-600" for="notificator-toast-dismiss">
														<?php esc_html_e( 'Dismiss', 'notificator-companion' ); ?>
													</label>
													<select id="notificator-toast-dismiss" name="<?php echo esc_attr( $this->option_name ); ?>[toast_dismiss_mode]">
														<option value="auto" <?php selected( $toast_dismiss_mode, 'auto' ); ?>><?php esc_html_e( 'Auto dismiss', 'notificator-companion' ); ?></option>
														<option value="click" <?php selected( $toast_dismiss_mode, 'click' ); ?>><?php esc_html_e( 'Click to dismiss', 'notificator-companion' ); ?></option>
													</select>
												</div>
											</div>
										</div>
									</details>

									<button type="button" id="notificator-theme-toggle" class="btn-icon" aria-label="<?php echo esc_attr__( 'Switch to dark theme', 'notificator-companion' ); ?>" title="<?php echo esc_attr__( 'Switch to dark theme', 'notificator-companion' ); ?>" aria-pressed="false">
										<span class="notificator-theme-icon" data-theme-icon aria-hidden="true">🌙</span>
									</button>
								</div>

								<span class="notificator-action-divider" aria-hidden="true"></span>

								<div class="notificator-action-group notificator-action-group--primary">
									<button type="submit" id="notificator-save-settings" class="btn-primary">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php echo esc_html($save_label); ?>
									</button>
								</div>
							</div>
						</div>

						<?php $this->render_scan_modal(); ?>
						<?php $this->render_import_hooks_modal(); ?>

						<!-- API Configuration -->
						<div class="scenario-section notificator-section" id="notificator-api" data-notificator-section="api">
							<div class="notificator-scenario-head notificator-scenario-head--api">
								<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
									<div class="flex items-center gap-3 min-w-0">
										<div class="notificator-section-icon">
											<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('api') ); ?> text-white"></span>
										</div>
										<div class="min-w-0">
											<h3 class="text-base font-semibold text-white"><?php esc_html_e('API Keys', 'notificator-companion'); ?></h3>
											<p class="text-xs text-white text-opacity-70"><?php esc_html_e('Connect your WordPress site to the monitoring service.', 'notificator-companion'); ?></p>
										</div>
									</div>
								</div>
							</div>
							<div class="card-body space-y-4">
								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2" for="api_key_0">
										<?php esc_html_e('API Keys', 'notificator-companion'); ?> *
									</label>
									<div id="notificator-api-keys" class="space-y-2" data-has-api-key="<?php echo esc_attr( $has_api_key ? '1' : '0' ); ?>">
										<?php
										$api_keys_for_render = ! empty($api_keys) ? $api_keys : array('');
										foreach ($api_keys_for_render as $i => $key) :
											$input_id = 'api_key_' . (int) $i;
											$nickname_id = 'api_key_nickname_' . (int) $i;
											$nickname = isset($api_key_nicknames[$i]) ? $api_key_nicknames[$i] : '';
											$hide_remove = ! $has_api_key && 0 === $i && 1 === count( $api_keys_for_render );
										?>
											<div class="flex items-center gap-2 notificator-api-key-row">
												<input type="password"
													id="<?php echo esc_attr($input_id); ?>"
													name="<?php echo esc_attr($this->option_name); ?>[api_keys][]"
													value="<?php echo esc_attr($key); ?>"
													class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
													placeholder="wpnotif_...">
													<input type="text"
													id="<?php echo esc_attr($nickname_id); ?>"
													name="<?php echo esc_attr($this->option_name); ?>[api_key_nicknames][]"
													value="<?php echo esc_attr($nickname); ?>"
													class="px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[140px]"
													placeholder="<?php echo esc_attr__('Nickname', 'notificator-companion'); ?>">
													<button type="button" class="btn-secondary notificator-test-api-key" aria-label="<?php echo esc_attr(wp_json_encode(__('Send test notification', 'notificator-companion'))); ?>" data-notificator-unlock="test-connection">
														<span class="dashicons dashicons-yes-alt"></span>
														<?php esc_html_e('Test', 'notificator-companion'); ?>
													</button>
													<button type="button" class="btn-secondary btn-secondary--danger notificator-remove-api-key" aria-label="<?php echo esc_attr(wp_json_encode(__('Remove API key', 'notificator-companion'))); ?>" <?php echo $hide_remove ? 'hidden' : ''; ?> data-notificator-unlock="remove-api-key">
														<span class="dashicons dashicons-trash"></span>
														<?php esc_html_e('Remove', 'notificator-companion'); ?>
													</button>
											</div>
										<?php endforeach; ?>
									</div>
										<input type="hidden" id="notificator_companion_test_nonce" value="<?php echo esc_attr(wp_create_nonce('notificator_companion_test')); ?>">
									<div class="notificator-api-actions mt-2">
										<div class="notificator-api-actions-left">
											<button type="button" id="notificator-add-api-key" class="btn-secondary" data-notificator-unlock="add-api-key" <?php echo $has_api_key ? '' : 'hidden'; ?>>
												<span class="dashicons dashicons-plus-alt2"></span>
												<?php esc_html_e('Add another key', 'notificator-companion'); ?>
											</button>
											<button type="submit" id="notificator-save-api-keys" class="btn-primary btn-primary--compact">
												<span class="dashicons dashicons-yes-alt"></span>
												<?php esc_html_e('Save API Keys', 'notificator-companion'); ?>
											</button>
										</div>
										<p class="text-xs text-gray-500">
											<?php esc_html_e('Add multiple keys to send notifications to multiple devices/users.', 'notificator-companion'); ?>
										</p>
									</div>
								</div>

								<div class="notice notice-warning inline notice-inline-warning" data-notificator-lock="api-warning" <?php echo $has_api_key ? 'hidden style="display:none;"' : ''; ?>>
									<p>
										<?php esc_html_e('Add your API key and click Save to unlock scanning, scenarios, and test notifications.', 'notificator-companion'); ?>
									</p>
								</div>
								<!-- <div class="border-t border-gray-200 pt-3" data-notificator-unlock="test-connection" <?php echo $has_api_key ? '' : 'hidden'; ?>>
									<p class="text-xs text-gray-500">
										<?php esc_html_e('Use the Test button next to a key to test it.', 'notificator-companion'); ?>
									</p>
								</div> -->
							</div>
						</div>


						<div data-notificator-locked-sections <?php echo $has_api_key ? 'hidden' : ''; ?>>
							<div class="scenario-section notificator-section opacity-60 mt-6" id="notificator-templates" data-notificator-section="templates">
								<div class="notificator-scenario-head notificator-scenario-head--locked">
									<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
										<div class="flex items-center gap-3 min-w-0">
											<div class="notificator-section-icon">
												<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('templates') ); ?> text-white"></span>
											</div>
											<div class="min-w-0">
												<h3 class="text-base font-semibold text-white"><?php esc_html_e('Active Plugin Templates', 'notificator-companion'); ?></h3>
												<p class="text-xs text-white text-opacity-70"><?php esc_html_e('Add an API key to unlock template scanning.', 'notificator-companion'); ?></p>
											</div>
										</div>
									</div>
								</div>
								<div class="card-body">
									<div class="notice notice-warning inline notice-inline-warning" data-notificator-lock="templates-warning">
										<p><?php esc_html_e('Templates are locked until you add an API key and save settings.', 'notificator-companion'); ?></p>
									</div>
								</div>
							</div>
							<div class="scenario-section notificator-section opacity-60 mt-6" id="notificator-builder" data-notificator-section="builder">
								<div class="notificator-scenario-head notificator-scenario-head--locked">
									<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
										<div class="flex items-center gap-3 min-w-0">
											<div class="notificator-section-icon">
												<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('builder') ); ?> text-white"></span>
											</div>
											<div class="min-w-0">
												<h3 class="text-base font-semibold text-white"><?php esc_html_e('Notifications List', 'notificator-companion'); ?></h3>
												<p class="text-xs text-white text-opacity-70"><?php esc_html_e('Add an API key to unlock notification creation.', 'notificator-companion'); ?></p>
											</div>
										</div>
									</div>
								</div>
								<div class="card-body">
									<div class="notice notice-warning inline notice-inline-warning" data-notificator-lock="builder-warning">
										<p><?php esc_html_e('The scenario builder is locked until you add an API key and save settings.', 'notificator-companion'); ?></p>
									</div>
								</div>
							</div>
						</div>
						<div data-notificator-unlocked-sections <?php echo $has_api_key ? '' : 'hidden'; ?>>
							<?php $this->render_hooks_field(); ?>
						</div>

						<div data-notificator-log-wrapper <?php echo $has_api_key ? '' : 'hidden'; ?>>
							<?php $this->render_log_section(); ?>
						</div>
						<?php $this->render_help_section(); ?>

					</div>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Resolve section icon class for sidebar and section headers.
	 *
	 * @param string $section Section key.
	 * @return string
	 */
	private function get_section_icon_class( $section ) {
		$icons = array(
			'api'            => 'dashicons-admin-network',
			'api_locked'     => 'dashicons-lock',
			'templates'      => 'dashicons-media-code',
			'templates_locked' => 'dashicons-lock',
			'builder'        => 'dashicons-bell',
			'builder_locked' => 'dashicons-lock',
			'log'            => 'dashicons-list-view',
			'help'           => 'dashicons-sos',
		);

		return isset( $icons[ $section ] ) ? $icons[ $section ] : 'dashicons-admin-generic';
	}

	/**
	 * Render scan controls (button + toggle).
	 */
	private function render_scan_controls($disabled = false, $on_dark = false)
	{
	?>
		<div class="notificator-scan-controls">
			<button type="button" id="scan-plugins-btn" class="btn-secondary notificator-scan-btn" <?php echo $disabled ? 'disabled' : ''; ?>>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e('Scan Plugins', 'notificator-companion'); ?>
			</button>
			<!-- <label class="notificator-scan-checkbox">
				<input type="checkbox" id="notificator_companion_include_inactive_plugins" value="1" <?php echo $disabled ? 'disabled' : ''; ?>>
				<span class="text-xs <?php echo $on_dark ? 'text-white text-opacity-90' : 'text-gray-600'; ?>"><?php esc_html_e('Include inactive', 'notificator-companion'); ?></span>
			</label> -->
		</div>
	<?php
	}

	/**
	 * Render scan modal markup.
	 *
	 * This should be rendered outside of sticky containers to avoid stacking/overflow issues.
	 */
	private function render_scan_modal()
	{
		$nonce = wp_create_nonce('notificator_companion_refresh_hooks');
	?>
		<input type="hidden" id="notificator_companion_scan_nonce" value="<?php echo esc_attr($nonce); ?>">

		<!-- Scan Modal -->
		<div id="scan-modal" class="hidden fixed inset-0 z-50 overflow-y-auto scan-modal-backdrop">
			<div class="flex items-center justify-center min-h-screen px-4">
				<div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
					<div id="scan-progress">
						<div class="text-center">
							<div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
								<div class="loading-spinner"></div>
							</div>
							<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e('Scanning Plugins...', 'notificator-companion'); ?></h3>
							<p class="text-sm text-gray-600"><?php esc_html_e('This may take a moment', 'notificator-companion'); ?></p>
						</div>
					</div>

					<div id="scan-results" class="hidden">
						<div id="scan-success-message" class="hidden text-center">
							<div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
								<svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
								</svg>
							</div>
							<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e('Scan Complete!', 'notificator-companion'); ?></h3>
							<p class="text-sm text-gray-600">
								<?php esc_html_e('Found', 'notificator-companion'); ?> <span id="total-hooks" class="font-semibold">0</span> <?php esc_html_e('hooks from', 'notificator-companion'); ?> <span id="total-plugins" class="font-semibold">0</span> <?php esc_html_e('plugins', 'notificator-companion'); ?>
							</p>
						</div>

						<div id="scan-error-message" class="hidden text-center text-red-600">
							<div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
								<svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
								</svg>
							</div>
							<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e('Scan Failed', 'notificator-companion'); ?></h3>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render import scenarios modal markup.
	 *
	 * Rendered outside sticky containers to avoid stacking/overflow issues.
	 */
	private function render_import_hooks_modal()
	{
	?>
		<!-- Import Scenarios Modal -->
		<div id="notificator-import-modal" class="hidden fixed inset-0 z-50 overflow-y-auto scan-modal-backdrop">
			<div class="flex items-center justify-center min-h-screen px-4">
				<div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
					<div class="flex items-start justify-between gap-4">
						<div class="min-w-0">
							<h3 class="text-lg font-semibold text-gray-900 mb-1"><?php esc_html_e('Import Scenarios', 'notificator-companion'); ?></h3>
							<p class="text-sm text-gray-600"><?php esc_html_e('Upload a JSON export from another site to migrate your scenario builder rules. API keys are not imported.', 'notificator-companion'); ?></p>
						</div>
						<button type="button" id="notificator-import-modal-close" class="btn-secondary" aria-label="<?php echo esc_attr(__('Close', 'notificator-companion')); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>

					<div class="mt-4 space-y-4">
						<div>
							<label class="block text-sm font-semibold text-gray-700 mb-2" for="notificator-import-file"><?php esc_html_e('Scenario JSON file', 'notificator-companion'); ?></label>
							<input type="file" id="notificator-import-file" accept="application/json,.json" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
							<p class="text-xs text-gray-500 mt-2" id="notificator-import-file-hint"><?php esc_html_e('Choose a file exported with “Export Scenarios”.', 'notificator-companion'); ?></p>
						</div>

						<div class="border border-gray-200 rounded-xl p-4">
							<div class="text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Import mode', 'notificator-companion'); ?></div>
							<label class="flex items-center gap-2 mb-2">
								<input type="radio" name="notificator-import-mode" value="merge" checked />
								<span class="text-sm text-gray-700"><?php esc_html_e('Merge (recommended): keep existing scenarios and append imported ones.', 'notificator-companion'); ?></span>
							</label>
							<label class="flex items-center gap-2">
								<input type="radio" name="notificator-import-mode" value="replace" />
								<span class="text-sm text-gray-700"><?php esc_html_e('Replace: delete all existing scenarios and use the imported file.', 'notificator-companion'); ?></span>
							</label>
							<div class="mt-3 hidden" id="notificator-import-replace-warning">
								<div class="notice notice-warning inline notice-inline-warning">
									<p><?php esc_html_e('Replace will remove all existing scenarios on this site.', 'notificator-companion'); ?></p>
								</div>
								<label class="flex items-start gap-2 mt-3 cursor-pointer">
									<input type="checkbox" id="notificator-import-confirm-replace" value="1" class="mt-0.5" />
									<span class="text-sm text-gray-700"><?php esc_html_e('I understand, replace my scenarios.', 'notificator-companion'); ?></span>
								</label>
							</div>
						</div>

						<div id="notificator-import-status" class="text-sm text-gray-700" aria-live="polite" hidden></div>

						<div class="flex items-center justify-end gap-2">
							<button type="button" id="notificator-import-cancel" class="btn-secondary"><?php esc_html_e('Cancel', 'notificator-companion'); ?></button>
							<button type="button" id="notificator-import-confirm" class="btn-primary btn-primary--compact">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e('Import', 'notificator-companion'); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render hooks field with Alpine.js-powered scenario builder
	 */
	public function render_hooks_field()
	{
		$options              = get_option($this->option_name);
		if (! is_array($options)) {
			$options = array();
		}
		$hooks                = isset($options['hooks']) ? $options['hooks'] : array();
		$available_plugins    = $this->plugin->get_available_plugins_with_hooks();
		$last_scan            = get_option('notificator_companion_last_scan', 0);
		$show_first_time_setup = empty($last_scan) && 1 === count($available_plugins);

		// Build active status for plugins.
		$plugin_active_status = array();
		foreach ($available_plugins as $key => $plugin) {
			$plugin_active_status[$key] = $this->plugin->is_plugin_active_check($plugin['file']);
		}

		// Build active status for hooks.
		$hook_active_status = array();
		foreach ($hooks as $index => $hook) {
			$hook_active_status[$index] = $this->plugin->is_hook_active($hook['hook_name']);
		}

		// Prepare JSON data for Alpine with proper flags.
		$hooks_json              = wp_json_encode(array_values($hooks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		$available_plugins_json  = wp_json_encode($available_plugins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		$plugin_active_json      = wp_json_encode($plugin_active_status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		$hook_active_json        = wp_json_encode($hook_active_status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

	?>
		<div x-data="window.initScenarioBuilder(
			<?php echo esc_attr($hooks_json); ?>,
			<?php echo esc_attr($available_plugins_json); ?>,
			<?php echo esc_attr($plugin_active_json); ?>,
			<?php echo esc_attr($hook_active_json); ?>,
			<?php echo esc_attr(wp_json_encode($this->option_name)); ?>
		)" @notificator:add-scenario.window="openAddModal()" class="space-y-5 mt-6">

			<?php if ( $show_first_time_setup ) : ?>
				<?php $this->render_first_time_setup(); ?>
			<?php endif; ?>

			<!-- Hidden inputs for form submission -->
			<div class="hidden">
				<template x-for="(hook, index) in hooks" :key="'hidden-' + index">
					<div>
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][hook_name]'" :value="hook.hook_name">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][description]'" :value="hook.description">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][enabled]'" :value="hook.enabled ? '1' : '0'">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][scenario_name]'" :value="hook.scenario_name || ''">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][scenario_notes]'" :value="hook.scenario_notes || ''">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][plugin_key]'" :value="hook.plugin_key || ''">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][plugin_name]'" :value="hook.plugin_name || ''">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][severity]'" :value="hook.severity || 'info'">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][send_push]'" :value="hook.send_push ? '1' : '0'">
						<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][send_mqtt]'" :value="hook.send_mqtt ? '1' : '0'">
						<!-- Hook metadata (for conditions support) -->
						<template x-if="hook.hook_meta">
							<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][hook_meta]'" :value="JSON.stringify(hook.hook_meta)">
						</template>
						<!-- Conditions hidden inputs -->
						<template x-if="hook.conditions && hook.conditions.length">
							<template x-for="(condition, cIndex) in hook.conditions" :key="'hidden-cond-' + index + '-' + cIndex">
								<div>
									<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][conditions][' + cIndex + '][field]'" :value="condition.field">
									<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][conditions][' + cIndex + '][operator]'" :value="condition.operator">
									<input type="hidden" :name="'<?php echo esc_attr($this->option_name); ?>[hooks][' + index + '][conditions][' + cIndex + '][value]'" :value="condition.value">
								</div>
							</template>
						</template>
					</div>
				</template>
			</div>

			<!-- Scenarios Wrapper - Full Width Container -->
			<div class="scenarios-wrapper">
				<div class="scenarios-container">

					<!-- Templates -->
					<div class="scenario-section notificator-section mt-6" id="notificator-templates" data-notificator-section="templates">
						<div class="notificator-scenario-head notificator-scenario-head--templates">
							<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
								<div class="flex items-center gap-3 min-w-0">
									<div class="notificator-section-icon">
										<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('templates') ); ?> text-white"></span>
									</div>
									<div class="min-w-0">
										<h3 class="text-base font-semibold text-white"><?php esc_html_e('Active Plugin Templates', 'notificator-companion'); ?></h3>
										<p class="text-xs text-white text-opacity-70" x-text="getFilteredTemplates().length + ' template' + (getFilteredTemplates().length === 1 ? '' : 's') + ' available from active plugins'"></p>
									</div>
								</div>

								<div class="flex items-center gap-3 flex-wrap justify-end notificator-templates-controls">
									<?php $this->render_scan_controls( false, true ); ?>
									<div>
										<label for="notificator-template-plugin-filter" class="screen-reader-text"><?php esc_html_e('Filter templates by active plugin', 'notificator-companion'); ?></label>
										<select
											id="notificator-template-plugin-filter"
											x-model="templatePluginFilter"
											@change="onTemplatePluginFilterChange()"
											class="notificator-section-control notificator-section-control--select notificator-templates-filter-select">
											<template x-for="plugin in getTemplatePluginFilterOptions()" :key="'template-plugin-filter-' + plugin.value">
												<option :value="plugin.value" x-text="plugin.label"></option>
											</template>
										</select>
									</div>
									<div>
										<label for="notificator-template-per-page" class="screen-reader-text"><?php esc_html_e('Templates shown per page', 'notificator-companion'); ?></label>
										<select
											id="notificator-template-per-page"
											x-model="templatesPerPage"
											@change="onTemplatesPerPageChange()"
											class="notificator-section-control notificator-section-control--select notificator-templates-filter-select">
											<template x-for="count in getTemplatesPerPageOptions()" :key="'templates-per-page-' + count">
												<option :value="count" x-text="'Show ' + count"></option>
											</template>
										</select>
									</div>
									<div class="relative notificator-templates-search notificator-search notificator-search--on-dark">
										<input type="text"
											x-model="templateSearchQuery"
											@input="onTemplateSearchChange()"
											placeholder="<?php esc_attr_e('Search templates...', 'notificator-companion'); ?>"
											class="notificator-section-control notificator-section-control--search notificator-templates-search-input">
										<svg class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 notificator-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
										</svg>
									</div>
								</div>
							</div>
						</div>

						<div class="card-body">
							<template x-if="getFilteredTemplates().length === 0">
								<div class="text-center py-12">
									<div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
										<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
										</svg>
									</div>
									<p class="text-gray-900 font-medium mb-1"><?php esc_html_e('No templates found', 'notificator-companion'); ?></p>
									<p class="text-xs text-gray-500"><?php esc_html_e('Try a different search term or plugin filter', 'notificator-companion'); ?></p>
								</div>
							</template>

							<template x-if="getFilteredTemplates().length > 0">
								<div>
											<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
										<template x-for="template in getPaginatedTemplates()" :key="'template-' + template.hook_name + '-' + template.title">
													<button @click="useTemplate(template)" type="button" class="notificator-template-card cursor-pointer text-left p-4 rounded-xl bg-white border-2 border-gray-200 hover:border-indigo-400 transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40">
														<div class="flex items-start gap-3 mb-3">
															<div class="w-12 h-12 rounded-lg bg-linear-to-br from-indigo-50 to-purple-50 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
																<template x-if="template.plugin_icon_url">
																	<img :src="template.plugin_icon_url" alt="" class="w-8 h-8 object-contain rounded" @error="template.plugin_icon_url = ''" />
																</template>
																<template x-if="!template.plugin_icon_url && template.icon_class">
																	<span class="dashicons text-2xl" :class="template.icon_class"></span>
																</template>
																<template x-if="!template.plugin_icon_url && !template.icon_class">
																	<span class="text-2xl" x-text="template.icon"></span>
																</template>
															</div>
															<div class="flex-1 min-w-0">
																<div class="text-sm font-semibold text-gray-900 mb-1 line-clamp-2" x-text="template.title"></div>
																<div class="flex items-center gap-1.5">
																	<code class="text-xs text-indigo-600 font-mono bg-indigo-50 px-2 py-1 rounded truncate block" x-text="template.hook_name"></code>
																</div>
																<div class="mt-1">
																	<span class="inline-flex items-center text-[11px] font-medium text-slate-600 bg-slate-100 border border-slate-200 rounded-full px-2 py-0.5" x-text="template.plugin_name || template.required_plugin"></span>
																</div>
															</div>
														</div>
												<template x-if="template.conditions && template.conditions.length > 0">
															<div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-gray-100">
																<svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
															<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
														</svg>
																<span class="text-xs text-purple-600 font-semibold" x-text="template.conditions.length + ' condition' + (template.conditions.length === 1 ? '' : 's')"></span>
													</div>
												</template>
											</button>
										</template>
									</div>

									<div class="flex items-center justify-between pt-3 border-t border-gray-200">
										<div class="text-xs text-gray-500">
											<?php esc_html_e('Page', 'notificator-companion'); ?>
											<span x-text="templatePage"></span>
											<?php esc_html_e('of', 'notificator-companion'); ?>
											<span x-text="getTemplateTotalPages()"></span>
											<span class="ml-2">(<span x-text="getFilteredTemplates().length"></span> <?php esc_html_e('total', 'notificator-companion'); ?>)</span>
										</div>

										<div class="flex items-center gap-2">
											<button @click="prevTemplatePage()" type="button" :disabled="templatePage === 1" class="btn-secondary btn-secondary--compact">
												<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
												</svg>
												<?php esc_html_e('Previous', 'notificator-companion'); ?>
											</button>
											<button @click="nextTemplatePage()" type="button" :disabled="templatePage >= getTemplateTotalPages()" class="btn-secondary btn-secondary--compact">
												<?php esc_html_e('Next', 'notificator-companion'); ?>
												<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
												</svg>
											</button>
										</div>
									</div>
								</div>
							</template>
						</div>
					</div>

					<!-- Builder -->
					<div class="scenario-section notificator-section mt-6" id="notificator-builder" data-notificator-section="builder">
						<div class="notificator-scenario-head notificator-scenario-head--builder">
							<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
								<div class="flex items-center gap-3 min-w-0">
									<div class="notificator-section-icon">
										<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('builder') ); ?> text-white"></span>
									</div>
									<div class="min-w-0">
										<h3 class="text-base font-semibold text-white"><?php esc_html_e('Notifications List', 'notificator-companion'); ?></h3>
										<p class="text-xs text-white text-opacity-70" x-text="hooks.length + ' notification' + (hooks.length === 1 ? '' : 's')"></p>
									</div>
								</div>

								<div class="flex items-center gap-3 flex-wrap justify-end">
									<button @click="openAddModal()" type="button" class="btn-secondary">
										<span class="dashicons dashicons-plus-alt2"></span>
										<?php esc_html_e('Add Scenario', 'notificator-companion'); ?>
									</button>
								</div>
							</div>
						</div>

						<div class="card-body">
							<template x-if="hooks.length === 0">
								<div class="empty-state">
									<div class="empty-state-icon">
										<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
										</svg>
									</div>
									<p class="empty-state-title"><?php esc_html_e('No notifications yet', 'notificator-companion'); ?></p>
									<p class="empty-state-description mb-4"><?php esc_html_e('Use “Add Scenario” to create your first notification.', 'notificator-companion'); ?></p>
									<button @click="openAddModal()" type="button" class="btn-primary"><?php esc_html_e('Create Your First Notification', 'notificator-companion'); ?></button>
								</div>
							</template>

							<template x-if="hooks.length > 0">
								<div class="space-y-2">
									<template x-for="(hook, index) in hooks" :key="'scenario-row-' + index">
										<div class="group flex items-center justify-between p-3 rounded-xl bg-white border border-purple-100 hover:bg-indigo-50/30 hover:border-purple-300 hover:shadow-sm transition-all" :class="isScenarioPluginInactive(hook) ? 'notificator-scenario--plugin-inactive' : ''">
											<div class="flex-1 min-w-0">
												<div class="flex items-center gap-2 mb-1">
													<span class="text-sm font-semibold text-gray-900 truncate" x-text="hook.scenario_name || hook.hook_name"></span>
													<code class="text-xs font-mono bg-purple-100 text-purple-700 px-2 py-0.5 rounded" x-text="hook.hook_name"></code>
													<span class="badge text-xs" :class="hook.enabled ? 'badge-success' : 'badge-warning'">
														<span class="w-1.5 h-1.5 rounded-full mr-1" :class="hook.enabled ? 'bg-green-500' : 'bg-gray-400'"></span>
														<span x-text="hook.enabled ? <?php echo esc_attr(wp_json_encode(__('Active', 'notificator-companion'))); ?> : <?php echo esc_attr(wp_json_encode(__('Paused', 'notificator-companion'))); ?>"></span>
													</span>
													<span class="badge text-xs" :class="(hook.severity || 'info') === 'critical' ? 'badge-danger' : ((hook.severity || 'info') === 'warning' ? 'badge-warning' : 'badge-info')">
														<span x-text="(hook.severity || 'info').charAt(0).toUpperCase() + (hook.severity || 'info').slice(1)"></span>
													</span>
													<template x-if="getScenarioPluginStatus(hook) === 'inactive'">
														<span class="badge text-xs badge-warning" :title="getScenarioPluginName(hook) ? (<?php echo esc_attr(wp_json_encode(__('Plugin:', 'notificator-companion'))); ?> + ' ' + getScenarioPluginName(hook)) : ''">
															<span x-text="getScenarioPluginBadgeLabel(hook)"></span>
														</span>
													</template>
													<template x-if="getScenarioPluginStatus(hook) === 'missing'">
														<span class="badge text-xs badge-warning" :title="getScenarioPluginName(hook) ? (<?php echo esc_attr(wp_json_encode(__('Plugin:', 'notificator-companion'))); ?> + ' ' + getScenarioPluginName(hook)) : ''">
															<span x-text="getScenarioPluginBadgeLabel(hook)"></span>
														</span>
													</template>
												</div>
												<div class="flex items-center gap-2 text-xs text-gray-500">
													<span x-text="hook.description"></span>
													<template x-if="hook.scenario_notes"><span class="text-purple-600">• <span x-text="hook.scenario_notes"></span></span></template>
												</div>
											</div>
											<div class="ml-3 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
												<button @click="openEditModal(index)" type="button" class="cursor-pointer inline-flex items-center justify-center h-9 w-9 bg-slate-50 border border-slate-200/70 text-slate-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40" :title="<?php echo esc_attr(wp_json_encode(__('Edit scenario', 'notificator-companion'))); ?>">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
													</svg>
												</button>
												<button @click="removeHook(index)" type="button" class="cursor-pointer inline-flex items-center justify-center h-9 w-9 bg-slate-50 border border-slate-200/70 text-slate-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40" :title="<?php echo esc_attr(wp_json_encode(__('Delete scenario', 'notificator-companion'))); ?>">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
													</svg>
												</button>
											</div>
										</div>
									</template>
								</div>
							</template>
						</div>
					</div>

				</div>
			</div>

			<?php $this->render_scenario_modal(); ?>
		</div>
	<?php
	}

	/**
	 * Render notification log section.
	 */
	private function render_log_section() {
		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$log_per_page = isset( $options['log_per_page'] ) ? (int) $options['log_per_page'] : 20;
		if ( $log_per_page < 5 ) {
			$log_per_page = 5;
		} elseif ( $log_per_page > 200 ) {
			$log_per_page = 200;
		}

		$log_enabled = ! isset( $options['log_enabled'] ) || (bool) $options['log_enabled'];
		$log = get_option( 'notificator_companion_notification_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$updated_ids = false;
		foreach ( $log as $index => $entry ) {
			if ( is_array( $entry ) && empty( $entry['id'] ) ) {
				$log[ $index ]['id'] = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'log_', true );
				$updated_ids = true;
			}
		}
		if ( $updated_ids ) {
			update_option( 'notificator_companion_notification_log', $log, false );
		}
		$log = array_values( $log );
		$log = array_reverse( $log );
		$log = array_slice( $log, 0, 200 );

		$date_format = get_option( 'date_format', 'Y-m-d' );
		$time_format = get_option( 'time_format', 'H:i' );
		$api_suffix_to_nickname = array();
		$settings = get_option( $this->option_name );
		if ( is_array( $settings ) && isset( $settings['api_keys'] ) && is_array( $settings['api_keys'] ) ) {
			$settings_api_keys = array_values( $settings['api_keys'] );
			$settings_api_nicknames = isset( $settings['api_key_nicknames'] ) && is_array( $settings['api_key_nicknames'] )
				? array_values( $settings['api_key_nicknames'] )
				: array();

			foreach ( $settings_api_keys as $index => $raw_key ) {
				$key = is_string( $raw_key ) ? trim( $raw_key ) : '';
				if ( '' === $key ) {
					continue;
				}
				$suffix = substr( $key, -6 );
				if ( '' === $suffix ) {
					continue;
				}

				$nickname = '';
				if ( isset( $settings_api_nicknames[ $index ] ) && is_string( $settings_api_nicknames[ $index ] ) ) {
					$nickname = trim( $settings_api_nicknames[ $index ] );
				}

				if ( '' !== $nickname && ! isset( $api_suffix_to_nickname[ $suffix ] ) ) {
					$api_suffix_to_nickname[ $suffix ] = $nickname;
				}
			}
		}
	?>
		<div class="scenario-section notificator-section mt-6" id="notificator-log" data-notificator-section="log">
			<div class="notificator-scenario-head notificator-scenario-head--help">
				<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
					<div class="flex items-center gap-3 min-w-0">
						<div class="notificator-section-icon">
							<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('log') ); ?> text-white"></span>
						</div>
						<div class="min-w-0">
							<h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Log', 'notificator-companion' ); ?></h3>
							<p class="text-xs text-white text-opacity-70"><?php esc_html_e( 'Recent triggered notifications.', 'notificator-companion' ); ?></p>
						</div>
					</div>
					<div class="notificator-log-header-controls">
						<div class="notificator-log-per-page">
							<label for="notificator-log-per-page" class="screen-reader-text"><?php esc_html_e( 'Log entries shown per page', 'notificator-companion' ); ?></label>
							<select id="notificator-log-per-page" name="<?php echo esc_attr( $this->option_name ); ?>[log_per_page]" class="notificator-section-control notificator-section-control--select">
								<?php foreach ( array( 10, 20, 50, 100, 200 ) as $count ) : ?>
									<?php /* translators: %d: Number of log entries shown per page. */ ?>
									<option value="<?php echo esc_attr( $count ); ?>" <?php selected( $log_per_page, $count ); ?>><?php echo esc_html( sprintf( __( 'Show %d', 'notificator-companion' ), $count ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="relative notificator-search notificator-search--on-dark notificator-log-search-header">
							<label class="sr-only" for="notificator-log-search"><?php esc_html_e( 'Search log', 'notificator-companion' ); ?></label>
							<input type="text" id="notificator-log-search" placeholder="<?php esc_attr_e( 'Search log…', 'notificator-companion' ); ?>" class="notificator-section-control notificator-section-control--search notificator-templates-search-input" />
							<svg class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 notificator-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
							</svg>
						</div>
						<?php if ( $log_enabled && ! empty( $log ) ) : ?>
							<div class="notificator-log-meta">
								<button type="button" id="notificator-clear-log" class="btn-secondary btn-secondary--danger btn-secondary--compact">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Clear Log', 'notificator-companion' ); ?>
								</button>
								<div class="notificator-log-pagination">
									<button type="button" id="notificator-log-prev" class="btn-secondary btn-secondary--compact" disabled>
										<span class="dashicons dashicons-arrow-left-alt2"></span>
										<?php esc_html_e( 'Prev', 'notificator-companion' ); ?>
									</button>
									<span class="text-xs text-gray-500" id="notificator-log-page">1 / 1</span>
									<button type="button" id="notificator-log-next" class="btn-secondary btn-secondary--compact" disabled>
										<?php esc_html_e( 'Next', 'notificator-companion' ); ?>
										<span class="dashicons dashicons-arrow-right-alt2"></span>
									</button>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="card-body">
				<?php if ( ! $log_enabled ) : ?>
					<div class="notice notice-warning inline notice-inline-warning">
						<p><?php esc_html_e( 'Log is disabled. Enable it from Tools to start tracking notifications.', 'notificator-companion' ); ?></p>
					</div>
				<?php elseif ( empty( $log ) ) : ?>
					<p class="text-sm text-gray-600"><?php esc_html_e( 'No notifications have been triggered yet.', 'notificator-companion' ); ?></p>
				<?php else : ?>
					<div class="overflow-x-auto">
						<table class="widefat striped notificator-log-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Time', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'Title', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'Hook', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'Scenario', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'API', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'Status', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'Severity', 'notificator-companion' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'notificator-companion' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $log as $entry ) : ?>
									<?php
										$timestamp = isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '';
										$time_display = '';
										if ( $timestamp ) {
											$time_display = date_i18n( $date_format . ' ' . $time_format, strtotime( $timestamp ) );
										}
										$severity = isset( $entry['severity'] ) ? (string) $entry['severity'] : 'info';
										$status = isset( $entry['status'] ) ? (string) $entry['status'] : '';
										$sent = isset( $entry['sent'] ) ? (bool) $entry['sent'] : true;
										if ( '' === $status ) {
											$status = $sent ? 'sent' : 'not_sent';
										}
										$status_label = $sent ? __( 'Sent', 'notificator-companion' ) : __( 'Not sent', 'notificator-companion' );
										if ( 'throttled' === $status ) {
											$status_label = __( 'Throttled', 'notificator-companion' );
										}
										$status_badge = $sent ? 'badge-success' : 'badge-warning';
										if ( 'throttled' === $status ) {
											$status_badge = 'badge-warning';
										}
										$badge_class = 'badge-info';
										if ( 'critical' === $severity ) {
											$badge_class = 'badge-danger';
										} elseif ( 'warning' === $severity ) {
											$badge_class = 'badge-warning';
										}
										$api_display_values = array();
										$api_nicknames = isset( $entry['api_nicknames'] ) && is_array( $entry['api_nicknames'] ) ? $entry['api_nicknames'] : array();
										$api_nicknames = array_filter( array_map( 'strval', $api_nicknames ), 'strlen' );

										if ( ! empty( $api_nicknames ) ) {
											$api_display_values = array_values( array_unique( $api_nicknames ) );
										} else {
											$api_keys = isset( $entry['api_keys'] ) && is_array( $entry['api_keys'] ) ? $entry['api_keys'] : array();
											$api_keys = array_filter( array_map( 'strval', $api_keys ), 'strlen' );
											if ( ! empty( $api_keys ) ) {
												$api_keys = array_map(
													static function( $suffix ) {
														return trim( (string) $suffix );
													},
													$api_keys
												);
												$api_display_values = array();
												foreach ( $api_keys as $suffix ) {
													if ( isset( $api_suffix_to_nickname[ $suffix ] ) && '' !== $api_suffix_to_nickname[ $suffix ] ) {
														$api_display_values[] = $api_suffix_to_nickname[ $suffix ];
													} else {
														$api_display_values[] = '…' . $suffix;
													}
												}
												$api_display_values = array_values( array_unique( $api_display_values ) );
											}
										}

										$api_display = implode( ', ', $api_display_values );
									?>
									<?php $entry_id = isset( $entry['id'] ) ? (string) $entry['id'] : ''; ?>
									<tr class="notificator-log-row" data-log-id="<?php echo esc_attr( $entry_id ); ?>">
										<td><?php echo esc_html( $time_display ); ?></td>
										<td><?php echo esc_html( isset( $entry['title'] ) ? (string) $entry['title'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $entry['hook_name'] ) ? (string) $entry['hook_name'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $entry['scenario_name'] ) ? (string) $entry['scenario_name'] : '' ); ?></td>
										<td><?php echo esc_html( $api_display ); ?></td>
										<td><span class="badge <?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
										<td><span class="badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $severity ); ?></span></td>
										<td>
											<button type="button" class="btn-icon btn-icon--danger notificator-log-delete" data-log-id="<?php echo esc_attr( $entry_id ); ?>" aria-label="<?php echo esc_attr__( 'Delete log entry', 'notificator-companion' ); ?>">
												<span class="dashicons dashicons-trash"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p id="notificator-log-empty" class="text-sm text-gray-600 mt-3" hidden><?php esc_html_e( 'No log entries match your search.', 'notificator-companion' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Render a help section shown below the notifications list.
	 */
	private function render_help_section()
	{
	?>
		<div class="scenario-section notificator-section mt-6" id="notificator-help" data-notificator-section="help">
			<div class="notificator-scenario-head notificator-scenario-head--help">
				<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
					<div class="flex items-center gap-3 min-w-0">
						<div class="notificator-section-icon">
							<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class('help') ); ?> text-white"></span>
						</div>
						<div class="min-w-0">
							<h3 class="text-base font-semibold text-white"><?php esc_html_e('Help', 'notificator-companion'); ?></h3>
							<p class="text-xs text-white text-opacity-70"><?php esc_html_e('Documentation and setup guides.', 'notificator-companion'); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="card-body">
				<div class="space-y-4">
					<p class="text-sm text-slate-800">
						<?php esc_html_e( 'Need help? We have you covered. Pick a path below to get the right setup guides and documentation for your workflow.', 'notificator-companion' ); ?>
					</p>

					<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 notificator-help-links-grid">
						<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 notificator-help-link-card">
							<p class="text-sm font-semibold text-slate-900 mb-2"><?php esc_html_e( 'I am a non-technical user', 'notificator-companion' ); ?></p>
							<div class="flex flex-col gap-2">
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/workflow-overview/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Complete Workflow', 'notificator-companion' ); ?></a>
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/account-creation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create Account', 'notificator-companion' ); ?></a>
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/mobile-api-key-creation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create API Key (Mobile)', 'notificator-companion' ); ?></a>
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/wordpress-plugin-setup/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WordPress Plugin Setup', 'notificator-companion' ); ?></a>
							</div>
						</div>

						<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 notificator-help-link-card">
							<p class="text-sm font-semibold text-slate-900 mb-2"><?php esc_html_e( 'I am a developer', 'notificator-companion' ); ?></p>
							<div class="flex flex-col gap-2">
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/quick-start/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Quick Start', 'notificator-companion' ); ?></a>
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/code-samples/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Code Samples', 'notificator-companion' ); ?></a>
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/reference/public-notify/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Public Notify API', 'notificator-companion' ); ?></a>
								<a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/copy-paste-snippets/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Copy-Paste Snippets', 'notificator-companion' ); ?></a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render the scenario modal
	 */
	private function render_scenario_modal()
	{
	?>
		<!-- Add/Edit Scenario Modal -->
		<div x-show="modalOpen"
			x-cloak
			class="fixed inset-0 z-50 overflow-y-auto modal-backdrop">
			<div class="flex items-center justify-center min-h-screen px-4">
				<!-- Backdrop -->
				<div @click="modalOpen = false"
					x-show="modalOpen"
					x-transition:enter="ease-out duration-300"
					x-transition:enter-start="opacity-0"
					x-transition:enter-end="opacity-100"
					x-transition:leave="ease-in duration-200"
					x-transition:leave-start="opacity-100"
					x-transition:leave-end="opacity-0"
					class="fixed inset-0"></div>

				<!-- Modal Content -->
				<div x-show="modalOpen"
					x-transition:enter="ease-out duration-300"
					x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
					x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
					x-transition:leave="ease-in duration-200"
					x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
					x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
					@keydown.escape.window="modalOpen = false"
					class="relative flex flex-col bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[95vh] overflow-hidden">

					<!-- Modal Header -->
					<div class="notificator-modal-head notificator-scenario-head--builder">
						<div class="flex items-center justify-between">
							<div>
								<h3 class="text-lg font-semibold text-white" x-text="editingIndex !== null ? <?php echo esc_attr(wp_json_encode(__('Edit Scenario', 'notificator-companion'))); ?> : <?php echo esc_attr(wp_json_encode(__('Add New Scenario', 'notificator-companion'))); ?>"></h3>
								<p class="text-xs text-white text-opacity-70 mt-0.5">
									<span x-show="modalStep === 1"><?php esc_html_e('Step 1: Select Plugin', 'notificator-companion'); ?></span>
									<span x-show="modalStep === 2"><?php esc_html_e('Step 2: Choose Hook', 'notificator-companion'); ?></span>
									<span x-show="modalStep === 3 && editingIndex === null"><?php esc_html_e('Step 3: Configure Scenario', 'notificator-companion'); ?></span>
								</p>
							</div>
							<button @click="modalOpen = false" type="button"
								class="cursor-pointer inline-flex items-center justify-center rounded-full p-2 bg-white/10 text-white/90 hover:text-white hover:bg-white/20 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
								</svg>
							</button>
						</div>
					</div>

					<!-- Step Indicator -->
					<template x-if="editingIndex === null">
						<div class="notificator-modal-step-indicator flex items-center justify-center gap-2 px-6 py-3 bg-gray-50 border-b">
							<div class="flex items-center gap-2">
								<div :class="modalStep >= 1 ? 'bg-indigo-500' : 'bg-gray-300'" class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold">1</div>
								<div class="w-12 h-0.5" :class="modalStep >= 2 ? 'bg-indigo-500' : 'bg-gray-300'"></div>
								<div :class="modalStep >= 2 ? 'bg-indigo-500' : 'bg-gray-300'" class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold">2</div>
								<div class="w-12 h-0.5" :class="modalStep >= 3 ? 'bg-indigo-500' : 'bg-gray-300'"></div>
								<div :class="modalStep >= 3 ? 'bg-indigo-500' : 'bg-gray-300'" class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold">3</div>
							</div>
						</div>
					</template>

					<!-- Modal Body -->
					<div class="p-6 overflow-y-auto custom-scrollbar modal-body-scrollable">

						<!-- Step 1: Select Plugin -->
						<div x-show="modalStep === 1">
							<div class="mb-4">
								<h3 class="text-sm font-semibold text-gray-900 mb-3"><?php esc_html_e('Custom Scenario Builder', 'notificator-companion'); ?></h3>
								<p class="text-xs text-gray-500 mb-4"><?php esc_html_e('Choose a plugin to browse available hooks and create a custom scenario.', 'notificator-companion'); ?></p>
							</div>

							<!-- Plugin Selection Grid -->
							<div class="grid grid-cols-2 gap-3">
								<template x-for="(plugin, key) in availablePlugins" :key="'modal-plugin-' + key">
									<button @click="selectPlugin(key)" type="button"
										class="notificator-plugin-select text-left p-4 rounded-xl border-2 transition-all hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40"
										:class="plugin.file && !pluginActiveStatus[key] ? 'opacity-40 cursor-not-allowed border-gray-200' : 'cursor-pointer border-gray-200 hover:border-indigo-300'">
										<div class="flex items-center gap-3">
											<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-2xl">
												<template x-if="plugin.icon_url">
													<img :src="plugin.icon_url" alt="" class="w-7 h-7 object-contain rounded" @error="plugin.icon_url = ''" />
												</template>
												<template x-if="!plugin.icon_url">
													<span x-text="plugin.icon || '🔌'"></span>
												</template>
											</div>
											<div class="flex-1 min-w-0">
												<div class="font-semibold text-sm text-gray-900 truncate" x-text="plugin.name"></div>
												<div class="text-xs text-gray-500" x-text="Object.keys(plugin.hooks).length + ' hooks'"></div>
											</div>
										</div>
									</button>
								</template>
							</div>
						</div>

						<!-- Step 2: Select Hook -->
						<div x-show="modalStep === 2">
							<!-- Search input -->
							<div class="mb-4">
								<div class="relative notificator-search">
									<input type="text"
										x-model="hookSearchQuery"
										placeholder="<?php esc_attr_e('Search hooks...', 'notificator-companion'); ?>"
										class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
									<svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 notificator-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
									</svg>
								</div>
								<p class="text-xs text-gray-500 mt-2" x-text="Object.keys(getFilteredPluginHooks()).length + ' hook' + (Object.keys(getFilteredPluginHooks()).length === 1 ? '' : 's')"></p>
							</div>

							<!-- Hooks list -->
							<div class="space-y-2 overflow-y-auto hook-list-scrollable">
								<template x-for="(hookData, hookName) in getFilteredPluginHooks()" :key="'modal-hook-' + hookName">
									<button @click="selectHook(hookName, hookData)" type="button"
										class="cursor-pointer w-full text-left p-3 rounded-lg bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-300 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40">
										<div class="flex items-start justify-between gap-2">
											<div class="flex-1 min-w-0">
												<code class="text-xs font-mono text-indigo-600 font-semibold" x-text="hookName"></code>
												<p class="text-xs text-gray-600 mt-1" x-text="(hookData && hookData.label) ? hookData.label : hookData"></p>
												<template x-if="hookData && typeof hookData === 'object' && hookData.payload_arity !== null && hookData.payload_arity > 0">
													<div class="mt-1.5 flex items-center gap-2">
														<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
															<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
															</svg>
															<span x-text="hookData.payload_arity + ' arg' + (hookData.payload_arity === 1 ? '' : 's')"></span>
														</span>
														<template x-if="Array.isArray(hookData.arg_names) && hookData.arg_names.length > 0">
															<span class="text-xs text-gray-500 font-mono" x-text="'(' + hookData.arg_names.join(', ') + ')'"></span>
														</template>
													</div>
												</template>
											</div>
										</div>
									</button>
								</template>
								<template x-if="Object.keys(getFilteredPluginHooks()).length === 0">
									<div class="text-center py-8 text-gray-500">
										<p class="text-sm"><?php esc_html_e('No hooks found matching your search', 'notificator-companion'); ?></p>
									</div>
								</template>
							</div>
						</div>
						<!-- Step 3: Configure Scenario -->
						<div x-show="modalStep === 3">
							<div class="space-y-4">
								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e('Hook', 'notificator-companion'); ?></label>
									<code class="inline-block text-sm font-mono bg-purple-100 text-purple-700 px-3 py-1.5 rounded-lg" x-text="scenarioForm.hook_name"></code>
									<p class="text-xs text-gray-500 mt-1" x-text="scenarioForm.description"></p>
									<div class="mt-2 text-xs text-gray-600" x-show="!!scenarioForm.hook_meta">
										<template x-if="scenarioForm.hook_meta && scenarioForm.hook_meta.payload_arity !== null">
											<span>
												<strong><?php esc_html_e('Args:', 'notificator-companion'); ?></strong>
												<span x-text="scenarioForm.hook_meta.payload_arity"></span>
											</span>
										</template>
										<template x-if="scenarioForm.hook_meta && Array.isArray(scenarioForm.hook_meta.arg_names) && scenarioForm.hook_meta.arg_names.length">
											<span class="ml-2">
												<strong><?php esc_html_e('Names:', 'notificator-companion'); ?></strong>
												<span x-text="scenarioForm.hook_meta.arg_names.join(', ')"></span>
											</span>
										</template>
										<template x-if="scenarioForm.hook_meta && scenarioForm.hook_meta.arg_mode === 'ref_array'">
											<span class="ml-2">
												(<?php esc_html_e('ref_array hook', 'notificator-companion'); ?>)
											</span>
										</template>
									</div>
								</div>

								<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e('Scenario Name', 'notificator-companion'); ?></label>
										<input type="text" x-model="scenarioForm.scenario_name"
											class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
											placeholder="<?php esc_attr_e('e.g. New order placed', 'notificator-companion'); ?>">
									</div>

									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e('Severity Level', 'notificator-companion'); ?></label>
										<select x-model="scenarioForm.severity"
											class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
											<option value="info"><?php esc_html_e('Low', 'notificator-companion'); ?></option>
											<option value="warning"><?php esc_html_e('Medium', 'notificator-companion'); ?></option>
											<option value="critical"><?php esc_html_e('Critical', 'notificator-companion'); ?></option>
										</select>
									</div>
								</div>

								<div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
									<label class="block text-sm font-semibold text-gray-700 mb-3"><?php esc_html_e('Delivery', 'notificator-companion'); ?></label>
									<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
										<label class="flex items-center gap-2 text-sm text-gray-700">
											<input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="scenarioForm.send_push">
											<span><?php esc_html_e('Send Push', 'notificator-companion'); ?></span>
										</label>
										<label class="flex items-center gap-2 text-sm text-gray-700">
											<input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="scenarioForm.send_mqtt">
											<span><?php esc_html_e('Send MQTT', 'notificator-companion'); ?></span>
										</label>
									</div>
								</div>

								<!-- Conditions Builder (only for hooks with args) -->
								<div x-show="hasConditionSupport()">
									<div class="flex items-center justify-between mb-2">
										<label class="block text-sm font-semibold text-gray-700"><?php esc_html_e('Conditions', 'notificator-companion'); ?></label>
										<button @click="addCondition()" type="button"
											x-show="!areAllConditionsLocked()"
											class="cursor-pointer inline-flex items-center gap-1 h-8 px-3 text-xs font-semibold bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40">
											<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
											</svg>
											<?php esc_html_e('Add Condition', 'notificator-companion'); ?>
										</button>
									</div>

									<div class="space-y-2 mb-3">
										<template x-for="(condition, cIndex) in scenarioForm.conditions" :key="'condition-' + cIndex">
											<div class="notificator-condition-row flex gap-3 items-start p-3.5 bg-white rounded-xl border border-slate-200 shadow-sm transition-colors focus-within:border-indigo-300 focus-within:ring-2 focus-within:ring-indigo-500/15">
												<div class="notificator-condition-grid flex-1 grid grid-cols-3 gap-2">
													<!-- Field selector -->
													<div class="notificator-condition-col">
														<label class="block text-xs text-gray-600 mb-1"><?php esc_html_e('Field', 'notificator-companion'); ?></label>
														<template x-if="condition.locked || condition.lock_field">
														<div class="notificator-condition-locked w-full h-9 px-2 text-sm border border-gray-200 rounded bg-slate-50 text-gray-800 font-mono flex items-center" x-text="condition.field"></div>
														</template>
														<template x-if="!(condition.locked || condition.lock_field)">
															<select x-model="condition.field" class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
																<template x-for="field in getConditionFields()" :key="'field-' + field.value">
																	<option :value="field.value" x-text="field.label"></option>
																</template>
															</select>
														</template>
													</div>

													<!-- Operator selector -->
													<div class="notificator-condition-col">
														<label class="block text-xs text-gray-600 mb-1"><?php esc_html_e('Operator', 'notificator-companion'); ?></label>
														<template x-if="condition.locked || condition.lock_operator">
														<div class="notificator-condition-locked w-full h-9 px-2 text-sm border border-gray-200 rounded bg-slate-50 text-gray-800 font-mono flex items-center" x-text="condition.operator"></div>
														</template>
														<template x-if="!(condition.locked || condition.lock_operator)">
															<select x-model="condition.operator" class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
																<template x-for="op in getOperators()" :key="'op-' + op.value">
																	<option :value="op.value" x-text="op.label"></option>
																</template>
															</select>
														</template>
													</div>

													<!-- Value input -->
													<div class="notificator-condition-col">
														<label class="block text-xs text-gray-600 mb-1" x-text="condition.value_label || <?php echo esc_attr(wp_json_encode(__('Value', 'notificator-companion'))); ?>"></label>
														<template x-if="Array.isArray(getConditionValueOptions(condition)) && getConditionValueOptions(condition).length">
															<select x-model="condition.value" class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
																<option value=""><?php esc_html_e('Select…', 'notificator-companion'); ?></option>
																<template x-for="opt in getConditionValueOptions(condition)" :key="'opt-' + opt.value">
																	<option :value="opt.value" x-text="opt.label"></option>
																</template>
															</select>
														</template>
														<template x-if="!(Array.isArray(getConditionValueOptions(condition)) && getConditionValueOptions(condition).length)">
															<input :type="condition.value_type || 'text'" x-model="condition.value"
																class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
																:placeholder="condition.value_placeholder || <?php echo esc_attr(wp_json_encode(__('Enter value', 'notificator-companion'))); ?>">
														</template>
													</div>
												</div>

												<!-- Remove button -->
												<button @click="removeCondition(cIndex)" type="button"
													x-show="!(condition.locked || areAllConditionsLocked())"
													class="cursor-pointer inline-flex items-center justify-center h-9 w-9 mt-5 bg-red-50/40 border border-red-200/70 text-red-700 hover:bg-red-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
													</svg>
												</button>
											</div>
										</template>

										<template x-if="scenarioForm.conditions.length === 0">
											<p class="text-xs text-gray-500 italic text-center py-2">
												<?php esc_html_e('No conditions set - scenario will trigger for all hook events', 'notificator-companion'); ?>
											</p>
										</template>

										<template x-if="scenarioForm.conditions.length > 1">
											<p class="text-xs text-gray-500 mt-2">
												<strong><?php esc_html_e('Note:', 'notificator-companion'); ?></strong>
												<?php esc_html_e('All conditions must be met (AND logic)', 'notificator-companion'); ?>
											</p>
										</template>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e('Notification note', 'notificator-companion'); ?></label>
									<p class="text-xs text-gray-500 mb-2"><?php esc_html_e('Shown below the title in every notification for this scenario.', 'notificator-companion'); ?></p>
									<textarea x-model="scenarioForm.scenario_notes" rows="3"
										class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
										placeholder="<?php esc_attr_e('Optional: Short context or next steps', 'notificator-companion'); ?>"></textarea>
									<template x-if="getNoteTagSuggestions().length">
										<div class="mt-2">
											<div class="text-xs text-gray-500 mb-1"><?php esc_html_e('Available tags:', 'notificator-companion'); ?></div>
											<div class="flex flex-wrap gap-2">
												<template x-for="tag in getNoteTagSuggestions()" :key="'note-tag-' + tag">
													<button type="button" @click="insertNoteTag(tag)"
														class="notificator-note-tag-btn text-xs font-mono px-2 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-100 hover:border-indigo-200">
														<span x-text="'{{' + tag + '}}'"></span>
													</button>
												</template>
											</div>
										</div>
									</template>
								</div>

								<div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
									<label class="inline-flex items-center cursor-pointer gap-2">
										<input type="checkbox" x-model="scenarioForm.enabled" aria-label="<?php echo esc_attr(__('Enable scenario', 'notificator-companion')); ?>">
										<div>
											<div class="text-sm font-semibold text-gray-900"><?php esc_html_e('Enable Scenario', 'notificator-companion'); ?></div>
											<div class="text-xs text-gray-500"><?php esc_html_e('Start monitoring this hook immediately', 'notificator-companion'); ?></div>
										</div>
									</label>
								</div>
							</div>
						</div>
					</div>

					<!-- Modal Footer -->
					<div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between gap-3 flex-wrap">
						<template x-if="editingIndex === null">
							<button @click="modalStep > 1 ? modalStep-- : modalOpen = false" type="button" class="btn-secondary btn-secondary--ghost">
								<span x-show="modalStep === 1"><?php esc_html_e('Cancel', 'notificator-companion'); ?></span>
								<span x-show="modalStep > 1">← <?php esc_html_e('Back', 'notificator-companion'); ?></span>
							</button>
						</template>

						<div class="flex items-center gap-2">
							<button x-show="modalStep === 1" type="button" class="btn-primary" disabled>
								<?php esc_html_e('Choose a plugin', 'notificator-companion'); ?>
							</button>
							<button x-show="modalStep === 2" type="button" class="btn-primary" disabled>
								<?php esc_html_e('Choose a hook', 'notificator-companion'); ?>
							</button>
							<button @click="modalStep === 3 ? saveScenario() : null"
								x-show="modalStep === 3"
								type="button"
								class="btn-primary">
								<span x-text="editingIndex !== null ? <?php echo esc_attr(wp_json_encode(__('Update Scenario', 'notificator-companion'))); ?> : <?php echo esc_attr(wp_json_encode(__('Create Scenario', 'notificator-companion'))); ?>"></span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render first time setup prompt
	 */
	private function render_first_time_setup()
	{
	?>
		<div class="notificator-first-time-setup">
			<div class="notificator-first-time-setup__icon" aria-hidden="true">
				<span class="dashicons dashicons-search"></span>
			</div>
			<div class="notificator-first-time-setup__content">
				<h3><?php esc_html_e('First-time setup', 'notificator-companion'); ?></h3>
				<p><?php esc_html_e('Run a quick plugin scan to discover available hooks and unlock ready-to-use templates.', 'notificator-companion'); ?></p>
				<button type="button" id="auto-scan-btn" class="btn-secondary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e('Scan Plugins Now', 'notificator-companion'); ?>
				</button>
			</div>
		</div>
<?php
	}
}
