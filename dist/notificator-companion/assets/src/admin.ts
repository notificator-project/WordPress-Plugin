// Admin bundle entry (Vite)
// - Bundles Tailwind + SCSS into admin.css
// - Bundles scenario templates + admin UI JS
// - Bundles Alpine.js locally and starts it after our globals are defined

import './tailwind.css';
import './admin.scss';

import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

// These scripts are written as side-effect files (IIFEs) and attach to window.
import '../js/scenario-templates';
import '../js/admin-scenarios';

import Alpine from 'alpinejs';

type ToastType = 'success' | 'error' | 'warn' | 'info';
type ToastOptions = { duration?: number; url?: string };

// Expose Alpine for debugging/devtools parity with CDN usage.
window.Alpine = Alpine;

const initAdminToasts = (): void => {
	const data = window.notificatorCompanionData || {};
	const enabled = data.toastsEnabled !== false;
	if (!enabled) {
		return;
	}

	const toastSettings = data.toastSettings || {};
	const durationMs = Math.max(1000, Math.min(15000, (parseInt(toastSettings.duration, 10) || 3) * 1000));
	const positionX = ['left', 'center', 'right'].includes(toastSettings.positionX) ? toastSettings.positionX : 'right';
	const positionY = ['top', 'bottom'].includes(toastSettings.positionY) ? toastSettings.positionY : 'top';
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
		const text = String(message ?? '').replace(/^[✅❌⚠️🔔]\s*/, '');
		const duration = typeof options.duration === 'number' ? options.duration : durationMs;
		const url = typeof options.url === 'string' ? options.url : undefined;
		const colors = {
			success: '#16a34a',
			error: '#ef4444',
			warn: '#f59e0b',
			info: '#2563eb'
		};
		const onClick = url
			? () => {
				window.location.href = url;
			}
			: undefined;
		const variant = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
		notyf.open({
			type: variant,
			message: text,
			background: colors[type] || colors.info,
			duration,
			className: `notificator-toast notificator-toast--${type}`,
			clickable: !!onClick,
			onClick
		});
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

function initLogSearch(): void {
	const input = document.getElementById('notificator-log-search') as HTMLInputElement | null;
	const table = document.querySelector('.notificator-log-table') as HTMLTableElement | null;
	const empty = document.getElementById('notificator-log-empty') as HTMLElement | null;
	const count = document.getElementById('notificator-log-count') as HTMLElement | null;
	const prev = document.getElementById('notificator-log-prev') as HTMLButtonElement | null;
	const next = document.getElementById('notificator-log-next') as HTMLButtonElement | null;
	const page = document.getElementById('notificator-log-page') as HTMLElement | null;
	const perPageSelect = document.getElementById('notificator-log-per-page') as HTMLSelectElement | null;
	if (!input || !table) {
		return;
	}

	const rows = Array.from(table.querySelectorAll('tbody tr')) as HTMLTableRowElement[];
	let perPage = perPageSelect ? parseInt(perPageSelect.value, 10) || 20 : 20;
	let currentPage = 1;
	let filteredRows = rows;

	const applyPagination = () => {
		const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
		if (currentPage > totalPages) {
			currentPage = totalPages;
		}
		const start = (currentPage - 1) * perPage;
		const end = start + perPage;
		filteredRows.forEach((row, index) => {
			row.style.display = index >= start && index < end ? '' : 'none';
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
		const query = input.value.trim().toLowerCase();
		let visible = 0;
		filteredRows = rows.filter((row) => row && row.isConnected).filter((row) => {
			const text = row.textContent ? row.textContent.toLowerCase() : '';
			const match = !query || text.includes(query);
			if (match) {
				visible += 1;
			}
			return match;
		});
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
	applyFilter();
}

function initLogTools(): void {
	const toggleBtn = document.getElementById('notificator-toggle-log') as HTMLButtonElement | null;
	const exportBtn = document.getElementById('notificator-export-log') as HTMLButtonElement | null;
	const clearBtn = document.getElementById('notificator-clear-log') as HTMLButtonElement | null;
	const logTable = document.querySelector('.notificator-log-table') as HTMLTableElement | null;
	const data = window.notificatorCompanionData || {};
	const ajaxUrl = data.ajaxUrl || '';
	const actions = (data.actions || {}) as Record<string, string>;
	const nonces = (data.nonces || {}) as Record<string, string>;

	const postAction = (action: string, nonce: string, payload: Record<string, string>): Promise<Response> => {
		const params = new URLSearchParams();
		params.set('action', action);
		params.set('nonce', nonce);
		Object.keys(payload).forEach((key) => params.set(key, payload[key]));
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: params.toString(),
			credentials: 'same-origin',
		});
	};

	if (toggleBtn) {
		toggleBtn.addEventListener('click', () => {
			if (!ajaxUrl || !actions.toggleLog || !nonces.toggleLog) {
				alert('Missing AJAX configuration.');
				return;
			}
			const isEnabled = toggleBtn.getAttribute('data-log-enabled') === '1';
			const nextState = isEnabled ? 'disable' : 'enable';
			const confirmText = isEnabled ? 'Disable notifications log?' : 'Enable notifications log?';
			if (!confirm(confirmText)) {
				return;
			}
			toggleBtn.disabled = true;
			postAction(actions.toggleLog, nonces.toggleLog, { state: nextState })
				.then((res) => res.json())
				.then((json) => {
					if (json && json.success) {
						alert(json.data.message || 'Log updated.');
						window.location.reload();
					} else {
						alert((json && json.data && json.data.message) || 'Failed to update log.');
					}
				})
				.catch(() => alert('Network error.'))
				.finally(() => {
					toggleBtn.disabled = false;
				});
		});
	}

	if (exportBtn) {
			exportBtn.addEventListener('click', () => {
			if (!ajaxUrl || !actions.exportLog || !nonces.exportLog) {
				alert('Missing AJAX configuration.');
				return;
			}
			exportBtn.disabled = true;
			const original = exportBtn.innerHTML;
			exportBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Exporting...';
			postAction(actions.exportLog, nonces.exportLog, {})
				.then((res) => res.json())
				.then((json) => {
					if (!json || !json.success) {
						throw new Error((json && json.data && json.data.message) || 'Export failed');
					}
					const csv = json.data && json.data.csv ? json.data.csv : '';
					const fileName = json.data && json.data.file_name ? json.data.file_name : 'notificator-log.csv';
					const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
					const url = URL.createObjectURL(blob);
					const link = document.createElement('a');
					link.href = url;
					link.download = fileName;
					document.body.appendChild(link);
					link.click();
					link.remove();
					URL.revokeObjectURL(url);
				})
				.catch((err) => alert(err && err.message ? err.message : 'Export failed'))
				.finally(() => {
					exportBtn.disabled = false;
					exportBtn.innerHTML = original;
				});
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', () => {
			if (!ajaxUrl || !actions.clearLog || !nonces.clearLog) {
				alert('Missing AJAX configuration.');
				return;
			}
			if (!confirm('Clear all log entries?')) {
				return;
			}
			clearBtn.disabled = true;
			postAction(actions.clearLog, nonces.clearLog, {})
				.then((res) => res.json())
				.then((json) => {
					if (json && json.success) {
						window.location.reload();
					} else {
						alert((json && json.data && json.data.message) || 'Failed to clear log.');
					}
				})
				.catch(() => alert('Network error.'))
				.finally(() => {
					clearBtn.disabled = false;
				});
		});
	}

	if (logTable) {
		logTable.addEventListener('click', (event) => {
			const target = event.target instanceof Element
				? event.target.closest('.notificator-log-delete')
				: null;
			if (!target) {
				return;
			}
			const actionButton = target instanceof HTMLButtonElement ? target : null;
			const entryId = target.getAttribute('data-log-id');
			if (!entryId) {
				return;
			}
			if (!ajaxUrl || !actions.deleteLog || !nonces.deleteLog) {
				alert('Missing AJAX configuration.');
				return;
			}
			if (!confirm('Delete this log entry?')) {
				return;
			}
			if (actionButton) {
				actionButton.disabled = true;
			}
			postAction(actions.deleteLog, nonces.deleteLog, { entry_id: entryId })
				.then((res) => res.json())
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
						alert((json && json.data && json.data.message) || 'Failed to delete log entry.');
					}
				})
				.catch(() => alert('Network error.'))
				.finally(() => {
					if (actionButton) {
						actionButton.disabled = false;
					}
				});
		});
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initLogSearch);
} else {
	initLogSearch();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initLogTools);
} else {
	initLogTools();
}

