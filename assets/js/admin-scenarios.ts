/**
 * Admin Scenarios JavaScript
 * 
 * Handles the scenario builder UI with Alpine.js and plugin scanning
 * 
 * @package NotificatorCompanion
 * @since 1.1.0
 */

type AnyRecord = Record<string, any>;
type AnyFn = (...args: any[]) => any;

(function($: any) {
	'use strict';

	/**
	 * Initialize Alpine.js data for scenario builder
	 */
	window.initScenarioBuilder = function(
		hooks: AnyRecord[] = [],
		availablePlugins: AnyRecord = {},
		pluginActiveStatus: AnyRecord = {},
		hookActiveStatus: AnyRecord = {},
		_optionName?: string
	) {
		const normalizeEnabled = (value: unknown): boolean => {
			if (value === true || value === 1 || value === '1') {
				return true;
			}
			if (value === false || value === 0 || value === '0' || value === null || typeof value === 'undefined') {
				return false;
			}
			return !!value;
		};

		const normalizeSeverity = (value: unknown): string => {
			const severity = (typeof value === 'string' ? value : '').toLowerCase();
			return ['info', 'warning', 'critical'].includes(severity) ? severity : 'info';
		};

		const normalizedHooks = hooks.map((hook: AnyRecord) => {
			const hasSendPush = hook && typeof hook.send_push !== 'undefined';
			const hasSendMqtt = hook && typeof hook.send_mqtt !== 'undefined';
			return {
				...hook,
				enabled: normalizeEnabled(hook && hook.enabled),
				severity: normalizeSeverity(hook && hook.severity),
				send_push: hasSendPush ? normalizeEnabled(hook && hook.send_push) : true,
				send_mqtt: hasSendMqtt ? normalizeEnabled(hook && hook.send_mqtt) : true
			};
		});

		const getHookLabel = (hookData: unknown): string => {
			if (!hookData) {
				return '';
			}
			if (typeof hookData === 'string') {
				return hookData;
			}
			if (typeof hookData === 'object' && hookData !== null && 'label' in hookData) {
				const labeled = hookData as { label?: unknown };
				return typeof labeled.label === 'string' ? labeled.label : '';
			}
			return '';
		};

		const derivePluginSlug = (pluginKey: string, pluginData: AnyRecord): string => {
			if (pluginData && typeof pluginData.slug === 'string' && pluginData.slug.trim() !== '') {
				return pluginData.slug.trim();
			}
			if (pluginData && typeof pluginData.file === 'string' && pluginData.file.includes('/')) {
				return pluginData.file.split('/')[0];
			}
			if (pluginKey && pluginKey !== 'wordpress-core') {
				return pluginKey;
			}
			return '';
		};

		const getPluginFallbackIcon = (pluginKey: string, pluginName: string): string => {
			const key = (pluginKey || '').toLowerCase();
			const name = (pluginName || '').toLowerCase();
			if (key === 'wordpress-core' || name.includes('wordpress')) return '⚙️';
			if (key.includes('woocommerce') || name.includes('woocommerce')) return '🛒';
			if (key.includes('contact-form-7') || name.includes('contact form 7')) return '📨';
			if (key.includes('gravityforms') || name.includes('gravity forms')) return '📬';
			if (key.includes('wordpress-seo') || name.includes('yoast')) return '📊';
			if (key.includes('rank-math') || key.includes('seo-by-rank-math') || name.includes('rank math')) return '📈';
			if (key.includes('wordfence') || name.includes('wordfence')) return '🔒';
			if (key.includes('elementor') || name.includes('elementor')) return '🎨';
			if (key.includes('updraft') || name.includes('updraft')) return '💾';
			return '🔌';
		};

		const normalizeAvailablePlugins = (plugins: AnyRecord): AnyRecord => {
			const normalized: AnyRecord = {};
			if (!plugins || typeof plugins !== 'object') {
				return normalized;
			}

			for (const pluginKey in plugins) {
				if (!Object.prototype.hasOwnProperty.call(plugins, pluginKey)) continue;
				const pluginData = plugins[pluginKey] || {};
				const pluginName = pluginData && pluginData.name ? String(pluginData.name) : pluginKey;
				const slug = derivePluginSlug(pluginKey, pluginData);
				const iconUrl = pluginData && pluginData.icon_url
					? String(pluginData.icon_url)
					: (slug ? `https://ps.w.org/${slug}/assets/icon-128x128.png` : '');
				const icon = pluginData && pluginData.icon
					? String(pluginData.icon)
					: getPluginFallbackIcon(pluginKey, pluginName);

				normalized[pluginKey] = {
					...pluginData,
					slug,
					icon_url: iconUrl,
					icon
				};
			}

			return normalized;
		};

		const builder: AnyRecord = {
			init() {
				// Expose for external updaters (e.g. AJAX hook scanner)
				window.notificatorScenarioBuilder = this;
				this.templatePluginFilter = '__all__';
				this.onTemplatesPerPageChange();
			},
			selectedPlugin: '',
			hooks: normalizedHooks,
			availablePlugins: normalizeAvailablePlugins(availablePlugins || {}),
			pluginActiveStatus: pluginActiveStatus || {},
			hookActiveStatus: hookActiveStatus || {},
			searchQuery: '',
			hookSearchQuery: '',
			templateSearchQuery: '',
			templatePluginFilter: '__all__',
			templatePage: 1,
			templatesPerPage: 12,
			modalOpen: false,
			modalStep: 1,
			editingIndex: null,
			selectedPluginModal: '',
			selectedHook: null,
			scenarioForm: {
				hook_name: '',
				description: '',
				scenario_name: '',
				scenario_notes: '',
				severity: 'info',
				enabled: true,
				send_push: true,
				send_mqtt: true,
				plugin_key: '',
				plugin_name: '',
				hook_meta: null,
				conditions: []
			},

			/**
			 * Get WooCommerce order statuses (for select dropdowns)
			 */
			getWooCommerceOrderStatusOptions: function() {
				if (typeof notificatorWooCommerceOrderStatuses !== 'undefined' && Array.isArray(notificatorWooCommerceOrderStatuses)) {
					return notificatorWooCommerceOrderStatuses;
				}
				return [];
			},

			/**
			 * Get available fields for conditions.
			 * Includes hook arg names plus nested property paths from hook_meta.properties.
			 */
			getConditionFields: function() {
				const fields = [];
				const seen = {};

				const addField = function(value, label) {
					if (!value || seen[value]) {
						return;
					}
					seen[value] = true;
					fields.push({ value, label: label || value });
				};

				const hookMeta = this.scenarioForm && this.scenarioForm.hook_meta ? this.scenarioForm.hook_meta : null;
				const argNames = hookMeta && Array.isArray(hookMeta.arg_names) ? hookMeta.arg_names : [];
				argNames.forEach((argName) => addField(argName, argName));

				// Add nested fields from properties map: { argName: [{name,label,...}] }
				if (hookMeta && hookMeta.properties && typeof hookMeta.properties === 'object') {
					for (const argKey in hookMeta.properties) {
						if (!Object.prototype.hasOwnProperty.call(hookMeta.properties, argKey)) continue;
						const props = hookMeta.properties[argKey];
						if (!Array.isArray(props)) continue;
						props.forEach((prop) => {
							if (!prop || !prop.name) return;
							const value = argKey + '.' + prop.name;
							const label = prop.label ? (argKey + '.' + prop.label) : value;
							addField(value, label);
						});
					}
				}

				// Ensure currently set fields remain selectable (even if not in meta)
				if (Array.isArray(this.scenarioForm && this.scenarioForm.conditions)) {
					this.scenarioForm.conditions.forEach((condition) => {
						if (condition && condition.field) {
							addField(condition.field, condition.field);
						}
					});
				}

				return fields;
			},

			/**
			 * Return available note tag placeholders for the current hook.
			 */
			getNoteTagSuggestions: function() {
				const fields = this.getConditionFields();
				if (!Array.isArray(fields)) {
					return [];
				}
				return fields
					.map((field) => field && field.value ? String(field.value) : '')
					.filter((value) => value);
			},

			/**
			 * Insert a note tag placeholder into the notification note.
			 */
			insertNoteTag: function(tag) {
				if (!tag) {
					return;
				}
				const placeholder = `{{${tag}}}`;
				const current = this.scenarioForm && this.scenarioForm.scenario_notes ? String(this.scenarioForm.scenario_notes) : '';
				this.scenarioForm.scenario_notes = current ? `${current} ${placeholder}` : placeholder;
			},

			/**
			 * Return value options for a condition (if it should render as select).
			 */
			getConditionValueOptions: function(condition) {
				if (!condition) {
					return [];
				}

				// Inline options (template-provided)
				if (Array.isArray(condition.value_options) && condition.value_options.length) {
					return condition.value_options;
				}

				// Keyed options
				if (condition.value_options_key === 'wc_order_statuses') {
					return this.getWooCommerceOrderStatusOptions();
				}

				// Heuristic: Woo status changed hook args
				if (condition.field === 'new_status' || condition.field === 'old_status') {
					return this.getWooCommerceOrderStatusOptions();
				}

				// Heuristic: comment approval (0/1)
				if (condition.field === 'comment_approved') {
					return [
						{ value: '0', label: 'Pending moderation' },
						{ value: '1', label: 'Approved' }
					];
				}

				return [];
			},

			/**
			 * Normalize template conditions by ensuring UI hints exist (type/placeholder/options).
			 */
			normalizeTemplateConditions: function(conditions) {
				const cloned = Array.isArray(conditions) ? JSON.parse(JSON.stringify(conditions)) : [];
				cloned.forEach((condition) => {
					if (!condition) return;
					if (!condition.operator) condition.operator = '=';
					if (typeof condition.value === 'undefined' || condition.value === null) condition.value = '';
					if (!condition.value_type) condition.value_type = 'text';
				});
				return cloned;
			},

			/**
			 * Detect whether ALL current conditions are locked (template preset mode).
			 */
			areAllConditionsLocked: function() {
				if (!Array.isArray(this.scenarioForm && this.scenarioForm.conditions) || this.scenarioForm.conditions.length === 0) {
					return false;
				}
				return this.scenarioForm.conditions.every((condition) => {
					return !!(condition && (condition.locked || condition.lock_field || condition.lock_operator));
				});
			},
			
			/**
			 * Check if current hook supports conditions
			 */
			hasConditionSupport: function() {
				return this.scenarioForm.hook_meta && 
					   Array.isArray(this.scenarioForm.hook_meta.arg_names) && 
					   this.scenarioForm.hook_meta.arg_names.length > 0;
			},
			
			/**
			 * Add a new condition to the scenario
			 */
			addCondition: function() {
				// Template preset conditions: don't allow adding extra conditions.
				if (this.areAllConditionsLocked()) {
					return;
				}
				if (!Array.isArray(this.scenarioForm.conditions)) {
					this.scenarioForm.conditions = [];
				}
				const availableFields = this.getConditionFields();
				const defaultField = Array.isArray(availableFields) && availableFields.length > 0 ? availableFields[0].value : 'arg_1';
				this.scenarioForm.conditions.push({
					field: defaultField,
					operator: '=',
					value: '',
					value_type: 'text',
					value_placeholder: ''
				});
			},
			
			/**
			 * Remove a condition by index
			 */
			removeCondition: function(index) {
				// Template preset conditions: don't allow removing.
				if (this.areAllConditionsLocked()) {
					return;
				}
				if (Array.isArray(this.scenarioForm.conditions)) {
					this.scenarioForm.conditions.splice(index, 1);
				}
			},
			
			/**
			 * Get available operators for conditions
			 */
			getOperators: function() {
				return [
					{ value: '=', label: 'equals' },
					{ value: '!=', label: 'not equals' },
					{ value: '>', label: 'greater than' },
					{ value: '>=', label: 'greater or equal' },
					{ value: '<', label: 'less than' },
					{ value: '<=', label: 'less or equal' },
					{ value: 'contains', label: 'contains' },
					{ value: 'not_contains', label: 'does not contain' }
				];
			},
			
			/**
			 * Get hooks for a specific plugin
			 */
			getPluginHooks: function(plugin) {
				// Avoid optional chaining for broader browser compatibility.
				if (this.availablePlugins && this.availablePlugins[plugin] && this.availablePlugins[plugin].hooks) {
					return this.availablePlugins[plugin].hooks;
				}
				return {};
			},
			
			/**
			 * Find hook metadata by hook name across all plugins
			 */
			findHookMeta: function(hookName) {
				if (!hookName || !this.availablePlugins) {
					return null;
				}
				
				// Search through all plugins for this hook
				for (const pluginKey in this.availablePlugins) {
					if (!this.availablePlugins.hasOwnProperty(pluginKey)) continue;
					
					const plugin = this.availablePlugins[pluginKey];
					if (plugin && plugin.hooks && plugin.hooks[hookName]) {
						const hookData = plugin.hooks[hookName];
						// Return only if it's an object with metadata
						if (typeof hookData === 'object' && hookData !== null) {
							return hookData;
						}
					}
				}
				
				return null;
			},

			/**
			 * Determine which plugin a scenario belongs to.
			 */
			getScenarioPluginKey: function(hook) {
				if (!hook) {
					return '';
				}
				if (hook.plugin_key) {
					return hook.plugin_key;
				}
				const hookName = hook.hook_name || '';
				if (!hookName || !this.availablePlugins) {
					return '';
				}
				for (const pluginKey in this.availablePlugins) {
					if (!this.availablePlugins.hasOwnProperty(pluginKey)) continue;
					const plugin = this.availablePlugins[pluginKey];
					if (plugin && plugin.hooks && plugin.hooks[hookName]) {
						return pluginKey;
					}
				}
				return '';
			},

			/**
			 * Friendly plugin name for a scenario.
			 */
			getScenarioPluginName: function(hook) {
				if (hook && hook.plugin_name) {
					return hook.plugin_name;
				}
				const pluginKey = this.getScenarioPluginKey(hook);
				if (pluginKey && this.availablePlugins && this.availablePlugins[pluginKey] && this.availablePlugins[pluginKey].name) {
					return this.availablePlugins[pluginKey].name;
				}
				return '';
			},

			/**
			 * Determine whether the scenario plugin is active, inactive, missing, or core.
			 */
			getScenarioPluginStatus: function(hook) {
				const pluginKey = this.getScenarioPluginKey(hook);
				if (!pluginKey || pluginKey === 'wordpress-core') {
					return 'core';
				}
				if (this.pluginActiveStatus && Object.prototype.hasOwnProperty.call(this.pluginActiveStatus, pluginKey)) {
					return this.pluginActiveStatus[pluginKey] ? 'active' : 'inactive';
				}
				if (this.availablePlugins && this.availablePlugins[pluginKey]) {
					return this.availablePlugins[pluginKey].active === false ? 'inactive' : 'active';
				}
				return hook && hook.plugin_key ? 'missing' : 'unknown';
			},

			/**
			 * Whether the scenario belongs to a plugin that is inactive or missing.
			 */
			isScenarioPluginInactive: function(hook) {
				const status = this.getScenarioPluginStatus(hook);
				return status === 'inactive' || status === 'missing';
			},

			/**
			 * Badge label for plugin status.
			 */
			getScenarioPluginBadgeLabel: function(hook) {
				const status = this.getScenarioPluginStatus(hook);
				if (status === 'inactive') {
					return 'Plugin inactive';
				}
				if (status === 'missing') {
					return 'Plugin missing';
				}
				return '';
			},
			
			/**
			 * Get filtered hooks for the selected plugin based on search query
			 */
			getFilteredPluginHooks: function() {
				const allHooks = this.getPluginHooks(this.selectedPluginModal);
				if (!this.hookSearchQuery || this.hookSearchQuery.trim() === '') {
					return allHooks;
				}
				
				const query = this.hookSearchQuery.toLowerCase().trim();
				const filtered = {};
				
				for (const hookName in allHooks) {
					if (!allHooks.hasOwnProperty(hookName)) continue;
					
					const hookData = allHooks[hookName];
					const label = getHookLabel(hookData);
					
					// Match against hook name or description
					if (hookName.toLowerCase().includes(query) || 
						(label && label.toLowerCase().includes(query))) {
						filtered[hookName] = hookData;
					}
				}
				
				return filtered;
			},
			
			/**
			 * Get common scenario templates
			 */
			/**
			 * Get common predefined templates (filtered by active plugins)
			 */
			getCommonTemplates: function() {
				// Load templates from external file
				if (typeof window.notificatorScenarioTemplates === 'undefined') {
					console.error('Scenario templates not loaded');
					return [];
				}

				const baseTemplates = Array.isArray(window.notificatorScenarioTemplates)
					? window.notificatorScenarioTemplates
					: [];
				const extraTemplates = Array.isArray(window.notificatorScenarioTemplatesExtra)
					? window.notificatorScenarioTemplatesExtra
					: [];
				const mergedTemplates = baseTemplates.concat(extraTemplates);

				// Get active plugins from PHP (passed via localized script)
				const activePlugins = this.getActivePlugins();

				// Filter templates based on active plugins
				const filtered = mergedTemplates.filter(template => {
					const requiredPlugin = (template && template.required_plugin) ? template.required_plugin : 'wordpress-core';
					
					// Always show WordPress core templates
					if (requiredPlugin === 'wordpress-core') {
						return true;
					}
					
					// Check if required plugin is active
					return activePlugins.includes(requiredPlugin);
				});

				const seen = new Set();
				const uniqueTemplates = filtered.filter(template => {
					if (!template || (!template.hook_name && !template.title)) {
						return false;
					}
					const key = `${template.hook_name || ''}::${template.title || ''}`;
					if (seen.has(key)) {
						return false;
					}
					seen.add(key);
					return true;
				});

				return uniqueTemplates.map((template) => {
					if (!template || typeof template !== 'object') {
						return template;
					}

					const requiredPlugin = template.required_plugin ? String(template.required_plugin) : 'wordpress-core';
					const pluginData = this.availablePlugins && this.availablePlugins[requiredPlugin]
						? this.availablePlugins[requiredPlugin]
						: null;
					const derivedSlug = derivePluginSlug(requiredPlugin, pluginData || {});
					const pluginIconUrl = pluginData && pluginData.icon_url
						? String(pluginData.icon_url)
						: (derivedSlug ? `https://ps.w.org/${derivedSlug}/assets/icon-128x128.png` : '');
					const pluginIcon = pluginData && pluginData.icon
						? String(pluginData.icon)
						: getPluginFallbackIcon(requiredPlugin, pluginData && pluginData.name ? String(pluginData.name) : '');
					const pluginName = pluginData && pluginData.name
						? String(pluginData.name)
						: (requiredPlugin === 'wordpress-core' ? 'WordPress Core' : String(requiredPlugin));

					if (!pluginIcon) {
						return {
							...template,
							plugin_key: requiredPlugin,
							plugin_name: pluginName,
							plugin_icon_url: pluginIconUrl,
							icon_class: ''
						};
					}

					return {
						...template,
						plugin_key: requiredPlugin,
						plugin_name: pluginName,
						plugin_icon_url: pluginIconUrl,
						icon: pluginIcon,
						icon_class: ''
					};
				});
			},
			
			/**
			 * Get list of active plugins (from PHP)
			 */
			getActivePlugins: function() {
				// This will be populated via wp_localize_script from PHP
				if (typeof notificatorActivePlugins !== 'undefined') {
					return notificatorActivePlugins;
				}
				
				// Fallback: return WordPress core only
				return ['wordpress-core'];
			},

			/**
			 * Build plugin options for the template filter dropdown.
			 * Includes only active plugins (plus WordPress core).
			 */
			getTemplatePluginFilterOptions: function() {
				const activePlugins = this.getActivePlugins();
				const options = [{ value: '__all__', label: 'All active plugins' }];
				const seen = new Set(['__all__']);

				activePlugins.forEach((pluginKey) => {
					if (!pluginKey || seen.has(pluginKey)) {
						return;
					}

					let label = '';
					if (pluginKey === 'wordpress-core') {
						label = 'WordPress Core';
					} else if (
						this.availablePlugins &&
						this.availablePlugins[pluginKey] &&
						this.availablePlugins[pluginKey].name
					) {
						label = this.availablePlugins[pluginKey].name;
					} else {
						label = pluginKey;
					}

					options.push({ value: pluginKey, label: label });
					seen.add(pluginKey);
				});

				return options;
			},

			/**
			 * Dropdown options for templates shown per page.
			 */
			getTemplatesPerPageOptions: function() {
				return [12, 24, 36, 60];
			},
			
			/**
			 * Use a template to create a scenario
			 */
			useTemplate: function(template) {
				// Ensure templates always create NEW scenarios (never overwrite an edited one)
				this.editingIndex = null;
				this.selectedHook = { hookName: template.hook_name, description: template.description };
				const templatePluginKey = template && template.required_plugin ? template.required_plugin : '';
				const templatePlugin = templatePluginKey && this.availablePlugins ? this.availablePlugins[templatePluginKey] : null;
				this.scenarioForm = {
					hook_name: template.hook_name,
					description: template.description,
					scenario_name: template.scenario_name,
					scenario_notes: '',
					severity: (typeof template.severity === 'string' ? template.severity : 'info'),
					enabled: true,
					send_push: true,
					send_mqtt: true,
					plugin_key: templatePluginKey,
					plugin_name: templatePlugin && templatePlugin.name ? templatePlugin.name : '',
					hook_meta: template.hook_meta || null,
					conditions: this.normalizeTemplateConditions(template.conditions)
				};
				// Normalize in case template provides unexpected value.
				if (typeof normalizeSeverity === 'function') {
					this.scenarioForm.severity = normalizeSeverity(this.scenarioForm.severity);
				}
				this.modalStep = 3;
				this.modalOpen = true;
			},
			
			/**
			 * Get filtered templates based on search
			 */
			getFilteredTemplates: function() {
				const selectedPlugin = this.templatePluginFilter ? String(this.templatePluginFilter) : '__all__';
				const showAllPlugins = selectedPlugin === '__all__';
				const activeTemplates = this.getCommonTemplates();
				const pluginFilteredTemplates = showAllPlugins
					? activeTemplates
					: activeTemplates.filter((template) => {
						const requiredPlugin = (template && template.required_plugin) ? template.required_plugin : 'wordpress-core';
						return requiredPlugin === selectedPlugin;
					});

				const query = this.templateSearchQuery.toLowerCase().trim();
				if (!query) {
					return pluginFilteredTemplates;
				}
				
				return pluginFilteredTemplates.filter(template => {
					return template.title.toLowerCase().includes(query) ||
						   template.hook_name.toLowerCase().includes(query) ||
						   template.description.toLowerCase().includes(query) ||
						   template.scenario_name.toLowerCase().includes(query);
				});
			},
			
			/**
			 * Get paginated templates
			 */
			getPaginatedTemplates: function() {
				const filtered = this.getFilteredTemplates();
				if (this.templatesPerPage <= 0) {
					return filtered;
				}
				const start = (this.templatePage - 1) * this.templatesPerPage;
				const end = start + this.templatesPerPage;
				return filtered.slice(start, end);
			},
			
			/**
			 * Get total pages for templates
			 */
			getTemplateTotalPages: function() {
				const filtered = this.getFilteredTemplates();
				if (this.templatesPerPage <= 0) {
					return 1;
				}
				return Math.ceil(filtered.length / this.templatesPerPage);
			},
			
			/**
			 * Navigate to next template page
			 */
			nextTemplatePage: function() {
				if (this.templatePage < this.getTemplateTotalPages()) {
					this.templatePage++;
				}
			},
			
			/**
			 * Navigate to previous template page
			 */
			prevTemplatePage: function() {
				if (this.templatePage > 1) {
					this.templatePage--;
				}
			},
			
			/**
			 * Reset to page 1 when search changes
			 */
			onTemplateSearchChange: function() {
				this.templatePage = 1;
			},

			/**
			 * Reset pagination when plugin filter changes.
			 */
			onTemplatePluginFilterChange: function() {
				if (!this.templatePluginFilter) {
					this.templatePluginFilter = '__all__';
				}
				this.templatePage = 1;
			},

			/**
			 * Normalize templates-per-page selection and reset pagination.
			 */
			onTemplatesPerPageChange: function() {
				const parsed = parseInt(String(this.templatesPerPage), 10);
				this.templatesPerPage = Number.isFinite(parsed) && parsed > 0 ? parsed : 12;
				this.templatePage = 1;
			},
			
			/**
			 * Open modal for adding new scenario
			 */
			openAddModal: function() {
				this.editingIndex = null;
				this.modalStep = 1;
				this.selectedPluginModal = '';
				this.selectedHook = null;
				this.hookSearchQuery = '';
				this.scenarioForm = {
					hook_name: '',
					description: '',
					scenario_name: '',
					scenario_notes: '',
					severity: 'info',
					enabled: true,
					send_push: true,
					send_mqtt: true,
					plugin_key: '',
					plugin_name: '',
					hook_meta: null,
					conditions: []
				};
				this.modalOpen = true;
			},
			
			/**
			 * Open modal for editing existing scenario
			 */
			openEditModal: function(index) {
				this.editingIndex = index;
				const hook = this.hooks[index];
				
				// Use stored hook_meta if available, otherwise look it up
				let hookMeta = hook.hook_meta || null;
				if (!hookMeta) {
					hookMeta = this.findHookMeta(hook.hook_name);
				}
				
				this.scenarioForm = {
					...hook,
					severity: normalizeSeverity(hook && hook.severity),
					enabled: normalizeEnabled(hook && hook.enabled),
					send_push: (hook && typeof hook.send_push !== 'undefined') ? normalizeEnabled(hook.send_push) : true,
					send_mqtt: (hook && typeof hook.send_mqtt !== 'undefined') ? normalizeEnabled(hook.send_mqtt) : true,
					conditions: Array.isArray(hook && hook.conditions) ? JSON.parse(JSON.stringify(hook.conditions)) : [],
					hook_meta: hookMeta,
					plugin_key: hook && hook.plugin_key ? hook.plugin_key : this.getScenarioPluginKey(hook),
					plugin_name: hook && hook.plugin_name ? hook.plugin_name : this.getScenarioPluginName(hook)
				};
				this.modalStep = 3;
				this.modalOpen = true;
			},
			
			/**
			 * Select plugin in modal and move to step 2
			 */
			selectPlugin: function(pluginKey) {
				const plugin = this.availablePlugins[pluginKey];
				
				// Check if plugin is active
				if (plugin.file && !this.pluginActiveStatus[pluginKey]) {
					alert('This plugin is not active. Please activate it first.');
					return;
				}
				
				this.selectedPluginModal = pluginKey;
				this.hookSearchQuery = '';
				this.modalStep = 2;
			},
			
			/**
			 * Select hook in modal and move to step 3
			 */
			selectHook: function(hookName, description) {
				const label = getHookLabel(description);
				this.selectedHook = {hookName, description: label};
				this.scenarioForm.hook_name = hookName;
				this.scenarioForm.description = label;
				this.scenarioForm.scenario_name = label || hookName;
				this.scenarioForm.hook_meta = (description && typeof description === 'object') ? description : null;
				this.scenarioForm.severity = normalizeSeverity(this.scenarioForm && this.scenarioForm.severity);
				if (this.selectedPluginModal) {
					const selectedPlugin = this.availablePlugins ? this.availablePlugins[this.selectedPluginModal] : null;
					this.scenarioForm.plugin_key = this.selectedPluginModal;
					this.scenarioForm.plugin_name = selectedPlugin && selectedPlugin.name ? selectedPlugin.name : '';
				}
				// Initialize empty conditions array for hooks with args
				if (!Array.isArray(this.scenarioForm.conditions)) {
					this.scenarioForm.conditions = [];
				}
				this.modalStep = 3;
			},
			
			/**
			 * Save scenario (add or update)
			 */
			saveScenario: function() {
				// Validate
				if (!this.scenarioForm.hook_name) {
					alert('Please select a hook');
					return;
				}
				
				if (!this.scenarioForm.scenario_name) {
					alert('Please enter a scenario name');
					return;
				}
				
				// Save (keep hook_meta for conditions support)
				const sanitizedConditions = Array.isArray(this.scenarioForm && this.scenarioForm.conditions)
					? this.scenarioForm.conditions
						.filter((c) => c && typeof c === 'object')
						.map((c) => ({
							field: (typeof c.field === 'string') ? c.field : '',
							operator: (typeof c.operator === 'string') ? c.operator : '=',
							value: (typeof c.value === 'string' || typeof c.value === 'number') ? String(c.value) : ''
						}))
					: [];

				const sanitizedScenario = {
					...this.scenarioForm,
					severity: normalizeSeverity(this.scenarioForm && this.scenarioForm.severity),
					enabled: normalizeEnabled(this.scenarioForm && this.scenarioForm.enabled),
					send_push: normalizeEnabled(this.scenarioForm && this.scenarioForm.send_push),
					send_mqtt: normalizeEnabled(this.scenarioForm && this.scenarioForm.send_mqtt),
					conditions: sanitizedConditions,
					plugin_key: this.scenarioForm && this.scenarioForm.plugin_key ? this.scenarioForm.plugin_key : this.getScenarioPluginKey(this.scenarioForm),
					plugin_name: this.scenarioForm && this.scenarioForm.plugin_name ? this.scenarioForm.plugin_name : this.getScenarioPluginName(this.scenarioForm)
				};

				if (this.editingIndex !== null) {
					this.hooks[this.editingIndex] = sanitizedScenario;
				} else {
					this.hooks.push(sanitizedScenario);
				}
				
				this.modalOpen = false;
				
				// Trigger form submission
				this.$nextTick(() => {
					this.submitForm();
				});
			},
			
			/**
			 * Remove hook/scenario
			 */
			removeHook: function(index) {
				if (confirm('Are you sure you want to delete this scenario?')) {
					this.hooks.splice(index, 1);
					
					// Trigger form submission
					this.$nextTick(() => {
						this.submitForm();
					});
				}
			},
			
			/**
			 * Submit the settings form programmatically
			 */
			submitForm: function() {
				const form = document.querySelector('form[action="options.php"]');
				if (!form) {
					console.error('Settings form not found');
					return;
				}

				try {
					window.dispatchEvent(new CustomEvent('notificator:save:state', { detail: { state: 'saving', suppressToast: true } }));
				} catch (e) {
					// no-op
				}

				const ajaxUrl = window.notificatorCompanionData && window.notificatorCompanionData.ajaxUrl ? window.notificatorCompanionData.ajaxUrl : null;
				const ajaxAction = window.notificatorCompanionData && window.notificatorCompanionData.actions ? window.notificatorCompanionData.actions.saveSettings : null;
				const ajaxNonce = window.notificatorCompanionData && window.notificatorCompanionData.nonces ? window.notificatorCompanionData.nonces.saveSettings : null;

				// Fallback to full submit if AJAX is not available.
				if (!ajaxUrl || !ajaxAction || !ajaxNonce) {
					const submitBtn = document.createElement('button');
					submitBtn.type = 'submit';
					submitBtn.style.display = 'none';
					form.appendChild(submitBtn);
					submitBtn.click();
					submitBtn.remove();
					return;
				}

				var toast = window.notificatorShowToast
					? window.notificatorShowToast('Saving…', 'info', 0)
					: null;

				const formData = new FormData(form);
				formData.append('action', ajaxAction);
				formData.append('nonce', ajaxNonce);

				fetch(ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
					headers: { 'Accept': 'application/json' }
				})
				.then(async (response) => {
					let data = null;
					try {
						data = await response.json();
					} catch (e) {
						data = null;
					}

														if (response.ok && data && data.success) {
														if (window.notificatorUpdateToast) {
															window.notificatorUpdateToast(toast, 'Saved', 'success', 1500);
														} else {
															window.notificatorShowToast && window.notificatorShowToast('Saved', 'success', 1500);
														}
						try {
							window.dispatchEvent(new CustomEvent('notificator:save:state', { detail: { state: 'saved', suppressToast: true } }));
						} catch (e) {
							// no-op
						}
						return;
					}

					const message = data && data.data && data.data.message ? data.data.message : 'Save failed';
													if (window.notificatorUpdateToast) {
														window.notificatorUpdateToast(toast, 'Error: ' + message, 'error', 2500);
													} else {
														window.notificatorShowToast && window.notificatorShowToast('Error: ' + message, 'error', 2500);
													}
					try {
						window.dispatchEvent(new CustomEvent('notificator:save:state', { detail: { state: 'error', message, suppressToast: true } }));
					} catch (e) {
						// no-op
					}
				})
				.catch((error) => {
					console.error('Save error:', error);
													if (window.notificatorUpdateToast) {
														window.notificatorUpdateToast(toast, 'Error: Save failed', 'error', 2500);
													} else {
														window.notificatorShowToast && window.notificatorShowToast('Error: Save failed', 'error', 2500);
													}
					try {
						window.dispatchEvent(new CustomEvent('notificator:save:state', { detail: { state: 'error', message: 'Save failed', suppressToast: true } }));
					} catch (e) {
						// no-op
					}
				});
			},
			
			/**
			 * Get filtered hooks based on search query
			 */
			get filteredHooks() {
				if (!this.searchQuery) {
					return this.hooks;
				}
				
				const query = this.searchQuery.toLowerCase();
				return this.hooks.filter(hook => 
					hook.hook_name.toLowerCase().includes(query) ||
					hook.description.toLowerCase().includes(query) ||
					(hook.scenario_name && hook.scenario_name.toLowerCase().includes(query))
				);
			}
		};
		return builder;
	};

	/**
	 * Plugin Scanner
	 */
	window.startPluginScan = function() {
		const $btn = $('#scan-plugins-btn, #auto-scan-btn');
		const $modal = $('#scan-modal');
		const $progress = $('#scan-progress');
		const $progressBar = $('#scan-progress-bar');
		const $currentPlugin = $('#scan-current-plugin');
		const $results = $('#scan-results');
		const includeInactive = $('#notificator_companion_include_inactive_plugins').is(':checked');
		
		// Show modal
		$modal.removeClass('hidden');
		$progress.removeClass('hidden');
		$results.addClass('hidden');
		
		// Disable button
		$btn.prop('disabled', true);
		
		// Start scan
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'notificator_companion_refresh_hooks',
				nonce: $('#notificator_companion_scan_nonce').val(),
				include_inactive: includeInactive ? 1 : 0
			},
			xhr: function() {
				const xhr = new window.XMLHttpRequest();
				
				// Track progress (if server supports it)
				xhr.addEventListener('progress', function(e) {
					if (e.lengthComputable) {
						const percentComplete = (e.loaded / e.total) * 100;
						$progressBar.css('width', percentComplete + '%');
					}
				}, false);
				
				return xhr;
			},
			success: function(response) {
				$progress.addClass('hidden');
				$results.removeClass('hidden');
				
				if (response.success) {
					$('#scan-success-message').removeClass('hidden');
					$('#scan-error-message').addClass('hidden');
					$('#total-plugins').text(response.data.plugins_scanned || 0);
					$('#total-hooks').text(response.data.hooks_found || 0);

					// Update scenario builder state without reloading the page.
					if (window.notificatorScenarioBuilder && response.data) {
						if (response.data.available_plugins) {
							window.notificatorScenarioBuilder.availablePlugins = response.data.available_plugins;
						}
						if (response.data.plugin_active_status) {
							window.notificatorScenarioBuilder.pluginActiveStatus = response.data.plugin_active_status;
						}
						if (response.data.hook_active_status) {
							window.notificatorScenarioBuilder.hookActiveStatus = response.data.hook_active_status;
						}
					}

					// Auto-close the modal shortly after success.
					setTimeout(function() {
						window.closeScanModal();
					}, 1200);
				} else {
					$('#scan-success-message').addClass('hidden');
					$('#scan-error-message').removeClass('hidden').text(response.data.message || 'Scan failed');
				}
			},
			error: function(xhr, status, error) {
				$progress.addClass('hidden');
				$results.removeClass('hidden');
				$('#scan-success-message').addClass('hidden');
				$('#scan-error-message').removeClass('hidden').text('Network error: ' + error);
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	};

	/**
	 * Close scan modal
	 */
	window.closeScanModal = function() {
		$('#scan-modal').addClass('hidden');
		$('#scan-progress').removeClass('hidden');
		$('#scan-results').addClass('hidden');
		$('#scan-success-message').addClass('hidden');
		$('#scan-error-message').addClass('hidden');
		$('#scan-progress-bar').css('width', '0%');
	};

	/**
	 * Initialize on DOM ready
	 */
	$(document).ready(function() {
		// Bind scan button
		$('#scan-plugins-btn, #auto-scan-btn').on('click', function(e) {
			e.preventDefault();
			window.startPluginScan();
		});
		
		// Close modal on backdrop click
		$('#scan-modal').on('click', function(e) {
			if ($(e.target).is('#scan-modal')) {
				window.closeScanModal();
			}
		});
		
		// Handle test notification per API key
		$(document).on('click', '.notificator-test-api-key', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const $row = $btn.closest('.notificator-api-key-row');
			const $input = $row.find('input[name*="[api_keys]"]');
			const apiKey = $input.val() ? String($input.val()).trim() : '';
			if (!apiKey) {
				alert('❌ ' + 'Please enter an API key first.');
				return;
			}
			const originalText = $btn.html();
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sending...');
			
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'notificator_companion_test',
					nonce: $('#notificator_companion_test_nonce').val(),
					api_key: apiKey
				},
				success: function(response) {
					if (response.success) {
						alert('✅ ' + response.data.message);
					} else {
						alert('❌ ' + (response.data.message || 'Test failed'));
					}
				},
				error: function() {
					alert('❌ Network error occurred');
				},
				complete: function() {
					$btn.prop('disabled', false).html(originalText);
				}
			});
		});
	});

})(jQuery as any);

