/**
 * Main Vite entry for Notificator-owned admin pages.
 *
 * Loads compiled styling, registers the template and scenario-builder browser
 * APIs, initializes Alpine, and exposes the page-local toast adapter.
 */

import './tailwind.css';
import './admin.scss';

import { Notyf, NotyfEvent } from 'notyf';
import 'notyf/notyf.min.css';

// These scripts are written as side-effect files (IIFEs) and attach to window.
import '../js/scenario-templates';
import '../js/admin-scenarios';

import Alpine from 'alpinejs';

type ToastType = 'success' | 'error' | 'warn' | 'info';
type ToastOptions = { duration?: number; url?: string; html?: boolean };
type AjaxEnvelope<T = Record<string, unknown>> = { success?: boolean; data?: T };

const escapeHtml = (value: unknown): string =>
	String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');

/** Display consistent feedback even when the toast bundle is unavailable. */
const notify = (message: unknown, type: ToastType = 'info', duration?: number): void => {
	if (window.notificatorToast?.show) {
		window.notificatorToast.show(message, type, { duration });
		return;
	}
	window.console?.warn(String(message ?? ''));
};

/** Send a nonce-protected WordPress admin request and validate its JSON response. */
const postAdminAction = async <T>(
	action: string,
	nonce: string,
	payload: Record<string, string> = {}
): Promise<AjaxEnvelope<T>> => {
	const ajaxUrl = window.notificatorCompanionData?.ajaxUrl || window.ajaxurl || '';
	if (!ajaxUrl || !action || !nonce) {
		throw new Error('Missing AJAX configuration.');
	}
	const params = new URLSearchParams({ action, nonce, ...payload });
	const response = await fetch(ajaxUrl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: params.toString(),
		credentials: 'same-origin'
	});
	const result = (await response.json().catch(() => null)) as AjaxEnvelope<T> | null;
	if (!response.ok || !result) {
		throw new Error('The server returned an invalid response.');
	}
	return result;
};

// Expose Alpine for debugging/devtools parity with CDN usage.
window.Alpine = Alpine;

const initAdminToasts = (): void => {
	const data = window.notificatorCompanionData || {};
	const enabled = data.toastsEnabled !== false;
	if (!enabled) {
		return;
	}

	const toastSettings = data.toastSettings || {};
	const durationMs = Math.max(1000, Math.min(15000, (parseInt(String(toastSettings.duration ?? 3), 10) || 3) * 1000));
	const positionX: 'left' | 'center' | 'right' =
		toastSettings.positionX === 'left' || toastSettings.positionX === 'center' ? toastSettings.positionX : 'right';
	const positionY: 'top' | 'bottom' = toastSettings.positionY === 'bottom' ? 'bottom' : 'top';
	const settingsKey = `${durationMs}:${positionX}:${positionY}`;

	if (window.notificatorToastSettings !== settingsKey) {
		window.notificatorToastSettings = settingsKey;
		window.notificatorNotyf = new Notyf({
			duration: durationMs,
			position: { x: positionX, y: positionY },
			ripple: false
		});
	}

	const show = (message: unknown, type: ToastType = 'info', options: ToastOptions = {}): void => {
		const notyf = window.notificatorNotyf;
		if (!notyf) {
			return;
		}
		const rawText = String(message ?? '').replace(/^[✅❌⚠️🔔]\s*/, '');
		const text = options.html ? rawText : escapeHtml(rawText);
		const duration = typeof options.duration === 'number' ? options.duration : durationMs;
		const url = typeof options.url === 'string' ? options.url : undefined;
		const colors = {
			success: '#16a34a',
			error: '#ef4444',
			warn: '#f59e0b',
			info: '#2563eb'
		};
		const variant = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
		const notification = notyf.open({
			type: variant,
			message: text,
			background: colors[type] || colors.info,
			duration,
			className: `notificator-toast notificator-toast--${type}`
		});
		if (url) {
			notification.on(NotyfEvent.Click, () => {
				window.location.href = url;
			});
		}
	};

	if (!window.notificatorToast) {
		window.notificatorToast = {
			show: (message, type, options) => show(message, type || 'info', options || {}),
			update: (_toast, message, type, options) => show(message, type || 'info', options || {})
		};
	}

	if (!window.notificatorOriginalAlert) {
		window.notificatorOriginalAlert = window.alert;
		window.alert = (msg?: unknown) => {
			const raw = String(msg ?? '');
			let type = 'info';
			let text = raw;
			if (raw.indexOf('❌') === 0 || raw.toLowerCase().indexOf('error') === 0) {
				type = 'error';
				text = raw.replace(/^❌\s*/, '');
			} else if (raw.indexOf('✅') === 0) {
				type = 'success';
				text = raw.replace(/^✅\s*/, '');
			}
			text = text.replace(/^[✅❌⚠️🔔]\s*/, '');
			show(text, type as ToastType);
		};
	}
};

// Start Alpine after DOM is ready.
// Our admin-scenarios.js defines window.initScenarioBuilder which Alpine uses.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => Alpine.start());
} else {
	Alpine.start();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initAdminToasts);
} else {
	initAdminToasts();
}

