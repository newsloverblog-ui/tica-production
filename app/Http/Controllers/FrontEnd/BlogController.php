<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\Journal\Blog;
use App\Models\Journal\BlogCategory;
use App\Models\Journal\BlogInformation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BlogController extends Controller
{
  public function blogs(Request $request)
  {
    $language = $this->getLanguage();

    $queryResult['seoInfo'] = $language->seoInfo()->select('meta_keyword_blog', 'meta_description_blog')->first();

    $queryResult['pageHeading'] = $this->getPageHeading($language);

    $queryResult['bgImg'] = $this->getBreadcrumb();

    $blogTitle = $blogCategory = null;

    if ($request->filled('title')) {
      $blogTitle = $request['title'];
    }
    if ($request->filled('category')) {
      $blogCategory = $this->resolveCategoryId(
        (int) $language->id,
        $request->string('category')->trim()->toString()
      );
    }

    $queryResult['blogs'] = Blog::join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
      ->join('blog_categories', 'blog_categories.id', '=', 'blog_informations.blog_category_id')
      ->where('blog_informations.language_id', '=', $language->id)
      ->when($blogTitle, function ($query, $blogTitle) {
        return $query->where('blog_informations.title', 'like', '%' . $blogTitle . '%');
      })
      ->when($blogCategory, function ($query, $blogCategory) {
        return $query->where('blog_informations.blog_category_id', '=', $blogCategory);
      })
      ->select('blogs.image', 'blogs.created_at', 'blog_informations.title', 'blog_informations.slug', 'blog_informations.content', 'blog_categories.name as categoryName', 'blog_categories.slug as categorySlug')
      ->orderBy('blogs.serial_number', 'asc')
      ->paginate(6);

    $queryResult['categories'] = $this->getCategories($language);

    $queryResult['allBlogs'] = $language->blogInformation()->count();

    return view('frontend.journal.blogs', $queryResult);
  }

  public function details($slug)
  {
    try {
      $language = $this->getLanguage();
      $queryResult['pageHeading'] = $this->getPageHeading($language);

      $queryResult['bgImg'] = $this->getBreadcrumb();

      $blogInformation = BlogInformation::query()
        ->where('language_id', $language->id)
        ->where('slug', $slug)
        ->firstOrFail();
      $blogId = $blogInformation->blog_id;

      $queryResult['details'] = Blog::join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
        ->join('blog_categories', 'blog_categories.id', '=', 'blog_informations.blog_category_id')
        ->where('blog_informations.language_id', '=', $language->id)
        ->where('blog_informations.blog_id', '=', $blogId)
        ->select('blogs.image', 'blogs.created_at', 'blog_informations.title', 'blog_informations.author', 'blog_informations.slug', 'blog_informations.content', 'blog_informations.meta_keywords', 'blog_informations.meta_description', 'blog_categories.name as categoryName', 'blog_categories.slug as blogSlug')
        ->firstOrFail();

      $categoryId = $blogInformation->blog_category_id;

      $queryResult['relatedBlogs'] = Blog::join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
        ->where('blog_informations.language_id', '=', $language->id)
        ->where('blog_informations.blog_category_id', '=', $categoryId)
        ->where('blog_informations.slug', '<>', $slug)
        ->select('blogs.image', 'blogs.created_at', 'blog_informations.title', 'blog_informations.slug', 'blog_informations.content')
        ->orderBy('blogs.serial_number', 'asc')
        ->limit(4)
        ->get();

      $queryResult['disqusInfo'] = Basic::select('disqus_status', 'disqus_short_name')->first();

      $queryResult['categories'] = $this->getCategories($language);

      $queryResult['allBlogs'] = $language->blogInformation()->count();

      return view('frontend.journal.blog-details', $queryResult);
    } catch (ModelNotFoundException $th) {
      return response()->view('errors.404', [], 404);
    }
  }

  public function getCategories($language)
  {
    $categories = $language->blogCategory()->where('status', 1)->orderBy('serial_number', 'asc')->get();

    $categories->map(function ($category) {
      $category['blogCount'] = BlogInformation::query()->where('blog_category_id', '=', $category->id)->count();
    });

    return $categories;
  }

  protected function resolveCategoryId(int $languageId, string $slug): int
  {
    return (int) BlogCategory::query()
      ->where('language_id', $languageId)
      ->where('slug', $slug)
      ->firstOrFail()
      ->id;
  }
}
