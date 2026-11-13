<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use App\Models\Language;
use App\Rules\ImageMimeTypeRule;
use App\Services\AuditLogger;
use App\Services\EventRegistrationService;
use App\Support\TicaCurrency;
use App\Support\TicaPlatform;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

/**
 * Organiser actions behind the TICA design: the one-page create-event form
 * saves the event, its dates, content, every ticket type and the optional
 * registration form together.
 */
class TicaOrganizerController extends Controller
{
  private const REFUND_POLICIES = [
    'Up to 7 days before' => 'Refunds are available up to 7 days before the event starts.',
    'Up to 48 hours before' => 'Refunds are available up to 48 hours before the event starts.',
  ];

  public function storeEvent(Request $request): JsonResponse
  {
    abort_if(\Illuminate\Support\Facades\Session::get('tica_team_member.role') === 'finance', 403, 'Your team role doesn\'t include creating events.');
    $organizer = Auth::guard('organizer')->user();

    return $this->createEvent($request, (int) $organizer->id, false, 'organizer');
  }

  /** Admins use the same builder and persistence path, with ownership controls. */
  public function storeAdminEvent(Request $request): JsonResponse
  {
    $admin = Auth::guard('admin')->user();
    abort_unless($admin, 401);

    $ownership = $request->validate([
      'organizer_id' => ['nullable', 'integer', Rule::exists('organizers', 'id')->where('status', 1)],
      'is_featured' => ['nullable', 'boolean'],
    ], [
      'organizer_id.exists' => 'Choose an active organizer, or leave the event under TICA.',
    ]);
    $organizerId = isset($ownership['organizer_id']) ? (int) $ownership['organizer_id'] : null;
    if ($request->input('entry') === 'corporate' && !$organizerId) {
      throw ValidationException::withMessages([
        'organizer_id' => 'Assign an organizer before using registration approvals.',
      ]);
    }

    return $this->createEvent($request, $organizerId, $request->boolean('is_featured'), 'admin');
  }

  private function createEvent(Request $request, ?int $organizerId, bool $featured, string $actor): JsonResponse
  {
    $draft = $request->boolean('draft');
    $validated = $this->validateEvent($request, $draft);

    $event = DB::transaction(function () use ($request, $validated, $organizerId, $featured, $draft, $actor) {
      $online = $validated['mode'] === 'Online';
      $multiple = $request->boolean('multiple');
      [$single, $dates] = $this->dates($validated, $multiple);

      $event = Event::create([
        'organizer_id' => $organizerId,
        'thumbnail' => $this->storeFlyer($request),
        'status' => $draft ? 0 : 1,
        'countdown_status' => $request->boolean('countdown') ? 1 : 0,
        'date_type' => $multiple ? 'multiple' : 'single',
        'start_date' => $single['start']?->toDateString(),
        'start_time' => $single['start']?->format('H:i'),
        'end_date' => $single['end']?->toDateString(),
        'end_time' => $single['end']?->format('H:i'),
        'duration' => $single['start'] && $single['end'] ? DurationCalulate($single['start'], $single['end']) : '',
        'end_date_time' => $multiple ? collect($dates)->max('end') : $single['end'],
        'is_featured' => $featured ? 'yes' : 'no',
        'event_type' => $online ? 'online' : 'venue',
        'is_hybrid' => $validated['mode'] === 'Hybrid',
        'online_join_url' => in_array($validated['mode'], ['Online', 'Hybrid'], true) ? ($validated['online_join_url'] ?? null) : null,
        'registration_mode' => $validated['entry'] === 'corporate' ? 'corporate' : 'ticketing',
        'fee_payer' => $validated['fee_payer'],
        'currency' => $validated['currency'],
        'age_restriction' => $validated['age_restriction'] ?? null,
        'visibility' => $validated['visibility'],
        'show_remaining' => $request->boolean('show_remaining'),
        'waitlist_enabled' => TicaPlatform::on('waitlist') && $request->boolean('waitlist_enabled'),
        'join_reveal_minutes' => (int) ($validated['join_reveal_minutes'] ?? 15),
        'attendee_question' => TicaPlatform::on('question') && $request->boolean('ask_question') ? ($validated['attendee_question'] ?? null) : null,
      ]);

      foreach ($dates as $date) {
        EventDates::create([
          'event_id' => $event->id,
          'start_date' => $date['start']->toDateString(),
          'start_time' => $date['start']->format('H:i'),
          'end_date' => $date['end']->toDateString(),
          'end_time' => $date['end']->format('H:i'),
          'duration' => DurationCalulate($date['start'], $date['end']),
          'start_date_time' => $date['start'],
          'end_date_time' => $date['end'],
        ]);
      }

      $this->storeContent($event, $validated, $online);
      if ($validated['entry'] !== 'corporate') {
        $this->storeTickets($event, $validated['tiers'] ?? [], $online);
      }

      AuditLogger::record('event.created_from_tica_ui', $event, [], [
        'actor' => $actor,
        'organizer_id' => $organizerId,
        'is_featured' => $event->is_featured,
        'status' => $event->status,
        'event_type' => $event->event_type,
        'registration_mode' => $event->registration_mode,
        'ticket_types' => count($validated['tiers'] ?? []),
      ]);

      return $event;
    });

    if ($validated['entry'] === 'corporate') {
      app(EventRegistrationService::class)->saveForm($event, (int) $organizerId, [
        'name' => $validated['registration_form_name'] ?: $validated['name'],
        'intro' => $validated['registration_intro'] ?? '',
        'privacy_notice' => '',
        'approval_mode' => $validated['registration_approval'] ?? 'manual',
      ]);
    }

    $slug = EventContent::query()->where('event_id', $event->id)->value('slug');

    return response()->json([
      'event_id' => $event->id,
      'name' => $validated['name'],
      'status' => $draft ? 'draft' : 'live',
      'url' => route('event.details', [$slug, $event->id]),
      'registration_url' => $actor === 'organizer' && $validated['entry'] === 'corporate' ? route('organizer.event.registrations.index', ['event_id' => $event->id]) : null,
      'redirect_url' => $actor === 'admin' ? route('admin.event_management.event', ['language' => $this->getLanguage()->code]) : null,
    ]);
  }

