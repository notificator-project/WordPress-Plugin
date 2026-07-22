// @ts-nocheck -- Legacy Alpine component; migrate feature slices to typed modules incrementally.
/**
 * Admin Scenarios JavaScript
 *
 * Owns the legacy Alpine scenario-builder state and the DOM adapters used by
 * WordPress AJAX, discovery scanning, import/export, and workspace controls.
 * New standalone behavior belongs in assets/src and should expose a small,
 * typed browser API instead of adding another responsibility to this module.
 *
 * @package NotificatorCompanion
 * @since 1.1.0
 */

type AnyRecord = Record<string, any>;
type AnyFn = (...args: any[]) => any;

(function ($: any) {
	'use strict';

	/**
	 * Initialize Alpine.js data for scenario builder
	 */
	window.initScenarioBuilder = function (
		hooks: AnyRecord[] = [],
		availablePlugins: AnyRecord = {},
		pluginActiveStatus: AnyRecord = {},
		hookActiveStatus: AnyRecord = {},
		_optionName?: string,
		hasRemoteDelivery: boolean = false
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
			const hasSendDashboard = hook && typeof hook.send_dashboard !== 'undefined';
			const hasSendPush = hook && typeof hook.send_push !== 'undefined';
			const hasSendMqtt = hook && typeof hook.send_mqtt !== 'undefined';
			return {
				...hook,
				enabled: normalizeEnabled(hook && hook.enabled),
				severity: normalizeSeverity(hook && hook.severity),
				send_dashboard: hasSendDashboard ? normalizeEnabled(hook && hook.send_dashboard) : true,
				send_push: hasRemoteDelivery && (hasSendPush ? normalizeEnabled(hook && hook.send_push) : true),
				send_mqtt: hasRemoteDelivery && (hasSendMqtt ? normalizeEnabled(hook && hook.send_mqtt) : true)
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
		const getHookDescription = (hookData: unknown): string => {
			if (!hookData || typeof hookData !== 'object' || !('description' in hookData)) return '';
			const described = hookData as { description?: unknown };
			return typeof described.description === 'string' ? described.description : '';
		};

		const friendlyFieldNames: AnyRecord = {
			arg_1: 'Event value',
			order_id: 'Order number',
			refund_id: 'Refund number',
			customer_id: 'Customer number',
			user_id: 'User number',
			post_id: 'Post number',
			comment_id: 'Comment number',
			product_id: 'Product number',
			form_id: 'Form number',
			entry_id: 'Entry number',
			old_status: 'Previous status',
			new_status: 'New status',
			status: 'Status',
			user_login: 'Username',
			username: 'Username',
			user_email: 'Email address',
			billing_email: 'Customer email',
			payment_method: 'Payment method',
			total: 'Order total',
			stock_quantity: 'Stock remaining',
			sku: 'SKU',
			post: 'Post or page',
			post_before: 'Previous post version',
			post_after: 'Updated post version',
			post_type: 'Content type',
			user: 'User account',
			order: 'Order details',
			product: 'Product details',
			comment: 'Comment details',
			contact_form: 'Contact form',
			form: 'Form details',
			entry: 'Submission details',
			role: 'New user role',
			old_roles: 'Previous user roles',
			option: 'Setting name',
			old_value: 'Previous value',
			value: 'New value',
			error: 'Error details',
			ip: 'IP address',
			url: 'Page address'
		};

		const humanizeFieldPart = (value: unknown): string => {
			const raw = typeof value === 'string' ? value.trim() : '';
			if (!raw) return 'Event information';
			if (friendlyFieldNames[raw]) return friendlyFieldNames[raw];
			const argMatch = raw.match(/^arg_(\d+)$/i);
			if (argMatch) return `Event value ${argMatch[1]}`;
			return raw.replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
		};

		const getFriendlyFieldLabel = (field: unknown): string => {
			const raw = typeof field === 'string' ? field : '';
			if (!raw) return 'Event information';
			if (friendlyFieldNames[raw]) return friendlyFieldNames[raw];
			const parts = raw.split('.');
			if (parts.length > 1) {
				const property = humanizeFieldPart(parts[parts.length - 1]);
				const parent = humanizeFieldPart(parts.slice(0, -1).join('.'));
				return `${property} (${parent})`;
			}
			return humanizeFieldPart(raw);
		};

		const getFriendlyFieldDescription = (field: unknown): string => {
			const raw = typeof field === 'string' ? field.toLowerCase() : '';
			if (!raw) return 'Information supplied by this event.';
			if (raw.includes('old_status')) return 'The status before this event happened.';
			if (raw.includes('new_status') || raw === 'status') return 'The status after this event happened.';
			if (raw.includes('email')) return 'The email address connected to this event.';
			if (raw.includes('total')) return 'The monetary total supplied by the event.';
			if (raw.includes('stock_quantity')) return 'How many units remain in stock.';
			if (raw.endsWith('_id') || raw.includes('.id')) return 'The WordPress or plugin record number for this item.';
			if (raw.includes('role')) return 'The access level assigned to the user.';
			if (raw.includes('error')) return 'The error message or failure details.';
			if (raw.includes('ip')) return 'The network address associated with the event.';
			if (raw.includes('url')) return 'The page or resource address associated with the event.';
			if (/order|product|post|user|comment|form|entry/.test(raw))
				return 'Details about the item involved in this event.';
			return 'A value supplied by WordPress when this event happens.';
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
				const icon =
					pluginData && pluginData.icon ? String(pluginData.icon) : getPluginFallbackIcon(pluginKey, pluginName);

				normalized[pluginKey] = {
					...pluginData,
					slug,
					icon
				};
			}

			return normalized;
		};

		const builder: AnyRecord = {
			init() {
				// Expose for external updaters (e.g. AJAX hook scanner)
				window.notificatorScenarioBuilder = this;
				const viewByHash = {
					'#discover': 'discover',
					'#notificator-discovery': 'discover',
					'#templates': 'templates',
					'#notificator-templates': 'templates',
					'#created-notifications': 'created',
					'#notificator-builder': 'created'
				};
				this.notificationView = viewByHash[window.location.hash] || 'created';
				this.templatePluginFilter = '__all__';
				this.templateCategoryFilter = '__all__';
				this.templateReadinessFilter = '__all__';
				this.onTemplatesPerPageChange();
			},
			selectedPlugin: '',
			hasRemoteDelivery: !!hasRemoteDelivery,
			notificationView: 'created',
			hooks: normalizedHooks,
			availablePlugins: normalizeAvailablePlugins(availablePlugins || {}),
			pluginActiveStatus: pluginActiveStatus || {},
			hookActiveStatus: hookActiveStatus || {},
			searchQuery: '',
			hookSearchQuery: '',
			hookResultsLimit: 50,
			templateSearchQuery: '',
			templatePluginFilter: '__all__',
			templateCategoryFilter: '__all__',
			templateReadinessFilter: '__all__',
			templatePage: 1,
			templatesPerPage: 12,
			modalOpen: false,
			modalStep: 1,
			modalError: '',
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
				send_dashboard: true,
				send_push: false,
				send_mqtt: false,
				plugin_key: '',
				plugin_name: '',
				hook_meta: null,
				conditions: []
			},

			setNotificationView: function (view) {
				const allowed = ['created', 'templates', 'discover'];
				this.notificationView = allowed.includes(view) ? view : 'created';
				const hashes = { created: '#created-notifications', templates: '#templates', discover: '#discover' };
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', hashes[this.notificationView]);
				}
				window.scrollTo({ top: 0, behavior: 'smooth' });
			},

			/**
			 * Get WooCommerce order statuses (for select dropdowns)
			 */
			getWooCommerceOrderStatusOptions: function () {
				if (
					typeof notificatorWooCommerceOrderStatuses !== 'undefined' &&
					Array.isArray(notificatorWooCommerceOrderStatuses)
				) {
					return notificatorWooCommerceOrderStatuses;
				}
				return [];
			},

			/**
			 * Get available fields for conditions.
			 * Includes hook arg names plus nested property paths from hook_meta.properties.
			 */
			getConditionFields: function () {
				const fields = [];
				const seen = {};

				const addField = function (value, label, description = '') {
					if (!value || seen[value]) {
						return;
					}
					seen[value] = true;
					fields.push({
						value,
						label: label || getFriendlyFieldLabel(value),
						description: description || getFriendlyFieldDescription(value)
					});
				};

				const hookMeta = this.scenarioForm && this.scenarioForm.hook_meta ? this.scenarioForm.hook_meta : null;
				const argNames = hookMeta && Array.isArray(hookMeta.arg_names) ? hookMeta.arg_names : [];
				argNames.forEach((argName) => {
					const hasProperties =
						hookMeta &&
						hookMeta.properties &&
						Array.isArray(hookMeta.properties[argName]) &&
						hookMeta.properties[argName].length > 0;
					if (!hasProperties) addField(argName, getFriendlyFieldLabel(argName), getFriendlyFieldDescription(argName));
				});

				// Add nested fields from properties map: { argName: [{name,label,...}] }
				if (hookMeta && hookMeta.properties && typeof hookMeta.properties === 'object') {
					for (const argKey in hookMeta.properties) {
						if (!Object.prototype.hasOwnProperty.call(hookMeta.properties, argKey)) continue;
						const props = hookMeta.properties[argKey];
						if (!Array.isArray(props)) continue;
						props.forEach((prop) => {
							if (!prop || !prop.name) return;
							const value = argKey + '.' + prop.name;
							const label = prop.label ? String(prop.label) : getFriendlyFieldLabel(value);
							addField(value, label, getFriendlyFieldDescription(value));
						});
					}
				}

				// Ensure currently set fields remain selectable (even if not in meta)
				if (Array.isArray(this.scenarioForm && this.scenarioForm.conditions)) {
					this.scenarioForm.conditions.forEach((condition) => {
						if (condition && condition.field) {
							addField(
								condition.field,
								getFriendlyFieldLabel(condition.field),
								getFriendlyFieldDescription(condition.field)
							);
						}
					});
				}

				return fields;
			},

			getFriendlyFieldLabel: function (field) {
				return getFriendlyFieldLabel(field);
			},

			getFriendlyFieldDescription: function (field) {
				return getFriendlyFieldDescription(field);
			},

			getHookArgumentSummary: function (hookMeta) {
				const meta = hookMeta && typeof hookMeta === 'object' ? hookMeta : {};
				const declaredNames = Array.isArray(meta.arg_names) ? meta.arg_names : [];
				const fallbackCount = Number.isFinite(Number(meta.payload_arity)) ? Math.max(0, Number(meta.payload_arity)) : 0;
				const argNames = declaredNames.length
					? declaredNames
					: Array.from({ length: fallbackCount }, (_unused, index) => `arg_${index + 1}`);
				const summary = [];
				argNames.forEach((name, index) => {
					const argName = String(name || `arg_${index + 1}`);
					const properties = meta.properties && Array.isArray(meta.properties[argName]) ? meta.properties[argName] : [];
					if (properties.length) {
						properties.forEach((property) => {
							if (!property || !property.name) return;
							const value = `${argName}.${property.name}`;
							summary.push({
								value,
								label: property.label ? String(property.label) : getFriendlyFieldLabel(value),
								description: getFriendlyFieldDescription(value)
							});
						});
						return;
					}
					summary.push({
						value: argName,
						label: getFriendlyFieldLabel(argName),
						description: getFriendlyFieldDescription(argName)
					});
				});
				return summary;
			},

			getOperatorLabel: function (operator) {
				const match = this.getOperators().find((item) => item.value === operator);
				return match ? match.label : String(operator || 'equals');
			},

			/**
			 * Return available note tag placeholders for the current hook.
			 */
			getNoteTagSuggestions: function () {
				const fields = this.getConditionFields();
				if (!Array.isArray(fields)) {
					return [];
				}
				return fields
					.filter((field) => field && field.value)
					.map((field) => ({
						value: String(field.value),
						label: String(field.label || getFriendlyFieldLabel(field.value))
					}));
			},

			/**
			 * Insert a note tag placeholder into the notification note.
			 */
			insertNoteTag: function (tag) {
				if (!tag) {
					return;
				}
				const placeholder = `{{${tag}}}`;
				const current =
					this.scenarioForm && this.scenarioForm.scenario_notes ? String(this.scenarioForm.scenario_notes) : '';
				this.scenarioForm.scenario_notes = current ? `${current} ${placeholder}` : placeholder;
			},

			/**
			 * Return value options for a condition (if it should render as select).
			 */
			getConditionValueOptions: function (condition) {
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
			normalizeTemplateConditions: function (conditions) {
				const cloned = (Array.isArray(conditions) ? JSON.parse(JSON.stringify(conditions)) : []).filter((condition) => {
					if (!condition) return false;
					const isBlank =
						!Object.prototype.hasOwnProperty.call(condition, 'value') || String(condition.value ?? '').trim() === '';
					const isOptional = String(condition.value_label || '')
						.toLowerCase()
						.includes('optional');
					return !(isBlank && isOptional);
				});
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
			areAllConditionsLocked: function () {
				if (
					!Array.isArray(this.scenarioForm && this.scenarioForm.conditions) ||
					this.scenarioForm.conditions.length === 0
				) {
					return false;
				}
				return this.scenarioForm.conditions.every((condition) => {
					return !!(condition && (condition.locked || condition.lock_field || condition.lock_operator));
				});
			},

			/**
			 * Check if current hook supports conditions
			 */
			hasConditionSupport: function () {
				return (
					this.scenarioForm.hook_meta &&
					Array.isArray(this.scenarioForm.hook_meta.arg_names) &&
					this.scenarioForm.hook_meta.arg_names.length > 0
				);
			},

			/**
			 * Add a new condition to the scenario
			 */
			addCondition: function () {
				// Template preset conditions: don't allow adding extra conditions.
				if (this.areAllConditionsLocked()) {
					return;
				}
				if (!Array.isArray(this.scenarioForm.conditions)) {
					this.scenarioForm.conditions = [];
				}
				const availableFields = this.getConditionFields();
				const defaultField =
					Array.isArray(availableFields) && availableFields.length > 0 ? availableFields[0].value : 'arg_1';
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
			removeCondition: function (index) {
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
			getOperators: function () {
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
			getPluginHooks: function (plugin) {
				// Avoid optional chaining for broader browser compatibility.
				if (this.availablePlugins && this.availablePlugins[plugin] && this.availablePlugins[plugin].hooks) {
					const hooks = this.availablePlugins[plugin].hooks;
					const selectable: AnyRecord = {};
					Object.keys(hooks).forEach((hookName) => {
						const meta = hooks[hookName];
						if (!meta || typeof meta !== 'object' || meta.selectable !== false) selectable[hookName] = meta;
					});
					return selectable;
				}
				return {};
			},

			/**
			 * Find hook metadata by hook name across all plugins
			 */
			findHookMeta: function (hookName) {
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
			getScenarioPluginKey: function (hook) {
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
			getScenarioPluginName: function (hook) {
				if (hook && hook.plugin_name) {
					return hook.plugin_name;
				}
				const pluginKey = this.getScenarioPluginKey(hook);
				if (
					pluginKey &&
					this.availablePlugins &&
					this.availablePlugins[pluginKey] &&
					this.availablePlugins[pluginKey].name
				) {
					return this.availablePlugins[pluginKey].name;
				}
				return '';
			},

			/**
			 * Determine whether the scenario plugin is active, inactive, missing, or core.
			 */
			getScenarioPluginStatus: function (hook) {
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
			isScenarioPluginInactive: function (hook) {
				const status = this.getScenarioPluginStatus(hook);
				return status === 'inactive' || status === 'missing';
			},

			/**
			 * Badge label for plugin status.
			 */
			getScenarioPluginBadgeLabel: function (hook) {
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
			getFilteredPluginHooks: function () {
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
					const plainDescription = getHookDescription(hookData);
					const friendlyData = this.getHookArgumentSummary(hookData)
						.map((arg) => arg.label)
						.join(' ')
						.toLowerCase();

					// Match against the technical event name and user-facing language.
					if (
						hookName.toLowerCase().includes(query) ||
						(label && label.toLowerCase().includes(query)) ||
						(plainDescription && plainDescription.toLowerCase().includes(query)) ||
						friendlyData.includes(query)
					) {
						filtered[hookName] = hookData;
					}
				}

				return filtered;
			},

			/**
			 * Keep large scans responsive by rendering only the first matching events.
			 * Search still runs against the complete event collection.
			 */
			getVisiblePluginHooks: function () {
				const filtered = this.getFilteredPluginHooks();
				const entries = Object.entries(filtered).slice(0, this.hookResultsLimit);
				return Object.fromEntries(entries);
			},

			getHookResultsSummary: function () {
				const count = Object.keys(this.getFilteredPluginHooks()).length;
				const label = `${count} event${count === 1 ? '' : 's'} found`;
				return count > this.hookResultsLimit
					? `${label} · Showing the first ${this.hookResultsLimit}. Search to narrow the list.`
					: label;
			},

			/**
			 * Get common scenario templates
			 */
			/**
			 * Get common predefined templates (filtered by active plugins)
			 */
			getCommonTemplates: function () {
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
				const filtered = mergedTemplates.filter((template) => {
					const requiredPlugin = template && template.required_plugin ? template.required_plugin : 'wordpress-core';

					// Always show WordPress core templates
					if (requiredPlugin === 'wordpress-core') {
						return true;
					}

					// Check if required plugin is active
					return activePlugins.includes(requiredPlugin);
				});

				const seen = new Set();
				const uniqueTemplates = filtered.filter((template) => {
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

				const featuredTemplates = new Set([
					'WooCommerce: New Order',
					'WooCommerce: Payment Failed',
					'WooCommerce: Low Stock',
					'WordPress: Administrator Created',
					'WordPress: Failed Login',
					'Contact Form 7: Submitted',
					'Gravity Forms: Submitted',
					'UpdraftPlus: Backup Failed'
				]);

				const inferCategory = (templateData) => {
					if (templateData.category) return String(templateData.category);
					const plugin = String(templateData.required_plugin || 'wordpress-core').toLowerCase();
					const text = `${templateData.title || ''} ${templateData.hook_name || ''}`.toLowerCase();
					if (plugin.includes('woocommerce')) return 'commerce';
					if (plugin.includes('contact-form') || plugin.includes('gravityform') || text.includes('form'))
						return 'forms';
					if (
						plugin.includes('wordfence') ||
						text.includes('login') ||
						text.includes('password') ||
						text.includes('administrator')
					)
						return 'security';
					if (plugin.includes('membership') || plugin.includes('paid-memberships')) return 'members';
					if (plugin.includes('seo') || plugin.includes('rank-math') || plugin.includes('fluentcrm'))
						return 'marketing';
					if (
						text.includes('post') ||
						text.includes('page') ||
						text.includes('comment') ||
						plugin.includes('elementor')
					)
						return 'content';
					return 'operations';
				};

				const inferSeverity = (templateData) => {
					if (templateData.severity) return normalizeSeverity(templateData.severity);
					const text = `${templateData.title || ''} ${templateData.description || ''}`.toLowerCase();
					if (/(failed|failure|malware|blocked|administrator created|password changed|out of stock)/.test(text))
						return 'critical';
					if (/(low stock|refund|cancel|spam|warning|deactivated)/.test(text)) return 'warning';
					return 'info';
				};

				const enrichedTemplates = uniqueTemplates.map((template) => {
					if (!template || typeof template !== 'object') {
						return template;
					}

					const requiredPlugin = template.required_plugin ? String(template.required_plugin) : 'wordpress-core';
					const conditions = Array.isArray(template.conditions) ? template.conditions : [];
					const hasRequiredBlank = conditions.some((condition) => {
						if (!condition || !Object.prototype.hasOwnProperty.call(condition, 'value')) return true;
						const isOptional = String(condition.value_label || '')
							.toLowerCase()
							.includes('optional');
						return String(condition.value ?? '').trim() === '' && !isOptional;
					});
					const titleRequiresChoice = /(specific|coupon used)/i.test(String(template.title || ''));
					const needsConfiguration = hasRequiredBlank || titleRequiresChoice;
					const readiness = needsConfiguration ? 'configure' : 'instant';
					const category = inferCategory(template);
					const severity = inferSeverity(template);
					const enriched = {
						...template,
						category,
						category_label: this.getTemplateCategoryLabel(category),
						severity,
						readiness,
						readiness_label: readiness === 'instant' ? 'Ready now' : 'Needs a setting',
						setup_hint:
							template.setup_hint ||
							(readiness === 'instant'
								? 'Review the message and enable it.'
								: 'Fill in the highlighted filter before enabling.'),
						featured:
							typeof template.featured === 'boolean'
								? template.featured
								: featuredTemplates.has(String(template.title || ''))
					};
					const pluginData =
						this.availablePlugins && this.availablePlugins[requiredPlugin]
							? this.availablePlugins[requiredPlugin]
							: null;
					const pluginIcon =
						pluginData && pluginData.icon
							? String(pluginData.icon)
							: getPluginFallbackIcon(requiredPlugin, pluginData && pluginData.name ? String(pluginData.name) : '');
					const pluginName =
						pluginData && pluginData.name
							? String(pluginData.name)
							: requiredPlugin === 'wordpress-core'
								? 'WordPress Core'
								: String(requiredPlugin);

					return {
						...enriched,
						plugin_key: requiredPlugin,
						plugin_name: pluginName,
						icon: pluginIcon
					};
				});

				return enrichedTemplates.sort((a, b) => {
					if (!!a.featured !== !!b.featured) return a.featured ? -1 : 1;
					if (a.readiness !== b.readiness) return a.readiness === 'instant' ? -1 : 1;
					return String(a.title || '').localeCompare(String(b.title || ''));
				});
			},

			getTemplateCategoryLabel: function (category) {
				const labels = {
					commerce: 'Commerce',
					content: 'Content',
					security: 'Security',
					forms: 'Forms',
					operations: 'Site operations',
					members: 'Members',
					learning: 'Learning',
					marketing: 'Marketing'
				};
				return labels[category] || 'Other';
			},

			getTemplateCategoryFilterOptions: function () {
				const counts = {};
				this.getCommonTemplates().forEach((template) => {
					counts[template.category] = (counts[template.category] || 0) + 1;
				});
				return [{ value: '__all__', label: 'All categories' }].concat(
					Object.keys(counts)
						.sort((a, b) => this.getTemplateCategoryLabel(a).localeCompare(this.getTemplateCategoryLabel(b)))
						.map((category) => ({
							value: category,
							label: `${this.getTemplateCategoryLabel(category)} (${counts[category]})`
						}))
				);
			},

			/**
			 * Get list of active plugins (from PHP)
			 */
			getActivePlugins: function () {
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
			getTemplatePluginFilterOptions: function () {
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
			getTemplatesPerPageOptions: function () {
				return [12, 24, 36, 60];
			},

			/**
			 * Use a template to create a scenario
			 */
			useTemplate: function (template) {
				// Ensure templates always create NEW scenarios (never overwrite an edited one)
				this.editingIndex = null;
				this.modalError = '';
				this.selectedHook = { hookName: template.hook_name, description: template.description };
				const templatePluginKey = template && template.required_plugin ? template.required_plugin : '';
				const templatePlugin =
					templatePluginKey && this.availablePlugins ? this.availablePlugins[templatePluginKey] : null;
				this.scenarioForm = {
					hook_name: template.hook_name,
					description: template.description,
					scenario_name: template.scenario_name,
					scenario_notes: template.default_notes || '',
					severity: typeof template.severity === 'string' ? template.severity : 'info',
					enabled: true,
					send_dashboard: true,
					send_push: this.hasRemoteDelivery,
					send_mqtt: false,
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
			getFilteredTemplates: function () {
				const selectedPlugin = this.templatePluginFilter ? String(this.templatePluginFilter) : '__all__';
				const showAllPlugins = selectedPlugin === '__all__';
				const activeTemplates = this.getCommonTemplates();
				const pluginFilteredTemplates = showAllPlugins
					? activeTemplates
					: activeTemplates.filter((template) => {
							const requiredPlugin = template && template.required_plugin ? template.required_plugin : 'wordpress-core';
							return requiredPlugin === selectedPlugin;
						});

				const selectedCategory = this.templateCategoryFilter ? String(this.templateCategoryFilter) : '__all__';
				const selectedReadiness = this.templateReadinessFilter ? String(this.templateReadinessFilter) : '__all__';
				const refinedTemplates = pluginFilteredTemplates.filter((template) => {
					const categoryMatches = selectedCategory === '__all__' || template.category === selectedCategory;
					const readinessMatches = selectedReadiness === '__all__' || template.readiness === selectedReadiness;
					return categoryMatches && readinessMatches;
				});

				const query = this.templateSearchQuery.toLowerCase().trim();
				if (!query) {
					return refinedTemplates;
				}

				return refinedTemplates.filter((template) => {
					return (
						String(template.title || '')
							.toLowerCase()
							.includes(query) ||
						String(template.hook_name || '')
							.toLowerCase()
							.includes(query) ||
						String(template.description || '')
							.toLowerCase()
							.includes(query) ||
						String(template.scenario_name || '')
							.toLowerCase()
							.includes(query) ||
						String(template.category_label || '')
							.toLowerCase()
							.includes(query)
					);
				});
			},

			/**
			 * Get paginated templates
			 */
			getPaginatedTemplates: function () {
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
			getTemplateTotalPages: function () {
				const filtered = this.getFilteredTemplates();
				if (this.templatesPerPage <= 0) {
					return 1;
				}
				return Math.ceil(filtered.length / this.templatesPerPage);
			},

			/**
			 * Navigate to next template page
			 */
			nextTemplatePage: function () {
				if (this.templatePage < this.getTemplateTotalPages()) {
					this.templatePage++;
				}
			},

			/**
			 * Navigate to previous template page
			 */
			prevTemplatePage: function () {
				if (this.templatePage > 1) {
					this.templatePage--;
				}
			},

			/**
			 * Reset to page 1 when search changes
			 */
			onTemplateSearchChange: function () {
				this.templatePage = 1;
			},

			/**
			 * Reset pagination when plugin filter changes.
			 */
			onTemplatePluginFilterChange: function () {
				if (!this.templatePluginFilter) {
					this.templatePluginFilter = '__all__';
				}
				this.templatePage = 1;
			},

			onTemplateFacetChange: function () {
				this.templateCategoryFilter = this.templateCategoryFilter || '__all__';
				this.templateReadinessFilter = this.templateReadinessFilter || '__all__';
				this.templatePage = 1;
			},

			/**
			 * Normalize templates-per-page selection and reset pagination.
			 */
			onTemplatesPerPageChange: function () {
				const parsed = parseInt(String(this.templatesPerPage), 10);
				this.templatesPerPage = Number.isFinite(parsed) && parsed > 0 ? parsed : 12;
				this.templatePage = 1;
			},

			/**
			 * Open modal for adding new scenario
			 */
			openAddModal: function () {
				this.editingIndex = null;
				this.modalError = '';
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
					send_dashboard: true,
					send_push: this.hasRemoteDelivery,
					send_mqtt: false,
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
			openEditModal: function (index) {
				this.editingIndex = index;
				this.modalError = '';
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
					send_dashboard:
						hook && typeof hook.send_dashboard !== 'undefined' ? normalizeEnabled(hook.send_dashboard) : true,
					send_push:
						this.hasRemoteDelivery &&
						(hook && typeof hook.send_push !== 'undefined' ? normalizeEnabled(hook.send_push) : true),
					send_mqtt:
						this.hasRemoteDelivery &&
						(hook && typeof hook.send_mqtt !== 'undefined' ? normalizeEnabled(hook.send_mqtt) : true),
					conditions: Array.isArray(hook && hook.conditions)
						? JSON.parse(JSON.stringify(hook.conditions)).filter(
								(condition) => condition && String(condition.value ?? '').trim() !== ''
							)
						: [],
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
			selectPlugin: function (pluginKey) {
				const plugin = this.availablePlugins[pluginKey];

				// Check if plugin is active
				if (plugin.file && !this.pluginActiveStatus[pluginKey]) {
					alert('This plugin is not active. Please activate it first.');
					return;
				}

				this.selectedPluginModal = pluginKey;
				this.modalError = '';
				this.hookSearchQuery = '';
				this.modalStep = 2;
			},

			/**
			 * Select hook in modal and move to step 3
			 */
			selectHook: function (hookName, description) {
				this.modalError = '';
				const label = getHookLabel(description);
				const plainDescription = getHookDescription(description) || label;
				this.selectedHook = { hookName, description: plainDescription };
				this.scenarioForm.hook_name = hookName;
				this.scenarioForm.description = plainDescription;
				this.scenarioForm.scenario_name = label || hookName;
				this.scenarioForm.hook_meta = description && typeof description === 'object' ? description : null;
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
			saveScenario: function () {
				// Validate
				if (!this.scenarioForm.hook_name) {
					this.modalError = 'Choose the event that should trigger this notification.';
					return;
				}

				if (!String(this.scenarioForm.scenario_name || '').trim()) {
					this.modalError = 'Give this notification a name.';
					return;
				}

				if (
					!normalizeEnabled(this.scenarioForm.send_dashboard) &&
					!normalizeEnabled(this.scenarioForm.send_push) &&
					!normalizeEnabled(this.scenarioForm.send_mqtt)
				) {
					this.modalError = 'Choose at least one delivery channel.';
					return;
				}

				const incompleteRule =
					Array.isArray(this.scenarioForm.conditions) &&
					this.scenarioForm.conditions.some((condition) => {
						return !condition || !String(condition.field || '').trim() || String(condition.value ?? '').trim() === '';
					});
				if (incompleteRule) {
					this.modalError = 'Complete or remove each notification rule.';
					return;
				}
				this.modalError = '';

				// Save (keep hook_meta for conditions support)
				const sanitizedConditions = Array.isArray(this.scenarioForm && this.scenarioForm.conditions)
					? this.scenarioForm.conditions
							.filter((c) => c && typeof c === 'object')
							.map((c) => ({
								field: typeof c.field === 'string' ? c.field : '',
								operator: typeof c.operator === 'string' ? c.operator : '=',
								value: typeof c.value === 'string' || typeof c.value === 'number' ? String(c.value) : ''
							}))
					: [];

				const sanitizedScenario = {
					...this.scenarioForm,
					severity: normalizeSeverity(this.scenarioForm && this.scenarioForm.severity),
					enabled: normalizeEnabled(this.scenarioForm && this.scenarioForm.enabled),
					send_dashboard: normalizeEnabled(this.scenarioForm && this.scenarioForm.send_dashboard),
					send_push: this.hasRemoteDelivery && normalizeEnabled(this.scenarioForm && this.scenarioForm.send_push),
					send_mqtt: this.hasRemoteDelivery && normalizeEnabled(this.scenarioForm && this.scenarioForm.send_mqtt),
					conditions: sanitizedConditions,
					plugin_key:
						this.scenarioForm && this.scenarioForm.plugin_key
							? this.scenarioForm.plugin_key
							: this.getScenarioPluginKey(this.scenarioForm),
					plugin_name:
						this.scenarioForm && this.scenarioForm.plugin_name
							? this.scenarioForm.plugin_name
							: this.getScenarioPluginName(this.scenarioForm)
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
			removeHook: function (index) {
				if (confirm('Are you sure you want to delete this scenario?')) {
					this.hooks.splice(index, 1);

					// Trigger form submission
					this.$nextTick(() => {
						this.submitForm();
					});
				}
			},

			/**
			 * Enable or pause a notification without opening the editor.
			 */
			toggleScenario: function (index) {
				if (!this.hooks[index]) return;
				this.hooks[index].enabled = !normalizeEnabled(this.hooks[index].enabled);
				this.$nextTick(() => this.submitForm());
			},

			/**
			 * Submit the settings form programmatically
			 */
			submitForm: function () {
				const form = document.querySelector('form[action="options.php"]');
				if (!form) {
					console.error('Settings form not found');
					return;
				}

				try {
					window.dispatchEvent(
						new CustomEvent('notificator:save:state', { detail: { state: 'saving', suppressToast: true } })
					);
				} catch (e) {
					// no-op
				}

				const ajaxUrl =
					window.notificatorCompanionData && window.notificatorCompanionData.ajaxUrl
						? window.notificatorCompanionData.ajaxUrl
						: null;
				const ajaxAction =
					window.notificatorCompanionData && window.notificatorCompanionData.actions
						? window.notificatorCompanionData.actions.saveSettings
						: null;
				const ajaxNonce =
					window.notificatorCompanionData && window.notificatorCompanionData.nonces
						? window.notificatorCompanionData.nonces.saveSettings
						: null;

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

				var toast = window.notificatorShowToast ? window.notificatorShowToast('Saving…', 'info', 0) : null;

				const formData = new FormData(form);
				formData.append('action', ajaxAction);
				formData.append('nonce', ajaxNonce);

				fetch(ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
					headers: { Accept: 'application/json' }
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
								window.dispatchEvent(
									new CustomEvent('notificator:save:state', { detail: { state: 'saved', suppressToast: true } })
								);
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
							window.dispatchEvent(
								new CustomEvent('notificator:save:state', { detail: { state: 'error', message, suppressToast: true } })
							);
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
							window.dispatchEvent(
								new CustomEvent('notificator:save:state', {
									detail: { state: 'error', message: 'Save failed', suppressToast: true }
								})
							);
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
				return this.hooks.filter(
					(hook) =>
						hook.hook_name.toLowerCase().includes(query) ||
						hook.description.toLowerCase().includes(query) ||
						(hook.scenario_name && hook.scenario_name.toLowerCase().includes(query))
				);
			},

			/**
			 * Preserve original indexes while filtering so row actions always update
			 * the correct saved notification.
			 */
			getFilteredScenarioRows: function () {
				const query = String(this.searchQuery || '')
					.trim()
					.toLowerCase();
				return this.hooks
					.map((hook, index) => ({ hook, index }))
					.filter((row) => {
						if (!query) return true;
						const hook = row.hook || {};
						return [hook.hook_name, hook.description, hook.scenario_name, hook.plugin_name].some((value) =>
							String(value || '')
								.toLowerCase()
								.includes(query)
						);
					});
			}
		};
		return builder;
	};

	/**
	 * Plugin Scanner
	 */
	window.startPluginScan = function () {
		const $btn = $(
			'#scan-plugins-btn, #auto-scan-btn, #notificator-scan-plugins-tool, #notificator-scan-recommendation-button'
		);
		const $modal = $('#scan-modal');
		const $progress = $('#scan-progress');
		const $progressBar = $('#scan-progress-bar');
		const $currentPlugin = $('#scan-current-plugin');
		const $results = $('#scan-results');
		const includeInactive = $('#notificator_companion_include_inactive_plugins').is(':checked');
		const companionData = window.notificatorCompanionData || {};
		const healthBefore = companionData.health || {};
		const initialScanAt = Number(healthBefore.last_scan_at || 0);
		const actions = companionData.actions || {};
		const nonces = companionData.nonces || {};
		let displayedProgress = 0;
		const setScanProgress = function (percent) {
			const next = Math.max(displayedProgress, Math.min(100, Math.round(Number(percent) || 0)));
			displayedProgress = next;
			$progressBar.css('width', next + '%');
		};
		const showScanError = function (message) {
			$progress.addClass('hidden');
			$results.removeClass('hidden');
			$('#scan-success-message').addClass('hidden');
			$('#scan-error-message').removeClass('hidden');
			$('#scan-error-detail').text(message || 'The plugin scan could not be completed.');
		};
		const markScanComplete = function (data) {
			const currentData = window.notificatorCompanionData || {};
			const currentHealth = currentData.health || {};
			const responseHealth = data && data.health ? data.health : {};
			currentData.health = {
				...currentHealth,
				...responseHealth,
				last_scan_status: 'complete',
				last_scan_at: Number(responseHealth.last_scan_at || currentHealth.last_scan_at || Math.floor(Date.now() / 1000))
			};
			window.notificatorCompanionData = currentData;

			const scanRecommendation = document.getElementById('notificator-scan-recommendation');
			if (scanRecommendation) {
				scanRecommendation.remove();
			}

			const overviewStep = document.getElementById('notificator-overview-scan-step');
			if (overviewStep) {
				overviewStep.classList.add('is-complete');
				const overviewIcon = overviewStep.querySelector('.dashicons');
				if (overviewIcon) {
					overviewIcon.classList.remove('dashicons-marker');
					overviewIcon.classList.add('dashicons-yes-alt');
				}
			}

			const overviewStatus = document.getElementById('notificator-overview-scan-status');
			if (overviewStatus) overviewStatus.textContent = 'Just now';

			const overviewEventCount = document.getElementById('notificator-overview-events-discovered');
			const discoveredCount = Number(responseHealth.last_scan_hooks || data?.hooks_found || 0);
			if (overviewEventCount && discoveredCount >= 0) overviewEventCount.textContent = String(discoveredCount);

			window.dispatchEvent(
				new CustomEvent('notificator:scan:complete', {
					detail: data || {}
				})
			);
		};

		const finishScan = function (data) {
			setScanProgress(100);
			$progress.addClass('hidden');
			$results.removeClass('hidden');
			$('#scan-success-message').removeClass('hidden');
			$('#scan-error-message').addClass('hidden');
			const health = data && data.health ? data.health : {};
			$('#total-plugins').text(health.last_scan_plugins || 0);
			$('#total-hooks').text(health.last_scan_hooks || 0);
			if (window.notificatorScenarioBuilder && data) {
				if (data.available_plugins) window.notificatorScenarioBuilder.availablePlugins = data.available_plugins;
				if (data.plugin_active_status) window.notificatorScenarioBuilder.pluginActiveStatus = data.plugin_active_status;
			}
			markScanComplete(data);
			$('#notificator-scan-review').removeClass('hidden');
			if (window.notificatorToast)
				window.notificatorToast.show('Scan complete. Review the discovered events when you are ready.', 'success');
		};

		const pollScan = function (attempt) {
			if (!actions.health || !nonces.health || attempt > 120) {
				showScanError('The scan is still running. You can close this window and check Overview shortly.');
				$btn.prop('disabled', false);
				return;
			}
			$currentPlugin.text('Scanning in the background…');
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: { action: actions.health, nonce: nonces.health },
				success: function (result) {
					const data = result && result.success ? result.data : null;
					const health = data && data.health ? data.health : {};
					const processed = Number(health.scan_processed || 0);
					const total = Number(health.scan_total || 0);
					if (health.last_scan_status === 'running') {
						$currentPlugin.text(
							health.scan_current_plugin
								? 'Scanning ' + health.scan_current_plugin + '…'
								: 'Scanning in the background…'
						);
						if (total > 0) setScanProgress(8 + (Math.min(processed, total) / total) * 87);
					}
					if (health.last_scan_status === 'failed') {
						showScanError('The background scan stopped before it could finish. Please try again.');
						$btn.prop('disabled', false);
						return;
					}
					if (Number(health.last_scan_at || 0) > initialScanAt && health.last_scan_status === 'complete') {
						finishScan(data);
						$btn.prop('disabled', false);
						return;
					}
					setTimeout(function () {
						pollScan(attempt + 1);
					}, 1500);
				},
				error: function () {
					setTimeout(function () {
						pollScan(attempt + 1);
					}, 2000);
				}
			});
		};

		// Show modal
		$modal.removeClass('hidden');
		document.body.classList.add('notificator-modal-open');
		$progress.removeClass('hidden');
		$results.addClass('hidden');
		$('#scan-success-message, #scan-error-message').addClass('hidden');
		$('#scan-error-detail').text('');
		$currentPlugin.text('Preparing the plugin scan…');
		displayedProgress = 0;
		setScanProgress(4);
		$('#notificator-scan-review').addClass('hidden');

		// Disable button
		$btn.prop('disabled', true);

		// Start scan
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'notificator_companion_refresh_hooks',
				nonce: $('#notificator_companion_scan_nonce').val(),
				include_inactive: includeInactive ? 1 : 0,
				background: 1
			},
			success: function (response) {
				if (response && response.success && (!response.data || typeof response.data.plugins_scanned === 'undefined')) {
					$currentPlugin.text('Scan started. Checking installed plugins…');
					setScanProgress(8);
					pollScan(0);
					return;
				}

				if (response && response.success) {
					$progress.addClass('hidden');
					$results.removeClass('hidden');
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
					markScanComplete(response.data || {});

					$('#notificator-scan-review').removeClass('hidden');
					$btn.prop('disabled', false);
					if (window.notificatorToast)
						window.notificatorToast.show('Scan complete. Review the discovered events when you are ready.', 'success');
				} else {
					showScanError(response && response.data && response.data.message ? response.data.message : 'Scan failed');
					$btn.prop('disabled', false);
				}
			},
			error: function (xhr, status, error) {
				showScanError('Network error: ' + error);
			},
			complete: function (_xhr, status) {
				if (status !== 'success') $btn.prop('disabled', false);
			}
		});
	};

	/**
	 * Close scan modal
	 */
	window.closeScanModal = function () {
		$('#scan-modal').addClass('hidden');
		document.body.classList.remove('notificator-modal-open');
		$('#scan-progress').removeClass('hidden');
		$('#scan-results').addClass('hidden');
		$('#scan-success-message').addClass('hidden');
		$('#scan-error-message').addClass('hidden');
		$('#scan-progress-bar').css('width', '0%');
	};

	/**
	 * Initialize on DOM ready
	 */
	$(document).ready(function () {
		// Close modal on backdrop click
		$('#scan-modal').on('click', function (e) {
			if ($(e.target).is('#scan-modal')) {
				window.closeScanModal();
			}
		});
		$('#notificator-scan-modal-close, #notificator-scan-modal-done').on('click', function () {
			window.closeScanModal();
		});
		$('#notificator-scan-review').on('click', function (e) {
			e.preventDefault();
			window.closeScanModal();
			const destination = new URL(String($(this).attr('href') || window.location.href), window.location.href);
			destination.searchParams.set('scan_review', String(Date.now()));
			destination.hash = 'notificator-discovery';
			window.location.href = destination.toString();
		});

		// A scan review reloads the server-rendered discovery inbox, then focuses it.
		const currentUrl = new URL(window.location.href);
		if (currentUrl.searchParams.has('scan_review')) {
			window.setTimeout(function () {
				const discovery = document.getElementById('notificator-discovery');
				if (discovery) discovery.scrollIntoView({ behavior: 'smooth', block: 'start' });
				currentUrl.searchParams.delete('scan_review');
				window.history.replaceState(
					null,
					'',
					currentUrl.pathname + (currentUrl.search ? currentUrl.search : '') + '#notificator-discovery'
				);
			}, 350);
		}

		// Handle test notification per API key
		$(document).on('click', '.notificator-test-api-key', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const $row = $btn.closest('.notificator-api-key-row');
			const $input = $row.find('input[name*="[api_keys]"]');
			const apiKey = $input.val() ? String($input.val()).trim() : '';
			const existingIndex = $input.attr('data-existing-key-index');
			if (!apiKey && (typeof existingIndex === 'undefined' || existingIndex === '')) {
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
					api_key: apiKey,
					api_key_index: existingIndex || ''
				},
				success: function (response) {
					if (response.success) {
						alert('✅ ' + response.data.message);
					} else {
						alert('❌ ' + (response.data.message || 'Test failed'));
					}
				},
				error: function () {
					alert('❌ Network error occurred');
				},
				complete: function () {
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

		function updateKeyStatus(): void {
			var rows = Array.prototype.slice.call(container.querySelectorAll('.notificator-api-key-row'));
			var configuredRows = rows.filter(function (row: Element) {
				var key = row.querySelector('input[name*="[api_keys]"]');
				var existing = row.querySelector('input[name*="[api_key_existing_indexes]"]');
				return (
					(key instanceof HTMLInputElement && key.value.trim() !== '') ||
					(existing instanceof HTMLInputElement && existing.value !== '')
				);
			});
			var active = configuredRows.filter(function (row: Element) {
				var toggle = row.querySelector('.notificator-api-key-toggle');
				return !(toggle instanceof HTMLInputElement) || toggle.checked;
			}).length;
			var disabled = configuredRows.length - active;
			var configuredCount = document.getElementById('notificator-configured-key-count');
			var activeCount = document.getElementById('notificator-active-key-count');
			var remoteSummary = document.getElementById('notificator-remote-summary');
			var remoteSectionStatus = document.getElementById('notificator-remote-section-status');
			var connection = document.getElementById('notificator-overview-connection-status');
			var alert = document.getElementById('notificator-overview-key-alert');
			if (configuredCount) configuredCount.textContent = String(configuredRows.length);
			if (activeCount) activeCount.textContent = String(active);
			if (remoteSummary) {
				remoteSummary.classList.toggle('is-active', active > 0);
				remoteSummary.classList.toggle('is-neutral', active === 0);
			}
			if (remoteSectionStatus) {
				remoteSectionStatus.textContent = active > 0 ? 'Connected' : configuredRows.length > 0 ? 'Paused' : 'Optional';
				remoteSectionStatus.classList.toggle('is-active', active > 0);
				remoteSectionStatus.classList.toggle('is-neutral', active === 0);
			}
			if (connection)
				connection.textContent =
					configuredRows.length === 0
						? 'Dashboard only'
						: active === 0
							? 'Delivery paused'
							: disabled > 0
								? 'Partially active'
								: 'Connected';
			if (alert) {
				alert.hidden = disabled === 0;
				alert.classList.toggle('is-danger', active === 0);
				alert.classList.toggle('is-warning', active > 0);
				var title = alert.querySelector('[data-key-alert-title]');
				var message = alert.querySelector('[data-key-alert-message]');
				if (title)
					title.textContent = active === 0 ? 'Notification delivery is paused' : 'Some destinations are paused';
				if (message)
					message.textContent =
						disabled === 1
							? '1 API key is turned off. Events for that destination will not be delivered.'
							: disabled + ' API keys are turned off. Events for those destinations will not be delivered.';
			}
		}

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
			row.className = 'notificator-api-key-row is-enabled';

			var state = document.createElement('div');
			state.className = 'notificator-api-key-state';
			var switchLabel = document.createElement('label');
			switchLabel.className = 'notificator-switch';
			var toggle = document.createElement('input');
			toggle.type = 'checkbox';
			toggle.checked = true;
			toggle.className = 'notificator-api-key-toggle';
			toggle.setAttribute('aria-label', 'Disable API key');
			var switchTrack = document.createElement('span');
			switchLabel.appendChild(toggle);
			switchLabel.appendChild(switchTrack);
			var stateText = document.createElement('strong');
			stateText.textContent = 'On';
			var enabledValue = document.createElement('input');
			enabledValue.type = 'hidden';
			enabledValue.className = 'notificator-api-key-enabled-value';
			enabledValue.name = 'notificator_companion_settings[api_key_enabled][]';
			enabledValue.value = '1';
			state.appendChild(switchLabel);
			state.appendChild(stateText);
			state.appendChild(enabledValue);

			var input = document.createElement('input');
			input.type = 'password';
			input.name = 'notificator_companion_settings[api_keys][]';
			input.placeholder = 'wpnotif_...';
			input.className =
				'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500';

			var existingIndex = document.createElement('input');
			existingIndex.type = 'hidden';
			existingIndex.name = 'notificator_companion_settings[api_key_existing_indexes][]';
			existingIndex.value = '';

			var nickname = document.createElement('input');
			nickname.type = 'text';
			nickname.name = 'notificator_companion_settings[api_key_nicknames][]';
			nickname.placeholder = 'Device or account label';
			nickname.className =
				'px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[140px]';

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

			row.appendChild(state);
			row.appendChild(input);
			row.appendChild(existingIndex);
			row.appendChild(nickname);
			row.appendChild(test);
			row.appendChild(remove);
			return row;
		}

		addBtn.addEventListener('click', function () {
			container.appendChild(createRow());
			updateRemoveButtons();
			updateKeyStatus();
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
			updateKeyStatus();
		});

		container.addEventListener('change', function (e) {
			var target = e.target;
			if (!(target instanceof HTMLInputElement) || !target.classList.contains('notificator-api-key-toggle')) return;
			var row = target.closest('.notificator-api-key-row');
			if (!row) return;
			var enabledValue = row.querySelector('.notificator-api-key-enabled-value');
			var stateText = row.querySelector('.notificator-api-key-state strong');
			var testButton = row.querySelector('.notificator-test-api-key');
			row.classList.toggle('is-enabled', target.checked);
			row.classList.toggle('is-disabled', !target.checked);
			target.setAttribute('aria-label', target.checked ? 'Disable API key' : 'Enable API key');
			if (enabledValue instanceof HTMLInputElement) {
				enabledValue.value = target.checked ? '1' : '0';
				enabledValue.dispatchEvent(new Event('change', { bubbles: true }));
			}
			if (stateText) stateText.textContent = target.checked ? 'On' : 'Off';
			if (testButton instanceof HTMLButtonElement) testButton.disabled = !target.checked;
			updateKeyStatus();
		});

		container.addEventListener('input', updateKeyStatus);

		document.addEventListener('notificator:api-keys:updated', function () {
			container.setAttribute('data-has-api-key', '1');
			updateRemoveButtons();
		});

		updateRemoveButtons();
		updateKeyStatus();
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
				showToast(message ? 'Error: ' + message : 'Save failed', 'error', 2500);
			}
		}

		function hasApiKeyInput(): boolean {
			var inputs = Array.prototype.slice.call(document.querySelectorAll('input[name*="[api_keys]"]'));
			if (
				inputs.some(function (input) {
					return input.value && input.value.trim();
				})
			) {
				return true;
			}
			var legacy = document.querySelector('input[name*="[api_key]"]');
			return !!(legacy && legacy.value && legacy.value.trim());
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
			try {
				document.dispatchEvent(new CustomEvent('notificator:api-keys:updated'));
			} catch (e) {
				// no-op
			}
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

		// Intercept manual form submit (Save Settings button) to avoid reload.
		document.addEventListener(
			'submit',
			function (e) {
				var form = e && e.target;
				if (!form || !(form instanceof HTMLFormElement)) return;
				if (!form.matches('form[action="options.php"]')) return;

				var ajaxUrl =
					window.notificatorCompanionData && window.notificatorCompanionData.ajaxUrl
						? window.notificatorCompanionData.ajaxUrl
						: null;
				var ajaxAction =
					window.notificatorCompanionData && window.notificatorCompanionData.actions
						? window.notificatorCompanionData.actions.saveSettings
						: null;
				var ajaxNonce =
					window.notificatorCompanionData && window.notificatorCompanionData.nonces
						? window.notificatorCompanionData.nonces.saveSettings
						: null;
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
					headers: { Accept: 'application/json' }
				})
					.then(function (resp) {
						return resp
							.json()
							.catch(function () {
								return null;
							})
							.then(function (data) {
								if (resp.ok && data && data.success) {
									setSaveStatus('saved');
									maybeUnlockUi();
									return;
								}

								var message = data && data.data && data.data.message ? data.data.message : 'Save failed';
								setSaveStatus('error', message);
							});
					})
					.catch(function () {
						setSaveStatus('error', 'Save failed');
					});
			},
			true
		);
	}

	function initTopBarScenarioButton(): void {
		var btn = document.getElementById('notificator-add-scenario-top');
		if (!btn) return;
		// Workspace navigation owns the click so the hidden Notifications panel
		// is activated before Alpine opens the modal.
		btn.setAttribute('data-notificator-create', '');
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
		var ajaxUrl = data.ajaxUrl || window.ajaxurl || '';
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
			if (value === false || value === 0 || value === '0' || value === null || typeof value === 'undefined')
				return false;
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
			statusEl.className =
				'text-sm ' + (kind === 'error' ? 'text-red-700' : kind === 'success' ? 'text-green-700' : 'text-gray-700');
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
				fileHint.textContent = file ? 'Selected: ' + file.name : 'Choose a file exported with “Export Scenarios”.';
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
					.then(function (r) {
						return r.json();
					})
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
						.then(function (r) {
							return r.json();
						})
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
		initThrottleStatus();
		initThemeToggle();
		initGlobalSaveUx();
		initTopBarScenarioButton();
		initScenarioImportExport();
	});
})();
