<?php

namespace RattingManager\Http\Controllers;

use RattingManager\Services\Configuration\SettingsRegistry;
use RattingManager\Services\VersionChecker;
use RattingManager\Support\Access;
use Seat\Web\Http\Controllers\Controller;

class HelpController extends Controller
{
    public function index(VersionChecker $versions)
    {
        return view('ratting-manager::help.index', [
            'isMember' => Access::atLeast('member'),
            'isDirector' => Access::isDirector(),
            'isAdmin' => Access::isAdmin(),
            'sessionGap' => SettingsRegistry::default('data.session_gap_minutes'),
            'versionStatus' => $versions->getStatus(),
        ]);
    }
}