  private function validateEvent(Request $request, bool $draft): array
  {
    $needed = $draft ? 'nullable' : 'required';
    $mode = (string) $request->input('mode');
    $tiers = $request->input('entry') !== 'corporate';

    $validated = $request->validate([
      'name' => ['required', 'string', 'min:3', 'max:255'],
      'category_id' => [$needed, 'nullable', 'integer', Rule::exists('event_categories', 'id')->where('status', 1)],
      'age_restriction' => ['nullable', 'string', 'max:120'],
      'description' => ['nullable', 'string', 'max:20000'],
      'start_date' => [$draft || $request->boolean('multiple') ? 'nullable' : 'required', 'nullable', 'date'],
      'start_time' => [$draft || $request->boolean('multiple') ? 'nullable' : 'required', 'nullable', 'date_format:H:i'],
      'end_date' => ['nullable', 'date'],
      'end_time' => ['nullable', 'date_format:H:i'],
      'dates' => [$request->boolean('multiple') && !$draft ? 'required' : 'nullable', 'array', 'max:60'],
      'dates.*.start_date' => ['required', 'date'],
      'dates.*.start_time' => ['required', 'date_format:H:i'],
      'dates.*.end_date' => ['nullable', 'date'],
      'dates.*.end_time' => ['nullable', 'date_format:H:i'],
      'mode' => ['required', Rule::in(TicaPlatform::modes())],
      'currency' => ['required', Rule::in(TicaPlatform::currencies())],
      'address' => [$mode !== 'Online' && !$draft ? 'required' : 'nullable', 'nullable', 'string', 'max:500'],
      'city' => [$mode !== 'Online' && !$draft ? 'required' : 'nullable', 'nullable', 'string', 'max:120'],
      'state' => ['nullable', 'string', 'max:120'],
      'country' => ['nullable', 'string', 'max:120'],
      'online_join_url' => [$mode === 'Online' && !$draft ? 'required' : 'nullable', 'nullable', 'url', 'max:2000'],
      'join_reveal_minutes' => ['nullable', Rule::in(['0', '15', '30', '60', 0, 15, 30, 60])],
      'entry' => ['required', Rule::in(TicaPlatform::on('registration') ? ['ticketing', 'corporate'] : ['ticketing'])],
      'tiers' => [$tiers && !$draft ? 'required' : 'nullable', 'array', 'min:1', 'max:30'],
      'tiers.*.name' => ['required', 'string', 'max:120'],
      'tiers.*.price' => ['required', 'numeric', 'min:0', 'max:100000000'],
      'tiers.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
      'tiers.*.max_per_order' => ['nullable', 'integer', 'min:1', 'max:10000'],
      'tiers.*.description' => ['nullable', 'string', 'max:300'],
      'tiers.*.early_price' => ['nullable', 'numeric', 'min:0'],
      'tiers.*.early_ends' => ['nullable', 'date'],
      'fee_payer' => ['required', Rule::in(TicaPlatform::on('organizer_pays') ? ['buyer', 'organizer'] : ['buyer'])],
      'visibility' => ['required', Rule::in(TicaPlatform::visibilities())],
      'refund_policy' => ['nullable', 'string', 'max:60'],
      'attendee_question' => [$request->boolean('ask_question') && !$draft ? 'required' : 'nullable', 'nullable', 'string', 'max:200'],
      'meta_description' => ['nullable', 'string', 'max:300'],
      'meta_keywords' => ['nullable', 'string', 'max:300'],
      'registration_form_name' => ['nullable', 'string', 'max:150'],
      'registration_intro' => ['nullable', 'string', 'max:2000'],
      'registration_approval' => ['nullable', Rule::in(['manual', 'automatic'])],
      'flyer' => [$draft ? 'nullable' : 'required', 'nullable', 'file', 'max:5120', new ImageMimeTypeRule()],
    ], [
      'name.required' => 'Give your event a name first.',
      'flyer.required' => 'Add a flyer before you publish.',
      'start_date.required' => 'Choose the date your event starts.',
      'start_time.required' => 'Choose the time your event starts.',
      'address.required' => 'Enter the venue.',
      'city.required' => 'Enter the city.',
      'online_join_url.required' => 'Add the link people use to join.',
      'tiers.required' => 'Add at least one ticket type.',
      'dates.required' => 'Add at least one date.',
      'attendee_question.required' => 'Write the question you want to ask.',
      'mode.in' => 'This kind of event isn\'t available on TICA right now. Choose another one.',
      'currency.in' => 'TICA doesn\'t sell in this currency right now. Choose another one.',
      'entry.in' => 'Registration events aren\'t available right now. Sell or give away tickets instead.',
      'fee_payer.in' => 'Buyers pay the booking fee on TICA right now.',
      'visibility.in' => 'This visibility option isn\'t available right now. Choose another one.',
    ]);

    $paid = collect($validated['tiers'] ?? [])->contains(fn ($tier) => (float) $tier['price'] > 0);
    if ($paid && !in_array($validated['currency'], TicaCurrency::PAYSTACK, true)) {
      throw ValidationException::withMessages(['currency' => $validated['currency'] . ' can\'t take card payments yet. Make the tickets free or choose another currency.']);
    }
    if ($mode === 'Online' && count($validated['tiers'] ?? []) > 1) {
      throw ValidationException::withMessages(['tiers' => 'Online events have one ticket type. Choose "Both" for several ticket types.']);
    }
    foreach ($validated['tiers'] ?? [] as $index => $tier) {
      if (isset($tier['early_price']) && $tier['early_price'] !== null && $tier['early_price'] !== '' && (float) $tier['early_price'] >= (float) $tier['price']) {
        throw ValidationException::withMessages(["tiers.$index.early_price" => 'The early bird price must be lower than the ticket price.']);
      }
      if (!empty($tier['early_price']) && empty($tier['early_ends'])) {
        throw ValidationException::withMessages(["tiers.$index.early_ends" => 'Choose when the early bird price ends.']);
      }
    }

    return $validated;
  }

