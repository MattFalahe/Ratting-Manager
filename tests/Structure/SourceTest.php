<?php

namespace RattingManager\Tests\Structure;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Configuration\SettingsRegistry;
use RattingManager\Services\Diagnostic\Checks;

/**
 * Things a mistake in the source would break on a live install that nothing
 * else catches before then, read from the files themselves.
 */
class SourceTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const SRC = self::ROOT . 'src/';

    /** @return string[] paths of the files under src/ with this ending */
    private static function files(string $ending, string $under = ''): array
    {
        $found = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC . $under, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getPathname(), $ending)) {
                $found[] = $file->getPathname();
            }
        }
        sort($found);

        return $found;
    }

    /**
     * Blade reads a directive with \B@\w+, so counting @if and @endif proves
     * nothing: {{ $h }}h@endif is not a directive, and leaves its @if open.
     */
    public function test_every_blade_directive_is_closed_the_way_blade_reads_it(): void
    {
        $opens = [
            'if' => 'endif', 'unless' => 'endunless', 'isset' => 'endisset', 'foreach' => 'endforeach',
            'forelse' => 'endforelse', 'for' => 'endfor', 'while' => 'endwhile', 'push' => 'endpush',
        ];
        $closes = array_flip($opens) + ['endsection' => 'section', 'stop' => 'section', 'endphp' => 'php'];

        foreach (self::files('.blade.php') as $path) {
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($path));
            $source = preg_replace('/\B@php\b(?!\s*\().*?\B@endphp\b/s', '', $source);
            // A section with its content in the call has nothing to close.
            $source = preg_replace("/\B@section\(\s*'[^']*'\s*,.*\)\s*$/m", '', $source);

            preg_match_all('/\B@(\w+)/', $source, $matches);
            $stack = [];
            foreach ($matches[1] as $directive) {
                if ($directive === 'section') {
                    $stack[] = 'section';
                } elseif (isset($opens[$directive])) {
                    $stack[] = $directive;
                } elseif (isset($closes[$directive])) {
                    $this->assertSame($closes[$directive], array_pop($stack), basename($path) . ": @$directive closes the wrong block");
                }
            }

            $this->assertSame([], $stack, basename($path) . ': left open');
        }
    }

    public function test_every_language_key_a_literal_call_names_exists(): void
    {
        $lang = [];
        foreach (self::files('.php', 'Resources/lang/en') as $path) {
            $lang[basename($path, '.php')] = require $path;
        }

        foreach (array_merge(self::files('.php')) as $path) {
            preg_match_all("/\b(?:trans|__)\(\s*'ratting-manager::([a-z_]+)\.([a-zA-Z0-9_.]+)'\s*[,)]/", file_get_contents($path), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $file, $key]) {
                $value = $lang[$file] ?? null;
                foreach (explode('.', $key) as $part) {
                    $value = is_array($value) ? ($value[$part] ?? null) : null;
                }
                $this->assertNotNull($value, basename($path) . " names $file.$key, which is not there");
            }
        }
    }

    public function test_migrations_are_named_classes_matching_their_file(): void
    {
        foreach (self::files('.php', 'Database/migrations') as $path) {
            $source = file_get_contents($path);
            $suffix = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($path, '.php'));
            $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $suffix)));

            $this->assertStringNotContainsString('return new class', $source, basename($path));
            $this->assertMatchesRegularExpression('/\bclass ' . $class . ' extends Migration\b/', $source, basename($path));
            $this->assertStringContainsString('ratting_manager', $suffix, basename($path) . ' has to name the plugin: migrations share one namespace');
        }
    }

    public function test_migration_numbers_run_in_order_with_none_repeated(): void
    {
        $numbers = array_map(fn ($path) => (int) substr(basename($path), 11, 6), self::files('.php', 'Database/migrations'));

        $this->assertSame(range(1, count($numbers)), $numbers);
    }

    public function test_index_names_fit_in_what_mysql_allows(): void
    {
        foreach (self::files('.php', 'Database/migrations') as $path) {
            preg_match_all("/'(rm_[a-z_]+)'/", file_get_contents($path), $matches);
            foreach ($matches[1] as $name) {
                $this->assertLessThanOrEqual(64, strlen($name), $name);
            }
        }
    }

    /** SeAT's tables are read, never written. Our data lives in our own tables. */
    public function test_nothing_writes_a_table_that_is_not_the_plugins(): void
    {
        foreach (self::files('.php') as $path) {
            preg_match_all("/DB::table\('([a-zA-Z_]+)(?: as \w+)?'\)((?:(?!DB::table|;).)*?)->(insert|insertOrIgnore|insertUsing|insertGetId|update|updateOrInsert|upsert|delete|truncate|increment|decrement)\(/s", file_get_contents($path), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $table]) {
                $this->assertStringStartsWith('ratting_manager_', $table, basename($path) . " writes $table");
            }
        }
    }

    /** Not a column, not an index: SeAT's schema is SeAT's. What is slow there is read differently instead. */
    public function test_nothing_changes_a_table_that_is_not_the_plugins(): void
    {
        foreach (self::files('.php', 'Database/migrations') as $path) {
            $source = file_get_contents($path);

            preg_match_all("/Schema::(?:create|table|drop|dropIfExists|rename)\(\s*'([a-zA-Z_]+)'/", $source, $named);
            preg_match_all("/const [A-Z_]*TABLE\w* = '([a-zA-Z_]+)'/", $source, $constants);
            preg_match_all('/\b(?:ALTER TABLE|CREATE (?:UNIQUE )?INDEX \S+ ON|DROP TABLE(?: IF EXISTS)?)\s+`?([a-zA-Z_]+)/i', $source, $statements);

            foreach (array_merge($named[1], $constants[1], $statements[1]) as $table) {
                $this->assertStringStartsWith('ratting_manager_', $table, basename($path));
            }

            // Lists of tables to go through are keyed by name, so a SeAT table
            // named anywhere in a migration is one it would change.
            foreach (Checks::SEAT_TABLES as $table) {
                $this->assertStringNotContainsString("'$table'", $source, basename($path) . " names SeAT's $table");
            }
        }

        foreach (self::files('.php') as $path) {
            if (!str_contains($path, 'Database' . DIRECTORY_SEPARATOR . 'migrations') && !str_contains($path, 'Database/migrations')) {
                $this->assertDoesNotMatchRegularExpression('/Schema::(create|table|drop|dropIfExists|rename)\(/', file_get_contents($path), basename($path) . ' changes a schema outside a migration');
            }
        }
    }

    public function test_every_command_is_registered(): void
    {
        $provider = file_get_contents(self::SRC . 'RattingManagerServiceProvider.php');

        foreach (glob(self::SRC . 'Console/Commands/*Command.php') as $path) {
            $class = basename($path, '.php');
            $this->assertStringContainsString("use RattingManager\\Console\\Commands\\$class;", $provider, $class);
            $this->assertStringContainsString("$class::class", $provider, $class);
        }
    }

    public function test_the_schedule_and_the_health_check_list_the_same_commands(): void
    {
        preg_match_all("/'command' => '([^']+)'/", file_get_contents(self::SRC . 'Database/Seeders/ScheduleSeeder.php'), $matches);

        $this->assertEqualsCanonicalizing($matches[1], Checks::COMMANDS);
    }

    public function test_every_table_the_health_check_expects_has_a_migration(): void
    {
        $migrations = '';
        foreach (self::files('.php', 'Database/migrations') as $path) {
            $migrations .= file_get_contents($path);
        }

        foreach (Checks::PLUGIN_TABLES as $table) {
            $this->assertStringContainsString("'$table'", $migrations, $table);
        }
    }

    public function test_every_setting_the_code_names_is_registered(): void
    {
        foreach (self::files('.php') as $path) {
            preg_match_all("/(?:settings|Settings::class\))->(?:get|set)\('([a-z_.]+)'/", file_get_contents($path), $matches);
            foreach ($matches[1] as $key) {
                $this->assertTrue(SettingsRegistry::has($key), basename($path) . " names the setting $key");
            }
        }
    }

    public function test_the_docs_cover_every_command_and_option_and_name_none_that_is_gone(): void
    {
        $known = [];
        foreach (glob(self::SRC . 'Console/Commands/*Command.php') as $path) {
            $source = file_get_contents($path);
            $this->assertSame(1, preg_match("/signature = 'ratting-manager:([a-z-]+)(.*?)';/s", $source, $signature), basename($path));
            preg_match_all('/\{--([a-z-]+)/', $signature[2], $options);
            $known[$signature[1]] = $options[1];
        }

        foreach (['README.md', 'src/Resources/views/help/index.blade.php'] as $file) {
            $docs = file_get_contents(self::ROOT . $file);

            foreach ($known as $command => $options) {
                $this->assertStringContainsString("ratting-manager:$command", $docs, "$file leaves out $command");
                foreach ($options as $option) {
                    $this->assertStringContainsString("--$option", $docs, "$file leaves out $command --$option");
                }
            }

            preg_match_all('/ratting-manager:([a-z-]+)/', $docs, $named);
            foreach (array_unique($named[1]) as $command) {
                $this->assertArrayHasKey($command, $known, "$file names $command, which is not a command");
            }
        }
    }

    /** A package name with a branch in it stops working the day the branch goes, and people copy what the docs say. */
    public function test_the_docs_name_the_package_and_no_branch(): void
    {
        foreach (['README.md', 'CHANGELOG.md', 'src/Resources/views/help/index.blade.php'] as $file) {
            $source = file_get_contents(self::ROOT . $file);
            $this->assertDoesNotMatchRegularExpression('/ratting-manager:dev|dev-dev-|dev-1\.0/', $source, $file);
        }
    }

    public function test_user_facing_text_has_no_em_dashes(): void
    {
        $paths = array_merge([self::ROOT . 'README.md', self::ROOT . 'CHANGELOG.md'], self::files('.php', 'Resources/lang/en'), self::files('.blade.php'));

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('—', file_get_contents($path), basename($path));
        }
    }
}
