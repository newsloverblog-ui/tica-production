<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use Illuminate\Http\Request;

class SummernoteController extends Controller
{
  public function upload(Request $request)
  {
    $validated = $request->validate([
      'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
    ]);
    $imageName = UploadFile::store(public_path('assets/admin/img/summernotes/'), $validated['image']);

    return url('/') . '/assets/admin/img/summernotes/' . $imageName;
  }

  public function remove(Request $request)
  {
    $validated = $request->validate([
      'image' => ['required', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/'],
    ]);
    $image = $validated['image'];
    $extension = strtolower((string) pathinfo($image, PATHINFO_EXTENSION));
    abort_unless(basename($image) === $image && in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true), 422);

    $path = public_path('assets/admin/img/summernotes/' . $image);
    if (!is_file($path)) {
      return response()->json(['message' => 'Image not found.'], 404);
    }
    unlink($path);

    return response()->json(['data' => 'Image removed successfully!'], 200);
  }
}
