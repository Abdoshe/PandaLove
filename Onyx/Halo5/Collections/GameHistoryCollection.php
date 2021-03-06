<?php

namespace Onyx\Halo5\Collections;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Onyx\Account;
use Onyx\Halo5\Client;
use Onyx\Halo5\Enums\GameMode;
use Onyx\Halo5\Helpers\Date\DateHelper;
use Onyx\Halo5\Objects\Data;
use Onyx\Halo5\Objects\Gametype;
use Onyx\Halo5\Objects\Map;
use Onyx\Halo5\Objects\MapVariant;
use Onyx\Halo5\Objects\Playlist;

/**
 * Class SeasonCollection.
 *
 * @property array $items
 */
class GameHistoryCollection extends Collection
{
    public function __construct(Account $account, $matches)
    {
        $maps = Map::getAll();
        $gametypes = Gametype::getAll();
        $playlists = Playlist::getAll();
        $map_variants = MapVariant::getAll();

        foreach ($matches as $match) {
            $mapVariantId = $match['MapVariant']['ResourceId'];

            $game = [
                'gametype'    => $gametypes[$match['GameBaseVariantId']],
                'map'         => $maps[$match['MapId']],
                'map_variant' => isset($map_variants[$mapVariantId]) ? $map_variants[$mapVariantId] : $this->getMapVariant($mapVariantId),
                'playlist'    => $playlists[$match['HopperId']],
                'player'      => new Data($match['Players'][0]),
                'date'        => new Carbon($match['MatchCompletedDate']['ISO8601Date']),
                'duration'    => DateHelper::returnSeconds($match['MatchDuration']),
                'win'         => $match['Players'][0]['Result'],
                'url'         => action('Halo5\GameController@getGame', [GameMode::getName($match['Id']['GameMode'], true), $match['Id']['MatchId']]),
            ];

            // fix some cases
            $game['player']['totalDeaths'] = $game['player']['TotalDeaths'];
            $game['player']['totalKills'] = $game['player']['TotalKills'];
            $game['player']['totalSpartanKills'] = $game['player']['TotalKills'];
            $game['player']['totalAssists'] = $game['player']['TotalAssists'];

            $this->items[$match['Id']['MatchId']] = $game;
        }
    }

    /**
     * @param $mapVariantId
     *
     * @return bool
     */
    private function getMapVariant($mapVariantId)
    {
        $client = new Client();

        return $client->checkOrInsertMapVariant($mapVariantId);
    }
}
