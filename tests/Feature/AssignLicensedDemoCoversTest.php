<?php

use App\Models\TourOperator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('assigns licensed covers to matching demo packages only', function () {
    $operator = TourOperator::create([
        'name' => 'Cover Op',
        'slug' => 'cover-op-'.Str::uuid(),
        'is_active' => true,
    ]);

    $id = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Daku Island Tropical Picnic',
        'boat_name' => 'Boat',
        'destination' => 'Daku Island',
        'description' => 'Demo',
        'cover_photo' => null,
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $source = resource_path('demo-covers/06_daku.jpg');
    expect(is_file($source))->toBeTrue();

    Artisan::call('demo:assign-licensed-covers', [
        '--operator' => $operator->id,
    ]);

    $package = DB::table('packages')->where('id', $id)->first();

    expect($package->cover_photo)->toBe('packages/'.$id.'/cover_licensed_06_daku.jpg');
    expect(Storage::disk('public')->exists($package->cover_photo))->toBeTrue();
});

test('skips packages that already have a cover unless forced', function () {
    $operator = TourOperator::create([
        'name' => 'Cover Op Skip',
        'slug' => 'cover-op-skip-'.Str::uuid(),
        'is_active' => true,
    ]);

    $id = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Daku Island Tropical Picnic',
        'boat_name' => 'Boat',
        'destination' => 'Daku Island',
        'description' => 'Demo',
        'cover_photo' => null,
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('packages')->where('id', $id)->update([
        'cover_photo' => 'packages/'.$id.'/existing.jpg',
    ]);

    Artisan::call('demo:assign-licensed-covers', [
        '--operator' => $operator->id,
    ]);

    expect(DB::table('packages')->where('id', $id)->value('cover_photo'))
        ->toBe('packages/'.$id.'/existing.jpg');
});
