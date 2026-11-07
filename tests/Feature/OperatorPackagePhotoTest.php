<?php

use App\Models\Package;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Storage::fake('public');
});

function photoOperator(string $name): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'photo-op-'.Str::uuid(),
        'is_active' => true,
    ]);
}

function photoAdmin(TourOperator $operator): User
{
    $role = Role::firstOrCreate(['name' => 'Admin']);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $user->forceFill([
        'tour_operator_id' => $operator->id,
    ])->save();

    return $user->refresh();
}

function photoPackage(TourOperator $operator, ?string $cover = null): int
{
    return DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Photo Tour '.Str::random(4),
        'boat_name' => 'Photo Boat',
        'destination' => 'Daku Island',
        'description' => 'Demo',
        'cover_photo' => $cover,
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('operator can create a package with a cover photo', function () {
    $operator = photoOperator('Photo Operator A');
    $admin = photoAdmin($operator);
    $file = UploadedFile::fake()->image('cover.jpg', 800, 450)->size(512);

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.store'), [
            'package_name' => 'Cove Island Tour',
            'boat_name' => 'Blue Boat',
            'destinations_text' => "Daku Island\nGuyam Island",
            'description' => 'With cover',
            'price' => '1800.00',
            'cover_photo' => $file,
        ])
        ->assertRedirect(route('operator.packages.index'));

    $package = DB::table('packages')
        ->where('package_name', 'Cove Island Tour')
        ->first();

    expect($package)->not->toBeNull()
        ->and($package->cover_photo)->not->toBeNull();

    Storage::disk('public')->assertExists($package->cover_photo);
});

test('operator can create a package without a cover photo', function () {
    $operator = photoOperator('Photo Operator B');
    $admin = photoAdmin($operator);

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.store'), [
            'package_name' => 'No Photo Tour',
            'boat_name' => 'Plain Boat',
            'destinations_text' => 'Naked Island',
            'price' => '1200.00',
        ])
        ->assertRedirect(route('operator.packages.index'));

    $package = DB::table('packages')
        ->where('package_name', 'No Photo Tour')
        ->first();

    expect($package)->not->toBeNull()
        ->and($package->cover_photo)->toBeNull();
});

test('operator can replace an existing cover photo without duplicating the package', function () {
    $operator = photoOperator('Photo Operator C');
    $admin = photoAdmin($operator);
    $packageId = photoPackage($operator);

    $first = UploadedFile::fake()->image('first.png', 640, 360)->size(200);
    $second = UploadedFile::fake()->image('second.webp', 640, 360)->size(220);

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.update', $packageId), [
            '_method' => 'put',
            'package_name' => 'Updated Photo Tour',
            'boat_name' => 'Photo Boat',
            'destinations_text' => 'Daku Island',
            'description' => 'Updated',
            'price' => '1500.00',
            'cover_photo' => $first,
        ])
        ->assertRedirect(route('operator.packages.index'));

    $afterFirst = DB::table('packages')->where('id', $packageId)->first();
    expect($afterFirst->cover_photo)->not->toBeNull();
    $oldPath = $afterFirst->cover_photo;

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.update', $packageId), [
            '_method' => 'put',
            'package_name' => 'Updated Photo Tour',
            'boat_name' => 'Photo Boat',
            'destinations_text' => 'Daku Island',
            'description' => 'Updated again',
            'price' => '1500.00',
            'cover_photo' => $second,
        ])
        ->assertRedirect(route('operator.packages.index'));

    $afterSecond = DB::table('packages')->where('id', $packageId)->first();

    expect(DB::table('packages')->where('package_name', 'Updated Photo Tour')->count())->toBe(1)
        ->and($afterSecond->cover_photo)->not->toBe($oldPath);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($afterSecond->cover_photo);
});