/** Initialize activity filtering and client-side pagination. */
function initLogSearch(): void {
	const input = document.getElementById('notificator-log-search') as HTMLInputElement | null;
	const table = document.querySelector('.notificator-log-table') as HTMLTableElement | null;
	const empty = document.getElementById('notificator-log-empty') as HTMLElement | null;
	const count = document.getElementById('notificator-log-count') as HTMLElement | null;
	const prev = document.getElementById('notificator-log-prev') as HTMLButtonElement | null;
	const next = document.getElementById('notificator-log-next') as HTMLButtonElement | null;
	const page = document.getElementById('notificator-log-page') as HTMLElement | null;
	const perPageSelect = document.getElementById('notificator-log-per-page') as HTMLSelectElement | null;
	const statusFilter = document.getElementById('notificator-log-status-filter') as HTMLSelectElement | null;
	const severityFilter = document.getElementById('notificator-log-severity-filter') as HTMLSelectElement | null;
	const reset = document.getElementById('notificator-log-reset') as HTMLButtonElement | null;
	if (!input || !table) {
		return;
	}

	let perPage = perPageSelect ? parseInt(perPageSelect.value, 10) || 20 : 20;
	let currentPage = 1;
	let filteredRows: HTMLTableRowElement[] = [];
	const getRows = (): HTMLTableRowElement[] =>
		Array.from(table.querySelectorAll<HTMLTableRowElement>('tbody tr.notificator-log-row'));
	const normalize = (value: string): string =>
		value
			.normalize('NFKD')
			.replace(/[\u0300-\u036f]/g, '')
			.toLocaleLowerCase();

	const applyPagination = () => {
		const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
		if (currentPage > totalPages) {
			currentPage = totalPages;
		}
		const start = (currentPage - 1) * perPage;
		const end = start + perPage;
		getRows().forEach((row) => {
			row.hidden = true;
		});
		filteredRows.forEach((row, index) => {
			row.hidden = !(index >= start && index < end);
		});

		if (page) {
			page.textContent = `${currentPage} / ${totalPages}`;
		}
		if (prev) {
			prev.disabled = currentPage <= 1;
		}
		if (next) {
			next.disabled = currentPage >= totalPages;
		}
	};

	const applyFilter = () => {
		const terms = normalize(input.value.trim()).split(/\s+/).filter(Boolean);
		const selectedStatus = statusFilter ? statusFilter.value : '';
		const selectedSeverity = severityFilter ? severityFilter.value : '';
		const statusGroups: Record<string, string[]> = {
			delivered: ['delivered', 'sent', 'dashboard_only'],
			queued: ['pending', 'retrying'],
			attention: ['failed', 'partial', 'not_sent', 'connection_required'],
			suppressed: ['throttled', 'delivery_disabled']
		};
		filteredRows = getRows().filter((row) => {
			const text = normalize(row.dataset.logSearch || row.textContent || '');
			const rowStatus = row.dataset.logStatus || '';
			const rowSeverity = row.dataset.logSeverity || '';
			const statusMatch = !selectedStatus || (statusGroups[selectedStatus] || []).includes(rowStatus);
			const severityMatch = !selectedSeverity || selectedSeverity === rowSeverity;
			const searchMatch = terms.every((term) => text.includes(term));
			return searchMatch && statusMatch && severityMatch;
		});
		const visible = filteredRows.length;
		currentPage = 1;
		applyPagination();

		if (empty) {
			empty.hidden = visible !== 0;
		}
		if (count) {
			count.textContent = String(visible);
		}
	};

	const tbody = table.querySelector('tbody');
	if (tbody && typeof MutationObserver !== 'undefined') {
		const observer = new MutationObserver(() => {
			applyFilter();
		});
		observer.observe(tbody, { childList: true });
	}

	if (prev) {
		prev.addEventListener('click', () => {
			currentPage = Math.max(1, currentPage - 1);
			applyPagination();
		});
	}

	if (next) {
		next.addEventListener('click', () => {
			const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
			currentPage = Math.min(totalPages, currentPage + 1);
			applyPagination();
		});
	}

	if (perPageSelect) {
		perPageSelect.addEventListener('change', () => {
			const value = parseInt(perPageSelect.value, 10);
			perPage = Number.isNaN(value) ? 20 : value;
			currentPage = 1;
			applyPagination();
		});
	}

	input.addEventListener('input', applyFilter);
	statusFilter?.addEventListener('change', applyFilter);
	severityFilter?.addEventListener('change', applyFilter);
	reset?.addEventListener('click', () => {
		input.value = '';
		if (statusFilter) statusFilter.value = '';
		if (severityFilter) severityFilter.value = '';
		applyFilter();
		input.focus();
	});
	applyFilter();
}

