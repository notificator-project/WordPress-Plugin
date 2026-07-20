import type { Notyf } from 'notyf';

type ToastType = 'success' | 'error' | 'warn' | 'info';

type NotificatorToastSettings = {
	duration?: number | string;
	positionX?: 'left' | 'center' | 'right' | string;
	positionY?: 'top' | 'bottom' | string;
	dismissMode?: 'auto' | 'click' | string;
	deliveryMode?: 'account' | 'tab' | string;
};

type NotificatorToastOptions = {
	duration?: number;
	url?: string;
	/** Render trusted, already-escaped markup instead of escaping as plain text. */
	html?: boolean;
};

type NotificatorScenarioBuilderApi = {
	submitForm?: () => void;
	openAddModal?: () => void;
	selectHook?: (hookName: string, metadata: unknown) => void;
	selectedPluginModal?: string;
	hooks?: unknown;
	availablePlugins?: Record<string, { hooks?: Record<string, unknown> }>;
	pluginActiveStatus?: unknown;
	hookActiveStatus?: unknown;
};

type NotificatorToastApi = {
	show: (message: unknown, type?: ToastType, options?: NotificatorToastOptions) => void;
	update: (toast: unknown, message: unknown, type?: ToastType, options?: NotificatorToastOptions) => void;
	formatMessage?: (toast: unknown) => string;
};

type NotificatorCompanionData = {
	toastsEnabled?: boolean;
	toastSettings?: NotificatorToastSettings;
	ajaxUrl?: string;
	actions?: Record<string, string>;
	nonces?: Record<string, string>;
	health?: Record<string, unknown>;
};

type NotificatorAdminToastData = {
	enabled?: boolean;
	pollInterval?: number | string;
	toastSettings?: NotificatorToastSettings;
	toastDeliveryMode?: 'account' | 'tab' | string;
	ajaxUrl?: string;
	action?: string;
	nonce?: string;
	pendingToasts?: Array<Record<string, unknown>>;
};

declare global {
	const ajaxurl: string;
	const notificatorWooCommerceOrderStatuses: Array<{ value: string; label: string } | string> | undefined;
	const notificatorActivePlugins: string[] | undefined;
	const notificatorScenarioTemplatesExtra: unknown[] | undefined;
	const jQuery: unknown;

	interface Window {
		ajaxurl?: string;
		Alpine?: unknown;
		notificatorCompanionData?: NotificatorCompanionData;
		notificatorAdminToastData?: NotificatorAdminToastData;
		notificatorNotyf?: Notyf | null;
		notificatorToastSettings?: string | null;
		notificatorToast?: NotificatorToastApi;
		notificatorOriginalAlert?: typeof window.alert;
		notificatorToastPollStarted?: boolean;
		notificatorNoticesObserver?: MutationObserver;
		notificatorScenarioBuilder?: NotificatorScenarioBuilderApi;
		notificatorScenarioTemplates?: unknown[];
		notificatorScenarioTemplatesExtra?: unknown[];
		initScenarioBuilder?: (...args: unknown[]) => unknown;
		startPluginScan?: () => void;
		closeScanModal?: () => void;
		notificatorShowToast?: (message: string, type?: string, duration?: number) => unknown;
		notificatorUpdateToast?: (toast: unknown, message: string, type?: string, duration?: number) => unknown;
	}
}

export {};
