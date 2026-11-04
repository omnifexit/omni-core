<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Omnifexit\OmniCore\Uploads\Attachments;

function attachmentErrors(UploadedFile $file): array
{
    return Validator::make(['files' => [$file]], Attachments::rules())->errors()->all();
}

it('allows the usual files and html', function (string $name, string $content) {
    expect(attachmentErrors(UploadedFile::fake()->createWithContent($name, $content)))->toBe([]);
})->with([
    ['deck.html', '<!doctype html><html><body>Slides</body></html>'],
    ['notes.txt', 'Hello'],
]);

it('names the file that is not allowed', function () {
    $errors = attachmentErrors(UploadedFile::fake()->createWithContent('setup.exe', 'MZ'));

    expect($errors)->toBe(['File not allowed: setup.exe']);
});

it('answers in the language of the request', function () {
    request()->headers->set('Accept-Language', 'nl');

    $errors = attachmentErrors(UploadedFile::fake()->createWithContent('setup.exe', 'MZ'));

    expect($errors)->toBe(['Bestand niet toegestaan: setup.exe']);
});

it('stops a program renamed to an allowed type', function () {
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, "\x7fELF\x02\x01\x01\x00".str_repeat("\x00", 64));

    $errors = attachmentErrors(new UploadedFile($path, 'photo.png', null, null, true));

    expect($errors)->not->toBe([]);
});

it('stops files over the limit', function () {
    $file = UploadedFile::fake()->create('big.pdf', Attachments::MAX_KILOBYTES + 1, 'application/pdf');

    expect(attachmentErrors($file))->toBe(['File too large: big.pdf']);
});

it('sandboxes html but not plain files', function () {
    $html = Attachments::headers('deck.html', 'text/html', 10);
    $pdf = Attachments::headers('a.pdf', 'application/pdf', 10);

    expect($html['Content-Security-Policy'])->toContain('sandbox allow-scripts')
        ->and($html['Content-Security-Policy'])->not->toContain('allow-same-origin')
        ->and($html['X-Content-Type-Options'])->toBe('nosniff')
        ->and($pdf)->not->toHaveKey('Content-Security-Policy');
});

it('keeps a strange file name out of the other headers', function () {
    $headers = Attachments::headers("a\"b\r\nX-Evil: 1.pdf", 'application/pdf', 10);

    expect($headers['Content-Disposition'])->not->toContain("\r\n");
});
