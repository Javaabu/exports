<?php

namespace Javaabu\Exports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Exports;

/**
 * Starting a registered export: `type` (its registered name) and `format`
 */
class ExportRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(array_keys(Exports::registered()))],
            'format' => ['nullable', 'string', Rule::in(array_map(fn (Formats $format) => $format->value, Formats::enabled()))],
            'filename' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('export'),
            'format' => __('format'),
            'filename' => __('file name'),
        ];
    }
}