/** Persist binary preferences without reloading the settings workspace. */
function initPreferenceToggles(): void {
	type PreferenceDefinition = {
		button: HTMLButtonElement | null;
		kind: 'log' | 'toasts';
		action?: string;
		nonce?: string;
		attribute: 'data-log-enabled' | 'data-toasts-enabled';
	};

	const data = window.notificatorCompanionData || {};
	const actions = data.actions || {};
	const nonces = data.nonces || {};
	const definitions: PreferenceDefinition[] = [
		{
			button: document.getElementById('notificator-toggle-log') as HTMLButtonElement | null,
			kind: 'log',
			action: actions.toggleLog,
			nonce: nonces.toggleLog,
			attribute: 'data-log-enabled'
		},
		{
			button: document.getElementById('notificator-toggle-admin-toasts') as HTMLButtonElement | null,
			kind: 'toasts',
			action: actions.toggleAdminToasts,
			nonce: nonces.toggleAdminToasts,
			attribute: 'data-toasts-enabled'
		}
	];

	const render = (definition: PreferenceDefinition, enabled: boolean): void => {
		const { button, kind, attribute } = definition;
		if (!button) return;
		const isLog = kind === 'log';
		button.setAttribute(attribute, enabled ? '1' : '0');
		button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
		button.innerHTML = `<span class="dashicons ${enabled ? 'dashicons-no' : 'dashicons-yes'}"></span>${
			isLog ? (enabled ? 'Disable activity log' : 'Enable activity log') : enabled ? 'Turn off' : 'Turn on'
		}`;
		if (!isLog) {
			const settings = document.getElementById('notificator-dashboard-alert-settings');
			settings?.classList.toggle('is-enabled', enabled);
			settings?.classList.toggle('is-disabled', !enabled);
		}
		const cardStatus = document.getElementById(
			isLog ? 'notificator-log-card-status' : 'notificator-dashboard-card-status'
		);
		const summary = document.getElementById(isLog ? 'notificator-log-summary' : 'notificator-dashboard-summary');
		[cardStatus, summary].forEach((element) => {
			if (!element) return;
			element.classList.toggle('is-active', enabled);
			element.classList.toggle('is-neutral', !enabled);
			const label = element.matches('.notificator-card-status') ? element : element.querySelector('strong');
			if (label) label.textContent = enabled ? 'On' : 'Off';
		});
	};

	definitions.forEach((definition) => {
		const { button, action, nonce, attribute } = definition;
		if (!button) return;
		button.addEventListener('click', async (event) => {
			event.preventDefault();
			const enabled = button.getAttribute(attribute) === '1';
			button.disabled = true;
			try {
				const result = await postAdminAction<{ enabled?: boolean; message?: string }>(action || '', nonce || '', {
					state: enabled ? 'disable' : 'enable'
				});
				if (!result.success) {
					throw new Error(result.data?.message || 'Unable to update this preference.');
				}
				const nextEnabled = !!result.data?.enabled;
				render(definition, nextEnabled);
				if (definition.kind === 'toasts') {
					data.toastsEnabled = nextEnabled;
					document.dispatchEvent(
						new CustomEvent('notificator:admin-toasts-toggle', { detail: { enabled: nextEnabled } })
					);
				}
				notify(result.data?.message || 'Preference updated.', 'success');
			} catch (error) {
				notify(error instanceof Error ? error.message : 'Network error.', 'error');
			} finally {
				button.disabled = false;
			}
		});
	});
}

/** Bind activity-log mutations and CSV export to nonce-protected AJAX actions. */
function initLogTools(): void {
	const exportBtn = document.getElementById('notificator-export-log') as HTMLButtonElement | null;
	const clearBtn = document.getElementById('notificator-clear-log') as HTMLButtonElement | null;
	const logTable = document.querySelector('.notificator-log-table') as HTMLTableElement | null;
	const data = window.notificatorCompanionData || {};
	const actions = (data.actions || {}) as Record<string, string>;
	const nonces = (data.nonces || {}) as Record<string, string>;

	if (exportBtn) {
		exportBtn.addEventListener('click', () => {
			exportBtn.disabled = true;
			const original = exportBtn.innerHTML;
			exportBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Exporting...';
			postAdminAction<{ csv?: string; file_name?: string; message?: string }>(actions.exportLog, nonces.exportLog)
				.then((json) => {
					if (!json || !json.success) {
						throw new Error(json.data?.message || 'Export failed');
					}
					const csv = json.data?.csv || '';
					const fileName = json.data?.file_name || 'notificator-log.csv';
					const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
					const url = URL.createObjectURL(blob);
					const link = document.createElement('a');
					link.href = url;
					link.download = fileName;
					document.body.appendChild(link);
					link.click();
					link.remove();
					URL.revokeObjectURL(url);
					notify('Log exported.', 'success');
				})
				.catch((error) => notify(error instanceof Error ? error.message : 'Export failed', 'error'))
				.finally(() => {
					exportBtn.disabled = false;
					exportBtn.innerHTML = original;
				});
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', () => {
			if (!confirm('Clear all log entries?')) {
				return;
			}
			clearBtn.disabled = true;
			postAdminAction<{ message?: string }>(actions.clearLog, nonces.clearLog)
				.then((json) => {
					if (json && json.success) {
						window.location.reload();
					} else {
						throw new Error(json.data?.message || 'Failed to clear log.');
					}
				})
				.catch((error) => notify(error instanceof Error ? error.message : 'Network error.', 'error'))
				.finally(() => {
					clearBtn.disabled = false;
				});
		});
	}

	if (logTable) {
		logTable.addEventListener('click', (event) => {
			const target = event.target instanceof Element ? event.target.closest('.notificator-log-delete') : null;
			if (!target) {
				return;
			}
			const actionButton = target instanceof HTMLButtonElement ? target : null;
			const entryId = target.getAttribute('data-log-id');
			if (!entryId) {
				return;
			}
			if (!confirm('Delete this log entry?')) {
				return;
			}
			if (actionButton) {
				actionButton.disabled = true;
			}
			postAdminAction<{ message?: string }>(actions.deleteLog, nonces.deleteLog, { entry_id: entryId })
				.then((json) => {
					if (json && json.success) {
						const row = target.closest('tr');
						if (row) {
							row.remove();
						}
						const countEl = document.getElementById('notificator-log-count');
						if (countEl) {
							const rows = Array.from(logTable.querySelectorAll('tbody tr'));
							const visible = rows.filter((item) => {
								if (!item || !item.isConnected) {
									return false;
								}
								const style = window.getComputedStyle(item);
								return style.display !== 'none' && style.visibility !== 'hidden';
							}).length;
							countEl.textContent = String(visible);
							const emptyEl = document.getElementById('notificator-log-empty');
							if (emptyEl) {
								emptyEl.hidden = visible !== 0;
							}
						}
					} else {
						throw new Error(json.data?.message || 'Failed to delete log entry.');
					}
				})
				.catch((error) => notify(error instanceof Error ? error.message : 'Network error.', 'error'))
				.finally(() => {
					if (actionButton) {
						actionButton.disabled = false;
					}
				});
		});
	}
}

