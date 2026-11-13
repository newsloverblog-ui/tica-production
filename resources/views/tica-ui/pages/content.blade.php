{{-- Blog, blog posts and pages made in the admin, in the TICA design. --}}
@php
  $tcImg = fn ($image) => $image ? asset('assets/admin/img/blogs/' . $image) : null;
  $tcExcerpt = fn ($html) => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html)))), 140);
  $tcDate = fn ($date) => \Carbon\Carbon::parse($date)->format('j M Y');
@endphp
<section class="page" data-page="content">
  @if ($content['kind'] === 'blog')
    <div class="wrap pagehead tc-head">
      <h1 class="display">{{ $content['title'] }}</h1>
      <p class="lead">{{ $content['lead'] }}</p>
      @if ($content['categories']->isNotEmpty())
        <nav class="tabs tc-cats" aria-label="Blog categories">
          <a class="tab" href="/blog" @if (!$content['category']) aria-current="page" @endif>All</a>
          @foreach ($content['categories'] as $cat)
            <a class="tab" href="/blog?category={{ urlencode($cat->slug) }}" @if ($content['category'] && $content['category']->slug === $cat->slug) aria-current="page" @endif>{{ $cat->name }}</a>
          @endforeach
        </nav>
      @endif
    </div>
    <div class="wrap tc-sec">
      @if ($content['posts']->isEmpty())
        <p class="tc-empty">No posts here yet. Check back soon.</p>
      @else
        <div class="tc-grid">
          @foreach ($content['posts'] as $i => $post)
            <a class="tc-card @if ($i === 0 && !$content['category']) lead-card @endif" href="/blog/{{ $post->slug }}">
              <span class="tc-img">@if ($tcImg($post->image))<img data-src="{{ $tcImg($post->image) }}" alt="" loading="lazy" decoding="async">@endif</span>
              <span class="tc-body">
                <span class="tc-meta">@if ($post->category)<em>{{ $post->category }}</em>@endif<span>{{ $tcDate($post->created_at) }}</span></span>
                <b>{{ $post->title }}</b>
                <span class="tc-ex">{{ $tcExcerpt($post->content) }}</span>
                <span class="tc-more">Read the story</span>
              </span>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  @elseif ($content['kind'] === 'post')
    @php($post = $content['post'])
    <article class="tc-post">
      <div class="wrap tc-narrow">
        <a class="tc-back" href="/blog">All stories</a>
        <p class="tc-meta">@if ($post->category)<a href="/blog?category={{ urlencode($post->category_slug) }}"><em>{{ $post->category }}</em></a>@endif<span>{{ $tcDate($post->created_at) }}</span>@if ($post->author)<span>By {{ $post->author }}</span>@endif</p>
        <h1 class="display">{{ $post->title }}</h1>
      </div>
      @if ($tcImg($post->image))
        <div class="wrap tc-wide"><img class="tc-hero" data-src="{{ $tcImg($post->image) }}" alt=""></div>
      @endif
      <div class="wrap tc-narrow">
        <div class="tc-prose">{!! \Mews\Purifier\Facades\Purifier::clean($post->content, 'youtube') !!}</div>
        <div class="tc-share"><span>Share this story</span><a href="https://wa.me/?text={{ urlencode($post->title . ' ' . url()->current()) }}" target="_blank" rel="noopener">WhatsApp</a><a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&amp;text={{ urlencode($post->title) }}" target="_blank" rel="noopener">X</a><a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener">Facebook</a></div>
      </div>
    </article>
    @if ($content['related']->isNotEmpty())
      <div class="band"><div class="wrap tc-sec">
        <h2 class="display tc-h2">More stories</h2>
        <div class="tc-grid three">
          @foreach ($content['related'] as $rel)
            <a class="tc-card" href="/blog/{{ $rel->slug }}">
              <span class="tc-img">@if ($tcImg($rel->image))<img data-src="{{ $tcImg($rel->image) }}" alt="" loading="lazy" decoding="async">@endif</span>
              <span class="tc-body"><span class="tc-meta"><span>{{ $tcDate($rel->created_at) }}</span></span><b>{{ $rel->title }}</b><span class="tc-ex">{{ $tcExcerpt($rel->content) }}</span></span>
            </a>
          @endforeach
        </div>
      </div></div>
    @endif
  @else
    @php($pg = $content['page'])
    <div class="wrap pagehead tc-narrow">
      <h1 class="display">{{ $pg->title }}</h1>
      <p class="tc-updated">Last updated {{ $tcDate($pg->updated_at) }}</p>
    </div>
    <div class="wrap tc-narrow tc-sec">
      <div class="tc-prose">{!! \Mews\Purifier\Facades\Purifier::clean((string) $pg->content, 'youtube') !!}</div>
    </div>
  @endif
</section>