function initApiKeyWarning(): void {
	const apiContainer = document.getElementById('notificator-api-keys') as HTMLElement | null;
	const warning = document.querySelector('[data-notificator-lock="api-warning"]') as HTMLElement | null;
	if (!apiContainer || !warning) {
		return;
	}
	const inputs = Array.from(
		apiContainer.querySelectorAll('input[name*="[api_keys]"]')
	) as HTMLInputElement[];
	const hasKey = apiContainer.getAttribute('data-has-api-key') === '1'
		|| inputs.some((input) => input.value && input.value.trim());
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

function initToastSettingsAutosave(): void {
	const durationInput = document.getElementById('notificator-toast-duration') as HTMLInputElement | null;
	const positionX = document.getElementById('notificator-toast-position-x') as HTMLSelectElement | null;
	const positionY = document.getElementById('notificator-toast-position-y') as HTMLSelectElement | null;
	const deliveryMode = document.getElementById('notificator-toast-delivery') as HTMLSelectElement | null;
	const form = document.querySelector('form[action="options.php"]') as HTMLFormElement | null;
	if (!durationInput || !positionX || !positionY || !deliveryMode || !form) {
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
			window.notificatorAdminToastData.toastSettings = nextSettings;
			window.notificatorAdminToastData.toastDeliveryMode = nextSettings.deliveryMode;
		}
		initAdminToasts();
	};
	const save = (): void => {
		if (timer) {
			clearTimeout(timer);
		}
		timer = window.setTimeout(() => {
			const formData = new FormData(form);
			formData.append('action', actions.saveSettings);
			formData.append('nonce', nonces.saveSettings);
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
		durationInput.addEventListener(evt, save);
		positionX.addEventListener(evt, save);
		positionY.addEventListener(evt, save);
		deliveryMode.addEventListener(evt, save);
	});
}

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
			const formData = new FormData(form);
			formData.append('action', actions.saveSettings);
			formData.append('nonce', nonces.saveSettings);
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

function moveWpNotices(): void {
	const target = document.getElementById('notificator-admin-notices');
	const selectors = ['#message', '.notice', '.updated', '.error', '.settings-error'];
	const notices = Array.from(document.querySelectorAll(selectors.join(','))).filter((node) => {
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
			parent.id === 'wpbody-content'
			|| parent.classList.contains('wrap')
			|| parent.classList.contains('notificator-companion-wrap')
			|| node.closest('.notificator-companion-header') !== null
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
		const observer = new MutationObserver(() => {
			moveWpNotices();
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