/** Manage the tools dialog, including keyboard and backdrop dismissal. */
function initToolsModal(): void {
	const details = document.getElementById('notificator-scenarios-menu') as HTMLDetailsElement | null;
	const overviewTrigger = document.getElementById('notificator-overview-tools') as HTMLButtonElement | null;
	const headerTrigger = document.getElementById('notificator-header-tools') as HTMLButtonElement | null;
	const resetTestData = document.getElementById('notificator-reset-test-data') as HTMLButtonElement | null;
	if (!details) return;
	const data = window.notificatorCompanionData || {};
	const actions = (data.actions || {}) as Record<string, string>;
	const nonces = (data.nonces || {}) as Record<string, string>;

	const close = (): void => {
		details.open = false;
		document.body.classList.remove('notificator-modal-open');
		overviewTrigger?.setAttribute('aria-expanded', 'false');
		headerTrigger?.setAttribute('aria-expanded', 'false');
	};
	const open = (): void => {
		if (details.dataset.notificatorDisabled === '1') return;
		details.open = true;
		document.body.classList.add('notificator-modal-open');
		overviewTrigger?.setAttribute('aria-expanded', 'true');
		headerTrigger?.setAttribute('aria-expanded', 'true');
		window.setTimeout(
			() =>
				details.querySelector<HTMLElement>('.notificator-tools-modal button, .notificator-tools-modal input')?.focus(),
			0
		);
	};
	const openFromTrigger = (event: Event): void => {
		event.preventDefault();
		event.stopPropagation();
		open();
	};

	details.addEventListener('toggle', () => {
		document.body.classList.toggle('notificator-modal-open', details.open);
	});
	details
		.querySelectorAll<HTMLElement>('[data-notificator-tools-close]')
		.forEach((button) => button.addEventListener('click', close));
	details
		.querySelectorAll<HTMLElement>('#notificator-import-scenarios')
		.forEach((button) => button.addEventListener('click', () => window.setTimeout(close, 0)));
	overviewTrigger?.setAttribute('aria-controls', details.id);
	headerTrigger?.setAttribute('aria-controls', details.id);
	overviewTrigger?.setAttribute('aria-expanded', 'false');
	headerTrigger?.setAttribute('aria-expanded', 'false');
	overviewTrigger?.addEventListener('click', openFromTrigger);
	headerTrigger?.addEventListener('click', openFromTrigger);
	resetTestData?.addEventListener('click', () => {
		if (!actions.resetTestData || !nonces.resetTestData) return;
		if (
			!window.confirm(
				'Reset notifications, activity, scan results, observation data, and preferences? Your API keys and their enabled states will be kept.'
			)
		)
			return;
		resetTestData.disabled = true;
		const original = resetTestData.innerHTML;
		resetTestData.innerHTML = '<span class="dashicons dashicons-update spin"></span> Resetting…';
		const body = new URLSearchParams({ action: actions.resetTestData, nonce: nonces.resetTestData });
		fetch(data.ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then((response) => response.json())
			.then((json) => {
				if (!json?.success) throw new Error(json?.data?.message || 'Unable to reset plugin data.');
				window.notificatorToast?.show(json.data.message, 'success');
				window.setTimeout(() => window.location.reload(), 800);
			})
			.catch((error) => {
				window.notificatorToast?.show(error.message, 'error');
				resetTestData.disabled = false;
				resetTestData.innerHTML = original;
			});
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && details.open) close();
	});
}

