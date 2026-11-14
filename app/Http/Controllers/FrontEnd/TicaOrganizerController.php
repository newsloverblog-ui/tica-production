<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Event;
use App\Models\Event\BookingTicket;
use App\Services\TicaUi\EventBuilderPrefill;
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
    abort_unless(\App\Support\TeamPermissions::can('events.manage'), 403, 'Your team role doesn\'t include creating events.');
    $organizer = Auth::guard('organizer')->user();

    return $this->createEvent($request, (int) $organizer->id);
  }

  /**
   * A new event starts from the organiser's saved defaults (Settings > Event
   * defaults) for anything the request leaves out, so the defaults apply to
   * every way an event is created, not only the prefilled form.
   */
  private function applyOrganizerDefaults(Request $request, int $organizerId): void
  {
    $organizer = \App\Models\Organizer::find($organizerId);
    if (!$organizer) {
      return;
    }
    $platformBuyer = TicaPlatform::all()['default_fee_payer'] === 'buyer';
    $defaults = [
      'fee_payer' => $organizer->preferenceEnabled('buyers_pay_fee', $platformBuyer) || !TicaPlatform::on('organizer_pays') ? 'buyer' : 'organizer',
      'show_remaining' => $organizer->preferenceEnabled('show_remaining', true),
      'waitlist_enabled' => $organizer->preferenceEnabled('waitlist', true),
      'allow_transfers' => $organizer->preferenceEnabled('transfers', true),
    ];
    $request->merge(array_diff_key($defaults, array_filter($request->all(), fn ($value) => $value !== null)));
  }

  private function createEvent(Request $request, int $organizerId): JsonResponse
  {
    $this->applyOrganizerDefaults($request, $organizerId);
    $draft = $request->boolean('draft');
    $validated = $this->validateEvent($request, $draft);

    $event = DB::transaction(function () use ($request, $validated, $organizerId, $draft) {
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
        'is_featured' => 'no',
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
        'allow_transfers' => TicaPlatform::on('transfers') && $request->boolean('allow_transfers'),
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
        'actor' => 'organizer',
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
      'registration_url' => $validated['entry'] === 'corporate' ? route('organizer.event.registrations.index', ['event_id' => $event->id]) : null,
    ]);
  }

  /** Organisers edit their own event in the same builder they created it with. */
  public function updateOrganizerEvent(Request $request, int $id): JsonResponse
  {
    abort_unless(\App\Support\TeamPermissions::can('events.manage'), 403, 'Your team role doesn\'t include editing events.');
    $organizer = Auth::guard('organizer')->user();
    $event = Event::query()->whereKey($id)->where('organizer_id', $organizer->id)->firstOrFail();

    return $this->updateEvent($request, $event, 'organizer');
  }

  /** Admins edit any event in the builder, plus ownership, status and featured. */
  public function updateAdminEvent(Request $request, int $id): JsonResponse
  {
    abort_unless(Auth::guard('admin')->user(), 401);
    $event = Event::query()->findOrFail($id);
    $admin = $request->validate([
      'organizer_id' => ['nullable', 'integer', Rule::exists('organizers', 'id')->where('status', 1)],
      'is_featured' => ['nullable', 'boolean'],
      'status' => ['required', Rule::in(['live', 'draft'])],
    ], [
      'organizer_id.exists' => 'Choose an active organizer, or leave the event under TICA.',
    ]);
    if ($event->registration_mode === 'corporate' && empty($admin['organizer_id'])) {
      throw ValidationException::withMessages(['organizer_id' => 'Registration events need an organizer. Assign one first.']);
    }
    $request->merge(['draft' => $admin['status'] === 'draft' ? '1' : '0']);

    return $this->updateEvent($request, $event, 'admin', [
      'organizer_id' => isset($admin['organizer_id']) ? (int) $admin['organizer_id'] : null,
      'is_featured' => $request->boolean('is_featured') ? 'yes' : 'no',
    ]);
  }

  private function updateEvent(Request $request, Event $event, string $actor, array $ownership = []): JsonResponse
  {
    $draft = $request->boolean('draft');
    $sales = EventBuilderPrefill::hasSales($event);
    // The kind of entry can't change after creation; keep what the event already uses.
    $request->merge(['entry' => $event->registration_mode === 'corporate' ? 'corporate' : 'ticketing']);
    if ($sales) {
      // With tickets sold, the currency and dates stay put (dates move through "Change date", which tells attendees).
      $request->merge(['currency' => $event->currency ?: $request->input('currency')]);
    }
    $validated = $this->validateEvent($request, $draft, (bool) $event->thumbnail);

    DB::transaction(function () use ($request, $validated, $event, $draft, $actor, $ownership, $sales) {
      $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
      $before = $event->only(['organizer_id', 'status', 'is_featured', 'event_type', 'fee_payer', 'visibility', 'start_date', 'start_time']);
      $online = $validated['mode'] === 'Online';
      $multiple = $request->boolean('multiple');

      $fields = [
        'status' => $draft ? 0 : 1,
        'countdown_status' => $request->boolean('countdown') ? 1 : 0,
        'event_type' => $online ? 'online' : 'venue',
        'is_hybrid' => $validated['mode'] === 'Hybrid',
        'online_join_url' => in_array($validated['mode'], ['Online', 'Hybrid'], true) ? ($validated['online_join_url'] ?? null) : null,
        'fee_payer' => $validated['fee_payer'],
        'currency' => $validated['currency'],
        'age_restriction' => $validated['age_restriction'] ?? null,
        'visibility' => $validated['visibility'],
        'show_remaining' => $request->boolean('show_remaining'),
        'waitlist_enabled' => (TicaPlatform::on('waitlist') || $event->waitlist_enabled) && $request->boolean('waitlist_enabled'),
        'allow_transfers' => (TicaPlatform::on('transfers') || $event->allow_transfers) && $request->boolean('allow_transfers'),
        'join_reveal_minutes' => (int) ($validated['join_reveal_minutes'] ?? 15),
        'attendee_question' => TicaPlatform::on('question') && $request->boolean('ask_question') ? ($validated['attendee_question'] ?? null) : null,
      ];
      if ($request->hasFile('flyer')) {
        $fields['thumbnail'] = $this->storeFlyer($request);
      }
      if (!$sales) {
        [$single, $dates] = $this->dates($validated, $multiple);
        $fields += [
          'date_type' => $multiple ? 'multiple' : 'single',
          'start_date' => $single['start']?->toDateString(),
          'start_time' => $single['start']?->format('H:i'),
          'end_date' => $single['end']?->toDateString(),
          'end_time' => $single['end']?->format('H:i'),
          'duration' => $single['start'] && $single['end'] ? DurationCalulate($single['start'], $single['end']) : '',
          'end_date_time' => $multiple ? collect($dates)->max('end') : $single['end'],
        ];
        EventDates::query()->where('event_id', $event->id)->delete();
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
      }
      if ($actor === 'admin') {
        $fields['organizer_id'] = $ownership['organizer_id'];
        $fields['is_featured'] = $ownership['is_featured'];
      }
      $event->forceFill($fields)->save();

      $this->storeContent($event, $validated, $online);
      if ($validated['entry'] !== 'corporate') {
        $this->syncTickets($event, $request->input('tiers', []), $validated['tiers'] ?? [], $online);
      }

      AuditLogger::record('event.edited_from_tica_ui', $event, $before, array_merge(
        $event->only(array_keys($before)),
        ['actor' => $actor, 'ticket_types' => count($validated['tiers'] ?? [])]
      ));
    });

    $slug = EventContent::query()->where('event_id', $event->id)->value('slug');

    return response()->json([
      'event_id' => $event->id,
      'name' => $validated['name'],
      'status' => $draft ? 'draft' : 'live',
      'url' => route('event.details', [$slug, $event->id]),
      'redirect_url' => $actor === 'admin'
        ? route('admin.event_management.event', ['language' => $this->getLanguage()->code])
        : url('/dashboard/events'),
    ]);
  }

  /**
   * Updates ticket types by id: sold tickets keep their price and can't drop
   * below what has sold or be removed; new rows become new ticket types.
   */
  private function syncTickets(Event $event, array $raw, array $tiers, bool $online): void
  {
    $existing = Ticket::query()->where('event_id', $event->id)->lockForUpdate()->get()->keyBy('id');
    $kept = [];
    $new = [];
    foreach ($tiers as $index => $tier) {
      $id = (int) ($raw[$index]['id'] ?? 0);
      if (!$id) {
        $new[] = $tier;
        continue;
      }
      $ticket = $existing->get($id);
      if (!$ticket) {
        throw ValidationException::withMessages(["tiers.$index.name" => 'This ticket type no longer exists. Reload the page and try again.']);
      }
      $kept[] = $id;
      if ($ticket->pricing_type === 'variation') {
        continue;
      }
      $sold = EventBuilderPrefill::soldFor($ticket);
      $price = round((float) $tier['price'], 2);
      if ($sold > 0 && abs($price - (float) $ticket->price) > 0.001) {
        throw ValidationException::withMessages(["tiers.$index.price" => "You've sold {$sold} {$ticket->title} tickets, so its price can't change. Add a new ticket type for a new price."]);
      }
      if (!empty($tier['quantity']) && (int) $tier['quantity'] < $sold) {
        throw ValidationException::withMessages(["tiers.$index.quantity" => "You've sold {$sold} {$ticket->title} tickets. Set the quantity to at least {$sold}."]);
      }
      $early = isset($tier['early_price']) && $tier['early_price'] !== '' && $tier['early_price'] !== null && $price > 0;
      $earlyEnds = $early ? Carbon::parse($tier['early_ends']) : null;
      $ticket->forceFill([
        'event_type' => $online ? 'online' : 'venue',
        'title' => $tier['name'],
        'pricing_type' => $price > 0 ? 'normal' : 'free',
        'price' => $price > 0 ? $price : 0,
        'f_price' => $price > 0 ? $price : null,
        'ticket_available_type' => empty($tier['quantity']) ? 'unlimited' : 'limited',
        'ticket_available' => empty($tier['quantity']) ? null : (int) $tier['quantity'] - $sold,
        'max_ticket_buy_type' => empty($tier['max_per_order']) ? 'unlimited' : 'limited',
        'max_buy_ticket' => empty($tier['max_per_order']) ? null : (int) $tier['max_per_order'],
        'early_bird_discount' => $early ? 'enable' : 'disable',
        'early_bird_discount_type' => $early ? 'fixed' : null,
        'early_bird_discount_amount' => $early ? round($price - (float) $tier['early_price'], 2) : null,
        'early_bird_discount_date' => $earlyEnds?->toDateString(),
        'early_bird_discount_time' => $earlyEnds?->format('H:i'),
      ])->save();
      TicketContent::query()->where('ticket_id', $ticket->id)->update([
        'title' => $tier['name'],
        'description' => $tier['description'] ?? null,
      ]);
    }

    foreach ($existing as $id => $ticket) {
      if (in_array($id, $kept, true) || $ticket->pricing_type === 'variation') {
        continue;
      }
      $sold = EventBuilderPrefill::soldFor($ticket);
      if ($sold > 0) {
        throw ValidationException::withMessages(['tiers' => "You've sold {$sold} {$ticket->title} tickets, so that ticket type can't be removed."]);
      }
      $ticket->delete();
    }

    if ($new) {
      $this->storeTickets($event, $new, $online);
    }
  }

  private function validateEvent(Request $request, bool $draft, bool $hasFlyer = false): array
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
      'allow_transfers' => ['required', 'boolean'],
      'refund_policy' => ['nullable', 'string', 'max:60'],
      'attendee_question' => [$request->boolean('ask_question') && !$draft ? 'required' : 'nullable', 'nullable', 'string', 'max:200'],
      'meta_description' => ['nullable', 'string', 'max:300'],
      'meta_keywords' => ['nullable', 'string', 'max:300'],
      'registration_form_name' => ['nullable', 'string', 'max:150'],
      'registration_intro' => ['nullable', 'string', 'max:2000'],
      'registration_approval' => ['nullable', Rule::in(['manual', 'automatic'])],
      'flyer' => [$draft || $hasFlyer ? 'nullable' : 'required', 'nullable', 'file', 'max:5120', new ImageMimeTypeRule()],
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
    // Saved as a WebP no larger than 1600px so pages load fast on mobile data.
    return \App\Support\PublicImage::store($request->file('flyer'), 'assets/admin/img/event/thumbnail', 1600);
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
      EventContent::query()->firstOrNew(['event_id' => $event->id, 'language_id' => $language->id])->forceFill([
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
