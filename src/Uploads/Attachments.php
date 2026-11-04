<?php

namespace Omnifexit\OmniCore\Uploads;

use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * What can be attached in any app, and how a stored file is sent back to the browser.
 */
class Attachments
{
    public const array EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'html', 'htm',
    ];

    public const int MAX_KILOBYTES = 10240;

    /** Types a browser shows without running anything from the file. */
    private const array PLAIN_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'files' => 'nullable|array',
            'files.*' => [new AllowedAttachment],
        ];
    }

    /**
     * Files open on the app's own address, where a script in one would run as whoever opened it, logged in. Anything
     * that isn't plain gets a sandbox: an HTML presentation still works, but can't reach the session or the app.
     *
     * @return array<string, string>
     */
    public static function headers(string $fileName, string $mimeType, int $size): array
    {
        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $fileName,
                self::asciiName($fileName),
            ),
            'Cache-Control' => 'private, max-age=86400',
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (! in_array($mimeType, self::PLAIN_TYPES, true)) {
            $headers['Content-Security-Policy'] = 'sandbox allow-scripts allow-popups allow-forms allow-modals';
        }

        return $headers;
    }

    /**
     * Not every app sets the language itself, so it's read from the request here.
     *
     * @param  array<string, string>  $replace
     */
    public static function message(string $key, array $replace = []): string
    {
        $language = request()->header('Accept-Language');

        return __("omni-core::attachments.{$key}", $replace, $language === 'nl' ? 'nl' : 'en');
    }

    private static function asciiName(string $name): string
    {
        return preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '_', $name) ?? 'attachment';
    }
}
