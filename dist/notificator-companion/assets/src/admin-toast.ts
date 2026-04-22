import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import './admin-toast.css';

type ToastType = 'success' | 'error' | 'warn' | 'info';
type ToastOptions = { duration?: number; url?: string };
type ToastPayload = {
	title?: string;
	notes?: string;
	message?: string;
	type?: ToastType;
	url?: string;
	time?: number | string;
	seq?: number | string;
};

const getToastSettings = () => {
	const data = window.notificatorAdminToastData || {};
	const settings = data.toastSettings || {};
	const durationMs = Math.max(1000, Math.min(15000, (parseInt(settings.duration, 10) || 3) * 1000));
	const positionX = ['left', 'center', 'right'].includes(settings.positionX) ? settings.positionX : 'right';
	const positionY = ['top', 'bottom'].includes(settings.positionY) ? settings.positionY : 'top';
	const dismissMode = settings.dismissMode === 'click' ? 'click' : 'auto';
	return { durationMs, positionX, positionY, dismissMode };
};

const getDeliveryMode = () => {
	const data = window.notificatorAdminToastData || {};
	const mode = data.toastDeliveryMode || (data.toastSettings && data.toastSettings.deliveryMode) || 'account';
	return mode === 'tab' ? 'tab' : 'account';
};

const readSeenSeqs = (storage: Storage, key: string): number[] => {
	try {
		const raw = storage.getItem(key);
		const parsed = raw ? JSON.parse(raw) : [];
		return Array.isArray(parsed) ? parsed : [];
	} catch (err) {
		return [];
	}
};

const getSeenSeqs = (): number[] => {
	const sessionSeqs = readSeenSeqs(window.sessionStorage, 'notificatorToastSeen');
	const localSeqs = readSeenSeqs(window.localStorage, 'notificatorToastSeenGlobal');
	const combined = [...sessionSeqs, ...localSeqs];
	return Array.from(new Set(combined));
};

const setSeenSeqs = (seqs: number[]): void => {
	try {
		const serialized = JSON.stringify(seqs);
		window.sessionStorage.setItem('notificatorToastSeen', serialized);
		window.localStorage.setItem('notificatorToastSeenGlobal', serialized);
	} catch (err) {
		// Ignore storage errors.
	}
};

const getNotyf = (): Notyf => {
	const { durationMs, positionX, positionY, dismissMode } = getToastSettings();
	const settingsKey = `${durationMs}:${positionX}:${positionY}:${dismissMode}`;
	if (!window.notificatorNotyf || window.notificatorToastSettings !== settingsKey) {
		window.notificatorToastSettings = settingsKey;
		const dismissible = dismissMode === 'click';
		const duration = dismissible ? 0 : durationMs;
		window.notificatorNotyf = new Notyf({
			duration,
			position: { x: positionX, y: positionY },
			ripple: false,
			dismissible
		});
	}
	return window.notificatorNotyf as Notyf;
};

const escapeHtml = (value: unknown): string => {
	return String(value || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
};

const formatToastMessage = (toastOrMessage: unknown): string => {
	if (!toastOrMessage) {
		return '';
	}
	if (typeof toastOrMessage === 'string') {
		return escapeHtml(toastOrMessage);
	}
	if (typeof toastOrMessage !== 'object') {
		return '';
	}
	const toast = toastOrMessage as ToastPayload;
	const title = toast.title || '';
	const notes = toast.notes || '';
	const message = toast.message || '';
	const cleanTitle = escapeHtml(title || message);
	const cleanNotes = escapeHtml(notes);
	if (!cleanTitle && !cleanNotes) {
		return '';
	}
	if (!cleanNotes) {
		return `<div class="notificator-toast__title">${cleanTitle}</div>`;
	}
	return `<div class="notificator-toast__title">${cleanTitle}</div><div class="notificator-toast__notes">${cleanNotes}</div>`;
};

const showNotyf = (message: unknown, type: ToastType = 'info', options: ToastOptions = {}): void => {
	const notyf = getNotyf();
	const text = String(message ?? '').replace(/^[✅❌⚠️🔔]\s*/, '');
	const { durationMs } = getToastSettings();
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

window.notificatorToast = {
	show: (message, type, options) => showNotyf(message, type || 'info', options || {}),
	update: (_toast, message, type, options) => showNotyf(message, type || 'info', options || {}),
	formatMessage: (toast) => formatToastMessage(toast)
};

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
		showNotyf(text, type as ToastType);
	};
}

