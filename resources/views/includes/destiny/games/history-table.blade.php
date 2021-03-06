<table class="ui table">
    <thead class="desktop only">
    <tr>
        <th>{{ $t_header or 'Raid' }}</th>
        <th>Date</th>
        <th>Completion Time</th>
        <th>PandaLove Members Present</th>
    </tr>
    </thead>
    <tbody>
    @foreach($raids as $raid)
        <tr>
            <td>
                @if ($raid->type == "PVP")
                    <img class="ui avatar bordered image non-white-bg pvp-emblem" src="{{ $raid->type()->extra }}" />
                @elseif ($raid->type == "PoE")
                    <div class="ui purple horizontal label">Level {{ $raid->type()->extraThird }}</div>
                @elseif ($raid->type == "ToO")
                    <div class="ui black horizontal label">Trials of Osiris</div>
                @else
                    @if ($raid->isHard)
                        <div class="ui red horizontal label">Hard</div>
                    @else
                        <div class="ui green horizontal label">Normal</div>
                    @endif
                @endif
                @if ($raid->raidTuesday != 0)
                        <a href="{{ URL::action('Destiny\GameController@getTuesday', [$raid->raidTuesday]) }}">
                            {{ $raid->type()->title }}
                        </a>
                @elseif ($raid->passageId != 0)
                        <a href="{{ URL::action('Destiny\GameController@getPassage', [$raid->passageId]) }}">
                            @if (\Onyx\Destiny\Helpers\Utils\Game::explodeMap($raid->maps) == false)
                                {{ $raid->type()->title }}
                            @else
                                Random Maps
                            @endif
                        </a>
                @else
                    <a href="{{ URL::action('Destiny\GameController@getGame', [$raid->instanceId]) }}">
                        @if ($raid->type == "PVP")
                            {{ $raid->pvp->gametype }} <small>({{ $raid->type()->title }})</small>
                        @else
                            {{ $raid->type()->title }}
                        @endif
                    </a>
                @endif
            </td>
            <td class="completed-table">{{ $raid->occurredAt }}</td>
            <td class="timetook-table">
                @if ($raid->raidTuesday != 0 || $raid->passageId != 0)
                    {{ \Onyx\Destiny\Helpers\String\Text::timeDuration($raid->totalTime) }}
                @else
                    {{ $raid->timeTookInSeconds }}
                @endif
            </td>
            <td class="pandacount-table">
                {{ $raid->completed() }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
{!! with(new Onyx\Laravel\SemanticPresenter($raids))->render() !!}

@section('inline-css')
    <style type="text/css">
        .pvp-emblem {
            background: #9f342f !important;
        }
    </style>
@append