<?php namespace Onyx\Halo5\Helpers\Utils;

use Onyx\Calendar\Objects\Event;
use Onyx\Halo5\Enums\EventName;
use Onyx\Halo5\Enums\MetadataType;
use Onyx\Halo5\Objects\Match;
use Onyx\Halo5\Objects\MatchEvent;
use Onyx\Halo5\Objects\MatchPlayer;
use Onyx\Laravel\Helpers\Text;

class Game {

    /**
     * UUID for No scope award -- Snapshot medal
     */
    const MEDAL_NOSCOPE_UUID = '1986137636';

    /**
     * UUID for Sniper award -- Sniper Kill medal
     */
    const MEDAL_SNIPER_UUID = '775545297';

    /**
     * UUID for Sniper award -- Sniper Headshot medal
     */
    const MEDAL_SNIPER_HEAD_UUID = '848240062';

    /**
     * @param Match $match
     * @return string
     */
    public static function buildKillChartArray(Match $match)
    {
        $team_map = [];
        $kill_time = [];
        $team_label = [];

        foreach ($match->players as $player)
        {
            $team_map[$player->account_id] = ($match->isTeamGame) ? $player->team_id : $player->account_id;
        }

        if ($match->isTeamGame)
        {
            // Set all teams to 0 kills at 0 seconds
            foreach ($match->teams as $team)
            {
                $kill_time[0][$team->key] = 0;
                $team_label[$team->key] = [
                    'name' => $team->team->name,
                    'color' => $team->team->color,
                ];
            }
        }
        else
        {
            $colors = ['E61919', 'E6A119', 'E5E619', '9CB814', '4D8A0F', '14B84B',
            '19E6C4', '149CB8', '1F36AD', '4E1FAD', '9D26D9', 'D926D9', 'E6193C',
            'E8E3E3', '38302E', '33293D', 'F6CCFF'];

            $i = 0;
            foreach ($match->players as $player)
            {
                $kill_time[0][$player->account_id] = 0;
                $team_label[$player->account_id] = [
                    'name' => $player->account->gamertag,
                    'color' => "#" . $colors[$i++],
                ];
            }
        }

        $previousSecond = 0;
        foreach ($match->kill_events as $event)
        {
            /** @var integer $second */
            $second = $event->getOriginal('seconds_since_start');
            $team_id = $team_map[$event->killer_id];

            $kill_time[$second][$team_id] = $kill_time[$previousSecond][$team_id] + 1;

            if ($match->isTeamGame)
            {
                foreach ($match->teams as $team)
                {
                    if (! isset($kill_time[$second][$team->key]))
                    {
                        $kill_time[$second][$team->key] = $kill_time[$previousSecond][$team->key];
                    }
                }
            }
            else
            {
                foreach ($match->players as $player)
                {
                    if (! isset($kill_time[$second][$player->account_id]))
                    {
                        $kill_time[$second][$player->account_id] = $kill_time[$previousSecond][$player->account_id];
                    }
                }
            }

            $previousSecond = $second;
        }

        $label = [];
        $team_data = [];
        // Now lets build the format that the JSON expects
        foreach ($kill_time as $seconds => $teams)
        {
            $label[] = Text::timeDuration($seconds);
            foreach ($teams as $key => $kills)
            {
                $team_data[$key][] = $kills;
            }
        }
        
        $teams = [];
        foreach ($team_label as $key => $data)
        {
            $teams[] = [
                'label' => $data['name'],
                'borderColor' => $data['color'],
                'backgroundColor' => "rgba(" . Color::hex2rgb($data['color']) . ", 0.1)",
                'data' => $team_data[$key],
            ];
        }

        $json = [
            'labels' => $label,
            'datasets' => $teams,
        ];

        return json_encode($json);
    }