/** Route every scan entry point through the shared scanner workflow. */
function initScanTriggers(): void {
	const details = document.getElementById('notificator-scenarios-menu') as HTMLDetailsElement | null;
	document
		.querySelectorAll<HTMLButtonElement>('#scan-plugins-btn, #auto-scan-btn, #notificator-scan-plugins-tool')
		.forEach((button) => {
			button.addEventListener('click', (event) => {
				event.preventDefault();
				event.stopPropagation();
				if (button.disabled || typeof window.startPluginScan !== 'function') return;
				if (details?.open) details.open = false;
				document.body.classList.remove('notificator-modal-open');
				window.requestAnimationFrame(() => window.startPluginScan?.());
			});
		});
}

/** Filter, rank, ignore, and promote discovered events into the builder. */
function initDiscoveryInbox(): void {
	const list = document.getElementById('notificator-discovery-list');
	const search = document.getElementById('notificator-discovery-search') as HTMLInputElement | null;
	const filter = document.getElementById('notificator-discovery-filter') as HTMLSelectElement | null;
	const empty = document.getElementById('notificator-discovery-empty') as HTMLElement | null;
	const observationToggle = document.getElementById('notificator-observation-toggle') as HTMLButtonElement | null;
	const browseAll = document.getElementById('notificator-browse-all-events') as HTMLButtonElement | null;
	if (!list || !search || !filter) return;
	const data = window.notificatorCompanionData || {};
	const actions = (data.actions || {}) as Record<string, string>;
	const nonces = (data.nonces || {}) as Record<string, string>;

	type DiscoveryResponse = {
		success?: boolean;
		data?: { ignored?: boolean; message?: string };
	};
	const post = (action: string, nonce: string, payload: Record<string, string> = {}): Promise<DiscoveryResponse> => {
		const body = new URLSearchParams({ action, nonce, ...payload });
		return fetch(data.ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then((response) => response.json() as Promise<DiscoveryResponse>);
	};
	const matchesMode = (item: HTMLElement, mode: string): boolean =>
		mode === 'all'
			? item.dataset.ignored !== '1'
			: mode === 'recommended'
				? item.dataset.recommended === '1' && item.dataset.ignored !== '1'
				: mode === 'observed'
					? item.dataset.observed === '1' && item.dataset.ignored !== '1'
					: mode === 'noisy'
						? item.dataset.risk === 'potentially_noisy' && item.dataset.ignored !== '1'
						: mode === 'dynamic'
							? item.dataset.dynamic === '1' && item.dataset.ignored !== '1'
							: mode === 'registration'
								? item.dataset.registration === '1' && item.dataset.ignored !== '1'
								: item.dataset.ignored === '1';
	const updateFilterCounts = (): void => {
		const items = Array.from(list.querySelectorAll<HTMLElement>('[data-discovery-item]'));
		Array.from(filter.options).forEach((option) => {
			const count = items.filter((item) => matchesMode(item, option.value)).length;
			option.textContent = `${option.dataset.filterLabel || option.value} (${count})`;
			option.disabled = count === 0 && option.value !== 'ignored';
		});
	};

	const apply = (): void => {
		const query = search.value.trim().toLocaleLowerCase();
		const mode = filter.value;
		let visible = 0;
		list.querySelectorAll<HTMLElement>('[data-discovery-item]').forEach((item, index) => {
			const rank = Number(item.dataset.discoveryRank ?? index);
			const priority = Number(item.dataset.recommendPriority ?? 999);
			item.dataset.discoveryRank = String(rank);
			item.style.order = String(mode === 'recommended' ? priority * 10000 + rank : rank);
			const matchesQuery = !query || (item.dataset.search || '').includes(query);
			item.hidden = !(matchesQuery && matchesMode(item, mode));
			if (!item.hidden) visible += 1;
		});
		if (empty) empty.hidden = visible > 0;
	};

	search.addEventListener('input', apply);
	filter.addEventListener('change', apply);
	browseAll?.addEventListener('click', () => {
		const builder = window.notificatorScenarioBuilder;
		if (!builder || typeof builder.openAddModal !== 'function') return;
		builder.openAddModal?.();
	});
	list.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		const create = target?.closest<HTMLButtonElement>('[data-discovery-create]');
		if (create && !create.disabled) {
			const builder = window.notificatorScenarioBuilder;
			const pluginKey = create.dataset.plugin || '';
			const hookName = create.dataset.hook || '';
			if (builder && hookName) {
				builder.openAddModal?.();
				builder.selectedPluginModal = pluginKey;
				const meta = builder.availablePlugins?.[pluginKey]?.hooks?.[hookName] || hookName;
				builder.selectHook?.(hookName, meta);
			}
			return;
		}
		const ignore = target?.closest<HTMLButtonElement>('[data-discovery-ignore]');
		if (!ignore || !actions.discoveryIgnore || !nonces.discovery) return;
		ignore.disabled = true;
		post(actions.discoveryIgnore, nonces.discovery, { candidate_id: ignore.dataset.candidateId || '' })
			.then((json) => {
				if (!json?.success) throw new Error(json?.data?.message || 'Unable to update discovery item.');
				const responseData = json.data || {};
				const item = ignore.closest<HTMLElement>('[data-discovery-item]');
				if (item) item.dataset.ignored = responseData.ignored ? '1' : '0';
				ignore.textContent = responseData.ignored ? 'Restore' : 'Ignore';
				updateFilterCounts();
				apply();
			})
			.catch((error) => window.notificatorToast?.show(error.message, 'error'))
			.finally(() => {
				ignore.disabled = false;
			});
	});

	observationToggle?.addEventListener('click', () => {
		const observing = observationToggle.dataset.observing === '1';
		const action = observing ? actions.observationStop : actions.observationStart;
		if (!action || !nonces.observation) return;
		observationToggle.disabled = true;
		post(action, nonces.observation, observing ? {} : { duration: '600' })
			.then((json) => {
				if (!json?.success) throw new Error(json?.data?.message || 'Unable to update observation.');
				window.location.reload();
			})
			.catch((error) => {
				window.notificatorToast?.show(error.message, 'error');
				observationToggle.disabled = false;
			});
	});
	updateFilterCounts();
	apply();
}