  /** @return array{0: array{start: ?Carbon, end: ?Carbon}, 1: array<int, array{start: Carbon, end: Carbon}>} */
  private function dates(array $validated, bool $multiple): array
  {
    $range = function (?string $date, ?string $time, ?string $endDate, ?string $endTime): array {
      if (!$date) {
        return ['start' => null, 'end' => null];
      }
      $start = Carbon::parse($date . ' ' . ($time ?: '00:00'));
      $end = $endDate || $endTime ? Carbon::parse(($endDate ?: $date) . ' ' . ($endTime ?: '23:59')) : $start->copy()->addHours(3);
      if ($end->lessThanOrEqualTo($start)) {
        throw ValidationException::withMessages(['end_time' => 'The event must end after it starts.']);
      }

      return ['start' => $start, 'end' => $end];
    };

    if (!$multiple) {
      $single = $range($validated['start_date'] ?? null, $validated['start_time'] ?? null, $validated['end_date'] ?? null, $validated['end_time'] ?? null);
      if ($single['start'] === null) {
        // A draft without a date still needs a closing time for listings.
        $single['end'] = now()->addYear();
      }

      return [$single, []];
    }

    $dates = array_map(fn ($date) => $range($date['start_date'], $date['start_time'], $date['end_date'] ?? null, $date['end_time'] ?? null), $validated['dates'] ?? []);
    usort($dates, fn ($a, $b) => $a['start'] <=> $b['start']);

    return [['start' => null, 'end' => null], $dates];
  }

