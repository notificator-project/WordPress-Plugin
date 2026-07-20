/**
 * Global WordPress-admin dashboard-alert client.
 *
 * Polling pauses in hidden tabs, deduplicates sequence IDs across tabs, and
 * normalizes all server-owned preferences before constructing Notyf.
 */
import { Notyf, NotyfEvent } from 'notyf';
import 'notyf/notyf.min.css';
import './admin-toast.css';

type ToastType = 'success' | 'error' | 'warn' | 'info';
type ToastOptions = { duration?: number; url?: string; html?: boolean };
type ToastPayload = {
	title?: string;
	notes?: string;
	message?: string;
	type?: ToastType;
	url?: string;
	time?: number | string;
	seq?: number | string;
};
type PollLease = { owner: string; expiresAt: number };

const pollLeaseKey = 'notificatorToastPollLease';
const pollLeaseOwner =
	typeof window.crypto?.randomUUID === 'function'
		? window.crypto.randomUUID()
		: `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

/** Normalize server-provided toast preferences at the browser boundary. */
const getToastSettings = () => {
	const data = window.notificatorAdminToastData || {};
	const settings = data.toastSettings || {};
	const durationMs = Math.max(1000, Math.min(15000, (parseInt(String(settings.duration ?? 3), 10) || 3) * 1000));
	const positionX: 'left' | 'center' | 'right' =
		settings.positionX === 'left' || settings.positionX === 'center' ? settings.positionX : 'right';
	const positionY: 'top' | 'bottom' = settings.positionY === 'bottom' ? 'bottom' : 'top';
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
	} catch {
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
	} catch {
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
	const rawText = String(message ?? '').replace(/^[✅❌⚠️🔔]\s*/, '');
	const text = options.html ? rawText : escapeHtml(rawText);
	const { durationMs } = getToastSettings();
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

// Compatibility bridge for legacy admin handlers. New modules should call this API directly.
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

let toastRequestInFlight = false;

/**
 * Elect one visible tab to poll for account-wide alerts. The short lease
 * expires automatically, so another tab takes over after a crash or close.
 * Storage failures deliberately fail open to preserve alert delivery.
 */
const acquirePollLease = (): boolean => {
	if (getDeliveryMode() === 'tab') return true;
	try {
		const now = Date.now();
		const raw = window.localStorage.getItem(pollLeaseKey);
		const current = raw ? (JSON.parse(raw) as Partial<PollLease>) : null;
		if (current?.owner !== pollLeaseOwner && Number(current?.expiresAt || 0) > now) {
			return false;
		}
		const intervalSeconds = parseInt(String((window.notificatorAdminToastData || {}).pollInterval || 30), 10);
		const ttl = Math.max(30000, (Number.isNaN(intervalSeconds) ? 30 : intervalSeconds) * 2500);
		window.localStorage.setItem(pollLeaseKey, JSON.stringify({ owner: pollLeaseOwner, expiresAt: now + ttl }));
		const confirmed = JSON.parse(window.localStorage.getItem(pollLeaseKey) || '{}') as Partial<PollLease>;
		return confirmed.owner === pollLeaseOwner;
	} catch {
		return true;
	}
};

const releasePollLease = (): void => {
	try {
		const current = JSON.parse(window.localStorage.getItem(pollLeaseKey) || '{}') as Partial<PollLease>;
		if (current.owner === pollLeaseOwner) window.localStorage.removeItem(pollLeaseKey);
	} catch {
		// An expiring lease needs no cleanup when storage is unavailable.
	}
};

/** Render a queue payload through the same freshness and deduplication rules. */
const displayQueuedToasts = (toasts: ToastPayload[]): void => {
	const deliveryMode = getDeliveryMode();
	const nowSec = Math.floor(Date.now() / 1000);
	let seenSeqs = getSeenSeqs();
	toasts.forEach((toast) => {
		if (!toast || (!toast.message && !toast.title && !toast.notes)) return;
		if (deliveryMode === 'tab' && toast.time) {
			const toastTime = typeof toast.time === 'number' ? toast.time : parseInt(String(toast.time), 10);
			if (!Number.isNaN(toastTime) && toastTime > 0 && nowSec - toastTime > 180) return;
		}
		const seq = typeof toast.seq === 'number' ? toast.seq : parseInt(String(toast.seq), 10);
		if (!Number.isNaN(seq) && seq > 0) {
			if (seenSeqs.includes(seq)) return;
			seenSeqs.push(seq);
			if (seenSeqs.length > 100) seenSeqs = seenSeqs.slice(-100);
		}
		const formatted = formatToastMessage(toast);
		if (formatted) window.notificatorToast?.show(formatted, toast.type || 'info', { url: toast.url, html: true });
	});
	if (seenSeqs.length) setSeenSeqs(seenSeqs);
};

const fetchQueuedToasts = (): void => {
	if (document.hidden || toastRequestInFlight || !isToastPollingEnabled() || !acquirePollLease()) {
		return;
	}
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
	toastRequestInFlight = true;
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
			displayQueuedToasts(payload.data.toasts);
		})
		.catch(() => {
			// Background polling is best-effort; the next interval retries automatically.
		})
		.finally(() => {
			toastRequestInFlight = false;
		});
};

let toastPollTimer: number | null = null;

const isToastPollingEnabled = (): boolean => (window.notificatorAdminToastData || {}).enabled !== false;

const getToastPollIntervalMs = (): number => {
	const seconds = parseInt(String((window.notificatorAdminToastData || {}).pollInterval || 30), 10);
	return Math.max(15, Math.min(300, Number.isNaN(seconds) ? 30 : seconds)) * 1000;
};

const stopToastPolling = (): void => {
	if (toastPollTimer !== null) {
		window.clearInterval(toastPollTimer);
		toastPollTimer = null;
	}
};

const scheduleToastPolling = (): void => {
	stopToastPolling();
	if (!document.hidden && isToastPollingEnabled()) {
		toastPollTimer = window.setInterval(fetchQueuedToasts, getToastPollIntervalMs());
	}
};

const startToastPolling = (): void => {
	if (window.notificatorToastPollStarted) {
		return;
	}
	window.notificatorToastPollStarted = true;
	const initialData = window.notificatorAdminToastData || {};
	if (Array.isArray(initialData.pendingToasts) && initialData.pendingToasts.length) {
		displayQueuedToasts(initialData.pendingToasts as ToastPayload[]);
		initialData.pendingToasts = [];
	}
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
				const form = document.querySelector<HTMLFormElement>('form[action="options.php"]');
				if (form && typeof form.requestSubmit === 'function') {
					form.requestSubmit();
				}
			}
		});
	}
	if (isToastPollingEnabled()) {
		fetchQueuedToasts();
	}
	scheduleToastPolling();
	document.addEventListener('notificator:admin-toasts-toggle', (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		if (!window.notificatorAdminToastData) window.notificatorAdminToastData = {};
		window.notificatorAdminToastData.enabled = !!(detail && detail.enabled);
		if (window.notificatorAdminToastData.enabled) fetchQueuedToasts();
		scheduleToastPolling();
	});
	const pollIntervalInput = document.getElementById('notificator-toast-poll-interval') as HTMLSelectElement | null;
	pollIntervalInput?.addEventListener('change', () => {
		const seconds = Math.max(15, Math.min(300, parseInt(pollIntervalInput.value, 10) || 30));
		pollIntervalInput.value = String(seconds);
		if (!window.notificatorAdminToastData) window.notificatorAdminToastData = {};
		window.notificatorAdminToastData.pollInterval = seconds;
		const summaryDetail = document.querySelector<HTMLElement>('#notificator-dashboard-summary em');
		if (summaryDetail) {
			summaryDetail.textContent =
				seconds === 60
					? 'Every minute'
					: seconds % 60 === 0
						? `Every ${seconds / 60} minutes`
						: `Every ${seconds} seconds`;
		}
		scheduleToastPolling();
	});
	document.addEventListener('visibilitychange', () => {
		if (document.hidden) {
			stopToastPolling();
			releasePollLease();
			return;
		}
		if (isToastPollingEnabled()) fetchQueuedToasts();
		scheduleToastPolling();
	});
	window.addEventListener('pagehide', releasePollLease);
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', startToastPolling);
} else {
	startToastPolling();
}
