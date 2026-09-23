<?php

namespace App\Http\Requests\MiniApp;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Заявка из корзины Mini App. Пользователя уже проверил AuthenticateMiniApp,
 * здесь только форма. Цены с фронта не принимаем — только id и количество.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.service_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => [
                'required',
                'string',
                'max:32',
                // Как в диалоге бота: от 10 до 15 цифр, форматирование любое.
                function (string $attribute, mixed $value, Closure $fail) {
                    $digits = strlen(preg_replace('/\D+/', '', (string) $value) ?? '');

                    if ($digits < 10 || $digits > 15) {
                        $fail('Укажите телефон полностью, например +7 900 123-45-67.');
                    }
                },
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'корзина',
            'contact_name' => 'имя',
            'contact_phone' => 'телефон',
            'comment' => 'комментарий',
        ];
    }

    /**
     * @return array<int, int> service_id => количество
     */
    public function quantities(): array
    {
        return collect($this->validated('items'))
            ->mapWithKeys(fn (array $item) => [(int) $item['service_id'] => (int) $item['quantity']])
            ->all();
    }
}