  private function storeFlyer(Request $request): string
  {
    if (!$request->hasFile('flyer')) {
      return '';
    }
    $file = $request->file('flyer');
    $filename = (string) Str::uuid() . '.' . ImageMimeTypeRule::extension($file);
    $directory = public_path('assets/admin/img/event/thumbnail/');
    UploadFile::storeAs($directory, $file, $filename);

    return $filename;
  }

  private function storeContent(Event $event, array $validated, bool $online): void
  {
    $description = trim((string) ($validated['description'] ?? ''));
    $html = $description === '' ? '' : collect(preg_split("/\R{2,}/", $description))
      ->map(fn ($paragraph) => '<p>' . nl2br(e(trim($paragraph))) . '</p>')
      ->implode('');
    $categoryId = $validated['category_id'] ?? EventCategory::query()->where('status', 1)->orderBy('serial_number')->value('id');
    $category = EventCategory::query()->find($categoryId);

    foreach (Language::all() as $language) {
      // Each language gets the category that shares the chosen one's position.
      $languageCategory = $category
        ? (EventCategory::query()->where('language_id', $language->id)->where('serial_number', $category->serial_number)->value('id') ?? $category->id)
        : $categoryId;
      // language_id and slug are not mass assignable on EventContent.
      (new EventContent())->forceFill([
        'language_id' => $language->id,
        'event_category_id' => $languageCategory,
        'event_id' => $event->id,
        'title' => $validated['name'],
        'slug' => createSlug($validated['name']),
        'description' => $html === '' ? null : Purifier::clean($html, 'youtube'),
        'refund_policy' => self::REFUND_POLICIES[$validated['refund_policy'] ?? ''] ?? null,
        'meta_keywords' => $validated['meta_keywords'] ?? null,
        'meta_description' => $validated['meta_description'] ?? null,
        'address' => $online ? null : ($validated['address'] ?? null),
        'city' => $online ? null : ($validated['city'] ?? null),
        'state' => $online ? null : ($validated['state'] ?? null),
        'country' => $online || ($validated['country'] ?? '') === 'Other' ? null : ($validated['country'] ?? null),
      ])->save();
    }
  }

  private function storeTickets(Event $event, array $tiers, bool $online): void
  {
    $languages = Language::all();
    foreach ($tiers as $tier) {
      $price = round((float) $tier['price'], 2);
      $early = isset($tier['early_price']) && $tier['early_price'] !== '' && $tier['early_price'] !== null && $price > 0;
      $earlyEnds = $early ? Carbon::parse($tier['early_ends']) : null;
      $ticket = Ticket::create([
        'event_id' => $event->id,
        'event_type' => $online ? 'online' : 'venue',
        'title' => $tier['name'],
        'pricing_type' => $price > 0 ? 'normal' : 'free',
        'price' => $price > 0 ? $price : 0,
        'f_price' => $price > 0 ? $price : null,
        'ticket_available_type' => empty($tier['quantity']) ? 'unlimited' : 'limited',
        'ticket_available' => empty($tier['quantity']) ? null : (int) $tier['quantity'],
        'max_ticket_buy_type' => empty($tier['max_per_order']) ? 'unlimited' : 'limited',
        'max_buy_ticket' => empty($tier['max_per_order']) ? null : (int) $tier['max_per_order'],
        'early_bird_discount' => $early ? 'enable' : 'disable',
        'early_bird_discount_type' => $early ? 'fixed' : null,
        'early_bird_discount_amount' => $early ? round($price - (float) $tier['early_price'], 2) : null,
        'early_bird_discount_date' => $earlyEnds?->toDateString(),
        'early_bird_discount_time' => $earlyEnds?->format('H:i'),
      ]);
      foreach ($languages as $language) {
        TicketContent::create([
          'language_id' => $language->id,
          'ticket_id' => $ticket->id,
          'title' => $tier['name'],
          'description' => $tier['description'] ?? null,
        ]);
      }
    }
  }
}
