<?php

namespace Omnifexit\OmniCore\Uploads;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class AllowedAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(Attachments::message('not_allowed', ['name' => $value instanceof UploadedFile ? $value->getClientOriginalName() : '']));

            return;
        }

        $name = $value->getClientOriginalName();

        // Both the name and the content have to fit, so a renamed program doesn't get through.
        $extension = strtolower($value->getClientOriginalExtension());
        $content = $value->guessExtension();

        if (! in_array($extension, Attachments::EXTENSIONS, true) || ! in_array($content, Attachments::EXTENSIONS, true)) {
            $fail(Attachments::message('not_allowed', ['name' => $name]));

            return;
        }

        if ($value->getSize() > Attachments::MAX_KILOBYTES * 1024) {
            $fail(Attachments::message('too_large', ['name' => $name]));
        }
    }
}
