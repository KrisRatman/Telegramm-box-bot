import Alpine from 'alpinejs';

/*
 * Mini App: каталог, корзина и оформление заявки.
 *
 * Каталог приходит в HTML (аргумент x-data), корзина живёт в localStorage.
 * Сервер получает только id услуг и количество, цены считает сам.
 * Каждый запрос к API несёт Telegram.WebApp.initData — по нему сервер
 * проверяет, что запрос пришёл из Telegram от этого пользователя.
 */

const tg = window.Telegram?.WebApp;
const CART_KEY = 'mini-app-cart';

const money = (value) => `${Math.round(value).toLocaleString('ru-RU')} ₽`;

function readCart() {
    try {
        return JSON.parse(localStorage.getItem(CART_KEY) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

function writeCart(cart) {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    } catch {
        // Хранилище недоступно (приватный режим) — корзина просто не переживёт перезапуск.
    }
}

async function api(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Telegram-Init-Data': tg?.initData ?? '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message ?? 'Не удалось связаться с сервером.');
        error.status = response.status;
        error.errors = data.errors ?? {};
        throw error;
    }

    return data;
}

Alpine.data('miniApp', ({ catalog }) => ({
    catalog,
    screen: 'catalog', // catalog | cart | done
    activeCategory: catalog[0]?.id ?? null,
    cart: {},
    form: { contact_name: '', contact_phone: '', comment: '' },
    errors: {},
    error: '',
    sending: false,
    order: null,
    paid: false,
    insideTelegram: Boolean(tg?.initData),
    orderStartTracked: false,

    init() {
        // Услуги, которые убрали из каталога, из сохранённой корзины выбрасываем.
        const known = new Set(this.services.map((service) => service.id));
        this.cart = Object.fromEntries(
            Object.entries(readCart()).filter(([id, qty]) => known.has(Number(id)) && qty > 0),
        );

        this.$watch('cart', (cart) => writeCart(cart));
        this.$watch('screen', () => this.syncTelegramButtons());
        this.$watch('cart', () => this.syncTelegramButtons());

        if (tg) {
            tg.ready();
            tg.expand();
            tg.MainButton.onClick(() => this.onMainButton());
            tg.BackButton.onClick(() => this.back());
        }

        this.syncTelegramButtons();
        this.loadProfile();
        this.track('catalog_viewed');
    },

    get services() {
        return this.catalog.flatMap((category) => category.services);
    },

    get visibleServices() {
        return this.catalog.find((category) => category.id === this.activeCategory)?.services ?? [];
    },

    get cartLines() {
        return this.services
            .filter((service) => this.cart[service.id])
            .map((service) => ({ ...service, quantity: this.cart[service.id] }));
    },

    get cartCount() {
        return Object.values(this.cart).reduce((sum, qty) => sum + qty, 0);
    },

    get cartTotal() {
        return this.cartLines.reduce((sum, line) => sum + line.price * line.quantity, 0);
    },

    money,

    quantity(serviceId) {
        return this.cart[serviceId] ?? 0;
    },

    add(serviceId) {
        this.cart = { ...this.cart, [serviceId]: Math.min(this.quantity(serviceId) + 1, 99) };
        tg?.HapticFeedback?.selectionChanged();
    },

    remove(serviceId) {
        const next = { ...this.cart };
        next[serviceId] = this.quantity(serviceId) - 1;

        if (next[serviceId] <= 0) {
            delete next[serviceId];
        }

        this.cart = next;
        tg?.HapticFeedback?.selectionChanged();

        if (this.cartCount === 0 && this.screen === 'cart') {
            this.screen = 'catalog';
        }
    },

    openCart() {
        if (this.cartCount > 0) {
            this.screen = 'cart';
            window.scrollTo({ top: 0 });

            if (!this.orderStartTracked) {
                this.orderStartTracked = true;
                this.track('order_started');
            }
        }
    },

    back() {
        if (this.screen === 'cart') {
            this.screen = 'catalog';
        }
    },

    onMainButton() {
        if (this.screen === 'catalog') {
            this.openCart();
        } else if (this.screen === 'cart') {
            this.submit();
        } else if (this.order?.invoice_link && !this.paid) {
            this.pay();
        } else {
            tg?.close();
        }
    },

    /*
     * Главная кнопка Telegram внизу экрана повторяет действие текущего шага,
     * «Назад» в шапке видна только в корзине. В обычном браузере их нет —
     * там работают кнопки на самой странице.
     */
    syncTelegramButtons() {
        if (!this.insideTelegram) {
            return;
        }

        const main = tg.MainButton;
        let text = null;

        if (this.screen === 'catalog' && this.cartCount > 0) {
            text = `Корзина · ${this.cartCount} · ${money(this.cartTotal)}`;
        } else if (this.screen === 'cart') {
            text = `Оформить заявку · ${money(this.cartTotal)}`;
        } else if (this.screen === 'done') {
            text = this.order?.invoice_link && !this.paid ? `Оплатить ${this.order.total}` : 'Закрыть';
        }

        if (text) {
            main.setText(text);
            main.show();
        } else {
            main.hide();
        }

        if (this.screen === 'cart') {
            tg.BackButton.show();
        } else {
            tg.BackButton.hide();
        }
    },

    /*
     * Шаг воронки для аналитики в админке. Ответ не ждём и ошибки
     * глотаем: статистика не должна мешать оформлению заявки.
     */
    track(type) {
        if (this.insideTelegram) {
            api('POST', '/app/api/events', { type }).catch(() => {});
        }
    },

    async loadProfile() {
        const fromTelegram = tg?.initDataUnsafe?.user?.first_name ?? '';
        this.form.contact_name ||= fromTelegram;

        if (!this.insideTelegram) {
            return;
        }

        try {
            const profile = await api('GET', '/app/api/profile');
            this.form.contact_name ||= profile.name ?? '';
            this.form.contact_phone ||= profile.phone ?? '';
        } catch {
            // Автозаполнение — удобство, без него форма тоже работает.
        }
    },

    async submit() {
        if (this.sending) {
            return;
        }

        if (!this.insideTelegram) {
            this.error = 'Оформить заявку можно только из Telegram — откройте каталог кнопкой в боте.';
            return;
        }

        this.sending = true;
        this.errors = {};
        this.error = '';
        tg?.MainButton.showProgress();

        try {
            this.order = await api('POST', '/app/api/orders', {
                ...this.form,
                items: this.cartLines.map((line) => ({ service_id: line.id, quantity: line.quantity })),
            });

            this.cart = {};
            this.screen = 'done';
            tg?.HapticFeedback?.notificationOccurred('success');
        } catch (e) {
            this.errors = e.errors ?? {};
            this.error = e.errors?.items?.[0] ?? (Object.keys(this.errors).length ? '' : e.message);
            tg?.HapticFeedback?.notificationOccurred('error');
        } finally {
            this.sending = false;
            tg?.MainButton.hideProgress();
        }
    },

    pay() {
        if (!this.order?.invoice_link || !tg) {
            return;
        }

        // Статус из openInvoice — только для интерфейса. Оплату фиксирует
        // сервер по successful_payment, который Telegram присылает боту.
        tg.openInvoice(this.order.invoice_link, (status) => {
            if (status === 'paid') {
                this.paid = true;
                this.syncTelegramButtons();
            }
        });
    },
}));

Alpine.start();
