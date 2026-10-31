<?php

namespace RattingManager\Services\Npc;

/**
 * What the plugin knows about NPCs, as data only.
 *
 * SeAT's SDE gives every rat a name and a group. Classic belt and anomaly
 * groups say both faction and size ("Asteroid Guristas Battleship"); newer
 * ones do not ("Rogue Drone Entities" mixes frigates and battleships), so
 * those fall back to the type's mass. This is the one place to correct a
 * type or group when CCP adds rats the patterns get wrong.
 */
final class NpcRegistry
{
    public const FACTIONS = [
        'guristas' => 'Guristas',
        'sansha' => "Sansha's Nation",
        'blood_raiders' => 'Blood Raiders',
        'angel' => 'Angel Cartel',
        'serpentis' => 'Serpentis',
        'rogue_drones' => 'Rogue Drones',
        'triglavian' => 'Triglavian',
        'drifter' => 'Drifters',
        'sleeper' => 'Sleepers',
        'other' => 'Other',
    ];

    public const SIZES = ['small', 'medium', 'large', 'capital', 'unknown'];

    /**
     * Lowest first, so a setting can ask for one rank and everything above.
     * Each rank above regular is one whose kill drops something worth going
     * back for: a clone soldier always drops a security tag, a commander can
     * drop faction modules, and an officer always drops officer modules and
     * a dog tag.
     */
    public const RANKS = ['regular', 'clone', 'commander', 'officer'];

    /**
     * Ranks and sizes that make a tick worth listing as a special spawn.
     * Capitals drop nothing out of the ordinary, but they are rare enough to
     * list anyway.
     */
    public const SPECIAL_RANKS = ['clone', 'commander', 'officer'];
    public const SPECIAL_SIZES = ['capital'];

    /**
     * Raised whenever the patterns below change, so the types already on
     * file are classified again under the new ones.
     */
    public const RULES = 3;

    /**
     * Checked against the group name first, then the type name. The type
     * name patterns are the pirate naming schemes (Pithi, Centum, Corpus,
     * Gistatis, Coreli and so on), which cover rats whose group is generic.
     */
    public const FACTION_PATTERNS = [
        'guristas' => '/Guristas|\bPith(i|um|atis)?\b/i',
        'sansha' => '/Sansha|\bCent(ii|um|us|atis)\b/i',
        'blood_raiders' => '/Blood Raider|Dark Blood|\bCorp(ii|um|us|atis)\b/i',
        'angel' => '/Angel|Domination|\bGist(ii|um|atis)?\b/i',
        'serpentis' => '/Serpentis|\bCore(li|lum|latis)?\b/i',
        'rogue_drones' => '/Rogue Drone|Infesting|\bDrone\b|\bHive\b|\bSwarm\b/i',
        'triglavian' => '/Triglavian|Damavik|Vedmak|Leshak|Kikimora|Drekavac|Rodiva|Zirnitra/i',
        'drifter' => '/Drifter/i',
        'sleeper' => '/Sleeper|Sleepless|Awakened|Emergent/i',
    ];

    /** Order matters: Battlecruiser has to be read before Cruiser would match. */
    public const SIZE_PATTERNS = [
        'capital' => '/Dreadnought|Titan|Supercarrier|Carrier|Mothership|Capital/i',
        'large' => '/Battleship/i',
        'medium' => '/Battlecruiser|Cruiser/i',
        'small' => '/Frigate|Destroyer/i',
    ];

    /**
     * Officers and commanders are read from the group name only. The SDE keeps them
     * in groups of their own ("Asteroid Guristas Officer",
     * "Asteroid Serpentis Commander Battleship"), the faction-named belt rats
     * among them, while plenty of ordinary rats have Commander or Officer in
     * their own name ("Angel Commander", "Watch Officer").
     */
    public const RANK_GROUP_PATTERNS = [
        'officer' => '/\bOfficer\b/i',
        'commander' => '/\bCommander\b/i',
    ];

    /** Clone soldiers share the ordinary cruiser groups, so their own name is all that tells them apart. */
    public const RANK_NAME_PATTERNS = [
        'clone' => '/\bClone Soldier (Trainer|Recruiter|Transporter|Negotiator)\b/i',
    ];

    /** Upper mass bound in kg for each size, used when the names say nothing. */
    public const MASS_BANDS = [
        'small' => 5_000_000,
        'medium' => 50_000_000,
        'large' => 800_000_000,
    ];

    /** Groups whose faction the patterns cannot read. group_id => faction */
    public const GROUP_FACTIONS = [
        4037 => 'rogue_drones', // Rogue Drone Entities ("Infesting ..."), seen on a live install
    ];

    /**
     * Single types the patterns get wrong. type_id => [faction, size, rank]
     *
     * The overseers below guard Angel Cartel DED complexes. Their group says
     * overseer and their names say nothing, so only the type can place them.
     * They stay regular rats: a site boss is killed every time the site is run,
     * so it is no rare spawn. An admin who wants them listed adds them to the
     * watch list.
     */
    public const TYPES = [
        17141 => ['angel', 'large', 'regular'], // Tritan - The Underground Overseer, Angel Military Operations Complex
        17142 => ['angel', 'large', 'regular'], // The Battlestation Admiral, Angel Military Operations Complex
        17162 => ['angel', 'large', 'regular'], // The Stronghold General, Angel Military Operations Complex
        17165 => ['angel', 'medium', 'regular'], // Control Headquarters, Angel Military Operations Complex
        17166 => ['angel', 'medium', 'regular'], // Security Coordinator, Angel Military Operations Complex
        17413 => ['angel', 'large', 'regular'], // Captain Rouge, Angel's Red Light District
        17744 => ['angel', 'large', 'regular'], // Purple Particle Research Patrol
        17745 => ['angel', 'large', 'regular'], // General Hixous Puxley
        17746 => ['angel', 'large', 'regular'], // Supply Headman
        17749 => ['angel', 'large', 'regular'], // The Antimatter Channeler
        19423 => ['angel', 'large', 'regular'], // Watch Officer
        28863 => ['angel', 'large', 'regular'], // Hashi Keptzh
    ];

    /**
     * Rats that are meant to have no faction here, so the Unplaced NPCs check
     * does not ask about them: the Crimson Harvest event's, and the ones the
     * game marks with a diamond. Checked against the type and group names.
     */
    public const NO_FACTION_ON_PURPOSE = '/^Harvest\b|♦/u';
}
