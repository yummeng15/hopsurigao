<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoatMaintenanceController extends Controller
{
    public function index(): Response
    {
        $packages = Package::query()
            ->orderBy('boat_name')
            ->orderBy('id')
            ->get(['id', 'boat_name', 'destination', 'status', 'under_maintenance'])
            ->map(fn (Package $package) => [
                'id' => $package->id,
                'boat_name' => $package->boat_name ?? '',
                'route' => $package->destination,
                'status' => $package->status,
                'under_maintenance' => (bool) $package->under_maintenance,
            ])
            ->values();

        return Inertia::render('admin/boats/Index', [
            'boats' => $packages,
        ]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $request->merge([
            'boat_name' => trim((string) $request->input('boat_name', '')),
        ]);

        $data = $request->validate([
            'boat_name' => ['required', 'string', 'max:255'],
            'under_maintenance' => ['required', 'boolean'],
        ]);

        $package->update([
            'boat_name' => trim($data['boat_name']),
            'under_maintenance' => $data['under_maintenance'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->savedMessage($package),
        ]);

        return back();
    }

    private function savedMessage(Package $package): string
    {
        $name = $package->boat_name;

        if ($package->wasChanged('under_maintenance') && $package->under_maintenance) {
            return "{$name} is under maintenance. New bookings are closed.";
        }

        if ($package->wasChanged('under_maintenance')) {
            return "{$name} is available again. Bookings are open.";
        }

        return "Boat name saved as {$name}.";
    }
}