    /**
     * @param Match $match
     * @return array
     */
    public static function buildCombinedMatchEvents(Match $match)
    {
        $combined = [];

        // Our goal here is simple. Events occur at the same second can be bulked
        // IE all spawn events can be grouped
        // All events for same user at same second
        // This will make our lives easier for building the timeline
        $skipNextEvent = false;
        $secondToSkip = -1;

        foreach ($match->events as $event)
        {
            /** @var $second integer */
            $second = $event->getOriginal('seconds_since_start');
            if ($second != $secondToSkip && $secondToSkip != -1)
            {
                $skipNextEvent = false;
                $secondToSkip = 0;
            }
            
            if ($skipNextEvent)
            {
                $secondToSkip = $second;
                continue;
            }

            $id = $event->killer_id == null ? 0 : $event->killer_id;

            if ($event->event_name == EventName::RoundStart)
            {
                $skipNextEvent = true;
            }

            $combined[$second][$id][] = $event;
            $combined[$second]['stats'] = [
                'time' => $event->seconds_since_start,
                'type' => $event->event_name
            ];
        }

        // Lets go through the second groups and look for events we can group together
        $skipNextType = null;
        foreach ($combined as $time_key => &$time)
        {
            foreach ($time as $user_id => &$events)
            {
                if ($user_id == "stats")
                {
                    continue;
                }

                /** @var $event MatchEvent */
                foreach ($events as $key => &$event)
                {
                    if ($skipNextType != null && $skipNextType == $event->event_name)
                    {
                        unset($combined[$time_key][$user_id][$key]);
                        continue;
                    }

                    if ($event->event_name == EventName::WeaponPickupPad)
                    {
                        $skipNextType = EventName::WeaponPickup;
                    }
                    else if ($event->event_name == EventName::Medal)
                    {

                    }
                    else if ($event->event_name == EventName::Impulse)
                    {
                        if (MetadataType::isTickingImpulse($event->killer_weapon_id))
                        {
                            // Check if this id already has IN-Progress, if so update `last`.
                            // Iterate through the already active Impulse counts.
                        }
                    }
                }
            }
        }

        return $combined;
    }

    /**
     * @param $match Match
     * @return array
     */
    public static function buildQuickGameStats($match)
    {
        $combined = [
            'vip' => [
                'title' => 'Match VIP',
                'tooltip' => 'Who the game thought was the best spartan',
                'message' => 'Game VIP',
                'spartan' => null,
            ],
            'kd' => [
                'title' => 'KD',
                'tooltip' => 'Best KD Ratio (Kills / Deaths)',
                'message' => 'Highest KD Ratio',
                'spartan' => null,
            ],
            'kda' => [
                'title' => 'KDA',
                'tooltip' => 'Best KDA Ratio ( ( Kills + Assists ) / Deaths)',
                'message' => 'Highest KDA Ratio',
                'spartan' => null,
            ],
            'kills' => [
                'title' => 'Kills',
                'tooltip' => 'Most Kills in Match',
                'message' => 'Most Kills',
                'spartan' => null,
            ],
            'loser' => [
                'title' => 'Most Deaths',
                'tooltip' => 'The unfortunate spartan to die the most in this match.',
                'message' => 'Sir. Dies-a-lot',
                'spartan' => null,
            ],
            'deaths' => [
                'title' => 'Deaths',
                'tooltip' => 'The spartan who died the least in this match.',
                'message' => 'Least Deaths',
                'spartan' => null,
            ],
            'assists' => [
                'title' => 'Total Assists',
                'tooltip' => 'The spartan who got the most assists',
                'message' => 'Team Helper',
                'spartan' => null
            ],
            'medals' => [
                'title' => 'Medals',
                'tooltip' => 'The spartan who collected the most medals in this match.',
                'message' => 'Medal Collector',
                'spartan' => null,
            ],
            'damage' => [
                'title' => 'Damage',
                'tooltip' => 'The spartan who dealt the most damage in this match.',
                'message' => 'Maximum Damage',
                'spartan' => null,
            ],
            'avgtime' => [
                'title' => 'Average Time',
                'tooltip' => 'The spartan who had the longest average lifespan.',
                'message' => 'Longest Average Lifespan',
                'spartan' => null,
            ],
            'groundpound' => [
                'title' => 'Groundpound',
                'tooltip' => 'The spartan who got the most groundpounds',
                'message' => 'Falling Anvil',
                'spartan' => null,
                'zero' => true,
            ],
            'noscoper' => [
                'title' => 'NoScoper',
                'tooltip' => 'The spartan who got the most no-scopes in this match',
                'message' => 'NoScoper',
                'spartan' => null,
                'zero' => true
            ],
            'sniper' => [
                'title' => 'Sniper',
                'tooltip' => 'The spartan with the most snipes in this match.',
                'message' => 'Sniper',
                'spartan' => null,
                'zero' => true
            ],
            'assassin' => [
                'title' => 'Assassin',
                'tooltip' => 'The spartan with the most assassinations in this match.',
                'message' => 'Mr. Sneaks',
                'spartan' => null,
                'zero' => true
            ],
            'aikiller' => [
                'title' => 'AI Killer',
                'tooltip' => 'The spartan who killed the most AI in this match.',
                'message' => 'AI Killer',
                'spartan' => null,
                'zero' => true,
            ],
            'beater' => [
                'title' => 'Melee Kills',
                'tooltip' => 'The spartan who beat down (melee) the most spartans in this match.',
                'message' => 'Beater',
                'spartan' => null,
                'zero' => true,
            ],
            'powerholder' => [
                'title' => 'Power Weapon Held Time',
                'tooltip' => 'The spartan who held power weapons the longest',
                'message' => 'Power Weapon Hogger',
                'spartan' => null,
            ],
            'highest_rank' => [
                'title' => 'Highest Spartan Rank',
                'tooltip' => 'The spartan with highest Spartan Rank',
                'message' => 'Highest Spartan Rank',
                'spartan' => null,
            ],
            'grenade_spammer' => [
                'title' => 'Total Grenade Kills',
                'tooltip' => 'The spartan with the most grenade kills',
                'message' => 'Nade Spammer',
                'spartan' => null,
                'zero' => true,
            ],
            'accurate_shot' => [
                'title' => 'Accurate Shot',
                'tooltip' => 'The spartan who fired the most shots accurately. (Landed / Fired).',
                'message' => 'Accurate Shot',
                'spartan' => null
            ],
        ];

        foreach ($match->players as $player)
        {
            if ($player->dnf == 1) continue;

            self::checkOrSet($combined['vip'], $player, 'rank', false);
            self::checkOrSet($combined['kd'], $player, 'kd', true);
            self::checkOrSet($combined['kda'], $player, 'kad', true);
            self::checkOrSet($combined['kills'], $player, 'totalKills', true);
            self::checkOrSet($combined['loser'], $player, 'totalDeaths', true);
            self::checkOrSet($combined['deaths'], $player, 'totalDeaths', false);
            self::checkOrSet($combined['assists'], $player, 'totalAssists', true);
            self::checkOrSet($combined['damage'], $player, function($player) {
                return round($player->weapon_dmg, 2);
            }, true);
            self::checkOrSet($combined['avgtime'], $player, 'avg_lifestime', true);
            self::checkOrSet($combined['groundpound'], $player, 'totalGroundPounds', true);
            self::checkOrSet($combined['assassin'], $player, 'totalAssassinations', true);
            
            self::checkOrSet($combined['medals'], $player, function($player) {
                return collect($player->medals)->sum('count');
            }, true);

            self::checkOrSet($combined['noscoper'], $player, function ($player) {
                return self::getMedalCount($player, self::MEDAL_NOSCOPE_UUID);
            }, true);

            self::checkOrSet($combined['sniper'], $player, function ($player) {
                return self::getMedalCount($player, [self::MEDAL_SNIPER_UUID, self::MEDAL_SNIPER_HEAD_UUID]);
            }, true);

            self::checkOrSet($combined['accurate_shot'], $player, function($player) {
                if ($player->shots_fired == 0)
                {
                    return $player->shots_fired;
                }
                return round((($player->shots_landed / $player->shots_fired) * 100), 2) ."%";
            }, true);

            self::checkOrSet($combined['aikiller'], $player, 'totalAiKills', true);
            self::checkOrSet($combined['beater'], $player, 'totalMeleeKills', true);
            self::checkOrSet($combined['powerholder'], $player, 'totalPowerWeaponTime', true);
            self::checkOrSet($combined['highest_rank'], $player, 'spartanRank', true);
            self::checkOrSet($combined['grenade_spammer'], $player, 'totalGrenadeKills', true);
        }

        return [
            'top' => $combined,
            'funny' => null
        ];
    }