test('editing package details without a new photo keeps the existing cover photo', function () {
    $operator = photoOperator('Photo Operator D');
    $admin = photoAdmin($operator);
    $packageId = photoPackage($operator);

    $file = UploadedFile::fake()->image('keep.jpg', 700, 400)->size(180);

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.update', $packageId), [
            '_method' => 'put',
            'package_name' => 'Keep Photo Tour',
            'boat_name' => 'Photo Boat',
            'destinations_text' => 'Daku Island',
            'price' => '1500.00',
            'cover_photo' => $file,
        ])
        ->assertRedirect(route('operator.packages.index'));

    $path = DB::table('packages')->where('id', $packageId)->value('cover_photo');

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.update', $packageId), [
            '_method' => 'put',
            'package_name' => 'Keep Photo Tour Renamed',
            'boat_name' => 'Photo Boat',
            'destinations_text' => 'Daku Island',
            'description' => 'Name only',
            'price' => '1500.00',
        ])
        ->assertRedirect(route('operator.packages.index'));

    $package = DB::table('packages')->where('id', $packageId)->first();

    expect($package->package_name)->toBe('Keep Photo Tour Renamed')
        ->and($package->cover_photo)->toBe($path);

    Storage::disk('public')->assertExists($path);
});

test('invalid package photos are rejected', function () {
    $operator = photoOperator('Photo Operator E');
    $admin = photoAdmin($operator);

    $this->actingAs($admin, 'web')
        ->from(route('operator.packages.create'))
        ->post(route('operator.packages.store'), [
            'package_name' => 'Bad Photo Tour',
            'boat_name' => 'Boat',
            'destinations_text' => 'Daku Island',
            'price' => '1000.00',
            'cover_photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('operator.packages.create'))
        ->assertSessionHasErrors('cover_photo');

    expect(DB::table('packages')->where('package_name', 'Bad Photo Tour')->exists())->toBeFalse();
});

test('operator cannot change another operators package photo', function () {
    $operatorA = photoOperator('Photo Operator F');
    $operatorB = photoOperator('Photo Operator G');
    $adminA = photoAdmin($operatorA);
    $foreignId = photoPackage($operatorB);

    $this->actingAs($adminA, 'web')
        ->post(route('operator.packages.update', $foreignId), [
            '_method' => 'put',
            'package_name' => 'Hijack',
            'boat_name' => 'Boat',
            'destinations_text' => 'Daku Island',
            'price' => '1000.00',
            'cover_photo' => UploadedFile::fake()->image('steal.jpg'),
        ])
        ->assertNotFound();

    expect(DB::table('packages')->where('id', $foreignId)->value('cover_photo'))->toBeNull();
});

test('packageImages prefers the cover_photo path', function () {
    $operator = photoOperator('Photo Operator I');
    $packageId = photoPackage($operator);
    $directory = UploadPath::package($packageId);

    Storage::disk('public')->put($directory.'/gallery.png', 'gallery');
    Storage::disk('public')->put($directory.'/cover.jpg', 'cover');

    DB::table('packages')->where('id', $packageId)->update([
        'cover_photo' => $directory.'/cover.jpg',
    ]);

    $package = Package::withoutGlobalScopes()->findOrFail($packageId);
    $images = app(FileUploadService::class)->packageImages($package);

    expect($images[0]['path'])->toBe($directory.'/cover.jpg')
        ->and(count($images))->toBe(2);
});

test('tourist package browse includes the uploaded cover photo url', function () {
    $operator = photoOperator('Photo Operator H');
    $admin = photoAdmin($operator);
    $packageId = photoPackage($operator);

    $file = UploadedFile::fake()->image('browse.jpg', 640, 360);

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.update', $packageId), [
            '_method' => 'put',
            'package_name' => 'Browse Photo Tour',
            'boat_name' => 'Photo Boat',
            'destinations_text' => 'Daku Island',
            'price' => '1500.00',
            'cover_photo' => $file,
        ])
        ->assertRedirect();

    $coverPath = DB::table('packages')->where('id', $packageId)->value('cover_photo');

    $touristRole = Role::firstOrCreate(['name' => 'User']);
    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $this->actingAs($tourist, 'web')
        ->get(route('user.packages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/packages/Index')
            ->has('packages')
            ->where('packages', function ($packages) use ($packageId, $coverPath) {
                $match = collect($packages)->firstWhere('id', $packageId);

                return $match !== null
                    && ($match['images'][0]['url'] ?? null) === '/storage/'.$coverPath;
            })
        );
});
