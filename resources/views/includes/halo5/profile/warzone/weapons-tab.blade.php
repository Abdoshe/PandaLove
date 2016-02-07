@define $i = 0
@foreach ($account->h5->warzone->weapons as $key => $count)
    @if ($count > 0)
        @if ($i == 0)
            <div class="ui four doubling cards">
        @endif
                <div class="card">
                    <div class="image">
                        <img src="/images/weapons/{{ $key }}.png" />
                    </div>
                    <div class="content">
                        <div class="header">{{ $weapons[$key]->name }}</div>
                        <div class="meta">
                            {{ $count }} Kills
                        </div>
                    </div>
                </div>
        @if ($i == 7)
            @define $i = -1
            </div>
        @endif
        @define $i++
    @endif
@endforeach

@if ($i != 1)
    </div>
@endif