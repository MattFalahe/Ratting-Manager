<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts the webhook URLs already saved, as the Webhook model now reads and
 * writes them. An encrypted URL runs to several times its length, so the
 * column becomes TEXT first. Only rows still in plain text are touched, so
 * this can run on an install that is part way through.
 *
 * Code older than this reads the encrypted text as a broken URL and sends
 * nothing, so going back takes the down() below as well as the old code.
 */
class EncryptRattingManagerWebhookUrls extends Migration
{
    private const TABLE = 'ratting_manager_webhooks';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (stripos($this->columnType(), 'text') === false) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' MODIFY webhook_url TEXT NOT NULL');
        }

        foreach (DB::table(self::TABLE)->get(['id', 'webhook_url']) as $row) {
            if (str_starts_with((string) $row->webhook_url, 'https://')) {
                DB::table(self::TABLE)->where('id', $row->id)->update(['webhook_url' => Crypt::encryptString($row->webhook_url)]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (DB::table(self::TABLE)->get(['id', 'webhook_url']) as $row) {
            if (str_starts_with((string) $row->webhook_url, 'https://')) {
                continue;
            }

            try {
                DB::table(self::TABLE)->where('id', $row->id)->update(['webhook_url' => Crypt::decryptString((string) $row->webhook_url)]);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Made with another APP_KEY, so nothing can read it, and it
                // would not fit the old column. An admin enters it again.
                DB::table(self::TABLE)->where('id', $row->id)->update(['webhook_url' => '']);
            }
        }

        if (stripos($this->columnType(), 'text') !== false) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' MODIFY webhook_url VARCHAR(500) NOT NULL');
        }
    }

    private function columnType(): string
    {
        return (string) (DB::select('SHOW COLUMNS FROM ' . self::TABLE . " LIKE 'webhook_url'")[0]->Type ?? '');
    }
}
