<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Каталог — {{ config('app.name') }}</title>
    {{-- SDK должен загрузиться раньше приложения: он выставляет тему и initData. --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @vite(['resources/css/mini-app.css', 'resources/js/mini-app.js'])
</head>
<body class="bg-tg-secondary text-tg-text antialiased">
<div
    x-data="miniApp(@js(['catalog' => $catalog]))"
    x-cloak
    class="mx-auto flex min-h-screen max-w-lg flex-col gap-3 px-3 pt-3 pb-24"
>
    <p
        x-show="!insideTelegram"
        class="rounded-xl bg-tg-section p-3 text-sm text-tg-hint"
    >
        Страница открыта в браузере. Посмотреть каталог можно, а оформить заявку — только из Telegram-бота.
    </p>

    {{-- ─── Каталог ─────────────────────────────────────────────── --}}
    <template x-if="screen === 'catalog'">
        <div class="flex flex-col gap-3">
            <template x-if="catalog.length === 0">
                <p class="rounded-xl bg-tg-section p-6 text-center text-tg-hint">
                    Каталог пока пуст. Загляните чуть позже.
                </p>
            </template>

            <nav class="-mx-3 flex gap-2 overflow-x-auto px-3 pb-1" x-show="catalog.length > 1">
                <template x-for="category in catalog" :key="category.id">
                    <button
                        type="button"
                        @click="activeCategory = category.id"
                        class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="activeCategory === category.id ? 'bg-tg-button text-tg-button-text' : 'bg-tg-section text-tg-text'"
                        x-text="category.name"
                    ></button>
                </template>
            </nav>

            <template x-for="service in visibleServices" :key="service.id">
                <article class="flex flex-col gap-3 rounded-xl bg-tg-section p-4">
                    <div class="flex flex-col gap-1">
                        <h2 class="font-semibold" x-text="service.name"></h2>
                        <p class="text-sm text-tg-hint" x-show="service.description" x-text="service.description"></p>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex flex-col">
                            <span class="font-semibold" x-text="money(service.price)"></span>
                            <span class="text-xs text-tg-hint" x-show="service.duration" x-text="`${service.duration} мин.`"></span>
                        </div>

                        <button
                            type="button"
                            x-show="quantity(service.id) === 0"
                            @click="add(service.id)"
                            class="rounded-lg bg-tg-button px-4 py-2 text-sm font-medium text-tg-button-text active:opacity-80"
                        >В корзину</button>

                        <div x-show="quantity(service.id) > 0" class="flex items-center gap-3">
                            <button type="button" @click="remove(service.id)" aria-label="Убрать одну"
                                    class="size-9 rounded-lg bg-tg-secondary text-lg font-semibold">−</button>
                            <span class="min-w-5 text-center font-semibold" x-text="quantity(service.id)"></span>
                            <button type="button" @click="add(service.id)" aria-label="Добавить ещё"
                                    class="size-9 rounded-lg bg-tg-button text-lg font-semibold text-tg-button-text">+</button>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </template>

    {{-- ─── Корзина и форма ─────────────────────────────────────── --}}
    <template x-if="screen === 'cart'">
        <div class="flex flex-col gap-3">
            <button type="button" x-show="!insideTelegram" @click="back()" class="self-start text-sm text-tg-link">
                ← Назад в каталог
            </button>

            <section class="flex flex-col gap-3 rounded-xl bg-tg-section p-4">
                <h2 class="text-lg font-semibold">Корзина</h2>

                <template x-for="line in cartLines" :key="line.id">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 flex-col">
                            <span class="truncate" x-text="line.name"></span>
                            <span class="text-sm text-tg-hint" x-text="`${money(line.price)} × ${line.quantity}`"></span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" @click="remove(line.id)" aria-label="Убрать одну"
                                    class="size-8 rounded-lg bg-tg-secondary font-semibold">−</button>
                            <span class="min-w-5 text-center" x-text="line.quantity"></span>
                            <button type="button" @click="add(line.id)" aria-label="Добавить ещё"
                                    class="size-8 rounded-lg bg-tg-secondary font-semibold">+</button>
                        </div>
                    </div>
                </template>

                <div class="flex justify-between border-t border-tg-secondary pt-3 font-semibold">
                    <span>Итого</span>
                    <span x-text="money(cartTotal)"></span>
                </div>
            </section>

            <form @submit.prevent="submit()" class="flex flex-col gap-3 rounded-xl bg-tg-section p-4">
                <h2 class="text-lg font-semibold">Контакты</h2>

                <label class="flex flex-col gap-1">
                    <span class="text-sm text-tg-hint">Как к вам обращаться</span>
                    <input type="text" x-model="form.contact_name" maxlength="100" required autocomplete="given-name"
                           class="rounded-lg bg-tg-secondary px-3 py-2 outline-none focus:ring-2 focus:ring-tg-button">
                    <span class="text-sm text-tg-destructive" x-show="errors.contact_name" x-text="errors.contact_name?.[0]"></span>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-sm text-tg-hint">Телефон</span>
                    <input type="tel" x-model="form.contact_phone" maxlength="32" required autocomplete="tel"
                           placeholder="+7 900 123-45-67"
                           class="rounded-lg bg-tg-secondary px-3 py-2 outline-none focus:ring-2 focus:ring-tg-button">
                    <span class="text-sm text-tg-destructive" x-show="errors.contact_phone" x-text="errors.contact_phone?.[0]"></span>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-sm text-tg-hint">Комментарий (необязательно)</span>
                    <textarea x-model="form.comment" rows="3" maxlength="1000" placeholder="Удобное время, пожелания"
                              class="rounded-lg bg-tg-secondary px-3 py-2 outline-none focus:ring-2 focus:ring-tg-button"></textarea>
                </label>

                <p class="text-sm text-tg-destructive" x-show="error" x-text="error"></p>

                <button type="submit" x-show="!insideTelegram" :disabled="sending"
                        class="rounded-lg bg-tg-button py-3 font-medium text-tg-button-text disabled:opacity-60">
                    Оформить заявку
                </button>
            </form>
        </div>
    </template>

    {{-- ─── Заявка принята ──────────────────────────────────────── --}}
    <template x-if="screen === 'done'">
        <section class="flex flex-col items-center gap-3 rounded-xl bg-tg-section p-6 text-center">
            <div class="text-5xl" x-text="paid ? '💳' : '✅'"></div>
            <h2 class="text-xl font-semibold" x-text="`Заявка №${order.number} принята`"></h2>
            <p class="text-tg-hint" x-show="!paid">
                Сумма: <span x-text="order.total"></span>. Мы свяжемся с вами в ближайшее время,
                подтверждение уже в чате с ботом.
            </p>
            <p class="text-tg-hint" x-show="paid">Оплата прошла. Чек и статус заявки придут в чат с ботом.</p>
        </section>
    </template>

    {{-- В браузере нет MainButton Telegram — показываем свою кнопку корзины. --}}
    <button
        type="button"
        x-show="!insideTelegram && screen === 'catalog' && cartCount > 0"
        @click="openCart()"
        class="fixed inset-x-3 bottom-3 mx-auto max-w-lg rounded-xl bg-tg-button py-3 font-medium text-tg-button-text shadow-lg"
        x-text="`Корзина · ${cartCount} · ${money(cartTotal)}`"
    ></button>
</div>
</body>
</html>