    /**
     * @param $combined mixed
     * @param $player MatchPlayer
     * @param $key string
     * @param $high boolean (sort by high)
     */
    private static function checkOrSet(&$combined, $player, $key, $high = true)
    {
        if ($combined['spartan'] == null)
        {
            self::set($combined, $player, $key);
        }
        else
        {
            if ($high)
            {
                if (self::get($combined['spartan'], $key) < self::get($player, $key))
                {
                    self::set($combined, $player, $key);
                }
            }
            else
            {
                if (self::get($combined['spartan'], $key) > self::get($player, $key))
                {
                    self::set($combined, $player, $key);
                }
            }
        }
    }

    /**
     * @param $combined array
     * @param $player MatchPlayer
     * @param $key string
     * @return void
     */
    private static function set(&$combined, $player, $key)
    {
        $combined['spartan'] = $player;
        $combined['value'] = self::get($player, $key);
        $combined['formatted'] = self::get($player, $key, true);
    }

    /**
     * @param $player MatchPlayer
     * @param $key string|callable
     * @param $formatted boolean
     * @return mixed
     */
    private static function get($player, $key, $formatted = false)
    {
        if (is_callable($key))
        {
            return call_user_func($key, $player);
        }
        else if (method_exists($player, $key))
        {
            return $player->$key();
        }
        else
        {
            if ($formatted)
            {
                return $player->$key;
            }
            return $player->getOriginal($key);
        }
    }

    /**
     * @param $player MatchPlayer
     * @param $keys array
     * @return mixed
     */
    private static function getMedalCount($player, $keys)
    {
        return collect($player->medals)
            ->only($keys)
            ->sum('count');
    }
}