let discoveryRefreshRequest: Promise<void> | null = null;

/** Replace the server-rendered Discovery inbox after a scan without reloading wp-admin. */
function refreshDiscoveryInbox(): Promise<void> {
	if (discoveryRefreshRequest) return discoveryRefreshRequest;

	const data = window.notificatorCompanionData || {};
	const action = data.actions?.discoveryRefresh || '';
	const nonce = data.nonces?.discovery || '';
	if (!action || !nonce) return Promise.resolve();

	const currentInbox = document.getElementById('notificator-discovery');
	currentInbox?.setAttribute('aria-busy', 'true');
	discoveryRefreshRequest = postAdminAction<{ html?: string; message?: string }>(action, nonce)
		.then((result) => {
			const html = typeof result.data?.html === 'string' ? result.data.html.trim() : '';
			if (!result.success || !html) {
				throw new Error(result.data?.message || 'Discovery results could not be refreshed.');
			}

			const template = document.createElement('template');
			template.innerHTML = html;
			const replacement = template.content.querySelector<HTMLElement>('#notificator-discovery');
			const activeInbox = document.getElementById('notificator-discovery');
			if (!replacement || !activeInbox) throw new Error('The refreshed Discovery view was incomplete.');

			activeInbox.replaceWith(replacement);
			initDiscoveryInbox();
			window.dispatchEvent(new CustomEvent('notificator:discovery:refreshed'));
		})
		.catch((error) => {
			currentInbox?.removeAttribute('aria-busy');
			notify(error instanceof Error ? error.message : 'Discovery results could not be refreshed.', 'warn', 5000);
		})
		.finally(() => {
			discoveryRefreshRequest = null;
		});

	return discoveryRefreshRequest;
}

window.addEventListener('notificator:scan:complete', () => {
	void refreshDiscoveryInbox();
});

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initLogSearch);
} else {
	initLogSearch();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initPreferenceToggles);
} else {
	initPreferenceToggles();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initLogTools);
} else {
	initLogTools();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initToolsModal);
} else {
	initToolsModal();
}
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initScanTriggers);
} else {
	initScanTriggers();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initDiscoveryInbox);
} else {
	initDiscoveryInbox();
}

/** Keep API-key availability notices synchronized with editable key rows. */
function initApiKeyWarning(): void {
	const apiContainer = document.getElementById('notificator-api-keys') as HTMLElement | null;
	const warning = document.querySelector('[data-notificator-lock="api-warning"]') as HTMLElement | null;
	if (!apiContainer || !warning) {
		return;
	}
	const inputs = Array.from(apiContainer.querySelectorAll('input[name*="[api_keys]"]')) as HTMLInputElement[];
	const hasKey =
		apiContainer.getAttribute('data-has-api-key') === '1' || inputs.some((input) => input.value && input.value.trim());
	if (hasKey) {
		warning.setAttribute('hidden', '');
		apiContainer.setAttribute('data-has-api-key', '1');
	} else {
		warning.removeAttribute('hidden');
		apiContainer.setAttribute('data-has-api-key', '0');
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initApiKeyWarning);
} else {
	initApiKeyWarning();
}

