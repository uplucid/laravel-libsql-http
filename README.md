# Laravel libSQL HTTP Driver

Pure PHP HTTP driver for Turso/libSQL in Laravel 11+. No PDO dependency - uses Guzzle for HTTP communication with libSQL's Hrana v2 API.

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Guzzle 7.8+

## Installation

@TODO: Add to Packagist and update installation instructions. For now, you can require the package directly from GitHub:

```bash
composer require uplucid/laravel-libsql-http
```

## Configuration

Add your Turso connection to `config/database.php`:

```php
'connections' => [
    'libsql' => [
        'driver' => 'libsql',
        'host' => 'your-database.turso.io',
        'token' => env('TURSO_TOKEN'),
        'database' => 'mydb',
        'prefix' => '',
        'timeout' => 30,
    ],
],
```

Set your Turso token in `.env`:

```env
TURSO_TOKEN=your_token_here
```

## Usage

```php
use Illuminate\Support\Facades\DB;

// Select queries
$users = DB::connection('libsql')->select('SELECT * FROM users WHERE active = ?', [true]);

// Insert/Update/Delete
DB::connection('libsql')->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);

$affected = DB::connection('libsql')->affectingStatement(
    'UPDATE users SET active = ? WHERE id = ?',
    [false, 1]
);

// Transactions
DB::connection('libsql')->transaction(function ($db) {
    $db->insert('INSERT INTO orders (user_id, total) VALUES (?, ?)', [1, 100]);
    $db->statement('UPDATE users SET orders_count = orders_count + 1 WHERE id = ?', [1]);
});

// Query Builder
$users = DB::connection('libsql')
    ->table('users')
    ->where('active', true)
    ->get();
```

## Schema Builder

```php
use Illuminate\Support\Facades\Schema;

Schema::connection('libsql')->create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

## License

MIT
