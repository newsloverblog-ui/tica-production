@extends('organizer.layout')

@section('content')
    <div class="tica-workspace-hero">
        <div>
            <p class="tica-eyebrow">{{ __('Event workspace') }}</p>
            <h1>{{ __('Your events') }}</h1>
            <p class="tica-lede">{{ __('Create, publish and manage every attendee experience from one place.') }}</p>
        </div>
        <div class="tica-quick-actions">
            <a class="btn btn-primary" href="{{ route('organizer.add.event.event', ['type' => 'venue']) }}">
                <i class="fas fa-plus mr-1"></i>{{ __('Create an event') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card-title d-inline-block">
                                {{ __('Events') . ' (' . $language->name . ' ' . __('Language') . ')' }}
                            </div>
                        </div>

                        <div class="col-lg-3">
                            @if (!empty($langs))
                                <select name="language" class="form-control"
                                    onchange="window.location='{{ url()->current() . '?language=' }}' + this.value+'&event_type='+'{{ request()->input('event_type') }}'">
                                    <option selected disabled>{{ __('Select a Language') }}</option>
                                    @foreach ($langs as $lang)
                                        <option value="{{ $lang->code }}"
                                            {{ $lang->code == request()->input('language') ? 'selected' : '' }}>
                                            {{ $lang->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="col-lg-4 offset-lg-1 mt-2 mt-lg-0">

                            <div class="dropdown">
                                <button class="btn btn-primary dropdown-toggle btn-sm float-right" type="button"
                                    id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    {{ __('Add Event') }}
                                </button>

                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                    <a href="{{ route('organizer.add.event.event', ['type' => 'online']) }}"
                                        class="dropdown-item">
                                        {{ __('Online Event') }}
                                    </a>

                                    <a href="{{ route('organizer.add.event.event', ['type' => 'venue']) }}"
                                        class="dropdown-item">
                                        {{ __('Venue Event') }}
                                    </a>
                                </div>
                            </div>

                            <button class="btn btn-warning btn-sm float-right mr-2 d-none bulk-delete"
                                data-action="archive"
                                data-href="{{ route('organizer.event_management.bulk_delete_event') }}">
                                <i class="fas fa-archive"></i> {{ __('Archive') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-12">

                            <div class="float-right">
                                <div class="form-group">
                                    <form action="" method="get">
                                        <input type="hidden" name="language" value="{{ request()->input('language') }}"
                                            class="hidden">
                                        <input type="text" name="title" value="{{ request()->input('title') }}"
                                            name="name" placeholder="Enter Event Name" class="form-control">
                                    </form>
                                </div>
                            </div>

                            @if (count($events) == 0)
                                <h3 class="text-center mt-2">
                                    {{ __('NO EVENT CONTENT FOUND FOR ') . $language->name . '!' }}</h3>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped mt-3" id="">
                                        <thead>
                                            <tr>
                                                <th scope="col">
                                                    <input type="checkbox" class="bulk-check" data-val="all">
                                                </th>
                                                <th scope="col" width="30%">{{ __('Title') }}</th>
                                                <th scope="col">{{ __('Type') }}</th>
                                                <th scope="col">{{ __('Category') }}</th>
                                                <th scope="col">{{ __('Ticket') }}</th>
                                                <th scope="col">{{ __('Status') }}</th>
                                                <th scope="col">{{ __('Lifecycle') }}</th>
                                                <th scope="col">{{ __('Featured') }}</th>
                                                <th scope="col">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($events as $event)
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="bulk-check"
                                                            data-val="{{ $event->id }}">
                                                    </td>
                                                    <td width="20%">
                                                        <a target="_blank"
                                                            href="{{ route('organizer.event.preview', ['slug' => $event->slug, 'id' => $event->id]) }}">{{ strlen($event->title) > 30 ? mb_substr($event->title, 0, 30, 'UTF-8') . '....' : $event->title }}</a>
                                                    </td>
                                                    <td>
                                                        {{ ucfirst($event->event_type) }}
                                                    </td>
                                                    <td>
                                                        {{ $event->category }}
                                                    </td>
                                                    <td>
                                                        @if ($event->event_type == 'venue')
                                                            <a href="{{ route('organizer.event.ticket', ['language' => request()->input('language'), 'event_id' => $event->id, 'event_type' => $event->event_type]) }}"
                                                                class="btn btn-success btn-sm">{{ __('Manage') }}</a>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <form id="statusForm-{{ $event->id }}" class="d-inline-block"
                                                            action="{{ route('organizer.event_management.event.event_status', ['id' => $event->id, 'language' => request()->input('language')]) }}"
                                                            method="post">

                                                            @csrf
                                                            <select
                                                                class="form-control form-control-sm tica-state-select {{ $event->status == 0 ? 'is-warning' : 'is-positive' }}"
                                                                name="status"
                                                                onchange="document.getElementById('statusForm-{{ $event->id }}').submit()">
                                                                <option value="1"
                                                                    {{ $event->status == 1 ? 'selected' : '' }}>
                                                                    {{ __('Active') }}
                                                                </option>
                                                                <option value="0"
                                                                    {{ $event->status == 0 ? 'selected' : '' }}>
                                                                    {{ __('Deactive') }}
                                                                </option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        @php
                                                            $lifecycle = $event->lifecycle_status ?: 'scheduled';
                                                            $lifecycleBadge = match ($lifecycle) {
                                                                'cancelled' => 'danger',
                                                                'cancellation_pending' => 'warning',
                                                                default => 'success',
                                                            };
                                                        @endphp
                                                        <span class="badge badge-{{ $lifecycleBadge }}">{{ str_replace('_', ' ', ucfirst($lifecycle)) }}</span>
                                                    </td>
                                                    <td>

                                                        <form id="featuredForm-{{ $event->id }}"
                                                            class="d-inline-block"
                                                            action="{{ route('organizer.event_management.event.update_featured', ['id' => $event->id]) }}"
                                                            method="post">

                                                            @csrf
                                                            <select
                                                                class="form-control form-control-sm tica-state-select {{ $event->is_featured == 'yes' ? 'is-positive' : 'is-negative' }}"
                                                                name="is_featured"
                                                                onchange="document.getElementById('featuredForm-{{ $event->id }}').submit()">
                                                                <option value="yes"
                                                                    {{ $event->is_featured == 'yes' ? 'selected' : '' }}>
                                                                    {{ __('Yes') }}
                                                                </option>
                                                                <option value="no"
                                                                    {{ $event->is_featured == 'no' ? 'selected' : '' }}>
                                                                    {{ __('No') }}
                                                                </option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary dropdown-toggle btn-sm"
                                                                type="button" id="dropdownMenuButton"
                                                                data-toggle="dropdown" aria-haspopup="true"
                                                                aria-expanded="false">
                                                                {{ __('Select') }}
                                                            </button>

                                                            <div class="dropdown-menu"
                                                                aria-labelledby="dropdownMenuButton">
                                                                <a href="{{ route('organizer.event_management.edit_event', ['id' => $event->id]) }}"
                                                                    class="dropdown-item">
                                                                    {{ __('Edit') }}
                                                                </a>

                                                                @if (($event->lifecycle_status ?: 'scheduled') === 'scheduled')
                                                                    <button type="button" class="dropdown-item text-danger"
                                                                        data-toggle="modal" data-target="#cancelEventModal-{{ $event->id }}">
                                                                        {{ __('Request cancellation') }}
                                                                    </button>
                                                                @endif

                                                                <form class="deleteForm d-block"
                                                                    action="{{ route('organizer.event_management.delete_event', ['id' => $event->id]) }}"
                                                                    method="post">

                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm deleteBtn" data-action="archive">
                                                                        {{ __('Archive') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @foreach ($events as $event)
                                    @if (($event->lifecycle_status ?: 'scheduled') === 'scheduled')
                                        <div class="modal fade" id="cancelEventModal-{{ $event->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <form method="post" action="{{ route('organizer.event_cancellations.store', $event->id) }}">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Request event cancellation') }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="alert alert-warning">
                                                                {{ __('Submitting pauses sales immediately. An administrator will review the payment impact before refunds are sent.') }}
                                                            </div>
                                                            <div class="form-group">
                                                                <label>{{ __('Reason for cancellation') }}</label>
                                                                <textarea name="reason" class="form-control" rows="3" required minlength="10" maxlength="2000"></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>{{ __('Message to attendees') }}</label>
                                                                <textarea name="customer_message" class="form-control" rows="3" maxlength="3000"></textarea>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="confirm" value="1"
                                                                    id="confirm-organizer-cancel-{{ $event->id }}" required>
                                                                <label class="form-check-label" for="confirm-organizer-cancel-{{ $event->id }}">
                                                                    {{ __('I understand that sales will close immediately.') }}
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Keep event') }}</button>
                                                            <button type="submit" class="btn btn-danger">{{ __('Submit cancellation request') }}</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-footer text-center">
                    <div class="d-inline-block mt-3">
                        {{ $events->appends([
                                'language' => request()->input('language'),
                                'title' => request()->input('title'),
                            ])->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