/** Persist dashboard-alert preferences without a full settings-page reload. */
function initToastSettingsAutosave(): void {
	const pollIntervalInput = document.getElementById('notificator-toast-poll-interval') as HTMLSelectElement | null;
	const durationInput = document.getElementById('notificator-toast-duration') as HTMLInputElement | null;
	const positionX = document.getElementById('notificator-toast-position-x') as HTMLSelectElement | null;
	const positionY = document.getElementById('notificator-toast-position-y') as HTMLSelectElement | null;
	const deliveryMode = document.getElementById('notificator-toast-delivery') as HTMLSelectElement | null;
	const form = document.querySelector('form[action="options.php"]') as HTMLFormElement | null;
	if (!pollIntervalInput || !durationInput || !positionX || !positionY || !deliveryMode || !form) {
		return;
	}

	const data = window.notificatorCompanionData || {};
	const ajaxUrl = data.ajaxUrl;
	const actions = (data.actions || {}) as Record<string, string>;
	const nonces = (data.nonces || {}) as Record<string, string>;
	if (!ajaxUrl || !actions.saveSettings || !nonces.saveSettings) {
		return;
	}

	let timer: number | null = null;
	const notify = (message: string, type?: ToastType): void => {
		if (window.notificatorToast && window.notificatorToast.show) {
			window.notificatorToast.show(message, type || 'info');
			return;
		}
		if (window.console && console.warn) {
			console.warn(message);
		}
	};
	const applySettings = (): void => {
		const nextSettings = {
			duration: parseInt(durationInput.value, 10) || 3,
			positionX: positionX.value || 'right',
			positionY: positionY.value || 'top',
			deliveryMode: deliveryMode.value || 'account'
		};
		window.notificatorCompanionData = window.notificatorCompanionData || {};
		window.notificatorCompanionData.toastSettings = nextSettings;
		if (window.notificatorAdminToastData) {
			window.notificatorAdminToastData.pollInterval = Math.max(
				15,
				Math.min(300, parseInt(pollIntervalInput.value, 10) || 30)
			);
			window.notificatorAdminToastData.toastSettings = nextSettings;
			window.notificatorAdminToastData.toastDeliveryMode = nextSettings.deliveryMode;
		}
		const dashboardSummaryDetail = document.querySelector<HTMLElement>('#notificator-dashboard-summary em');
		if (dashboardSummaryDetail) {
			const seconds = Math.max(15, Math.min(300, parseInt(pollIntervalInput.value, 10) || 30));
			dashboardSummaryDetail.textContent =
				seconds === 60
					? 'Every minute'
					: seconds % 60 === 0
						? `Every ${seconds / 60} minutes`
						: `Every ${seconds} seconds`;
		}
		initAdminToasts();
	};
	const save = (): void => {
		if (timer) {
			clearTimeout(timer);
		}
		timer = window.setTimeout(() => {
			const formData = new FormData();
			formData.append('action', actions.saveSettings);
			formData.append('nonce', nonces.saveSettings);
			formData.append(
				'settings_patch',
				JSON.stringify({
					toast_poll_interval: Math.max(15, Math.min(300, parseInt(pollIntervalInput.value, 10) || 30)),
					toast_duration: parseInt(durationInput.value, 10) || 3,
					toast_position_x: positionX.value || 'right',
					toast_position_y: positionY.value || 'top',
					toast_delivery_mode: deliveryMode.value || 'account'
				})
			);
			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: { Accept: 'application/json' }
			})
				.then((res) => res.json().catch(() => null))
				.then((json) => {
					if (json && json.success) {
						applySettings();
						notify('Toast settings saved.', 'success');
						return;
					}
					notify('Failed to save toast settings.', 'error');
				})
				.catch(() => notify('Failed to save toast settings.', 'error'));
		}, 500);
	};

	['input', 'change'].forEach((evt) => {
		pollIntervalInput.addEventListener(evt, save);
		durationInput.addEventListener(evt, save);
		positionX.addEventListener(evt, save);
		positionY.addEventListener(evt, save);
		deliveryMode.addEventListener(evt, save);
	});
}

/** Persist delivery and discovery limits after a short input debounce. */
function initLimitsAutosave(): void {
	const throttleInput = document.getElementById('notificator-throttle-seconds') as HTMLInputElement | null;
	const scanHookLimitInput = document.getElementById('notificator-scan-hook-limit') as HTMLInputElement | null;
	const form = document.querySelector('form[action="options.php"]') as HTMLFormElement | null;
	if (!throttleInput || !scanHookLimitInput || !form) {
		return;
	}

	const data = window.notificatorCompanionData || {};
	const ajaxUrl = data.ajaxUrl;
	const actions = (data.actions || {}) as Record<string, string>;
	const nonces = (data.nonces || {}) as Record<string, string>;
	if (!ajaxUrl || !actions.saveSettings || !nonces.saveSettings) {
		return;
	}

	let timer: number | null = null;
	const notify = (message: string, type?: ToastType): void => {
		if (window.notificatorToast && window.notificatorToast.show) {
			window.notificatorToast.show(message, type || 'info');
			return;
		}
		if (window.console && console.warn) {
			console.warn(message);
		}
	};
	const save = (): void => {
		if (timer) {
			clearTimeout(timer);
		}
		timer = window.setTimeout(() => {
			const formData = new FormData();
			formData.append('action', actions.saveSettings);
			formData.append('nonce', nonces.saveSettings);
			formData.append(
				'settings_patch',
				JSON.stringify({
					throttle_seconds: parseInt(throttleInput.value, 10) || 0,
					scan_hook_limit: parseInt(scanHookLimitInput.value, 10) || 500
				})
			);
			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: { Accept: 'application/json' }
			})
				.then((res) => res.json().catch(() => null))
				.then((json) => {
					if (json && json.success) {
						notify('Limits saved.', 'success');
						return;
					}
					notify('Failed to save limits.', 'error');
				})
				.catch(() => notify('Failed to save limits.', 'error'));
		}, 450);
	};

	['input', 'change'].forEach((evt) => {
		throttleInput.addEventListener(evt, save);
		scanHookLimitInput.addEventListener(evt, save);
	});
}

