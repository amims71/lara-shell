<?php

use Amims71\LaraShell\Jobs\LogTail;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/lara-shell-tail-'.bin2hex(random_bytes(4)).'.log';
    file_put_contents($this->path, '');
});

afterEach(function () {
    if (is_file($this->path)) {
        unlink($this->path);
    }
});

it('reads new bytes from a given offset and reports the new offset', function () {
    file_put_contents($this->path, "line one\n");

    $tail = new LogTail($this->path);

    [$bytes, $offset] = $tail->read(0);

    expect($bytes)->toBe("line one\n")
        ->and($offset)->toBe(9);

    file_put_contents($this->path, "line two\n", FILE_APPEND);

    [$bytes, $offset] = $tail->read($offset);

    expect($bytes)->toBe("line two\n")
        ->and($offset)->toBe(18);
});

it('returns empty bytes and the same offset when nothing new was appended', function () {
    file_put_contents($this->path, "hello\n");

    $tail = new LogTail($this->path);
    [, $offset] = $tail->read(0);

    [$bytes, $newOffset] = $tail->read($offset);

    expect($bytes)->toBe('')
        ->and($newOffset)->toBe($offset);
});

it('resets the offset to zero when the file is truncated below the offset', function () {
    file_put_contents($this->path, 'aaaaaaaaaa'); // 10 bytes

    $tail = new LogTail($this->path);
    [, $offset] = $tail->read(0);

    expect($offset)->toBe(10);

    // Truncate: rewrite with fewer bytes than the previous offset.
    file_put_contents($this->path, 'bb'); // 2 bytes, size (2) < offset (10)

    [$bytes, $newOffset] = $tail->read($offset);

    expect($bytes)->toBe('bb')
        ->and($newOffset)->toBe(2);
});

it('is binary-safe (preserves null bytes and non-utf8 sequences)', function () {
    $binary = "start\x00\xff\xfemiddle\x00end";
    file_put_contents($this->path, $binary);

    $tail = new LogTail($this->path);
    [$bytes, $offset] = $tail->read(0);

    expect($bytes)->toBe($binary)
        ->and($offset)->toBe(strlen($binary));
});

it('returns empty bytes and zero offset when the file does not exist', function () {
    unlink($this->path);

    $tail = new LogTail($this->path);
    [$bytes, $offset] = $tail->read(5);

    expect($bytes)->toBe('')
        ->and($offset)->toBe(0);
});