const fetchQueuedToasts = (): void => {
	const data = window.notificatorAdminToastData || {};
	const ajaxUrl = data.ajaxUrl || window.ajaxurl || '';
	const action = data.action || '';
	const nonce = data.nonce || '';
	if (!ajaxUrl || !action || !nonce) {
		return;
	}
	const params = new URLSearchParams();
	params.set('action', action);
	params.set('nonce', nonce);
	fetch(ajaxUrl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: params.toString(),
		credentials: 'same-origin'
	})
		.then((res) => res.json())
		.then((json) => {
			const payload = json as { success?: boolean; data?: { toasts?: ToastPayload[] } } | null;
			if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.toasts)) {
				return;
			}
			const deliveryMode = getDeliveryMode();
			const nowSec = Math.floor(Date.now() / 1000);
			let seenSeqs = getSeenSeqs();
			if (!Array.isArray(seenSeqs)) {
				seenSeqs = [];
			}
			payload.data.toasts.forEach((toast) => {
				if (!toast || (!toast.message && !toast.title && !toast.notes)) {
					return;
				}
				if (deliveryMode === 'tab' && toast.time) {
					const toastTime = typeof toast.time === 'number'
						? toast.time
						: parseInt(String(toast.time), 10);
					if (!Number.isNaN(toastTime) && toastTime > 0 && (nowSec - toastTime) > 180) {
						return;
					}
				}
				const seq = typeof toast.seq === 'number' ? toast.seq : parseInt(String(toast.seq), 10);
				if (!Number.isNaN(seq) && seq > 0) {
					if (seenSeqs.includes(seq)) {
						return;
					}
					seenSeqs.push(seq);
					if (seenSeqs.length > 100) {
						seenSeqs = seenSeqs.slice(seenSeqs.length - 100);
					}
				}
				const formatted = formatToastMessage(toast);
				if (!formatted) {
					return;
				}
				window.notificatorToast.show(formatted, toast.type || 'info', { url: toast.url });
			});
			if (seenSeqs.length) {
				setSeenSeqs(seenSeqs);
			}
		})
		.catch(() => {});
};

const startToastPolling = (): void => {
	if (window.notificatorToastPollStarted) {
		return;
	}
	window.notificatorToastPollStarted = true;
	const dismissSelect = document.getElementById('notificator-toast-dismiss') as HTMLSelectElement | null;
	if (dismissSelect) {
		dismissSelect.addEventListener('change', (event) => {
			const target = event.target as HTMLSelectElement | null;
			const value = target ? target.value : '';
			if (!window.notificatorAdminToastData) {
				window.notificatorAdminToastData = {};
			}
			if (!window.notificatorAdminToastData.toastSettings) {
				window.notificatorAdminToastData.toastSettings = {};
			}
			window.notificatorAdminToastData.toastSettings.dismissMode = value === 'click' ? 'click' : 'auto';
			window.notificatorNotyf = null;
			window.notificatorToastSettings = null;
			getNotyf();
			if (window.notificatorScenarioBuilder && typeof window.notificatorScenarioBuilder.submitForm === 'function') {
				window.notificatorScenarioBuilder.submitForm();
			} else {
				const form = document.querySelector('form[action="options.php"]');
				if (form && typeof form.requestSubmit === 'function') {
					form.requestSubmit();
				}
			}
		});
	}
	fetchQueuedToasts();
	window.setInterval(fetchQueuedToasts, 8000);
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', startToastPolling);
} else {
	startToastPolling();
}