/** Isolate unrelated WordPress notices from the app-style workspace layout. */
function moveWpNotices(): void {
	const target = document.getElementById('notificator-admin-notices');
	const selectors = ['#message', '.notice', '.updated', '.error', '.settings-error'];
	const notices = Array.from(document.querySelectorAll<HTMLElement>(selectors.join(','))).filter((node) => {
		if (!(node instanceof HTMLElement)) {
			return false;
		}
		if (node.closest('.notificator-admin-notices')) {
			return false;
		}
		if (node.closest('.notificator-section')) {
			return false;
		}
		const parent = node.parentElement;
		if (!parent) {
			return false;
		}
		return (
			parent.id === 'wpbody-content' ||
			parent.classList.contains('wrap') ||
			parent.classList.contains('notificator-companion-wrap')
		);
	});

	if (notices.length) {
		notices.forEach((notice) => {
			notice.setAttribute('data-notificator-hidden-notice', '1');
			notice.style.display = 'none';
		});
	}

	if (target) {
		target.style.display = 'none';
	}

	if (!window.notificatorNoticesObserver && typeof MutationObserver !== 'undefined') {
		let updateScheduled = false;
		const observer = new MutationObserver(() => {
			if (updateScheduled) return;
			updateScheduled = true;
			window.requestAnimationFrame(() => {
				updateScheduled = false;
				moveWpNotices();
			});
		});
		observer.observe(document.body, { childList: true, subtree: true });
		window.notificatorNoticesObserver = observer;
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initToastSettingsAutosave);
} else {
	initToastSettingsAutosave();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initLimitsAutosave);
} else {
	initLimitsAutosave();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', moveWpNotices);
} else {
	moveWpNotices();
}

/**
 * Workspace navigation. Existing section ids remain supported so
 * bookmarks and links from older releases continue to land in the right place.
 */
function initWorkspaceNavigation(): void {
	const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-notificator-workspace]'));
	const tabs = Array.from(
		document.querySelectorAll<HTMLElement>('.notificator-workspace-tabs [data-notificator-workspace-tab]')
	);
	const title = document.getElementById('notificator-workspace-title');
	const shell = document.querySelector<HTMLElement>('.notificator-companion-wrap');
	if (!panels.length || !tabs.length) return;

	const labels: Record<string, string> = {
		overview: 'Overview',
		notifications: 'Notifications',
		activity: 'Activity',
		settings: 'Settings',
		developer: 'Developer',
		support: 'Support'
	};
	const legacy: Record<string, string> = {
		'notificator-api': 'settings',
		'notificator-help': 'support',
		'notificator-templates': 'notifications',
		'notificator-builder': 'notifications',
		'notificator-log': 'activity',
		'notificator-integrations': 'developer'
	};

	const resolveWorkspace = (): string => {
		const hash = window.location.hash.replace(/^#/, '');
		if (labels[hash]) return hash;
		if (legacy[hash]) return legacy[hash];
		const initial = shell?.dataset.notificatorInitialWorkspace || '';
		return labels[initial] ? initial : 'overview';
	};

	const activate = (workspace: string, updateHash = true): void => {
		const next = labels[workspace] ? workspace : 'overview';
		if (shell) shell.dataset.notificatorCurrentWorkspace = next;
		panels.forEach((panel) => {
			panel.hidden = panel.dataset.notificatorWorkspace !== next;
		});
		tabs.forEach((tab) => {
			const active = tab.dataset.notificatorWorkspaceTab === next;
			tab.classList.toggle('is-active', active);
			if (active) tab.setAttribute('aria-current', 'page');
			else tab.removeAttribute('aria-current');
		});
		if (title) title.textContent = labels[next];
		if (updateHash && window.location.hash !== `#${next}`) {
			history.replaceState(null, '', `#${next}`);
		}
		const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
	};

	document.addEventListener('click', (event) => {
		const target =
			event.target instanceof Element ? event.target.closest<HTMLElement>('[data-notificator-workspace-tab]') : null;
		if (!target) return;
		const workspace = target.dataset.notificatorWorkspaceTab;
		if (!workspace) return;
		event.preventDefault();
		activate(workspace);
	});

	document.addEventListener('click', (event) => {
		const target =
			event.target instanceof Element ? event.target.closest<HTMLElement>('[data-notificator-create]') : null;
		if (!target || target.hasAttribute('disabled')) return;
		activate('notifications');
		window.dispatchEvent(new CustomEvent('notificator:add-scenario'));
	});

	window.addEventListener('hashchange', () => activate(resolveWorkspace(), false));
	activate(resolveWorkspace(), false);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initWorkspaceNavigation);
} else {
	initWorkspaceNavigation();
}
