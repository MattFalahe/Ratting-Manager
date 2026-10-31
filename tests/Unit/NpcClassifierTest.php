<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Npc\NpcClassifier;
use RattingManager\Services\Npc\NpcRegistry;

class NpcClassifierTest extends TestCase
{
    private static function classify(?string $name, ?string $group, ?float $mass, int $typeId = 1, ?int $groupId = 1): array
    {
        return (new \ReflectionClass(NpcClassifier::class))->newInstanceWithoutConstructor()->classify($typeId, $name, $groupId, $group, $mass);
    }

    public function test_a_belt_rat_is_placed_from_its_group(): void
    {
        $this->assertSame(
            ['faction' => 'guristas', 'size' => 'large', 'rank' => 'regular', 'classified_by' => 'group'],
            self::classify('Pithum Nullifier', 'Asteroid Guristas Battleship', 19_000_000.0)
        );
    }

    /** What the research found: drones field carriers and supercarriers, pirates dreadnoughts and titans, Sansha dreadnoughts and supercarriers. */
    public function test_every_capital_class_rats_come_in_is_a_capital(): void
    {
        foreach ([
            ['Infested Carrier', 'Asteroid Rogue Drone Carrier', 'rogue_drones'],
            ['Sentient Infested Supercarrier', 'Asteroid Rogue Drone Supercarrier', 'rogue_drones'],
            ["True Sansha's Supercarrier", "Asteroid Sansha's Nation Supercarrier", 'sansha'],
            ["Sansha's Dreadnought", "Asteroid Sansha's Nation Dreadnought", 'sansha'],
            ['Domination Titan', 'Asteroid Angel Cartel Titan', 'angel'],
            ['Angel Dreadnought', 'Asteroid Angel Cartel Dreadnought', 'angel'],
            ['Dark Blood Titan', 'Asteroid Blood Raider Titan', 'blood_raiders'],
            ['Dread Guristas Titan', 'Asteroid Guristas Titan', 'guristas'],
            ['Shadow Serpentis Titan', 'Asteroid Serpentis Titan', 'serpentis'],
        ] as [$name, $group, $faction]) {
            $npc = self::classify($name, $group, 1_200_000_000.0);
            $this->assertSame('capital', $npc['size'], $name);
            $this->assertSame($faction, $npc['faction'], $name);
        }
    }

    public function test_a_carrier_that_is_a_hauler_is_no_capital(): void
    {
        foreach ([
            ['Angel Carrier', 'Asteroid Angel Cartel Hauler', 19_000_000.0, 'medium'],
            ['Guristas Carrier', 'Asteroid Guristas Hauler', 1_080_000.0, 'small'],
            ['Rogue Drone Carrier', 'Asteroid Rogue Drone Hauler', 19_000_000.0, 'medium'],
        ] as [$name, $group, $mass, $size]) {
            $npc = self::classify($name, $group, $mass);
            $this->assertSame($size, $npc['size'], $name);
            $this->assertSame('mass', $npc['classified_by'], $name);
        }
    }

    public function test_a_group_that_mixes_sizes_goes_by_mass(): void
    {
        $this->assertSame('small', self::classify('Infesting Darter', 'Rogue Drone Entities', 1_000_000.0, 1, 4037)['size']);
        $this->assertSame('large', self::classify('Infesting Subvertor Gamma', 'Rogue Drone Entities', 260_000_000.0, 1, 4037)['size']);
        $this->assertSame('capital', self::classify('Infesting Mutagenitor Zero', 'Rogue Drone Entities', 1_260_000_000.0, 1, 4037)['size']);
        $this->assertSame('rogue_drones', self::classify('Infesting Darter', 'Rogue Drone Entities', 1_000_000.0, 1, 4037)['faction']);
    }

    public function test_the_name_only_counts_with_no_mass_to_go_on(): void
    {
        $npc = self::classify('Some Dreadnought', 'Something Else', null);

        $this->assertSame('capital', $npc['size']);
        $this->assertSame('group', $npc['classified_by']);
    }

    public function test_nothing_to_go_on(): void
    {
        $this->assertSame('unknown', self::classify('Odd Thing', 'Something Else', null)['classified_by']);
        $this->assertSame(['faction' => 'other', 'size' => 'unknown', 'rank' => 'regular', 'classified_by' => 'unknown'], self::classify(null, null, null));
    }

    public function test_ranks_come_from_the_group_not_the_rats_own_name(): void
    {
        $this->assertSame('officer', self::classify("Estamel Tharchon", 'Asteroid Guristas Officer', 19_000_000.0)['rank']);
        $this->assertSame('commander', self::classify('Dread Guristas Arch Admiral', 'Asteroid Guristas Commander Battleship', 19_000_000.0)['rank']);
        $this->assertSame('regular', self::classify('Angel Commander', 'Asteroid Angel Cartel Battleship', 19_000_000.0)['rank']);
        $this->assertSame('regular', self::classify('Watch Officer', 'Deadspace Overseer Battleship', 19_000_000.0)['rank']);
    }

    public function test_clone_soldiers_are_told_by_name(): void
    {
        $this->assertSame('clone', self::classify('Clone Soldier Negotiator', 'Asteroid Guristas Cruiser', 10_000_000.0)['rank']);
    }

    public function test_the_overseers_nothing_else_can_place(): void
    {
        $tritan = self::classify('Tritan - The Underground Overseer', 'Deadspace Overseer Battleship', 19_000_000.0, 17141);

        $this->assertSame(['faction' => 'angel', 'size' => 'large', 'rank' => 'regular', 'classified_by' => 'registry'], $tritan);
        $this->assertSame('medium', self::classify('Control Headquarters', 'Deadspace Overseer Cruiser', 10_900_000.0, 17165)['size']);
    }

    public function test_event_rats_and_diamond_rats_are_left_off_the_warning(): void
    {
        foreach (['Harvest Savior', 'Harvest Overseer', '♦ Omen', '♦ Cruiser'] as $text) {
            $this->assertSame(1, preg_match(NpcRegistry::NO_FACTION_ON_PURPOSE, $text), $text);
        }
        foreach (['Tritan - The Underground Overseer', 'Harvester Drone', 'Mordus Bobcat'] as $text) {
            $this->assertSame(0, preg_match(NpcRegistry::NO_FACTION_ON_PURPOSE, $text), $text);
        }
    }

    public function test_every_special_rank_is_a_rank(): void
    {
        $this->assertSame([], array_diff(NpcRegistry::SPECIAL_RANKS, NpcRegistry::RANKS));
        $this->assertSame([], array_diff(NpcRegistry::SPECIAL_SIZES, NpcRegistry::SIZES));
    }
}
