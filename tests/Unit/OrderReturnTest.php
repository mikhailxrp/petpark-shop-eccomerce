<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderReturnTest extends TestCase
{
    /** @var list<string> */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_dir($path)) {
                array_map('unlink', glob($path . '/*') ?: []);
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempPaths = [];
    }

    public static function allowedTransitions(): array
    {
        return [
            ['submitted', 'in_review'],
            ['in_review', 'approved'],
            ['in_review', 'rejected'],
            ['approved', 'completed'],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function testAllowedTransition(string $from, string $to): void
    {
        $this->assertTrue(returnCanTransition($from, $to));
    }

    public static function forbiddenTransitions(): array
    {
        return [
            'пропуск рассмотрения'   => ['submitted', 'approved'],
            'отказ без рассмотрения' => ['submitted', 'rejected'],
            'завершить без решения'  => ['in_review', 'completed'],
            'одобренный → отказ'     => ['approved', 'rejected'],
            'назад'                  => ['in_review', 'submitted'],
            'отказ конечный'         => ['rejected', 'in_review'],
            'завершён конечный'      => ['completed', 'approved'],
            'тот же статус'          => ['submitted', 'submitted'],
            'неизвестный статус'     => ['nope', 'in_review'],
        ];
    }

    #[DataProvider('forbiddenTransitions')]
    public function testForbiddenTransition(string $from, string $to): void
    {
        $this->assertFalse(returnCanTransition($from, $to));
    }

    public function testEveryStatusHasTransitionsEntryAndSubject(): void
    {
        foreach (array_keys(RETURN_STATUS_TRANSITIONS) as $status) {
            $this->assertArrayHasKey($status, RETURN_STATUS_SUBJECTS);
        }
    }

    public function testOnlyDecisionsRequireComment(): void
    {
        $this->assertTrue(returnTransitionRequiresComment('approved'));
        $this->assertTrue(returnTransitionRequiresComment('rejected'));
        $this->assertFalse(returnTransitionRequiresComment('in_review'));
        $this->assertFalse(returnTransitionRequiresComment('completed'));
    }

    public function testReceivedOrderWithoutReturnCanBeReturned(): void
    {
        $this->assertTrue(returnOrderCanBeReturned('delivered', false));
        $this->assertTrue(returnOrderCanBeReturned('picked_up', false));
    }

    public function testOrderWithExistingReturnCannotBeReturnedAgain(): void
    {
        $this->assertFalse(returnOrderCanBeReturned('delivered', true));
    }

    public function testNotReceivedOrderCannotBeReturned(): void
    {
        foreach (['new', 'confirmed', 'assembled', 'shipped', 'ready_for_pickup', 'cancelled'] as $status) {
            $this->assertFalse(returnOrderCanBeReturned($status, false), $status);
        }
    }

    // ─── fileUploadSaveImages() ─────────────────────────────────────────

    public function testNormalizeMultiFileInputSkipsEmptySlots(): void
    {
        $files = fileUploadNormalize([
            'tmp_name' => ['/tmp/a', ''],
            'size'     => [10, 0],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
        ]);

        $this->assertSame([['tmp_name' => '/tmp/a', 'size' => 10, 'error' => UPLOAD_ERR_OK]], $files);
    }

    public function testSavesValidImagesWithRandomNames(): void
    {
        $dir = $this->makeTempDir();
        $files = [$this->makeFile($this->png()), $this->makeFile($this->png())];

        $result = fileUploadSaveImages($files, $dir, 'uploads/returns', mover: $this->mover());

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['paths']);
        $this->assertNotSame($result['paths'][0], $result['paths'][1]);
        foreach ($result['paths'] as $path) {
            $this->assertMatchesRegularExpression('#^uploads/returns/[0-9a-f]{32}\.png$#', $path);
            $this->assertFileExists($dir . '/' . basename($path));
        }
    }

    public function testRejectsPhpFileDisguisedAsJpg(): void
    {
        $dir = $this->makeTempDir();
        $files = [$this->makeFile("<?php echo 'x';", 'shell.jpg')];

        $result = fileUploadSaveImages($files, $dir, 'uploads/returns', mover: $this->mover());

        $this->assertFalse($result['ok']);
        $this->assertSame([], glob($dir . '/*'));
    }

    public function testRejectsFileOverSizeLimit(): void
    {
        $dir = $this->makeTempDir();
        $file = $this->makeFile($this->png());
        $file['size'] = RETURN_PHOTO_MAX_BYTES + 1;

        $result = fileUploadSaveImages([$file], $dir, 'uploads/returns', mover: $this->mover());

        $this->assertFalse($result['ok']);
    }

    public function testRejectsSixthFile(): void
    {
        $dir = $this->makeTempDir();
        $files = [];
        for ($i = 0; $i < RETURN_PHOTOS_MAX + 1; $i++) {
            $files[] = $this->makeFile($this->png());
        }

        $result = fileUploadSaveImages($files, $dir, 'uploads/returns', mover: $this->mover());

        $this->assertFalse($result['ok']);
        $this->assertSame([], glob($dir . '/*'));
    }

    public function testRejectsEmptySelection(): void
    {
        $result = fileUploadSaveImages([], $this->makeTempDir(), 'uploads/returns', mover: $this->mover());

        $this->assertFalse($result['ok']);
    }

    public function testBadFileAmongGoodSavesNothing(): void
    {
        $dir = $this->makeTempDir();
        $files = [$this->makeFile($this->png()), $this->makeFile('not an image')];

        $result = fileUploadSaveImages($files, $dir, 'uploads/returns', mover: $this->mover());

        $this->assertFalse($result['ok']);
        $this->assertSame([], glob($dir . '/*'));
    }

    public function testUploadErrorIsRejected(): void
    {
        $file = $this->makeFile($this->png());
        $file['error'] = UPLOAD_ERR_INI_SIZE;

        $result = fileUploadSaveImages([$file], $this->makeTempDir(), 'uploads/returns', mover: $this->mover());

        $this->assertFalse($result['ok']);
    }

    public function testDeleteRemovesOnlyFilesInsideTargetDir(): void
    {
        $dir = $this->makeTempDir();
        file_put_contents($dir . '/a.png', 'x');

        fileUploadDelete(['uploads/returns/a.png', 'other/b.png'], $dir, 'uploads/returns');

        $this->assertFileDoesNotExist($dir . '/a.png');
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/petpark-upload-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->tempPaths[] = $dir;

        return $dir;
    }

    /**
     * @return array{tmp_name: string, size: int, error: int}
     */
    private function makeFile(string $content, string $name = 'upload.tmp'): array
    {
        $path = sys_get_temp_dir() . '/' . bin2hex(random_bytes(6)) . '-' . $name;
        file_put_contents($path, $content);
        $this->tempPaths[] = $path;

        return ['tmp_name' => $path, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
    }

    private function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true
        );
    }

    /** Подмена move_uploaded_file(): в CLI настоящей HTTP-загрузки нет. */
    private function mover(): callable
    {
        return static fn (string $from, string $to): bool => copy($from, $to);
    }
}