// --- Notificator Admin UI Enhancements (layout + saving + navigation) ---
(function () {
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	function onReady(fn: () => void): void {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function showToast(message: string, type?: string, _duration?: number): unknown {
		if (window.notificatorToast && typeof window.notificatorToast.show === 'function') {
			window.notificatorToast.show(message, type || 'info');
			return null;
		}
		if (window.alert) {
			window.alert(message);
		}
		return null;
	}

	function updateToast(_toast: unknown, message: string, type?: string, _duration?: number): unknown {
		return showToast(message, type || 'info');
	}

	window.notificatorShowToast = showToast;
	window.notificatorUpdateToast = updateToast;

	function setWpAdminBarHeightVar(): void {
		var bar = document.getElementById('wpadminbar');
		var height = bar && bar.offsetHeight ? bar.offsetHeight : 0;
		document.documentElement.style.setProperty('--notificator-wpbar-h', String(height) + 'px');
	}

	function initApiKeysRepeatableFields(): void {
		var container = document.getElementById('notificator-api-keys');
		var addBtn = document.getElementById('notificator-add-api-key');
		if (!container || !addBtn) return;

		function updateRemoveButtons(): void {
			var rows = container.querySelectorAll('.notificator-api-key-row');
			var allowRemove = container.getAttribute('data-has-api-key') === '1';
			rows.forEach(function (row) {
				var remove = row.querySelector('.notificator-remove-api-key');
				if (!remove) return;
				if (!allowRemove) {
					remove.setAttribute('hidden', '');
					return;
				}
				remove.removeAttribute('hidden');
			});
		}

		function createRow(): HTMLDivElement {
			var row = document.createElement('div');
			row.className = 'flex items-center gap-2 notificator-api-key-row';

			var input = document.createElement('input');
			input.type = 'password';
			input.name = 'notificator_companion_settings[api_keys][]';
			input.placeholder = 'wpnotif_...';
			input.className = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500';

			var nickname = document.createElement('input');
			nickname.type = 'text';
			nickname.name = 'notificator_companion_settings[api_key_nicknames][]';
			nickname.placeholder = 'Nickname';
			nickname.className = 'px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[140px]';

			var test = document.createElement('button');
			test.type = 'button';
			test.className = 'btn-secondary notificator-test-api-key';
			test.setAttribute('aria-label', 'Send test notification');
			test.setAttribute('data-notificator-unlock', 'test-connection');
			var testIcon = document.createElement('span');
			testIcon.className = 'dashicons dashicons-yes-alt';
			test.appendChild(testIcon);
			test.appendChild(document.createTextNode(' Test'));

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'btn-secondary btn-secondary--danger notificator-remove-api-key';
			var icon = document.createElement('span');
			icon.className = 'dashicons dashicons-trash';
			remove.appendChild(icon);
			remove.appendChild(document.createTextNode(' Remove'));

			row.appendChild(input);
			row.appendChild(nickname);
			row.appendChild(test);
			row.appendChild(remove);
			return row;
		}

		addBtn.addEventListener('click', function () {
			container.appendChild(createRow());
			updateRemoveButtons();
		});

		container.addEventListener('click', function (e) {
			var target = e.target;
			if (!(target instanceof Element)) return;
			var removeBtn = target.closest('.notificator-remove-api-key');
			if (!removeBtn) return;

			var row = removeBtn.closest('.notificator-api-key-row');
			if (!row) return;

			row.remove();
			updateRemoveButtons();
		});

		document.addEventListener('notificator:api-keys:updated', function () {
			container.setAttribute('data-has-api-key', '1');
			updateRemoveButtons();
		});

		updateRemoveButtons();
	}

	function initDisabledControlsGuard(): void {
		document.addEventListener('click', function (event) {
			var target = event && event.target ? event.target : null;
			if (!(target instanceof Element)) return;
			var disabledWrap = target.closest('[data-notificator-disable][data-notificator-disabled]');
			if (!disabledWrap) return;
			event.preventDefault();
			event.stopPropagation();
		});
	}

	function initThemeToggle(): void {
		var toggle = document.getElementById('notificator-theme-toggle');
		if (!toggle) return;

		var storageKey = 'notificatorAdminTheme';
		var prefersDark = false;
		if (window.matchMedia) {
			prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
		}

		var stored = null;
		try {
			stored = localStorage.getItem(storageKey);
		} catch (e) {
			stored = null;
		}

		var isDark = stored ? stored === 'dark' : prefersDark;

		function applyTheme(dark) {
			document.documentElement.classList.toggle('notificator-theme-dark', dark);
			document.body.classList.toggle('notificator-theme-dark', dark);
			toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
			var label = dark ? 'Switch to light theme' : 'Switch to dark theme';
			toggle.setAttribute('aria-label', label);
			toggle.setAttribute('title', label);
			var icon = toggle.querySelector('[data-theme-icon]');
			if (icon) {
				icon.textContent = dark ? '☀️' : '🌙';
			}
		}

		applyTheme(isDark);
		toggle.addEventListener('click', function () {
			isDark = !isDark;
			applyTheme(isDark);
			try {
				localStorage.setItem(storageKey, isDark ? 'dark' : 'light');
			} catch (e) {
				// Ignore storage errors.
			}
		});
	}

	function initGlobalSaveUx(): void {
		var unlockApplied = false;

		function setSaveStatus(state: string, message?: string): void {
			var nextState = state || 'idle';
			if (nextState === 'saving') {
				showToast('Saving…', 'info', 1200);
				return;
			}
			if (nextState === 'saved') {
				showToast('Saved', 'success', 1500);
				return;
			}
			if (nextState === 'error') {
				showToast(message ? ('Error: ' + message) : 'Save failed', 'error', 2500);
			}
		}

		function hasApiKeyInput(): boolean {
			var inputs = Array.prototype.slice.call(document.querySelectorAll('input[name*="[api_keys]"]'));
			if (inputs.some(function (input) { return input.value && input.value.trim(); })) {
				return true;
			}
			var legacy = document.querySelector('input[name*="[api_key]"]');
			return !!(legacy && legacy.value && legacy.value.trim());
		}

		function bindScanButtons(): void {
			var buttons = document.querySelectorAll('#scan-plugins-btn, #auto-scan-btn');
			if (!buttons.length) return;
			buttons.forEach(function (btn) {
				if (!btn || btn.getAttribute('data-notificator-scan-bound') === '1') {
					return;
				}
				btn.setAttribute('data-notificator-scan-bound', '1');
				btn.addEventListener('click', function (e) {
					e.preventDefault();
					if (typeof window.startPluginScan === 'function') {
						window.startPluginScan();
					}
				});
			});
		}

		function unlockUi(): void {
			if (unlockApplied) {
				return;
			}
			unlockApplied = true;

			Array.prototype.slice.call(document.querySelectorAll('[data-notificator-lock]')).forEach(function (el) {
				el.setAttribute('hidden', '');
			});
			Array.prototype.slice.call(document.querySelectorAll('[data-notificator-unlock]')).forEach(function (el) {
				el.removeAttribute('hidden');
			});
			var lockedSections = document.querySelector('[data-notificator-locked-sections]');
			if (lockedSections) {
				lockedSections.remove();
			}
			var unlockedSections = document.querySelector('[data-notificator-unlocked-sections]');
			if (unlockedSections) {
				unlockedSections.removeAttribute('hidden');
			}
			var logWrap = document.querySelector('[data-notificator-log-wrapper]');
			if (logWrap) {
				logWrap.removeAttribute('hidden');
			}

			var scanStep = document.querySelector('[data-notificator-step="scan"]');
			if (scanStep) {
				var lockedClass = scanStep.getAttribute('data-locked-class') || 'is-disabled';
				scanStep.classList.remove(lockedClass);
				var badge = scanStep.querySelector('[data-notificator-step-badge]');
				if (badge) {
					var readyClass = badge.getAttribute('data-class-ready');
					var lockedBadge = badge.getAttribute('data-class-locked');
					if (lockedBadge) {
						badge.classList.remove(lockedBadge);
					}
					if (readyClass) {
						badge.classList.add(readyClass);
					}
				}
			}

			var apiStepStatus = document.querySelector('[data-notificator-step="api"] [data-notificator-step-status]');
			if (apiStepStatus) {
				var doneLabel = apiStepStatus.getAttribute('data-status-done');
				if (doneLabel) {
					apiStepStatus.textContent = doneLabel;
				}
			}
			var scanStepStatus = document.querySelector('[data-notificator-step="scan"] [data-notificator-step-status]');
			if (scanStepStatus) {
				var readyLabel = scanStepStatus.getAttribute('data-status-ready');
				if (readyLabel) {
					scanStepStatus.textContent = readyLabel;
				}
			}

			var scanTool = document.getElementById('notificator-scan-plugins-tool');
			if (scanTool) {
				scanTool.disabled = false;
				scanTool.setAttribute('aria-disabled', 'false');
			}
			Array.prototype.slice.call(document.querySelectorAll('[data-notificator-disable]')).forEach(function (el) {
				el.removeAttribute('disabled');
				el.removeAttribute('aria-disabled');
				el.removeAttribute('data-notificator-disabled');
				el.classList.remove('is-disabled');
			});
			try {
				document.dispatchEvent(new CustomEvent('notificator:api-keys:updated'));
			} catch (e) {
				// no-op
			}
			bindScanButtons();
		}

		function maybeUnlockUi(): void {
			if (hasApiKeyInput()) {
				unlockUi();
			}
		}

		try {
			window.addEventListener('notificator:save:state', function (e) {
				var detail = e && e.detail ? e.detail : {};
				if (!detail.suppressToast) {
					setSaveStatus(detail.state || 'idle', detail.message);
				}
				if (detail.state === 'saved') {
					maybeUnlockUi();
				}
			});
		} catch (e) {
			// no-op
		}

		bindScanButtons();

		// Intercept manual form submit (Save Settings button) to avoid reload.
		document.addEventListener('submit', function (e) {
			var form = e && e.target;
			if (!form || !(form instanceof HTMLFormElement)) return;
			if (!form.matches('form[action="options.php"]')) return;

			var ajaxUrl = window.notificatorCompanionData && window.notificatorCompanionData.ajaxUrl ? window.notificatorCompanionData.ajaxUrl : null;
			var ajaxAction = window.notificatorCompanionData && window.notificatorCompanionData.actions ? window.notificatorCompanionData.actions.saveSettings : null;
			var ajaxNonce = window.notificatorCompanionData && window.notificatorCompanionData.nonces ? window.notificatorCompanionData.nonces.saveSettings : null;
			if (!ajaxUrl || !ajaxAction || !ajaxNonce) return; // allow normal submit

			e.preventDefault();
			setSaveStatus('saving');

			var formData = new FormData(form);
			formData.append('action', ajaxAction);
			formData.append('nonce', ajaxNonce);

			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' }
			})
				.then(function (resp) {
					return resp.json().catch(function () { return null; }).then(function (data) {
						if (resp.ok && data && data.success) {
							setSaveStatus('saved');
							maybeUnlockUi();
							return;
						}

						var message = (data && data.data && data.data.message) ? data.data.message : 'Save failed';
						setSaveStatus('error', message);
					});
				})
				.catch(function () {
					setSaveStatus('error', 'Save failed');
				});
		}, true);
	}

	function initSectionNavHighlight(): void {
		var links = Array.prototype.slice.call(document.querySelectorAll('[data-notificator-nav]'));
		if (!links.length) return;

		var sections = links
			.map(function (link) {
				var target = link.getAttribute('data-notificator-nav');
				return target ? document.querySelector(target) : null;
			})
			.filter(Boolean);
		if (!sections.length) return;

		function setActiveLink(active: Element | null): void {
			links.forEach(function (link) {
				var isActive = link === active;
				link.classList.toggle('is-active', isActive);
				if (isActive) link.setAttribute('aria-current', 'true');
				else link.removeAttribute('aria-current');
			});
		}

		if (!('IntersectionObserver' in window)) {
			setActiveLink(links[0]);
			return;
		}

		var sectionToLink = new Map();
		links.forEach(function (link) {
			var target = link.getAttribute('data-notificator-nav');
			var section = target ? document.querySelector(target) : null;
			if (section) sectionToLink.set(section, link);
		});

		var observer = new IntersectionObserver(
			function (entries) {
				var best = null;
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) return;
					if (!best || entry.intersectionRatio > best.intersectionRatio) {
						best = entry;
					}
				});
				if (best && sectionToLink.has(best.target)) {
					setActiveLink(sectionToLink.get(best.target));
				}
			},
			{
				root: null,
				rootMargin: '-140px 0px -70% 0px',
				threshold: [0.1, 0.25, 0.5]
			}
		);

		sections.forEach(function (section) {
			observer.observe(section);
		});
	}

	function initSmoothAnchorScroll(): void {
		document.addEventListener('click', function (e) {
			var target = e && e.target ? e.target : null;
			if (!(target instanceof Element)) return;

			var link = target.closest('a.notificator-nav-link[href^="#"]');
			if (!link) return;

			var href = link.getAttribute('href');
			if (!href || href === '#') return;

			var el = document.querySelector(href);
			if (!el) return;

			e.preventDefault();
			try {
				el.scrollIntoView({ behavior: 'smooth', block: 'start' });
			} catch (err) {
				el.scrollIntoView();
			}

			try {
				if (window.history && window.history.pushState) {
					window.history.pushState(null, '', href);
				}
			} catch (err2) {
				// no-op
			}
		}, true);
	}

	function initTopBarScenarioButton(): void {
		var btn = document.getElementById('notificator-add-scenario-top');
		if (!btn) return;

		btn.addEventListener('click', function () {
			var builder = document.getElementById('notificator-builder');
			if (builder) {
				try {
					builder.scrollIntoView({ behavior: 'smooth', block: 'start' });
				} catch (err) {
					builder.scrollIntoView();
				}
			}

			try {
				window.dispatchEvent(new CustomEvent('notificator:add-scenario'));
			} catch (err2) {
				// no-op
			}
		});
	}

	function initThrottleStatus(): void {
		var input = document.getElementById('notificator-throttle-seconds') as HTMLInputElement | null;
		var status = document.querySelector('[data-notificator-throttle-status]') as HTMLElement | null;
		if (!input || !status) return;

		function renderLabel(rawValue?: string): void {
			var parsed = parseInt(typeof rawValue === 'string' ? rawValue : input.value || '0', 10);
			if (!Number.isFinite(parsed) || parsed < 0) parsed = 0;

			var disabledLabel = status.getAttribute('data-disabled-label') || 'Disabled';
			var currentTemplate = status.getAttribute('data-current-template') || 'Current: %ds';
			status.textContent = parsed > 0 ? currentTemplate.replace('%d', String(parsed)) : disabledLabel;
		}

		input.addEventListener('input', function () {
			renderLabel();
		});

		window.addEventListener('notificator:save:state', function (e) {
			var detail = e && (e as CustomEvent).detail ? (e as CustomEvent).detail : {};
			if (detail.state === 'saved') {
				renderLabel();
			}
		});

		renderLabel();
	}

	function initScenarioImportExport(): void {
		var exportBtn = document.getElementById('notificator-export-scenarios');
		var importBtn = document.getElementById('notificator-import-scenarios');
		var modal = document.getElementById('notificator-import-modal');
		var closeBtn = document.getElementById('notificator-import-modal-close');
		var cancelBtn = document.getElementById('notificator-import-cancel');
		var confirmBtn = document.getElementById('notificator-import-confirm');
		var fileInput = document.getElementById('notificator-import-file');
		var fileHint = document.getElementById('notificator-import-file-hint');
		var statusEl = document.getElementById('notificator-import-status');
		var replaceWarning = document.getElementById('notificator-import-replace-warning');
		var confirmReplace = document.getElementById('notificator-import-confirm-replace');
		var modeInputs = document.querySelectorAll('input[name="notificator-import-mode"]');

		var data = window.notificatorCompanionData || {};
		var actions = data.actions || {};
		var nonces = data.nonces || {};
		var ajaxUrl = (data.ajaxUrl || window.ajaxurl || '');
		var exportAction = actions.exportHooks || 'notificator_companion_export_hooks';
		var exportNonce = nonces.exportHooks || '';
		var importAction = actions.importHooks || 'notificator_companion_import_hooks';
		var importNonce = nonces.importHooks || '';

		function closeContainingMenu(el: Element | null): void {
			if (!el || !(el instanceof Element)) return;
			var details = el.closest('details');
			if (details) {
				details.removeAttribute('open');
			}
		}

		function normalizeEnabled(value: unknown): boolean {
			if (value === true || value === 1 || value === '1') return true;
			if (value === false || value === 0 || value === '0' || value === null || typeof value === 'undefined') return false;
			return !!value;
		}

		function normalizeSeverity(value: unknown): string {
			var severity = (typeof value === 'string' ? value : '').toLowerCase();
			return ['info', 'warning', 'critical'].includes(severity) ? severity : 'info';
		}

		function normalizeHooks(hooks: unknown): AnyRecord[] {
			if (!Array.isArray(hooks)) return [];
			return hooks.map(function (hook): AnyRecord {
				if (!hook || typeof hook !== 'object') return hook as AnyRecord;
				var cloned = Object.assign({}, hook);
				cloned.enabled = normalizeEnabled(cloned.enabled);
				cloned.severity = normalizeSeverity(cloned.severity);
				return cloned as AnyRecord;
			});
		}

		function setStatus(text: string, kind?: string): void {
			if (!statusEl) return;
			statusEl.hidden = false;
			statusEl.textContent = text || '';
			statusEl.className = 'text-sm ' + (kind === 'error' ? 'text-red-700' : kind === 'success' ? 'text-green-700' : 'text-gray-700');
		}

		function getSelectedMode(): string {
			var selected = document.querySelector('input[name="notificator-import-mode"]:checked');
			return selected ? selected.value : 'merge';
		}

		function updateReplaceUi(): void {
			var mode = getSelectedMode();
			if (!replaceWarning) return;
			replaceWarning.classList.toggle('hidden', mode !== 'replace');
			if (mode !== 'replace' && confirmReplace) {
				confirmReplace.checked = false;
			}
		}

		function openModal(): void {
			if (!modal) return;
			modal.classList.remove('hidden');
			updateReplaceUi();
			setStatus('', '');
			if (statusEl) statusEl.hidden = true;
		}

		function closeModal(): void {
			if (!modal) return;
			modal.classList.add('hidden');
			if (fileInput) fileInput.value = '';
			if (fileHint) fileHint.textContent = 'Choose a file exported with “Export Scenarios”.';
			if (confirmReplace) confirmReplace.checked = false;
			if (statusEl) {
				statusEl.hidden = true;
				statusEl.textContent = '';
			}
		}

		if (modeInputs && modeInputs.length) {
			modeInputs.forEach(function (el) {
				el.addEventListener('change', updateReplaceUi);
			});
		}

		if (importBtn && modal) {
			importBtn.addEventListener('click', function () {
				closeContainingMenu(importBtn);
				openModal();
			});
		}

		if (closeBtn) closeBtn.addEventListener('click', closeModal);
		if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

		if (modal) {
			modal.addEventListener('click', function (e) {
				if (e && e.target === modal) {
					closeModal();
				}
			});
		}

		if (fileInput && fileHint) {
			fileInput.addEventListener('change', function () {
				var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
				fileHint.textContent = file ? ('Selected: ' + file.name) : 'Choose a file exported with “Export Scenarios”.';
			});
		}

		if (exportBtn) {
			exportBtn.addEventListener('click', function () {
				closeContainingMenu(exportBtn);
				if (!ajaxUrl) {
					alert('Missing ajax URL');
					return;
				}
				if (!exportNonce) {
					alert('Missing export nonce');
					return;
				}

				exportBtn.disabled = true;
				var original = exportBtn.innerHTML;
				exportBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Exporting...';

				var body = new URLSearchParams();
				body.set('action', exportAction);
				body.set('nonce', exportNonce);

				fetch(ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				})
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (!json || !json.success) {
							var msg = json && json.data && json.data.message ? json.data.message : 'Export failed';
							throw new Error(msg);
						}
						var payload = json.data && json.data.payload ? json.data.payload : null;
						var fileName = json.data && json.data.file_name ? json.data.file_name : 'notificator-scenarios.json';
						var text = JSON.stringify(payload, null, 2);

						var blob = new Blob([text], { type: 'application/json' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = fileName;
						document.body.appendChild(a);
						a.click();
						a.remove();
						URL.revokeObjectURL(url);
					})
					.catch(function (err) {
						alert('❌ ' + (err && err.message ? err.message : 'Export failed'));
					})
					.finally(function () {
						exportBtn.disabled = false;
						exportBtn.innerHTML = original;
					});
			});
		}

		if (confirmBtn) {
			confirmBtn.addEventListener('click', function () {
				if (!ajaxUrl) {
					setStatus('Missing ajax URL', 'error');
					return;
				}
				if (!importNonce) {
					setStatus('Missing import nonce', 'error');
					return;
				}

				var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
				if (!file) {
					setStatus('Please choose a JSON file to import.', 'error');
					return;
				}

				var mode = getSelectedMode();
				if (mode === 'replace' && confirmReplace && !confirmReplace.checked) {
					setStatus('Please confirm replacement to continue.', 'error');
					return;
				}

				confirmBtn.disabled = true;
				var original = confirmBtn.innerHTML;
				confirmBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Importing...';
				setStatus('Reading file...', '');

				var reader = new FileReader();
				reader.onload = function () {
					var text = typeof reader.result === 'string' ? reader.result : '';
					text = (text || '').trim();
					if (!text) {
						setStatus('Selected file is empty.', 'error');
						confirmBtn.disabled = false;
						confirmBtn.innerHTML = original;
						return;
					}

					setStatus('Uploading scenarios...', '');
					var body = new URLSearchParams();
					body.set('action', importAction);
					body.set('nonce', importNonce);
					body.set('mode', mode);
					body.set('payload', text);

					fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString()
					})
						.then(function (r) { return r.json(); })
						.then(function (json) {
							if (!json || !json.success) {
								var msg = json && json.data && json.data.message ? json.data.message : 'Import failed';
								throw new Error(msg);
							}
							var hooks = json.data && json.data.hooks ? json.data.hooks : [];
							if (window.notificatorScenarioBuilder) {
								window.notificatorScenarioBuilder.hooks = normalizeHooks(hooks);
							}
							setStatus(json.data && json.data.message ? json.data.message : 'Imported.', 'success');
							setTimeout(closeModal, 900);
						})
						.catch(function (err) {
							setStatus(err && err.message ? err.message : 'Import failed', 'error');
						})
						.finally(function () {
							confirmBtn.disabled = false;
							confirmBtn.innerHTML = original;
						});
				};
				reader.onerror = function () {
					setStatus('Failed to read file.', 'error');
					confirmBtn.disabled = false;
					confirmBtn.innerHTML = original;
				};
				reader.readAsText(file);
			});
		}
	}

	onReady(function () {
		setWpAdminBarHeightVar();
		window.addEventListener('resize', setWpAdminBarHeightVar);
		initApiKeysRepeatableFields();
		initDisabledControlsGuard();
		initThrottleStatus();
		initThemeToggle();
		initGlobalSaveUx();
		initSmoothAnchorScroll();
		initTopBarScenarioButton();
		initScenarioImportExport();
		initSectionNavHighlight();
	});
})();
