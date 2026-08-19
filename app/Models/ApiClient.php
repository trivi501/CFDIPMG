<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = [
        'name',
        'key_hash',
        'last_used_at',
        'is_active',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Create a new API client and return it along with the plaintext key,
     * which is only ever available at creation time.
     *
     * @return array{client: self, plainTextKey: string}
     */
    public static function generate(string $name): array
    {
        $plainTextKey = 'cfdi_'.Str::random(40);

        $client = self::create([
            'name' => $name,
            'key_hash' => hash('sha256', $plainTextKey),
            'is_active' => true,
        ]);

        return ['client' => $client, 'plainTextKey' => $plainTextKey];
    }

    public static function findByPlainTextKey(string $plainTextKey): ?self
    {
        return self::query()
            ->where('key_hash', hash('sha256', $plainTextKey))
            ->where('is_active', true)
            ->first();
    }
}
