<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\SummernoteController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SummernoteControllerSecurityTest extends TestCase
{
    public function test_remove_rejects_path_traversal_and_non_image_extensions(): void
    {
        foreach (['../.env', '..\\.env', 'document.pdf', '/etc/passwd'] as $candidate) {
            try {
                (new SummernoteController())->remove(Request::create('/admin/summernote/remove-image', 'POST', [
                    'image' => $candidate,
                ]));
                $this->fail("Unsafe Summernote filename was accepted: {$candidate}");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('image', $exception->errors());
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }
    }
}
